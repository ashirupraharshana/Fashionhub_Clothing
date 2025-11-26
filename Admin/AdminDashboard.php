<?php
session_start();
include '../db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['userrole'] != 1) {
    header("Location: /fashionhub/Homepage.php");
    exit();
}

// Fetch dashboard statistics
$totalCustomers = $conn->query("SELECT COUNT(*) as count FROM users WHERE userrole = 0")->fetch_assoc()['count'];
$totalProducts = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$totalOrders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$totalRevenue = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status = 1")->fetch_assoc()['total'] ?? 0;

// Pending orders
$pendingOrders = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 0")->fetch_assoc()['count'];

// Recent orders (last 5)
$recentOrdersQuery = "SELECT o.*, u.fullname, p.product_name 
                      FROM orders o 
                      LEFT JOIN users u ON o.user_id = u.id 
                      LEFT JOIN products p ON o.product_id = p.id 
                      ORDER BY o.order_date DESC 
                      LIMIT 5";
$recentOrders = $conn->query($recentOrdersQuery);

// Low stock products (quantity < 10)
$lowStockQuery = "SELECT p.product_name, ps.size, ps.quantity 
                  FROM product_sizes ps 
                  INNER JOIN products p ON ps.product_id = p.id 
                  WHERE ps.quantity < 10 
                  ORDER BY ps.quantity ASC 
                  LIMIT 5";
$lowStockProducts = $conn->query($lowStockQuery);

// Best selling products
$bestSellingQuery = "SELECT p.product_name, COUNT(o.id) as total_sold, SUM(o.total_price) as revenue
                     FROM orders o
                     INNER JOIN products p ON o.product_id = p.id
                     WHERE o.status = 1
                     GROUP BY o.product_id
                     ORDER BY total_sold DESC
                     LIMIT 5";
$bestSellingProducts = $conn->query($bestSellingQuery);

// Monthly sales data for chart (last 6 months)
$monthlySalesQuery = "SELECT DATE_FORMAT(order_date, '%Y-%m') as month, 
                      SUM(total_price) as revenue,
                      COUNT(*) as orders
                      FROM orders 
                      WHERE status = 1 AND order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                      GROUP BY month
                      ORDER BY month ASC";
$monthlySales = $conn->query($monthlySalesQuery);

$salesData = [];
while ($row = $monthlySales->fetch_assoc()) {
    $salesData[] = $row;
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
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<style>
    /* Dashboard.css - FashionHub Admin Dashboard */

:root {
    --primary-black: #000000;
    --primary-gold: #D4AF37;
    --light-beige: #F5F5DC;
    --soft-white: #FFFFFF;
    --text-gray: #666666;
    --border-gray: #E5E5E5;
    --success-green: #28A745;
    --warning-orange: #FFA500;
    --danger-red: #DC3545;
    --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
    --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
    --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.16);
}

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
/* Welcome Section */
.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding: 24px;
    background: linear-gradient(135deg, var(--primary-black) 0%, #2d2d2d 100%);
    border-radius: 16px;
    color: var(--soft-white);
    box-shadow: var(--shadow-md);
    animation: fadeInDown 0.6s ease;
}

.welcome-text h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
}

.welcome-text p {
    font-size: 16px;
    opacity: 0.9;
}

.quick-actions {
    display: flex;
    gap: 12px;
}

.quick-action-btn {
    padding: 12px 24px;
    background: var(--primary-gold);
    color: var(--primary-black);
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
}

.quick-action-btn:hover {
    background: #C5A028;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(212, 175, 55, 0.4);
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--soft-white);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
    border-radius: 50%;
    transform: translate(30%, -30%);
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: var(--soft-white);
    flex-shrink: 0;
}

.stat-card.customers .stat-icon {
    background: linear-gradient(135deg, #667EEA 0%, #764BA2 100%);
}

.stat-card.products .stat-icon {
    background: linear-gradient(135deg, #F093FB 0%, #F5576C 100%);
}

.stat-card.orders .stat-icon {
    background: linear-gradient(135deg, #4FACFE 0%, #00F2FE 100%);
}

.stat-card.revenue .stat-icon {
    background: linear-gradient(135deg, #43E97B 0%, #38F9D7 100%);
}

.stat-content h3 {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-black);
    margin-bottom: 4px;
}

.stat-content p {
    font-size: 14px;
    color: var(--text-gray);
    margin-bottom: 8px;
}

.stat-trend {
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stat-trend.positive {
    color: var(--success-green);
}

.stat-trend.neutral {
    color: var(--text-gray);
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.bottom-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
}

/* Dashboard Cards */
.dashboard-card {
    background: var(--soft-white);
    border-radius: 16px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: all 0.3s ease;
    animation: fadeIn 0.6s ease;
}

.dashboard-card:hover {
    box-shadow: var(--shadow-md);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 2px solid var(--border-gray);
}

.card-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-black);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header h3 i {
    color: var(--primary-gold);
}

.view-all-link {
    font-size: 14px;
    color: var(--primary-gold);
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.view-all-link:hover {
    color: #C5A028;
    gap: 10px;
}

.card-actions select {
    padding: 8px 16px;
    border: 2px solid var(--border-gray);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--primary-black);
    cursor: pointer;
    transition: all 0.3s ease;
}

.card-actions select:focus {
    outline: none;
    border-color: var(--primary-gold);
}

.card-body {
    padding: 24px;
}

/* Chart Card */
.chart-card canvas {
    max-height: 320px;
}

/* Orders List */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: var(--light-beige);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.order-item:hover {
    background: #ECECD6;
    transform: translateX(4px);
}

.order-info {
    flex: 1;
}

.order-id {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-black);
    margin-bottom: 4px;
}

.order-customer {
    font-size: 13px;
    color: var(--text-gray);
    margin-bottom: 2px;
}

.order-product {
    font-size: 13px;
    color: var(--text-gray);
}

.order-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}

.order-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.order-status.pending {
    background: #FFF3CD;
    color: #856404;
}

.order-status.delivered {
    background: #D4EDDA;
    color: #155724;
}

.order-status.cancelled {
    background: #F8D7DA;
    color: #721C24;
}

.order-price {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary-gold);
}

