<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Order Deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_order'])) {
    $order_id = intval($_POST['order_id']);
    
    $order_query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order_result = $stmt->get_result();
    
    if ($order_result->num_rows > 0) {
        $order = $order_result->fetch_assoc();
        $quantity = $order['quantity'];
        $product_id = $order['product_id'];
        $size = $order['size'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Restore stock to product_sizes using product_id and size
            if (!empty($product_id) && !empty($size)) {
                $update_stock = "UPDATE product_sizes 
                                SET quantity = quantity + ?, 
                                    updated_at = NOW() 
                                WHERE product_id = ? AND size = ?";
                $stmt = $conn->prepare($update_stock);
                $stmt->bind_param("iis", $quantity, $product_id, $size);
                $stmt->execute();
                
                // Check if stock was actually updated
                if ($stmt->affected_rows === 0) {
                    throw new Exception("Could not restore stock - size not found in inventory");
                }
            } else {
                throw new Exception("Invalid product or size information");
            }
            
            // Delete the order
            $delete_order = "DELETE FROM orders WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($delete_order);
            $stmt->bind_param("ii", $order_id, $user_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['message'] = "Order deleted successfully and stock of $quantity unit(s) restored!";
            $_SESSION['message_type'] = "success";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['message'] = "Error deleting order: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Order not found or you don't have permission to delete it.";
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: CustomerOrders.php");
    exit;
}


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

// Query based on current structure (orders has product_id)
$query = "SELECT o.*, 
          p.product_name
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

        .product-image-container {
            width: 120px;
            flex-shrink: 0;
        }

        .product-image-slider {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .product-image-slider img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .product-image-slider img.active {
            display: block;
        }

        .product-image-slider .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .slider-btn:hover {
            background: rgba(0, 0, 0, 0.8);
            transform: translateY(-50%) scale(1.1);
        }

        .slider-btn.prev {
            left: 5px;
        }

        .slider-btn.next {
            right: 5px;
        }

        .slider-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .image-counter {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
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

        .size-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
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
    top: 20px;
    right: 20px;
    background: #f0f0f0;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #666;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
}

.close-modal:hover {
    background: #e74c3c;
    color: white;
    transform: rotate(90deg) scale(1.1);
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

        .modal-image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }

        .modal-image-gallery img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e8e8e8;
        }

        .modal-image-gallery img:hover {
            transform: scale(1.05);
            border-color: #e74c3c;
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

            .product-image-container {
                margin: 0 auto;
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


        /* Toast Notification */
        .toast {
            position: fixed;
            top: 100px;
            right: -400px;
            background: var(--primary-white, #ffffff);
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 350px;
            max-width: 500px;
            transition: right 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .toast.show {
            right: 20px;
        }

        .toast.success {
            border-left: 5px solid #27ae60;
        }

        .toast.error {
            border-left: 5px solid #e74c3c;
        }

        .toast-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .toast.success .toast-icon {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }

        .toast.error .toast-icon {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
            color: #2c3e50;
        }

        .toast-message {
            font-size: 14px;
            color: #7f8c8d;
        }

        .toast-close {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #7f8c8d;
            transition: all 0.3s;
            padding: 5px;
        }

        .toast-close:hover {
            color: #2c3e50;
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
            .toast {
                right: -100%;
                left: 10px;
                min-width: auto;
                max-width: calc(100% - 20px);
            }

            .toast.show {
                right: auto;
                left: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
       <?php if (isset($_SESSION['message']) && isset($_SESSION['message_type'])): ?>
    <?php 
        // Store values before unsetting
        $toast_message = $_SESSION['message'];
        $toast_type = $_SESSION['message_type'];
        
        // Unset immediately after storing
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    ?>
    <div class="toast <?php echo htmlspecialchars($toast_type); ?>">
        <div class="toast-icon">
            <i class="fas fa-<?php echo $toast_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title"><?php echo $toast_type === 'success' ? 'Success!' : 'Error!'; ?></div>
            <div class="toast-message"><?php echo htmlspecialchars($toast_message); ?></div>
        </div>
        <button class="toast-close">&times;</button>
    </div>
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
    <?php 
    $statusInfo = $status_labels[$row['status']];
    
    // Get size information from product_sizes table
    $display_size = 'Standard';
    $original_price = $row['price'];
    $discount = 0;
    $discounted_price = $original_price;
    $size_id = null;
    $photos = [];
    
    // Check if order has a 'size' column (stored as text)
    if (isset($row['size']) && !empty($row['size'])) {
        $display_size = $row['size'];
        
        // Get size details from product_sizes
        $sizeStmt = $conn->prepare("SELECT ps.id, ps.price, ps.discount 
                                    FROM product_sizes ps 
                                    WHERE ps.product_id = ? AND ps.size = ?");
        $sizeStmt->bind_param("is", $row['product_id'], $row['size']);
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result();
        
        if ($sizeRow = $sizeResult->fetch_assoc()) {
            $size_id = $sizeRow['id'];
            $original_price = $sizeRow['price'];
            $discount = $sizeRow['discount'];
            $discounted_price = $original_price - ($original_price * $discount / 100);
        }
        $sizeStmt->close();
    } else {
        // No size stored, get first available size
        $sizeStmt = $conn->prepare("SELECT ps.id, ps.size, ps.price, ps.discount 
                                    FROM product_sizes ps 
                                    WHERE ps.product_id = ? 
                                    LIMIT 1");
        $sizeStmt->bind_param("i", $row['product_id']);
        $sizeStmt->execute();
        $sizeResult = $sizeStmt->get_result();
        
        if ($sizeRow = $sizeResult->fetch_assoc()) {
            $size_id = $sizeRow['id'];
            $display_size = $sizeRow['size'];
            $original_price = $sizeRow['price'];
            $discount = $sizeRow['discount'];
            $discounted_price = $original_price - ($original_price * $discount / 100);
        }
        $sizeStmt->close();
    }
    
    // Get photos for this size
    if ($size_id) {
        $photosStmt = $conn->prepare("SELECT photo FROM photos WHERE size_id = ?");
        $photosStmt->bind_param("i", $size_id);
        $photosStmt->execute();
        $photosResult = $photosStmt->get_result();
        while ($photo = $photosResult->fetch_assoc()) {
            $photos[] = $photo['photo'];
        }
        $photosStmt->close();
    }
    ?>
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
                                <div class="product-image-container">
                                    <div class="product-image-slider" id="slider-<?php echo $row['id']; ?>">
                                        <?php if (!empty($photos)): ?>
                                            <?php foreach ($photos as $index => $photo): ?>
                                                <img src="data:image/jpeg;base64,<?php echo $photo; ?>" 
                                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                     class="<?php echo $index === 0 ? 'active' : ''; ?>"
                                                     data-index="<?php echo $index; ?>">
                                            <?php endforeach; ?>
                                            <?php if (count($photos) > 1): ?>
                                                <button class="slider-btn prev" onclick="changeSlide(<?php echo $row['id']; ?>, -1)">
                                                    <i class="fas fa-chevron-left"></i>
                                                </button>
                                                <button class="slider-btn next" onclick="changeSlide(<?php echo $row['id']; ?>, 1)">
                                                    <i class="fas fa-chevron-right"></i>
                                                </button>
                                                <div class="image-counter">
                                                    <span id="counter-<?php echo $row['id']; ?>">1</span> / <?php echo count($photos); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-box"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                                    <div class="product-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            <span>Size: <span class="size-badge"><?php echo htmlspecialchars($display_size); ?></span></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-cube"></i>
                                            <span>Qty: <?php echo $row['quantity']; ?></span>
                                        </div>
                                        <?php if ($discount > 0): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-percent"></i>
                                                <span style="color: #e74c3c; font-weight: 600;"><?php echo number_format($discount, 0); ?>% OFF</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="order-info">
                                <div class="info-item">
                                    <span class="info-label">Unit Price</span>
                                    <span class="info-value">
                                        <?php if ($discount > 0): ?>
                                            <span style="text-decoration: line-through; color: #999; font-size: 14px;">Rs. <?php echo number_format($original_price, 2); ?></span>
                                            <span style="color: #e74c3c;">Rs. <?php echo number_format($discounted_price, 2); ?></span>
                                        <?php else: ?>
                                            Rs. <?php echo number_format($original_price, 2); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
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
                                <button class="view-details-btn" onclick='openModal(<?php echo json_encode(array_merge($row, ["photos" => $photos, "discounted_price" => $discounted_price, "discount" => $discount, "display_size" => $display_size, "original_price" => $original_price])); ?>)'>
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </button>
                                <?php if ($row['status'] == 0): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($row['product_name']); ?>', <?php echo $row['quantity']; ?>, '<?php echo htmlspecialchars($display_size); ?>')">
                                        <input type="hidden" name="delete_order" value="1">
                                        <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="delete-order-btn">
                                            <i class="fas fa-trash-alt"></i>
                                            Delete Order
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Found</h3>
                    <p>You haven't placed any orders matching your search criteria.</p>
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

     <?php include 'Components/Footer.php'; ?>

    <script>
        const statusLabels = <?php echo json_encode($status_labels); ?>;

        // Image slider functionality
        const sliderStates = {};

        function changeSlide(orderId, direction) {
            const slider = document.getElementById(`slider-${orderId}`);
            const images = slider.querySelectorAll('img');
            const counter = document.getElementById(`counter-${orderId}`);
            
            if (!sliderStates[orderId]) {
                sliderStates[orderId] = 0;
            }
            
            images[sliderStates[orderId]].classList.remove('active');
            
            sliderStates[orderId] += direction;
            
            if (sliderStates[orderId] < 0) {
                sliderStates[orderId] = images.length - 1;
            } else if (sliderStates[orderId] >= images.length) {
                sliderStates[orderId] = 0;
            }
            
            images[sliderStates[orderId]].classList.add('active');
            
            if (counter) {
                counter.textContent = sliderStates[orderId] + 1;
            }
        }

        function confirmDelete(productName, quantity, size) {
            return confirm(`Are you sure you want to delete this order?\n\nProduct: ${productName}\nSize: ${size}\nQuantity: ${quantity}\n\nThe stock quantity will be restored.`);
        }

        function openModal(data) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('orderDetailsContent');
            
            const statusInfo = statusLabels[data.status];
            
            let photosHtml = '';
            if (data.photos && data.photos.length > 0) {
                photosHtml = `
                    <div class="modal-image-gallery">
                        ${data.photos.map(photo => `
                            <img src="data:image/jpeg;base64,${photo}" alt="Product photo" onclick="viewFullImage(this.src)">
                        `).join('')}
                    </div>
                `;
            }

            const discount = parseFloat(data.discount || 0);
            const originalPrice = parseFloat(data.original_price || data.price);
            const discountedPrice = parseFloat(data.discounted_price || data.price);
            
            let priceHtml = '';
            if (discount > 0) {
                priceHtml = `
                    <span style="text-decoration: line-through; color: #999; font-size: 14px; display: block;">Rs. ${originalPrice.toFixed(2)}</span>
                    <span style="color: #e74c3c; font-size: 18px; font-weight: 700;">Rs. ${discountedPrice.toFixed(2)}</span>
                    <span style="color: #27ae60; font-size: 13px; display: block;">(${discount.toFixed(0)}% discount applied)</span>
                `;
            } else {
                priceHtml = `Rs. ${originalPrice.toFixed(2)}`;
            }
            
            content.innerHTML = `
                ${photosHtml}
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
                    <span class="detail-label">Size</span>
                    <span class="detail-value">
                        <span class="size-badge">${data.display_size || 'Standard'}</span>
                    </span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Quantity</span>
                    <span class="detail-value">${data.quantity} units</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Unit Price</span>
                    <span class="detail-value">${priceHtml}</span>
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
                <div class="detail-group" style="grid-column: 1 / -1; margin-top: 10px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <span class="detail-label">Total Amount Paid</span>
                    <span class="detail-value" style="color: #27ae60; font-size: 24px;">Rs. ${parseFloat(data.total_price).toFixed(2)}</span>
                </div>
            `;
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        function viewFullImage(src) {
            const imgWindow = window.open('', '_blank');
            imgWindow.document.write(`
                <html>
                <head>
                    <title>Product Image</title>
                    <style>
                        body { margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #000; }
                        img { max-width: 100%; max-height: 100vh; object-fit: contain; }
                    </style>
                </head>
                <body>
                    <img src="${src}" alt="Product Image">
                </body>
                </html>
            `);
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

// Toast Notification
        const toast = document.querySelector('.toast');
        if (toast) {
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            const closeBtn = toast.querySelector('.toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                });
            }

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentElement) toast.remove();
                }, 500);
            }, 5000);
        }
    </script>
   
</body>
</html>