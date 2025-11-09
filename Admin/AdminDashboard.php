<?php
include '../db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['userrole'] != 1) {
    header("Location: /fashionhub/Homepage.php");
    exit();
}

// Get Dashboard Statistics
$stats = [];

// Total Users
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$stats['new_users_today'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];

// Total Products
$stats['total_products'] = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$stats['low_stock'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= 10")->fetch_assoc()['count'];
$stats['out_of_stock'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity = 0")->fetch_assoc()['count'];

// Total Orders
$stats['total_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$stats['pending_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 0")->fetch_assoc()['count'];
$stats['completed_orders'] = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 1")->fetch_assoc()['count'];

// Total Revenue
$revenue_result = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status = 1");
$stats['total_revenue'] = $revenue_result->fetch_assoc()['total'] ?? 0;

$today_revenue = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status = 1 AND DATE(order_date) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$stats['today_revenue'] = $today_revenue;

// Total Categories
$stats['total_categories'] = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];

// Total Feedback
$stats['total_feedback'] = $conn->query("SELECT COUNT(*) as count FROM feedback")->fetch_assoc()['count'];
$stats['unanswered_feedback'] = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE admin_reply IS NULL")->fetch_assoc()['count'];

// Recent Orders
$recent_orders = $conn->query("
    SELECT o.*, u.fullname, p.product_name 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products p ON o.product_id = p.id
    ORDER BY o.order_date DESC
    LIMIT 5
");

// Low Stock Products
$low_stock_products = $conn->query("
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock_quantity <= 10
    ORDER BY p.stock_quantity ASC
    LIMIT 5
");

// Recent Users
$recent_users = $conn->query("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Pending Orders (for dedicated section)
$pending_orders_list = $conn->query("
    SELECT o.*, u.fullname, p.product_name 
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.status = 0
    ORDER BY o.order_date DESC
    LIMIT 5
");

// Sales data for chart (last 7 days)
$sales_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_sales = $conn->query("
        SELECT SUM(total_price) as total 
        FROM orders 
        WHERE DATE(order_date) = '$date' AND status = 1
    ")->fetch_assoc()['total'] ?? 0;
    
    $sales_data[] = [
        'date' => date('M d', strtotime($date)),
        'sales' => floatval($daily_sales)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .dashboard-header p {
            color: #999;
            font-size: 14px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            opacity: 0.1;
            border-radius: 50%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card.primary::before {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.success::before {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card.warning::before {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.danger::before {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .stat-trend.up {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }

        .stat-trend.down {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .stat-body h3 {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-body p {
            color: #999;
            font-size: 14px;
            font-weight: 600;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .card-header a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .card-header a:hover {
            color: #5568d3;
        }

        /* Recent Orders Table */
        .orders-table {
            width: 100%;
        }

        .orders-table .order-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .orders-table .order-row:last-child {
            border-bottom: none;
        }

        .order-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .order-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .order-details h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 3px;
        }

        .order-details p {
            font-size: 12px;
            color: #999;
        }

        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .order-status.pending {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .order-status.completed {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }

        .order-status.cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .order-price {
            font-weight: 700;
            color: #667eea;
            font-size: 14px;
        }

        /* Low Stock Products */
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-info h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
        }

        .product-info p {
            font-size: 12px;
            color: #999;
        }

        .stock-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .stock-badge.low {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .stock-badge.out {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        /* Recent Users */
        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .user-item:last-child {
            border-bottom: none;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-info-small h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 3px;
        }

        .user-info-small p {
            font-size: 12px;
            color: #999;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: white;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            font-weight: 600;
        }

        .action-btn:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }

        .action-btn i {
            font-size: 20px;
            color: #667eea;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .empty-state p {
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-body h3 {
                font-size: 24px;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="dashboard-header">
            <h1>Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname']); ?>! Here's what's happening today.</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <?php if ($stats['new_users_today'] > 0): ?>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        +<?php echo $stats['new_users_today']; ?> today
                    </div>
                    <?php endif; ?>
                </div>
                <div class="stat-body">
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <div class="stat-icon success">
                        <i class="fas fa-box"></i>
                    </div>
                    <?php if ($stats['low_stock'] > 0): ?>
                    <div class="stat-trend down">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $stats['low_stock']; ?> low
                    </div>
                    <?php endif; ?>
                </div>
                <div class="stat-body">
                    <h3><?php echo $stats['total_products']; ?></h3>
                    <p>Total Products</p>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-header">
                    <div class="stat-icon warning">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <?php if ($stats['pending_orders'] > 0): ?>
                    <div class="stat-trend up">
                        <i class="fas fa-clock"></i>
                        <?php echo $stats['pending_orders']; ?> pending
                    </div>
                    <?php endif; ?>
                </div>
                <div class="stat-body">
                    <h3><?php echo $stats['total_orders']; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>

            <div class="stat-card danger">
                <div class="stat-header">
                    <div class="stat-icon danger">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <?php if ($stats['today_revenue'] > 0): ?>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        $<?php echo number_format($stats['today_revenue'], 2); ?> today
                    </div>
                    <?php endif; ?>
                </div>
                <div class="stat-body">
                    <h3>Rs.<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Sales Chart -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Sales Overview (Last 7 Days)</h3>
                </div>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-tachometer-alt"></i> Quick Stats</h3>
                </div>
                <div style="display: grid; gap: 15px;">
                    <div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Categories</p>
                                <h3 style="font-size: 24px; font-weight: 700;"><?php echo $stats['total_categories']; ?></h3>
                            </div>
                            <i class="fas fa-tags" style="font-size: 32px; opacity: 0.5;"></i>
                        </div>
                    </div>

                    <div style="padding: 15px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 12px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Pending Orders</p>
                                <h3 style="font-size: 24px; font-weight: 700;"><?php echo $stats['pending_orders']; ?></h3>
                            </div>
                            <i class="fas fa-clock" style="font-size: 32px; opacity: 0.5;"></i>
                        </div>
                    </div>

                    <div style="padding: 15px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border-radius: 12px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Unanswered Feedback</p>
                                <h3 style="font-size: 24px; font-weight: 700;"><?php echo $stats['unanswered_feedback']; ?></h3>
                            </div>
                            <i class="fas fa-comments" style="font-size: 32px; opacity: 0.5;"></i>
                        </div>
                    </div>

                    <div style="padding: 15px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <p style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">Out of Stock</p>
                                <h3 style="font-size: 24px; font-weight: 700;"><?php echo $stats['out_of_stock']; ?></h3>
                            </div>
                            <i class="fas fa-exclamation-triangle" style="font-size: 32px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders and Low Stock -->
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-shopping-bag"></i> Recent Orders</h3>
                    <a href="ManageOrders.php">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="orders-table">
                    <?php if ($recent_orders->num_rows > 0): ?>
                        <?php while ($order = $recent_orders->fetch_assoc()): ?>
                            <div class="order-row">
                                <div class="order-info">
                                    <div class="order-avatar">
                                        <?php echo strtoupper(substr($order['fullname'], 0, 1)); ?>
                                    </div>
                                    <div class="order-details">
                                        <h4><?php echo htmlspecialchars($order['fullname']); ?></h4>
                                        <p><?php echo htmlspecialchars($order['product_name']); ?></p>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <span class="order-status <?php echo $order['status'] == 0 ? 'pending' : ($order['status'] == 1 ? 'completed' : 'cancelled'); ?>">
                                        <?php echo $order['status'] == 0 ? 'Pending' : ($order['status'] == 1 ? 'Completed' : 'Cancelled'); ?>
                                    </span>
                                    <span class="order-price">Rs.<?php echo number_format($order['total_price'], 2); ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>No recent orders</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
                    <a href="ManageProducts.php">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div>
                    <?php if ($low_stock_products->num_rows > 0): ?>
                        <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                            <div class="product-item">
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($product['category_name']); ?></p>
                                </div>
                                <span class="stock-badge <?php echo $product['stock_quantity'] == 0 ? 'out' : 'low'; ?>">
                                    <?php echo $product['stock_quantity']; ?> units
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>All products well stocked</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pending Orders Section -->
        <?php if ($stats['pending_orders'] > 0): ?>
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Pending Orders (Requires Attention)</h3>
                <a href="ManageOrders.php?status=0">View All Pending <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="orders-table">
                <?php if ($pending_orders_list->num_rows > 0): ?>
                    <?php while ($order = $pending_orders_list->fetch_assoc()): ?>
                        <div class="order-row">
                            <div class="order-info">
                                <div class="order-avatar">
                                    <?php echo strtoupper(substr($order['fullname'], 0, 1)); ?>
                                </div>
                                <div class="order-details">
                                    <h4>#<?php echo $order['id']; ?> - <?php echo htmlspecialchars($order['fullname']); ?></h4>
                                    <p><?php echo htmlspecialchars($order['product_name']); ?> × <?php echo $order['quantity']; ?></p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="text-align: right;">
                                    <p style="font-size: 12px; color: #999; margin-bottom: 3px;">
                                        <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?>
                                    </p>
                                    <span class="order-price">Rs.<?php echo number_format($order['total_price'], 2); ?></span>
                                </div>
                                <a href="ManageOrders.php?status=0" class="action-btn" style="padding: 8px 16px; margin: 0; text-decoration: none; font-size: 13px; white-space: nowrap;">
                                    <i class="fas fa-edit"></i> Process
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending orders</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="quick-actions">
                <a href="ManageProducts.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i>
                    Add Product
                </a>
                <a href="ManageCategory.php" class="action-btn">
                    <i class="fas fa-folder-plus"></i>
                    Add Category
                </a>
                <a href="ManageUsers.php" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    Add User
                </a>
                <a href="ManageOrders.php" class="action-btn">
                    <i class="fas fa-tasks"></i>
                    Manage Orders
                </a>
                <a href="ManageFeedbacks.php" class="action-btn">
                    <i class="fas fa-comment-dots"></i>
                    View Feedback
                </a>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?php echo json_encode($sales_data); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.date),
                datasets: [{
                    label: 'Sales (Rs.)',
                    data: salesData.map(d => d.sales),
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: 'rgb(102, 126, 234)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 14
                        },
                        callbacks: {
                            label: function(context) {
                                return 'Sales: Rs.' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs.' + value;
                            }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>