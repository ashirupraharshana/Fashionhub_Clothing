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

// Get recent feedback with admin replies
$feedback_query = "SELECT f.*, u.fullname 
                   FROM feedback f 
                   LEFT JOIN users u ON f.user_id = u.id 
                   WHERE f.admin_reply IS NOT NULL
                   ORDER BY f.replied_at DESC 
                   LIMIT 6";
$feedback_result = $conn->query($feedback_query);

// Get feedback statistics
$feedback_stats_query = "SELECT 
    COUNT(*) as total_feedbacks,
    COUNT(CASE WHEN admin_reply IS NOT NULL THEN 1 END) as replied_feedbacks,
    COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_feedbacks
    FROM feedback";
$feedback_stats_result = $conn->query($feedback_stats_query);
$feedback_stats = $feedback_stats_result->fetch_assoc();

// ===== IMAGE URLS =====
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
            background: #f5f7fa;
            padding-top: 70px;
            min-height: 100vh;
            color: #2d3748;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Hero Section */
        .hero-welcome {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.95) 0%, rgba(192, 57, 43, 0.95) 100%),
                        url('<?php echo $hero_background; ?>');
            background-size: cover;
            background-position: center;
            background-blend-mode: multiply;
            border-radius: 20px;
            padding: 80px 50px;
            color: white;
            margin-bottom: 40px;
            box-shadow: 0 10px 40px rgba(231, 76, 60, 0.3);
            position: relative;
            overflow: hidden;
            min-height: 320px;
            display: flex;
            align-items: center;
        }

        .hero-welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: heroFloat 8s ease-in-out infinite;
        }

        @keyframes heroFloat {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-30px, -30px); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-welcome h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .hero-welcome p {
            font-size: 20px;
            opacity: 0.95;
            max-width: 700px;
            line-height: 1.6;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #e74c3c;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: #e74c3c;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #2d3748;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #e74c3c;
        }

        .btn-primary {
            padding: 12px 24px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        /* Orders Section */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 40px;
        }

        .order-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            transform: translateX(5px);
        }

        .order-image {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            background: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .order-image i {
            font-size: 28px;
            color: #e74c3c;
        }

        .order-info {
            flex: 1;
        }

        .order-number {
            font-size: 12px;
            color: #718096;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .order-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .order-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #718096;
        }

        .order-meta i {
            color: #e74c3c;
            margin-right: 4px;
        }

        .order-price {
            font-size: 20px;
            font-weight: 700;
            color: #e74c3c;
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            width: 100%;
            height: 220px;
            background: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.1);
        }

        .product-image i {
            font-size: 50px;
            color: #cbd5e0;
        }

        .discount-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .product-details {
            padding: 20px;
        }

        .product-category {
            font-size: 11px;
            color: #e74c3c;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 12px;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-price {
            font-size: 22px;
            font-weight: 700;
            color: #e74c3c;
        }

        .product-old-price {
            font-size: 14px;
            color: #a0aec0;
            text-decoration: line-through;
            margin-left: 8px;
        }

        /* Feedback Section */
        .feedback-section {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }

        .feedback-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #f7fafc;
        }

        .feedback-stat {
            text-align: center;
        }

        .feedback-stat-icon {
            width: 50px;
            height: 50px;
            background: #fff5f5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: #e74c3c;
            font-size: 22px;
        }

        .feedback-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .feedback-stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .feedback-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .feedback-card {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .feedback-card:hover {
            border-color: #e74c3c;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .feedback-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .feedback-avatar {
            width: 45px;
            height: 45px;
            background: #e74c3c;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .feedback-user {
            flex: 1;
        }

        .feedback-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .feedback-date {
            font-size: 12px;
            color: #718096;
        }

        .feedback-message {
            color: #4a5568;
            line-height: 1.6;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .admin-reply {
            background: #fff5f5;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #e74c3c;
            margin-top: 12px;
        }

        .admin-reply-header {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #e74c3c;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .admin-reply-text {
            color: #4a5568;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #718096;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-welcome {
                padding: 50px 30px;
                min-height: 280px;
            }

            .hero-welcome h1 {
                font-size: 32px;
            }

            .hero-welcome p {
                font-size: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .products-grid,
            .feedback-grid {
                grid-template-columns: 1fr;
            }

            .order-card {
                flex-direction: column;
                text-align: center;
            }

            .feedback-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/CustomerNavBar.php'; ?>

    <div class="dashboard-container">
        <!-- Hero Welcome -->
        <div class="hero-welcome">
            <div class="hero-content">
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['fullname'])[0]); ?>! 👋</h1>
                <p>Explore the latest fashion trends and manage your shopping experience from your personal dashboard.</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-value"><?php echo $total_orders; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-label">Cart Items</div>
                </div>
                <div class="stat-value"><?php echo $cart_items; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-value">Rs. <?php echo number_format($total_spent, 0); ?></div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Recent Orders
            </h2>
            <?php if ($recent_orders->num_rows > 0): ?>
                <a href="Orders.php" class="btn-primary">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="orders-list">
            <?php if ($recent_orders->num_rows > 0): ?>
                <?php while($order = $recent_orders->fetch_assoc()): ?>
                    <div class="order-card">
                        <div class="order-image">
                            <?php if (!empty($order['product_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $order['product_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-shopping-bag"></i>
                            <?php endif; ?>
                        </div>
                        <div class="order-info">
                            <div class="order-number">Order #<?php echo $order['id']; ?></div>
                            <div class="order-title"><?php echo htmlspecialchars($order['product_name']); ?></div>
                            <div class="order-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                                <span><i class="fas fa-cube"></i> <?php echo $order['quantity']; ?> item(s)</span>
                            </div>
                        </div>
                        <div class="order-price">Rs. <?php echo number_format($order['total_price'], 2); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Orders Yet</h3>
                    <p>Start shopping to see your orders here!</p>
                    <a href="Products.php" class="btn-primary">Browse Products</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Customer Feedback -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-comments"></i>
                Customer Feedback
            </h2>
            <a href="Feedback.php" class="btn-primary">
                Share Feedback <i class="fas fa-paper-plane"></i>
            </a>
        </div>

        <div class="feedback-section">
            <div class="feedback-stats">
                <div class="feedback-stat">
                    <div class="feedback-stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="feedback-stat-value"><?php echo $feedback_stats['total_feedbacks']; ?></div>
                    <div class="feedback-stat-label">Total Feedback</div>
                </div>
                <div class="feedback-stat">
                    <div class="feedback-stat-icon">
                        <i class="fas fa-reply-all"></i>
                    </div>
                    <div class="feedback-stat-value"><?php echo $feedback_stats['replied_feedbacks']; ?></div>
                    <div class="feedback-stat-label">Replied</div>
                </div>
                <div class="feedback-stat">
                    <div class="feedback-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="feedback-stat-value"><?php echo $feedback_stats['recent_feedbacks']; ?></div>
                    <div class="feedback-stat-label">This Week</div>
                </div>
            </div>

            <?php if ($feedback_result->num_rows > 0): ?>
                <div class="feedback-grid">
                    <?php while($feedback = $feedback_result->fetch_assoc()): ?>
                        <div class="feedback-card">
                            <div class="feedback-header">
                                <div class="feedback-avatar">
                                    <?php echo strtoupper(substr($feedback['name'], 0, 1)); ?>
                                </div>
                                <div class="feedback-user">
                                    <div class="feedback-name"><?php echo htmlspecialchars($feedback['name']); ?></div>
                                    <div class="feedback-date">
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($feedback['submitted_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="feedback-message">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                            <?php if (!empty($feedback['admin_reply'])): ?>
                                <div class="admin-reply">
                                    <div class="admin-reply-header">
                                        <i class="fas fa-reply"></i>
                                        Admin Response
                                    </div>
                                    <div class="admin-reply-text">
                                        <?php echo nl2br(htmlspecialchars($feedback['admin_reply'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>No Feedback Yet</h3>
                    <p>Be the first to share your thoughts!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Latest Products -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-star"></i>
                Latest Arrivals
            </h2>
            <a href="Products.php" class="btn-primary">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="products-grid">
            <?php if ($featured_products->num_rows > 0): ?>
                <?php while($product = $featured_products->fetch_assoc()): ?>
                    <div class="product-card" onclick="window.location.href='Products.php'">
                        <div class="product-image">
                            <?php if ($product['discount'] > 0): ?>
                                <div class="discount-badge"><?php echo $product['discount']; ?>% OFF</div>
                            <?php endif; ?>
                            <?php if (!empty($product['product_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $product['product_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-tshirt"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-details">
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div>
                                <span class="product-price">Rs. <?php echo number_format($product['final_price'], 2); ?></span>
                                <?php if ($product['discount'] > 0): ?>
                                    <span class="product-old-price">Rs. <?php echo number_format($product['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>
</body>
</html>