/* Low Stock Alert */
.alert-card {
    border-left: 4px solid var(--danger-red);
}

.stock-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: #FFF3F3;
    border-radius: 12px;
    border-left: 3px solid var(--danger-red);
    transition: all 0.3s ease;
}

.stock-item:hover {
    background: #FFE5E5;
    transform: translateX(4px);
}

.stock-info {
    flex: 1;
}

.stock-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-black);
    margin-bottom: 4px;
}

.stock-size {
    font-size: 13px;
    color: var(--text-gray);
}

.stock-quantity {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

.stock-quantity.critical {
    background: var(--danger-red);
    color: var(--soft-white);
}

.stock-quantity.warning {
    background: var(--warning-orange);
    color: var(--soft-white);
}

/* Best Sellers */
.bestseller-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.bestseller-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: linear-gradient(135deg, #FFF9E6 0%, #FFF3CC 100%);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.bestseller-item:hover {
    background: linear-gradient(135deg, #FFF3CC 0%, #FFE699 100%);
    transform: scale(1.02);
}

.bestseller-rank {
    width: 36px;
    height: 36px;
    background: var(--primary-gold);
    color: var(--primary-black);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    flex-shrink: 0;
}

.bestseller-info {
    flex: 1;
}

.bestseller-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--primary-black);
    margin-bottom: 6px;
}

.bestseller-stats {
    display: flex;
    gap: 12px;
    font-size: 13px;
    color: var(--text-gray);
}

.bestseller-revenue {
    color: var(--primary-gold);
    font-weight: 700;
}

/* Quick Stats */
.quick-stats-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.quick-stat-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--light-beige);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.quick-stat-item:hover {
    background: #ECECD6;
    transform: translateX(4px);
}

.quick-stat-item i {
    font-size: 24px;
    color: var(--primary-gold);
}

.quick-stat-content {
    display: flex;
    flex-direction: column;
}

.quick-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-black);
}

.quick-stat-label {
    font-size: 13px;
    color: var(--text-gray);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-gray);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state p {
    font-size: 14px;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1400px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1024px) {
    .bottom-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 20px 16px;
    }

    .welcome-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }

    .quick-actions {
        width: 100%;
        flex-direction: column;
    }

    .quick-action-btn {
        width: 100%;
        justify-content: center;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .dashboard-grid,
    .bottom-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .view-all-link {
        align-self: flex-end;
    }
}

