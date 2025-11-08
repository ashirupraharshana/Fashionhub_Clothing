<?php
include '../db_connect.php';
session_start();

// Handle Status Update
if (isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = intval($_POST['status']);
    
    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_status, $order_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Order status updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update order status.";
    }
    
    header("Location: ManageOrders.php");
    exit;
}

// Handle Delete Order
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get order details before deleting to restore stock
    $order_query = "SELECT product_id, quantity FROM orders WHERE id = $id";
    $order_result = $conn->query($order_query);
    
    if ($order_result && $order_result->num_rows > 0) {
        $order = $order_result->fetch_assoc();
        
        // Restore stock quantity
        $update_stock = "UPDATE products SET stock_quantity = stock_quantity + {$order['quantity']} WHERE id = {$order['product_id']}";
        $conn->query($update_stock);
        
        // Delete order
        if ($conn->query("DELETE FROM orders WHERE id = $id")) {
            $_SESSION['success'] = "Order deleted successfully and stock restored!";
        } else {
            $_SESSION['error'] = "Failed to delete order.";
        }
    }
    
    header("Location: ManageOrders.php");
    exit;
}

// Handle Search and Filter
$search = "";
$status_filter = "";
$where_clauses = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "(u.fullname LIKE '%$search%' OR p.product_name LIKE '%$search%' OR o.phone LIKE '%$search%' OR o.id LIKE '%$search%')";
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status_filter = intval($_GET['status']);
    $where_clauses[] = "o.status = $status_filter";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch orders with user and product details
$query = "SELECT o.*, 
          u.fullname as customer_name,
          u.email, 
          p.product_name, 
          p.product_photo 
          FROM orders o
          LEFT JOIN users u ON o.user_id = u.id
          LEFT JOIN products p ON o.product_id = p.id
          $where_sql
          ORDER BY o.order_date DESC";
$result = $conn->query($query);

// Get order statistics
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as cancelled,
    SUM(total_price) as total_revenue
    FROM orders";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Status labels and colors
