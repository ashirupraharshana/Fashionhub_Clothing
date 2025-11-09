<?php

// Include the existing navbar
include 'Components/CustomerNavBar.php';

$stats_query = "SELECT 
    (SELECT COUNT(*) FROM products) as total_products,
    (SELECT COUNT(*) FROM users WHERE userrole = 0) as total_customers,
    (SELECT COUNT(DISTINCT category_id) FROM products) as total_categories,
    (SELECT COUNT(*) FROM feedback) as total_feedbacks
    FROM dual";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Fetch categories with product counts
$categories_query = "SELECT c.id, c.category_name, c.category_photo, COUNT(p.id) as product_count 
                     FROM categories c 
                     LEFT JOIN products p ON c.id = p.category_id 
                     GROUP BY c.id, c.category_name, c.category_photo 
                     ORDER BY product_count DESC 
                     LIMIT 4";
$categories_result = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Premium Fashion Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #2c3e50;
            overflow-x: hidden;
            background: #f8f9fa;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 120px 40px 80px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-content h1 {
            font-size: 64px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 24px;
            color: #2c3e50;
            letter-spacing: -2px;
        }

        .hero-content h1 .highlight {
            color: #e74c3c;
        }

        .hero-content p {
            font-size: 20px;
            color: #7f8c8d;
            margin-bottom: 40px;
            line-height: 1.6;
            max-width: 540px;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 18px 40px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #2c3e50;
            border: 2px solid #e8e8e8;
        }

        .btn-secondary:hover {
            border-color: #e74c3c;
            color: #e74c3c;
            transform: translateY(-2px);
        }

        .hero-image {
            position: relative;
            text-align: center;
        }

        .hero-image-placeholder {
            width: 100%;
            height: 500px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 120px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .floating-badge {
            position: absolute;
            background: white;
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: float 3s ease-in-out infinite;
        }

        .floating-badge.badge-1 {
            top: 10%;
            left: -5%;
        }

        .floating-badge.badge-2 {
            bottom: 15%;
            right: -5%;
        }

        .badge-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .badge-text h4 {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .badge-text p {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 600;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        /* Features Section */
        .features {
            padding: 100px 40px;
            background: white;
        }

        .features-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 70px;
        }

        .section-header h2 {
            font-size: 48px;
            font-weight: 900;
            color: #2c3e50;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .section-header p {
            font-size: 18px;
            color: #7f8c8d;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }

        .feature-card {
            background: #f8f9fa;
            padding: 40px 30px;
            border-radius: 16px;
            border: 2px solid #e8e8e8;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: #e74c3c;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .feature-card h3 {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        .feature-card p {
            font-size: 15px;
            color: #7f8c8d;
            line-height: 1.7;
        }

        /* Stats Section */
        .stats {
            padding: 100px 40px;
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            position: relative;
            overflow: hidden;
        }

        .stats::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .stats-container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 50px;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 56px;
            font-weight: 900;
            color: #e74c3c;
            margin-bottom: 12px;
        }

        .stat-item p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
        }

        /* Categories Section */
        .categories {
            padding: 100px 40px;
            background: #f8f9fa;
        }

        .categories-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .category-card {
            position: relative;
            height: 350px;
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
        }

        .category-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .category-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            font-size: 80px;
        }

        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, transparent 100%);
            padding: 30px;
            transition: all 0.3s;
        }

        .category-card:hover .category-overlay {
            background: linear-gradient(to top, rgba(231, 76, 60, 0.9) 0%, transparent 100%);
        }

        .category-overlay h3 {
            color: white;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .category-overlay p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            font-weight: 500;
        }

        /* CTA Section */
        .cta {
            padding: 100px 40px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .cta-container {
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .cta h2 {
            font-size: 48px;
            font-weight: 900;
            color: white;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .cta p {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .btn-white {
            background: white;
            color: #e74c3c;
            padding: 18px 40px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
        }

        .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 50px;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 48px;
            }

            .hero-content p {
                margin: 0 auto 40px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .floating-badge {
                display: none;
            }

            .section-header h2 {
                font-size: 36px;
            }
        }

        @media (max-width: 640px) {
            .hero {
                padding: 100px 20px 60px;
            }

            .hero-content h1 {
                font-size: 36px;
            }

            .hero-content p {
                font-size: 16px;
            }

            .features, .stats, .categories, .cta {
                padding: 60px 20px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1>
                    Discover Your<br>
                    <span class="highlight">Perfect Style</span>
                </h1>
                <p>
                    Explore the latest fashion trends and elevate your wardrobe with our premium collection. Quality meets affordability at FashionHub.
                </p>
                <div class="hero-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/fashionhub/Customer/Products.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag"></i>
                            Start Shopping
                        </a>
                        <a href="/fashionhub/Customer/CustomerOrders.php" class="btn btn-secondary">
                            <i class="fas fa-box"></i>
                            My Orders
                        </a>
                    <?php else: ?>
                        <a href="#" onclick="document.getElementById('signupBtn').click(); return false;" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Get Started
                        </a>
                        <a href="#" onclick="document.getElementById('loginBtn').click(); return false;" class="btn btn-secondary">
                            <i class="fas fa-sign-in-alt"></i>
                            Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hero-image">
                <div class="hero-image-placeholder">
                    <i class="fas fa-tshirt"></i>
                </div>
                
                <div class="floating-badge badge-1">
                    <div class="badge-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="badge-text">
                        <h4><?php echo $stats['total_customers']; ?>+</h4>
                        <p>Happy Customers</p>
                    </div>
                </div>

                <div class="floating-badge badge-2">
                    <div class="badge-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="badge-text">
                        <h4><?php echo $stats['total_products']; ?>+</h4>
                        <p>Products Available</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="features-container">
            <div class="section-header">
                <h2>Why Choose FashionHub</h2>
                <p>Experience shopping like never before with our premium services and unbeatable quality</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h3>Fast Delivery</h3>
                    <p>Get your orders delivered quickly to your doorstep. We ensure prompt and reliable shipping across the country.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure Payment</h3>
                    <p>Shop with confidence knowing your transactions are protected with our secure payment gateway.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3>Premium Quality</h3>
                    <p>Every product is carefully selected to meet our high standards of quality and style.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Our dedicated support team is always ready to assist you with any queries or concerns.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h3>Easy Returns</h3>
                    <p>Not satisfied? No worries! Enjoy hassle-free returns within 30 days of purchase.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-percent"></i>
                    </div>
                    <h3>Best Prices</h3>
                    <p>Get the best deals and exclusive discounts on your favorite fashion items.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3><?php echo $stats['total_products']; ?>+</h3>
                    <p>Products Available</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $stats['total_customers']; ?>+</h3>
                    <p>Happy Customers</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $stats['total_categories']; ?>+</h3>
                    <p>Categories</p>
                </div>
                <div class="stat-item">
                    <h3><?php echo $stats['total_feedbacks']; ?>+</h3>
                    <p>Customer Reviews</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories">
        <div class="categories-container">
            <div class="section-header">
                <h2>Shop By Category</h2>
                <p>Explore our diverse collection curated for every style and occasion</p>
            </div>

            <div class="categories-grid">
                <?php if ($categories_result->num_rows > 0): ?>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <div class="category-card" onclick="window.location.href='/fashionhub/Customer/Products.php?category=<?php echo $category['id']; ?>'">
                            <?php if (!empty($category['category_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $category['category_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($category['category_name']); ?>" 
                                     class="category-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="category-placeholder" style="display: none;">
                                    <i class="fas fa-tag"></i>
                                </div>
                            <?php else: ?>
                                <div class="category-placeholder">
                                    <i class="fas fa-tag"></i>
                                </div>
                            <?php endif; ?>
                            <div class="category-overlay">
                                <h3><?php echo htmlspecialchars($category['category_name']); ?></h3>
                                <p><?php echo $category['product_count']; ?> Products</p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="category-card">
                        <div class="category-placeholder">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="category-overlay">
                            <h3>Coming Soon</h3>
                            <p>New categories</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-container">
            <h2>Ready to Upgrade Your Wardrobe?</h2>
            <p>Join thousands of satisfied customers and discover the perfect fashion pieces that express your unique style.</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/fashionhub/Customer/Products.php" class="btn-white">
                    Browse Products
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php else: ?>
                <a href="#" onclick="document.getElementById('signupBtn').click(); return false;" class="btn-white">
                    Get Started Today
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'Components/Footer.php'; ?>
</body>
</html>