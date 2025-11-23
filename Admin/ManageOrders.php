<?php
include '../db_connect.php';
session_start();

// Handle AJAX request for order details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $order_id = intval($_GET['id']);
    
$stmt = $conn->prepare("SELECT o.*, u.fullname, u.email, p.product_name, ps.size, pc.color_name, ph.photo
                        FROM orders o 
                        LEFT JOIN users u ON o.user_id = u.id 
                        LEFT JOIN products p ON o.product_id = p.id
                        LEFT JOIN product_sizes ps ON o.size_id = ps.id
                        LEFT JOIN product_colors pc ON o.color_id = pc.id
                        LEFT JOIN photos ph ON p.id = ph.product_id AND ph.size_id = o.size_id AND ph.color_id = o.color_id
                        WHERE o.id = ?
                        LIMIT 1");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        echo json_encode($order);
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
    $stmt->close();
    exit;
}

// Handle Update Order Status
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = intval($_POST['status']);
    
    // Get current order details
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_order = $result->fetch_assoc();
    $stmt->close();
    
    if ($current_order) {
        // Update order status
        $updateStmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $updateStmt->bind_param("ii", $new_status, $order_id);
        
        if ($updateStmt->execute()) {
            $_SESSION['success'] = "Order status updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update order status: " . $conn->error;
        }
        $updateStmt->close();
    } else {
        $_SESSION['error'] = "Order not found.";
    }
    
    header("Location: ManageOrders.php");
    exit;
}

// Handle Edit Order
if (isset($_POST['edit_order'])) {
    $order_id = intval($_POST['order_id']);
    $delivery_address = trim($_POST['edit_delivery_address']);
    $phone = trim($_POST['edit_phone']);
    $status = intval($_POST['edit_status']);
    
    $stmt = $conn->prepare("UPDATE orders SET delivery_address = ?, phone = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssii", $delivery_address, $phone, $status, $order_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Order updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update order: " . $conn->error;
    }
    $stmt->close();
    
    header("Location: ManageOrders.php");
    exit;
}


