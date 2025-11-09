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

// ===== ADD YOUR IMAGE URLS HERE =====
$hero_background = "https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1920"; 
$user_avatar = "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=200"; 
$banner_decoration = "https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=800";
$empty_orders_image = "https://images.unsplash.com/photo-1607083206968-13611e3d76db?w=400";
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
            background: linear-gradient(135deg, #fef5f5 0%, #fdeef0 100%);
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

        /* Sidebar with Photo */
        .sidebar {
            width: 320px;
            background: white;
            border-radius: 24px;
            padding: 0;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
            height: fit-content;
            position: sticky;
            top: 100px;
            overflow: hidden;
        }

        .sidebar-header {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.95) 0%, rgba(192, 57, 43, 0.95) 100%),
                        url('<?php echo $banner_decoration; ?>');
            background-size: cover;
            background-position: center;
            background-blend-mode: multiply;
            padding: 45px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.15) 0%, transparent 60%);
        }

        .user-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
            border: 6px solid rgba(255, 255, 255, 0.4);
            position: relative;
            z-index: 1;
            overflow: hidden;
            background-image: url('<?php echo $user_avatar; ?>');
            background-size: cover;
            background-position: center;
        }

        .user-avatar-fallback {
            font-size: 42px;
            color: #e74c3c;
            font-weight: 800;
        }

        .user-welcome {
            font-size: 24px;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .user-role {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.95);
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            padding: 8px 18px;
            border-radius: 20px;
            display: inline-block;
            position: relative;
            z-index: 1;
            font-weight: 600;
        }

        .sidebar-menu {
            list-style: none;
            padding: 30px 20px;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 22px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 14px;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            transform: scaleY(0);
            transition: transform 0.3s;
        }

        .sidebar-menu a:hover {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.12) 0%, rgba(192, 57, 43, 0.12) 100%);
            transform: translateX(8px);
        }

        .sidebar-menu a:hover::before,
        .sidebar-menu a.active::before {
            transform: scaleY(1);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.18) 0%, rgba(192, 57, 43, 0.18) 100%);
            color: #e74c3c;
        }

        .sidebar-menu a i {
            width: 24px;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
        }

        /* Hero Welcome Section with Photo */
        .welcome-section {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.97) 0%, rgba(192, 57, 43, 0.97) 100%),
                        url('<?php echo $hero_background; ?>');
            background-size: cover;
            background-position: center;
            background-blend-mode: multiply;
            border-radius: 28px;
            padding: 60px 50px;
            color: white;
            margin-bottom: 35px;
            box-shadow: 0 20px 60px rgba(231, 76, 60, 0.35);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 65%);
            border-radius: 50%;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to top, rgba(0,0,0,0.1), transparent);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-section h1 {
            font-size: 48px;
            font-weight: 900;
            margin-bottom: 15px;
            text-shadow: 0 3px 15px rgba(0,0,0,0.2);
            letter-spacing: -0.5px;
        }

        .welcome-section p {
            font-size: 19px;
            opacity: 0.96;
            max-width: 600px;
            line-height: 1.6;
            text-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        /* Stats Cards with Icons */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 22px;
            padding: 38px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.07);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(231, 76, 60, 0.08);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(35%, -35%);
        }

        .stat-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            border-color: rgba(231, 76, 60, 0.2);
        }

        .stat-icon {
            width: 75px;
            height: 75px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin-bottom: 22px;
            position: relative;
            z-index: 1;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon.orders {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .stat-icon.cart {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .stat-icon.spent {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .stat-value {
            font-size: 44px;
            font-weight: 900;
            color: #2c3e50;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 15px;
            color: #7f8c8d;
            font-weight: 600;
            position: relative;
            z-index: 1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .section-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .section-header h2 i {
            color: #e74c3c;
            font-size: 26px;
        }

        .view-all-btn {
            padding: 13px 28px;
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .view-all-btn:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(231, 76, 60, 0.35);
        }

        /* Recent Orders with Product Images */
        .orders-section {
            margin-bottom: 40px;
        }

        .orders-grid {
            display: grid;
            gap: 22px;
        }

        .order-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            gap: 28px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(231, 76, 60, 0.06);
        }

        .order-card:hover {
            transform: translateX(10px);
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.12);
            border-color: rgba(231, 76, 60, 0.15);
        }

        .order-image {
            width: 90px;
            height: 90px;
            border-radius: 18px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-icon {
            font-size: 32px;
            color: #e74c3c;
        }

        .order-details {
            flex: 1;
        }

        .order-id {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .order-product {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .order-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .order-meta i {
            color: #e74c3c;
            margin-right: 4px;
        }

        .order-price {
            font-size: 24px;
            font-weight: 900;
            color: #e74c3c;
            letter-spacing: -0.5px;
        }

        /* Empty State with Image */
        .empty-state {
            background: white;
            border-radius: 22px;
            padding: 70px 40px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            border: 2px dashed rgba(231, 76, 60, 0.2);
        }

        .empty-state-image {
            width: 200px;
            height: 200px;
            margin: 0 auto 25px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-state-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.8;
        }

        .empty-state i {
            font-size: 80px;
            color: #e8e8e8;
        }

        .empty-state h3 {
            font-size: 26px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .empty-state p {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 25px;
        }

        .empty-state-btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            text-decoration: none;
            border-radius: 14px;
            font-weight: 700;
            transition: all 0.3s;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .empty-state-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(231, 76, 60, 0.4);
        }

        /* Featured Products with Photos */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 28px;
        }

        .product-card-mini {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            cursor: pointer;
            border: 1px solid rgba(231, 76, 60, 0.06);
        }

        .product-card-mini:hover {
            transform: translateY(-15px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            border-color: rgba(231, 76, 60, 0.15);
        }

        .product-image-mini {
            width: 100%;
            height: 250px;
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
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card-mini:hover .product-image-mini img {
            transform: scale(1.12);
        }

        .discount-badge-mini {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
            z-index: 10;
        }

        .product-info-mini {
            padding: 26px;
        }

        .product-category-mini {
            font-size: 11px;
            text-transform: uppercase;
            color: #e74c3c;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .product-name-mini {
            font-size: 17px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 14px;
            line-height: 1.5;
            height: 51px;
            overflow: hidden;
        }

        .product-price-mini {
            font-size: 24px;
            font-weight: 900;
            color: #e74c3c;
            letter-spacing: -0.5px;
        }

        .product-original-price-mini {
            font-size: 15px;
            color: #95a5a6;
            text-decoration: line-through;
            margin-left: 10px;
            font-weight: 600;
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
                padding: 40px 30px;
            }

            .user-avatar {
                width: 90px;
                height: 90px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            .welcome-section {
                padding: 40px 30px;
            }

            .welcome-section h1 {
                font-size: 36px;
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

            .order-image {
                width: 120px;
                height: 120px;
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
                    <!-- If user avatar image doesn't load, show initial -->
                    <span class="user-avatar-fallback" style="display: none;">
                        <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                    </span>
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
                <div class="welcome-content">
                    <h1>Hello, <?php echo htmlspecialchars(explode(' ', $user['fullname'])[0]); ?>! </h1>
                    <p>Discover the latest fashion trends and manage your orders seamlessly. Welcome to your personalized shopping dashboard!</p>
                </div>
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
                        <a href="CustomerOrders.php" class="view-all-btn">View All Orders</a>
                    <?php endif; ?>
                </div>

                <div class="orders-grid">
                    <?php if ($recent_orders->num_rows > 0): ?>
                        <?php while($order = $recent_orders->fetch_assoc()): ?>
                            <div class="order-card">
                                <div class="order-image">
                                    <?php if (!empty($order['product_photo'])): ?>
                                        <img src="data:image/jpeg;base64,<?php echo $order['product_photo']; ?>" 
                                             alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                                    <?php else: ?>
                                        <i class="order-icon fas fa-shopping-bag"></i>
                                    <?php endif; ?>
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
                            <div class="empty-state-image">
                                <img src="<?php echo $empty_orders_image; ?>" alt="No orders">
                            </div>
                            <h3>No Orders Yet</h3>
                            <p>Start shopping to see your orders here!</p>
                            <a href="Products.php" class="empty-state-btn">
                                <i class="fas fa-shopping-bag"></i> Browse Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Featured Products Section -->
            <div class="section-header">
                <h2><i class="fas fa-star"></i> Latest Arrivals</h2>
                <a href="Products.php" class="view-all-btn">Browse All Products</a>
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
                                    <i class="fas fa-tshirt" style="font-size: 60px; color: #e8e8e8;"></i>
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

    <script>
        // Handle user avatar fallback
        document.addEventListener('DOMContentLoaded', function() {
            const userAvatar = document.querySelector('.user-avatar');
            const fallback = document.querySelector('.user-avatar-fallback');
            
            // Check if background image loaded
            const img = new Image();
            img.src = '<?php echo $user_avatar; ?>';
            img.onerror = function() {
                userAvatar.style.backgroundImage = 'none';
                userAvatar.style.background = 'white';
                if (fallback) {
                    fallback.style.display = 'block';
                }
            };
        });
    </script>
      <?php include 'Components/Footer.php'; ?>
</body>
</html>