<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "fashionhubdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Handle Order Deletion - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_order'])) {
    $order_id = intval($_POST['order_id']);
    
    // First, get the order details to restore stock
    $order_query = "SELECT product_id, quantity FROM orders WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order_result = $stmt->get_result();
    
    if ($order_result->num_rows > 0) {
        $order = $order_result->fetch_assoc();
        $product_id = $order['product_id'];
        $quantity = $order['quantity'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Restore product stock quantity
            $update_stock = "UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?";
            $stmt = $conn->prepare($update_stock);
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();
            
            // Delete the order
            $delete_order = "DELETE FROM orders WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($delete_order);
            $stmt->bind_param("ii", $order_id, $user_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['message'] = "Order deleted successfully and stock restored!";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['message'] = "Error deleting order. Please try again.";
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Order not found or you don't have permission to delete it.";
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect to prevent form resubmission
    header("Location: CustomerOrders.php");
    exit;
}

// Include navbar AFTER handling POST requests
include 'Components/CustomerNavBar.php';

// Handle Search and Filter
$search = "";
$status_filter = "";
$where_clauses = ["o.user_id = $user_id"];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_clauses[] = "(p.product_name LIKE '%$search%' OR o.id LIKE '%$search%')";
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status_filter = intval($_GET['status']);
    $where_clauses[] = "o.status = $status_filter";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Fetch customer's orders with product details
$query = "SELECT o.*, 
          p.product_name, 
          p.product_photo 
          FROM orders o
          LEFT JOIN products p ON o.product_id = p.id
          $where_sql
          ORDER BY o.order_date DESC";
$result = $conn->query($query);

// Get order statistics for this customer
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as cancelled,
    SUM(total_price) as total_spent
    FROM orders
    WHERE user_id = $user_id";
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
    <title>My Orders | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 42px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 16px;
            color: #7f8c8d;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            animation: slideDown 0.3s ease;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #27ae60;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #e74c3c;
        }

        .alert i {
            font-size: 20px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 14px;
            color: #7f8c8d;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
        }

        .search-filter {
            display: flex;
            gap: 15px;
            flex: 1;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 14px 55px 14px 20px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .search-box input:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .search-box button {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-box button:hover {
            background: #c0392b;
        }

        .filter-select {
            padding: 14px 18px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            background: white;
            transition: all 0.3s ease;
            min-width: 160px;
            font-weight: 500;
            color: #2c3e50;
        }

        .filter-select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        /* Orders List */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .order-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-id {
            font-size: 18px;
            font-weight: 700;
        }

        .order-date {
            font-size: 14px;
            opacity: 0.9;
        }

        .order-body {
            padding: 25px;
        }

        .order-product {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .product-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        .product-details {
            flex: 1;
        }

        .product-name {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .product-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #7f8c8d;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-item i {
            color: #e74c3c;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .order-footer {
            padding: 20px 25px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .total-price {
            font-size: 24px;
            font-weight: 700;
            color: #27ae60;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .view-details-btn, .delete-order-btn {
            padding: 12px 28px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .view-details-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .view-details-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        .delete-order-btn {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .delete-order-btn:hover {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        /* Modal */
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
            padding: 35px;
            border-radius: 15px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h3 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 700;
        }

        .close-modal {
            position: absolute;
            top: 25px;
            right: 25px;
            background: transparent;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            color: #333;
            transform: rotate(90deg);
        }

        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 600;
        }

        .address-detail {
            grid-column: 1 / -1;
        }

        .address-detail .detail-value {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #e74c3c;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .empty-state i {
            font-size: 80px;
            color: #e8e8e8;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 25px;
        }

        .shop-now-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .shop-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 32px;
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

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-product {
                flex-direction: column;
                text-align: center;
            }

            .order-info {
                grid-template-columns: 1fr;
            }

            .order-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .order-actions {
                flex-direction: column;
            }

            .view-details-btn, .delete-order-btn {
                justify-content: center;
            }

            .order-details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?php echo $_SESSION['message_type']; ?>">
                <i class="fas fa-<?php echo $_SESSION['message_type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $_SESSION['message']; ?></span>
            </div>
            <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>



        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                    <i class="fas fa-shopping-bag"></i>
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
                    <p>Pending</p>
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
                <div class="stat-icon" style="background: #27ae60;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Rs. <?php echo number_format($stats['total_spent'], 2); ?></h3>
                    <p>Total Spent</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" action="CustomerOrders.php" class="search-filter">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search by order ID or product name..." value="<?php echo htmlspecialchars($search); ?>">
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

        <!-- Orders List -->
        <div class="orders-list">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php $statusInfo = $status_labels[$row['status']]; ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?php echo $row['id']; ?></div>
                                <div class="order-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('F d, Y • h:i A', strtotime($row['order_date'])); ?>
                                </div>
                            </div>
                            <span class="status-badge" style="background: <?php echo $statusInfo['color']; ?>20; color: <?php echo $statusInfo['color']; ?>;">
                                <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                                <?php echo $statusInfo['label']; ?>
                            </span>
                        </div>

                        <div class="order-body">
                            <div class="order-product">
                                <div class="product-image">
                                    <?php if (!empty($row['product_photo'])): ?>
                                        <img src="data:image/jpeg;base64,<?php echo $row['product_photo']; ?>" 
                                             alt="<?php echo htmlspecialchars($row['product_name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                                    <div class="product-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-cube"></i>
                                            <span>Qty: <?php echo $row['quantity']; ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            <span>Rs. <?php echo number_format($row['price'], 2); ?> each</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="order-info">
                                <div class="info-item">
                                    <span class="info-label">Phone</span>
                                    <span class="info-value"><?php echo htmlspecialchars($row['phone']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Delivery Address</span>
                                    <span class="info-value"><?php echo htmlspecialchars(substr($row['delivery_address'], 0, 50)) . (strlen($row['delivery_address']) > 50 ? '...' : ''); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="order-footer">
                            <div>
                                <div class="info-label">Total Amount</div>
                                <div class="total-price">Rs. <?php echo number_format($row['total_price'], 2); ?></div>
                            </div>
                            <div class="order-actions">
                                <button class="view-details-btn" onclick='openModal(<?php echo json_encode($row); ?>)'>
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($row['product_name']); ?>', <?php echo $row['quantity']; ?>)">
                                    <input type="hidden" name="delete_order" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="delete-order-btn">
                                        <i class="fas fa-trash-alt"></i>
                                        Delete Order
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders. Start shopping now!</p>
                    <a href="Products.php" class="shop-now-btn">
                        <i class="fas fa-shopping-cart"></i>
                        Shop Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Order Details</h3>
            </div>
            <div class="order-details-grid" id="orderDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        const statusLabels = <?php echo json_encode($status_labels); ?>;

        function confirmDelete(productName, quantity) {
            return confirm(`Are you sure you want to delete this order?\n\nProduct: ${productName}\nQuantity: ${quantity}\n\nThe stock quantity will be restored.`);
        }

        function openModal(data) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('orderDetailsContent');
            
            const statusInfo = statusLabels[data.status];
            
            content.innerHTML = `
                <div class="detail-group">
                    <span class="detail-label">Order ID</span>
                    <span class="detail-value">#${data.id}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Order Date</span>
                    <span class="detail-value">${new Date(data.order_date).toLocaleString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric', 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    })}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Product</span>
                    <span class="detail-value">${data.product_name}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Quantity</span>
                    <span class="detail-value">${data.quantity} units</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Unit Price</span>
                    <span class="detail-value">Rs. ${parseFloat(data.price).toFixed(2)}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Total Price</span>
                    <span class="detail-value" style="color: #27ae60; font-size: 20px;">Rs. ${parseFloat(data.total_price).toFixed(2)}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Phone Number</span>
                    <span class="detail-value">${data.phone}</span>
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
                <div class="detail-group address-detail">
                    <span class="detail-label">Delivery Address</span>
                    <span class="detail-value">${data.delivery_address}</span>
                </div>
            `;
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>