@media (max-width: 480px) {
    .welcome-text h1 {
        font-size: 22px;
    }

    .stat-content h3 {
        font-size: 28px;
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
}
    </style>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <div class="main-content" id="mainContent">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname']); ?>! 👋</h1>
                <p>Here's what's happening with your store today.</p>
            </div>
            <div class="quick-actions">
                <a href="ManageProducts.php" class="quick-action-btn">
                    <i class="fas fa-plus"></i> Add Product
                </a>
                <a href="ManageOrders.php" class="quick-action-btn">
                    <i class="fas fa-shopping-cart"></i> View Orders
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card customers">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($totalCustomers); ?></h3>
                    <p>Total Customers</p>
                    <span class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i> 12% from last month
                    </span>
                </div>
            </div>

            <div class="stat-card products">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($totalProducts); ?></h3>
                    <p>Total Products</p>
                    <span class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i> 8% from last month
                    </span>
                </div>
            </div>

            <div class="stat-card orders">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($totalOrders); ?></h3>
                    <p>Total Orders</p>
                    <span class="stat-trend neutral">
                        <i class="fas fa-minus"></i> No change
                    </span>
                </div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>Rs <?php echo number_format($totalRevenue, 2); ?></h3>
                    <p>Total Revenue</p>
                    <span class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i> 23% from last month
                    </span>
                </div>
            </div>
        </div>

        <!-- Charts & Tables Row -->
        <div class="dashboard-grid">
            <!-- Sales Chart -->
            <div class="dashboard-card chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Sales Overview</h3>
                    <div class="card-actions">
                        <select class="chart-period-select">
                            <option value="6months">Last 6 Months</option>
                            <option value="year">This Year</option>
                            <option value="all">All Time</option>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    <a href="ManageOrders.php" class="view-all-link">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="card-body">
                    <div class="orders-list">
                        <?php if ($recentOrders->num_rows > 0): ?>
                            <?php while ($order = $recentOrders->fetch_assoc()): 
                                $statusClass = $order['status'] == 0 ? 'pending' : ($order['status'] == 1 ? 'delivered' : 'cancelled');
                                $statusText = $order['status'] == 0 ? 'Pending' : ($order['status'] == 1 ? 'Delivered' : 'Cancelled');
                            ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <div class="order-id">#<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></div>
                                        <div class="order-customer"><?php echo htmlspecialchars($order['fullname'] ?: 'Guest'); ?></div>
                                        <div class="order-product"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                    </div>
                                    <div class="order-meta">
                                        <span class="order-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        <span class="order-price">Rs <?php echo number_format($order['total_price'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No recent orders</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row: Low Stock & Best Sellers -->
        <div class="bottom-grid">
            <!-- Low Stock Alert -->
            <div class="dashboard-card alert-card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
                </div>
                <div class="card-body">
                    <div class="stock-list">
                        <?php if ($lowStockProducts->num_rows > 0): ?>
                            <?php while ($product = $lowStockProducts->fetch_assoc()): ?>
                                <div class="stock-item">
                                    <div class="stock-info">
                                        <div class="stock-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                        <div class="stock-size">Size: <?php echo htmlspecialchars($product['size']); ?></div>
                                    </div>
                                    <div class="stock-quantity <?php echo $product['quantity'] < 5 ? 'critical' : 'warning'; ?>">
                                        <?php echo $product['quantity']; ?> left
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <p>All products are well stocked!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Best Selling Products -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Best Sellers</h3>
                </div>
                <div class="card-body">
                    <div class="bestseller-list">
                        <?php if ($bestSellingProducts->num_rows > 0): ?>
                            <?php $rank = 1; ?>
                            <?php while ($product = $bestSellingProducts->fetch_assoc()): ?>
                                <div class="bestseller-item">
                                    <div class="bestseller-rank">#<?php echo $rank++; ?></div>
                                    <div class="bestseller-info">
                                        <div class="bestseller-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                        <div class="bestseller-stats">
                                            <span><?php echo $product['total_sold']; ?> sold</span>
                                            <span class="bestseller-revenue">Rs <?php echo number_format($product['revenue'], 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-bar"></i>
                                <p>No sales data yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Quick Stats</h3>
                </div>
                <div class="card-body">
                    <div class="quick-stats-list">
                        <div class="quick-stat-item">
                            <i class="fas fa-clock"></i>
                            <div class="quick-stat-content">
                                <span class="quick-stat-value"><?php echo $pendingOrders; ?></span>
                                <span class="quick-stat-label">Pending Orders</span>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <i class="fas fa-percentage"></i>
                            <div class="quick-stat-content">
                                <span class="quick-stat-value">
                                    <?php echo $totalOrders > 0 ? round(($totalOrders - $pendingOrders) / $totalOrders * 100) : 0; ?>%
                                </span>
                                <span class="quick-stat-label">Completion Rate</span>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <i class="fas fa-star"></i>
                            <div class="quick-stat-content">
                                <span class="quick-stat-value">4.8</span>
                                <span class="quick-stat-label">Average Rating</span>
                            </div>
                        </div>
                        <div class="quick-stat-item">
                            <i class="fas fa-comments"></i>
                            <div class="quick-stat-content">
                                <span class="quick-stat-value">
                                    <?php echo $conn->query("SELECT COUNT(*) as count FROM feedback WHERE admin_reply IS NULL")->fetch_assoc()['count']; ?>
                                </span>
                                <span class="quick-stat-label">Pending Feedback</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart Data from PHP
        const salesData = <?php echo json_encode($salesData); ?>;
        
        // Extract labels and data
        const chartLabels = salesData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        });
        const chartRevenue = salesData.map(item => parseFloat(item.revenue));
        const chartOrders = salesData.map(item => parseInt(item.orders));

        // Create Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Revenue (Rs )',
                        data: chartRevenue,
                        borderColor: '#D4AF37',
                        backgroundColor: 'rgba(212, 175, 55, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#D4AF37',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7
                    },
                    {
                        label: 'Orders',
                        data: chartOrders,
                        borderColor: '#000',
                        backgroundColor: 'rgba(0, 0, 0, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#000',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 13,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.label === 'Revenue ($)') {
                                    label += 'Rs ' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return 'Rs ' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });

        // Animate stats on load
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>