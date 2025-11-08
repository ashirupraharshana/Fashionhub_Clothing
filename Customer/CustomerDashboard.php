<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

// Get logged-in user details
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, email, phone FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user statistics
$orders_query = "SELECT COUNT(*) as total_orders, 
                 COALESCE(SUM(total_price), 0) as total_spent 
                 FROM orders WHERE user_id = ?";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders_data = $orders_result->fetch_assoc();
$total_orders = $orders_data['total_orders'];
$total_spent = $orders_data['total_spent'];
$stmt->close();

$cart_query = "SELECT COUNT(*) as cart_items, 
               COALESCE(SUM(c.price * c.quantity), 0) as cart_total 
               FROM cart c WHERE user_id = ?";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$cart_data = $cart_result->fetch_assoc();
$cart_items = $cart_data['cart_items'];
$cart_total = $cart_data['cart_total'];
$stmt->close();

// Get recent orders with product details
$recent_orders_query = "SELECT o.*, p.product_name, p.product_photo
                        FROM orders o 
                        LEFT JOIN products p ON o.product_id = p.id
                        WHERE o.user_id = ? 
                        ORDER BY o.order_date DESC 
                        LIMIT 3";
$stmt = $conn->prepare($recent_orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result();
$stmt->close();

// Get featured products
$featured_products_query = "SELECT p.*, c.category_name,
                            (p.price - (p.price * p.discount / 100)) as final_price
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.stock_quantity > 0
                            ORDER BY p.id DESC 
                            LIMIT 4";
$featured_products = $conn->query($featured_products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
        }

        .dashboard-container {
            display: flex;
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
            gap: 30px;
        }

        /* Sidebar */
        .sidebar {
            width: 300px;
            background: white;
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
            overflow: hidden;
        }

        .sidebar-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            color: #e74c3c;
            font-weight: 800;
            margin: 0 auto 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            border: 5px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }

        .user-welcome {
            font-size: 22px;
            font-weight: 800;
            color: white;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .user-role {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.2);
            padding: 6px 16px;
            border-radius: 20px;
            display: inline-block;
            position: relative;
            z-index: 1;
        }

        .sidebar-menu {
            list-style: none;
            padding: 25px 15px;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: #e74c3c;
            transform: scaleY(0);
            transition: transform 0.3s;
        }

        .sidebar-menu a:hover {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.1) 100%);
            transform: translateX(5px);
        }

        .sidebar-menu a:hover::before,
        .sidebar-menu a.active::before {
            transform: scaleY(1);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.15) 0%, rgba(192, 57, 43, 0.15) 100%);
            color: #e74c3c;
        }

        .sidebar-menu a i {
            width: 22px;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
        }

        .welcome-section {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 24px;
            padding: 50px 45px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 15px 50px rgba(231, 76, 60, 0.3);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .welcome-section h1 {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
        }

        .welcome-section p {
            font-size: 18px;
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .stat-icon.orders {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .stat-icon.cart {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
        }

        .stat-icon.spent {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .stat-value {
            font-size: 42px;
            font-weight: 900;
            color: #2c3e50;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .stat-label {
            font-size: 15px;
            color: #7f8c8d;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
        }

        .section-header h2 {
            font-size: 26px;
            font-weight: 800;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header h2 i {
            color: #e74c3c;
        }

        .view-all-btn {
            padding: 12px 24px;
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .view-all-btn:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        /* Recent Orders */
        .orders-section {
            margin-bottom: 35px;
        }

        .orders-grid {
            display: grid;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            gap: 25px;
            transition: all 0.3s;
        }

        .order-card:hover {
            transform: translateX(8px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
        }

        .order-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            flex-shrink: 0;
        }

        .order-details {
            flex: 1;
        }

        .order-id {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .order-product {
            font-size: 17px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .order-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #7f8c8d;
        }

        .order-price {
            font-size: 22px;
            font-weight: 800;
            color: #e74c3c;
        }

        .empty-state {
            background: white;
            border-radius: 18px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
        }

        .empty-state i {
            font-size: 70px;
            color: #e8e8e8;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .empty-state p {
            color: #7f8c8d;
            font-size: 15px;
        }

        /* Featured Products */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .product-card-mini {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
            transition: all 0.4s;
            position: relative;
            cursor: pointer;
        }

        .product-card-mini:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .product-image-mini {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .product-image-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.6s;
        }

        .product-card-mini:hover .product-image-mini img {
            transform: scale(1.15) rotate(2deg);
        }

        .discount-badge-mini {
            position: absolute;
            top: 12px;
            left: 12px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }

        .product-info-mini {
            padding: 22px;
        }

        .product-category-mini {
            font-size: 11px;
            text-transform: uppercase;
            color: #e74c3c;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .product-name-mini {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.4;
            height: 44px;
            overflow: hidden;
        }

        .product-price-mini {
            font-size: 22px;
            font-weight: 900;
            color: #e74c3c;
        }

        .product-original-price-mini {
            font-size: 14px;
            color: #95a5a6;
            text-decoration: line-through;
            margin-left: 8px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: static;
            }

            .sidebar-header {
                padding: 35px 30px;
            }

            .user-avatar {
                width: 80px;
                height: 80px;
                font-size: 36px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
            }
        }

        @media (max-width: 768px) {
            .welcome-section {
                padding: 35px 30px;
            }

            .welcome-section h1 {
                font-size: 32px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .order-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/CustomerNavBar.php'; ?>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                </div>
                <div class="user-welcome">Welcome Back!</div>
                <div class="user-role"><?php echo htmlspecialchars($user['fullname']); ?></div>
            </div>
            <ul class="sidebar-menu">
                <li><a href="CustomerHome.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="Products.php"><i class="fas fa-shopping-bag"></i> Products</a></li>
                <li><a href="Orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                <li><a href="Cart.php"><i class="fas fa-shopping-cart"></i> Shopping Cart</a></li>
                <li><a href="Profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="Settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../Logout.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1>Hello, <?php echo htmlspecialchars(explode(' ', $user['fullname'])[0]); ?>! 👋</h1>
                <p>Discover the latest fashion trends and manage your orders seamlessly</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon orders">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon cart">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo $cart_items; ?></div>
                    <div class="stat-label">Items in Cart</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon spent">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-value">Rs. <?php echo number_format($total_spent, 0); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>

            <!-- Recent Orders Section -->
            <div class="orders-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent Orders</h2>
                    <?php if ($recent_orders->num_rows > 0): ?>
                        <a href="Orders.php" class="view-all-btn">View All</a>
                    <?php endif; ?>
                </div>

                <div class="orders-grid">
                    <?php if ($recent_orders->num_rows > 0): ?>
                        <?php while($order = $recent_orders->fetch_assoc()): ?>
                            <div class="order-card">
                                <div class="order-icon">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div class="order-details">
                                    <div class="order-id">Order #<?php echo $order['id']; ?></div>
                                    <div class="order-product">
                                        <?php echo htmlspecialchars($order['product_name']); ?>
                                    </div>
                                    <div class="order-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                                        <span><i class="fas fa-cube"></i> <?php echo $order['quantity']; ?> item(s)</span>
                                    </div>
                                </div>
                                <div class="order-price">
                                    Rs. <?php echo number_format($order['total_price'], 2); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>No Orders Yet</h3>
                            <p>Start shopping to see your orders here!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Featured Products Section -->
            <div class="section-header">
                <h2><i class="fas fa-star"></i> Latest Arrivals</h2>
                <a href="Products.php" class="view-all-btn">Browse All</a>
            </div>

            <div class="products-grid">
                <?php if ($featured_products->num_rows > 0): ?>
                    <?php while($product = $featured_products->fetch_assoc()): ?>
                        <div class="product-card-mini" onclick="window.location.href='Products.php'">
                            <?php if ($product['discount'] > 0): ?>
                                <div class="discount-badge-mini"><?php echo $product['discount']; ?>% OFF</div>
                            <?php endif; ?>
                            
                            <div class="product-image-mini">
                                <?php if (!empty($product['product_photo'])): ?>
                                    <img src="data:image/jpeg;base64,<?php echo $product['product_photo']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-box" style="font-size: 50px; color: #e8e8e8;"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-info-mini">
                                <div class="product-category-mini"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                <div class="product-name-mini"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                <div>
                                    <span class="product-price-mini">Rs. <?php echo number_format($product['final_price'], 2); ?></span>
                                    <?php if ($product['discount'] > 0): ?>
                                        <span class="product-original-price-mini">Rs. <?php echo number_format($product['price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>