// Handle Delete Order
if (isset($_GET['delete'])) {
    $order_id = intval($_GET['delete']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get order details including product_id, size_id, color_id, and quantity
        $stmt = $conn->prepare("SELECT product_id, size_id, color_id, quantity FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Order not found.");
        }
        
        $order = $result->fetch_assoc();
        $stmt->close();
        
        // Return quantity to product_sizes
        $updateSizeStmt = $conn->prepare("UPDATE product_sizes 
                                           SET quantity = quantity + ? 
                                           WHERE id = ? AND product_id = ?");
        $updateSizeStmt->bind_param("iii", 
            $order['quantity'], 
            $order['size_id'], 
            $order['product_id']
        );
        
        if (!$updateSizeStmt->execute()) {
            throw new Exception("Failed to return quantity to size inventory.");
        }
        
        if ($updateSizeStmt->affected_rows === 0) {
            throw new Exception("Product size not found in inventory.");
        }
        
        $updateSizeStmt->close();
        
        // Return quantity to product_colors
        $updateColorStmt = $conn->prepare("UPDATE product_colors 
                                            SET quantity = quantity + ? 
                                            WHERE id = ? AND product_id = ? AND size_id = ?");
        $updateColorStmt->bind_param("iiii", 
            $order['quantity'], 
            $order['color_id'], 
            $order['product_id'],
            $order['size_id']
        );
        
        if (!$updateColorStmt->execute()) {
            throw new Exception("Failed to return quantity to color inventory.");
        }
        
        if ($updateColorStmt->affected_rows === 0) {
            throw new Exception("Product color not found in inventory.");
        }
        
        $updateColorStmt->close();
        
        // Delete the order
        $deleteStmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $deleteStmt->bind_param("i", $order_id);
        
        if (!$deleteStmt->execute()) {
            throw new Exception("Failed to delete order.");
        }
        
        $deleteStmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Order deleted successfully and quantity restored!";
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error'] = "Error deleting order: " . $e->getMessage();
    }
    
    header("Location: ManageOrders.php");
    exit;
}

// Handle Search and Filter
$search = "";
$status_filter = isset($_GET['status_filter']) ? intval($_GET['status_filter']) : 0; // Default to Pending

$query = "SELECT o.*, u.fullname, p.product_name, ps.size as size_name, pc.color_name
          FROM orders o 
          LEFT JOIN users u ON o.user_id = u.id 
          LEFT JOIN products p ON o.product_id = p.id
          LEFT JOIN product_sizes ps ON o.size_id = ps.id
          LEFT JOIN product_colors pc ON o.color_id = pc.id
          WHERE o.status = ?";

$params = [$status_filter];
$types = "i";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $query .= " AND (u.fullname LIKE ? OR p.product_name LIKE ? OR o.delivery_address LIKE ? OR o.phone LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get order counts for each status
$pendingCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 0")->fetch_assoc()['count'];
$deliveredCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 1")->fetch_assoc()['count'];
$cancelledCount = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 2")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 70px);
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-close {
            margin-left: auto;
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            opacity: 0.5;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @keyframes slideDown {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .breadcrumb {
            color: #999;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .status-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 12px 24px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .filter-btn .badge {
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .filter-btn.active .badge {
            background: rgba(255, 255, 255, 0.3);
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            max-width: 500px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box button:hover {
            background: #5568d3;
        }

        .orders-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .orders-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .orders-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #666;
        }

        .orders-table tbody tr {
            transition: all 0.3s ease;
        }

        .orders-table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
            margin: 0 2px;
        }

 .view-btn {
    color: #667eea;
    text-decoration: none;
    display: inline-block;
}

.view-btn:hover {
    background: rgba(102, 126, 234, 0.1);
}

        .edit-btn {
            color: #28a745;
        }

        .edit-btn:hover {
            background: rgba(40, 167, 69, 0.1);
        }

        .delete-btn {
            color: #ff4757;
        }

        .delete-btn:hover {
            background: rgba(255, 71, 87, 0.1);
        }

        .status-update-btn {
            color: #ffc107;
        }

        .status-update-btn:hover {
            background: rgba(255, 193, 7, 0.1);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            margin-bottom: 25px;
        }

        .modal-header h3 {
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            width: 150px;
        }

        .info-value {
            color: #666;
            flex: 1;
        }

        .product-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 15px 0;
        }

        .product-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-preview .placeholder {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-primary,
        .btn-secondary {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #666;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .orders-table-container {
                overflow-x: auto;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .status-filters {
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Manage Orders</h2>
            <div class="breadcrumb">
                <a href="AdminDashboard.php">Dashboard</a> / Orders
            </div>
        </div>

        <div class="status-filters">
            <a href="?status_filter=0" class="filter-btn <?php echo $status_filter == 0 ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Pending
                <span class="badge"><?php echo $pendingCount; ?></span>
            </a>
            <a href="?status_filter=1" class="filter-btn <?php echo $status_filter == 1 ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Delivered
                <span class="badge"><?php echo $deliveredCount; ?></span>
            </a>
            <a href="?status_filter=2" class="filter-btn <?php echo $status_filter == 2 ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Cancelled
                <span class="badge"><?php echo $cancelledCount; ?></span>
            </a>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <form method="GET" action="ManageOrders.php">
                    <input type="hidden" name="status_filter" value="<?php echo $status_filter; ?>">
                    <input type="text" name="search" placeholder="Search by customer, product, address, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>

        <div class="orders-table-container">
            <?php if ($result->num_rows > 0): ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Size</th>
<th>Color</th>
<th>Quantity</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Order Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): 
                            $statusClass = $row['status'] == 0 ? 'status-pending' : 
                                          ($row['status'] == 1 ? 'status-delivered' : 'status-cancelled');
                            $statusText = $row['status'] == 0 ? 'Pending' : 
                                         ($row['status'] == 1 ? 'Delivered' : 'Cancelled');
                        ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['fullname'] ?: 'Guest'); ?></td>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['size_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['color_name'] ?? 'N/A'); ?></td>
                                <td><?php echo $row['quantity']; ?></td>
                                <td><strong>$<?php echo number_format($row['total_price'], 2); ?></strong></td>
                                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                                <td>
                                   <a href="ViewOrderDetails.php?id=<?php echo $row['id']; ?>" class="action-btn view-btn" title="View Details">
    <i class="fas fa-eye"></i>
</a>
                                    <button class="action-btn status-update-btn" onclick='openStatusModal(<?php echo json_encode($row); ?>)' title="Update Status">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <button class="action-btn edit-btn" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteOrder(<?php echo $row['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Found</h3>
                    <p>There are no <?php echo $status_filter == 0 ? 'pending' : ($status_filter == 1 ? 'delivered' : 'cancelled'); ?> orders at the moment</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Order Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeViewModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Order Details</h3>
            </div>
            <div id="view_content"></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeViewModal()" style="width: 100%">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeStatusModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Update Order Status</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="order_id" id="status_order_id">
                
                <div class="form-group">
                    <label>Order ID</label>
                    <input type="text" id="status_order_display" readonly style="background: #f8f9fa;">
                </div>

                <div class="form-group">
                    <label>New Status *</label>
                    <select name="status" id="status_select" required>
                        <option value="0">Pending</option>
                        <option value="1">Delivered</option>
                        <option value="2">Cancelled</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="update_status" class="btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeStatusModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Order Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Edit Order</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="order_id" id="edit_order_id">
                
                <div class="form-group">
                    <label>Delivery Address *</label>
                    <textarea name="edit_delivery_address" id="edit_delivery_address" required rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Phone *</label>
                    <input type="text" name="edit_phone" id="edit_phone" required>
                </div>

                <div class="form-group">
                    <label>Status *</label>
                    <select name="edit_status" id="edit_status" required>
                        <option value="0">Pending</option>
                        <option value="1">Delivered</option>
                        <option value="2">Cancelled</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="edit_order" class="btn-primary">
                        <i class="fas fa-save"></i> Update Order
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewOrder(id) {
            document.getElementById('viewModal').classList.add('active');
            
            fetch(`ManageOrders.php?ajax=get_details&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error loading order details');
                        closeViewModal();
                        return;
                    }
                    
                    const statusLabels = ['Pending', 'Delivered', 'Cancelled'];
                    const statusClasses = ['status-pending', 'status-delivered', 'status-cancelled'];
                    const statusLabel = statusLabels[data.status] || 'Unknown';
                    const statusClass = statusClasses[data.status] || 'status-pending';
                    
                    let productImageHtml = '';
                    if (data.photo) {
                        productImageHtml = `<img src="data:image/jpeg;base64,${data.photo}" alt="Product">`;
                    } else {
                        productImageHtml = `<div class="placeholder"><i class="fas fa-box"></i></div>`;
                    }
                    
                    document.getElementById('view_content').innerHTML = `
                        <div class="product-preview">
                            ${productImageHtml}
                            <div>
                                <div style="font-weight: 600; margin-bottom: 5px;">${data.product_name}</div>
<div style="color: #999; font-size: 13px;">Size: ${data.size} | Color: ${data.color_name || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Order ID:</div>
                            <div class="info-value"><strong>#${String(data.id).padStart(5, '0')}</strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Customer:</div>
                            <div class="info-value">${data.username || 'Guest'} (${data.email || 'N/A'})</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Quantity:</div>
                            <div class="info-value">${data.quantity}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Unit Price:</div>
                            <div class="info-value">${parseFloat(data.price).toFixed(2)}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Total Price:</div>
                            <div class="info-value"><strong style="color: #667eea; font-size: 18px;">${parseFloat(data.total_price).toFixed(2)}</strong></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Delivery Address:</div>
                            <div class="info-value">${data.delivery_address}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone:</div>
                            <div class="info-value">${data.phone}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value"><span class="status-badge ${statusClass}">${statusLabel}</span></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Order Date:</div>
                            <div class="info-value">${new Date(data.order_date).toLocaleString('en-US', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            })}</div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading order details');
                    closeViewModal();
                });
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            document.getElementById('view_content').innerHTML = '';
        }

        function openStatusModal(data) {
            document.getElementById('statusModal').classList.add('active');
            document.getElementById('status_order_id').value = data.id;
            document.getElementById('status_order_display').value = '#' + String(data.id).padStart(5, '0');
            document.getElementById('status_select').value = data.status;
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
            document.querySelector('#statusModal form').reset();
        }

        function openEditModal(data) {
            document.getElementById('editModal').classList.add('active');
            document.getElementById('edit_order_id').value = data.id;
            document.getElementById('edit_delivery_address').value = data.delivery_address;
            document.getElementById('edit_phone').value = data.phone;
            document.getElementById('edit_status').value = data.status;
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.querySelector('#editModal form').reset();
        }

        function deleteOrder(id) {
            if (confirm('Are you sure you want to delete this order? The quantity will be returned to the inventory.')) {
                window.location.href = 'ManageOrders.php?delete=' + id;
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const statusModal = document.getElementById('statusModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == statusModal) {
                closeStatusModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Alert close button functionality
        document.querySelectorAll('.alert-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const alert = this.parentElement;
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        });
    </script>
</body>
</html>