$status_labels = [
    0 => ['label' => 'Pending', 'color' => '#f39c12', 'icon' => 'fa-clock'],
    1 => ['label' => 'Delivered', 'color' => '#27ae60', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Cancelled', 'color' => '#e74c3c', 'icon' => 'fa-times-circle']
];
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

        /* Main Content Area */
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
            position: fixed;
            top: 80px;
            right: 20px;
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

        /* Page Header */
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-info h3 {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 13px;
            color: #999;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .search-filter {
            display: flex;
            gap: 15px;
            flex: 1;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
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

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Orders Table */
        .orders-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .order-id {
            font-weight: 600;
            color: #667eea;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-thumb {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-info {
            display: flex;
            flex-direction: column;
        }

        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .customer-name {
            color: #999;
            font-size: 12px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .price-cell {
            font-weight: 700;
            color: #27ae60;
        }

        .action-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .view-btn {
            color: #3498db;
        }

        .view-btn:hover {
            background: rgba(52, 152, 219, 0.1);
        }

        .edit-btn {
            color: #667eea;
        }

        .edit-btn:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .delete-btn {
            color: #ff4757;
        }

        .delete-btn:hover {
            background: rgba(255, 71, 87, 0.1);
        }

        /* Modal Styles */
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
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
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

        .order-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
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

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
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

        /* Empty State */
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-filter {
                flex-direction: column;
            }

            .search-box {
                min-width: 100%;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 8px;
            }

            .order-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="AdminDashboard.php">Dashboard</a> / Orders
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_orders']; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #f39c12;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #27ae60;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['delivered']; ?></h3>
                    <p>Delivered</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #e74c3c;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['cancelled']; ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #e74c3c;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Rs. <?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" action="ManageOrders.php" class="search-filter">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search by order ID, customer, product, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Pending</option>
                    <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Delivered</option>
                    <option value="2" <?php echo $status_filter === 2 ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="orders-container">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Product & Customer</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="order-id">#<?php echo $row['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="product-cell">
                                            <div class="product-thumb">
                                                <?php if (!empty($row['product_photo'])): ?>
                                                    <img src="data:image/jpeg;base64,<?php echo $row['product_photo']; ?>" 
                                                         alt="<?php echo htmlspecialchars($row['product_name']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-box"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="product-info">
                                                <span class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                                <span class="customer-name"><?php echo htmlspecialchars($row['customer_name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $row['quantity']; ?></td>
                                    <td>Rs. <?php echo number_format($row['price'], 2); ?></td>
                                    <td class="price-cell">Rs. <?php echo number_format($row['total_price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge" style="background: <?php echo $status_labels[$row['status']]['color']; ?>20; color: <?php echo $status_labels[$row['status']]['color']; ?>;">
                                            <i class="fas <?php echo $status_labels[$row['status']]['icon']; ?>"></i>
                                            <?php echo $status_labels[$row['status']]['label']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($row['order_date'])); ?></td>
                                    <td>
                                        <button class="action-btn view-btn" onclick='openViewModal(<?php echo json_encode($row); ?>)' title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit-btn" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete-btn" onclick="deleteOrder(<?php echo $row['id']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-shopping-cart"></i>
                                        <h3>No Orders Found</h3>
                                        <p>No orders match your search criteria</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
            <div class="order-details" id="orderDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Edit Order Status Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Update Order Status</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="order_id" id="edit_order_id">
                
                <div class="form-group">
                    <label>Current Status</label>
                    <div id="current_status" style="padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;"></div>
                </div>

                <div class="form-group">
                    <label>New Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="0">Pending</option>
                        <option value="1">Delivered</option>
                        <option value="2">Cancelled</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="update_status" class="btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const statusLabels = <?php echo json_encode($status_labels); ?>;

        function openViewModal(data) {
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('orderDetailsContent');
            
            const statusInfo = statusLabels[data.status];
            
            content.innerHTML = `
                <div class="detail-group">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">${data.email}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">${data.phone}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Product</span>
                    <span class="detail-value">${data.product_name}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Quantity</span>
                    <span class="detail-value">${data.quantity}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Unit Price</span>
                    <span class="detail-value">Rs. ${parseFloat(data.price).toFixed(2)}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Total Price</span>
                    <span class="detail-value" style="color: #27ae60; font-size: 18px;">Rs. ${parseFloat(data.total_price).toFixed(2)}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge" style="background: ${statusInfo.color}20; color: ${statusInfo.color};">
                            <i class="fas ${statusInfo.icon}"></i>
                            ${statusInfo.label}
                        </span>
                    </span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Order Date</span>
                    <span class="detail-value">${new Date(data.order_date).toLocaleString()}</span>
                </div>
                <div class="detail-group" style="grid-column: 1 / -1;">
                    <span class="detail-label">Delivery Address</span>
                    <span class="detail-value">${data.delivery_address}</span>
                </div>
            `;
            
            modal.classList.add('active');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }

        function openEditModal(data) {
            document.getElementById('editModal').classList.add('active');
            document.getElementById('edit_order_id').value = data.id;
            document.getElementById('edit_status').value = data.status;
            
            const statusInfo = statusLabels[data.status];
            document.getElementById('current_status').innerHTML = `
                <span class="status-badge" style="background: ${statusInfo.color}20; color: ${statusInfo.color};">
                    <i class="fas ${statusInfo.icon}"></i>
                    ${statusInfo.label}
                </span>
                <span style="margin-left: 10px; color: #999;">for Order #${data.id}</span>
            `;
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function deleteOrder(id) {
            if (confirm('Are you sure you want to delete this order? This will restore the product stock.')) {
                window.location.href = 'ManageOrders.php?delete=' + id;
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const editModal = document.getElementById('editModal');
            if (event.target == viewModal) {
                closeViewModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Manual close with animation
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