<?php

include 'Components/CustomerNavBar.php';

$stats_query = "SELECT 
    (SELECT COUNT(*) FROM products) as total_products,
    (SELECT COUNT(*) FROM users WHERE userrole = 0) as total_customers,
    (SELECT COUNT(*) FROM categories) as total_categories,
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
                     LIMIT 6";
$categories_result = $conn->query($categories_query);

// Fetch featured products (top 4)
$featured_products_query = "SELECT p.id, p.product_name, p.product_photo, p.stock_quantity, 
                            p.price, p.discount, c.category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.stock_quantity > 0 
                            ORDER BY p.id DESC 
                            LIMIT 4";
$featured_products = $conn->query($featured_products_query);

// Fetch a hero image (latest product with image)
$hero_image_query = "SELECT product_photo FROM products WHERE product_photo IS NOT NULL ORDER BY id DESC LIMIT 1";
$hero_image_result = $conn->query($hero_image_query);
$hero_image = $hero_image_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Elevate Your Style</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2c3e50;
            --accent: #e74c3c;
            --accent-dark: #c0392b;
            --text: #2c3e50;
            --text-light: #7f8c8d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text);
            overflow-x: hidden;
            background: var(--white);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 50%, #fef5f5 100%);
            padding: 50px 5% 80px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -15%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.1); opacity: 0.8; }
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

        .hero-content {
            animation: fadeInLeft 1s ease-out;
        }

        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 24px;
            animation: fadeIn 1s ease-out 0.3s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .hero-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 72px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 24px;
            color: var(--primary);
            letter-spacing: -2px;
        }

        .hero-content h1 .highlight {
            color: var(--accent);
            position: relative;
            display: inline-block;
        }

        .hero-content h1 .highlight::after {
            content: '';
            position: absolute;
            bottom: 8px;
            left: 0;
            right: 0;
            height: 12px;
            background: rgba(231, 76, 60, 0.2);
            z-index: -1;
            border-radius: 4px;
        }

        .hero-content p {
            font-size: 18px;
            color: var(--text-light);
            margin-bottom: 40px;
            line-height: 1.8;
            max-width: 540px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 36px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--text);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(44, 62, 80, 0.2);
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .stat {
            text-align: left;
        }

        .stat h3 {
            font-size: 36px;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 5px;
        }

        .stat p {
            font-size: 13px;
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-image {
            position: relative;
            animation: fadeInRight 1s ease-out;
            height: 600px;
        }

        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .hero-visual {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.15) 100%);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.12);
        }

        .hero-visual::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 255, 255, 0.03) 10px,
                rgba(255, 255, 255, 0.03) 20px
            );
            animation: moveStripes 20s linear infinite;
        }

        @keyframes moveStripes {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .hero-visual i {
            font-size: 200px;
            color: rgba(231, 76, 60, 0.3);
            position: relative;
            z-index: 1;
        }

        .floating-card {
            position: absolute;
            background: white;
            padding: 20px 24px;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            animation: float 4s ease-in-out infinite;
            z-index: 2;
        }

        .floating-card.card-1 {
            top: 8%;
            left: -8%;
            animation-delay: 0s;
        }

        .floating-card.card-2 {
            bottom: 12%;
            right: -8%;
            animation-delay: 2s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-25px) rotate(2deg); }
        }

        .card-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 26px;
        }

        .card-text h4 {
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 2px;
        }

        .card-text p {
            font-size: 13px;
            color: var(--text-light);
            font-weight: 600;
        }

        /* Features Section */
        .features {
            padding: 120px 5%;
            background: white;
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 80px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .section-label {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(231, 76, 60, 0.1);
            color: var(--accent);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-radius: 50px;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 52px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 20px;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .section-header p {
            font-size: 18px;
            color: var(--text-light);
            line-height: 1.8;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .feature-card {
            background: white;
            padding: 45px 35px;
            border-radius: 20px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent) 0%, var(--accent-dark) 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: rgba(231, 76, 60, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }

        .feature-icon {
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.15) 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            color: var(--accent);
            margin-bottom: 28px;
            transition: all 0.4s ease;
        }

        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            transform: scale(1.1);
        }

        .feature-card h3 {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 14px;
        }

        .feature-card p {
            font-size: 15px;
            color: var(--text-light);
            line-height: 1.8;
        }

        /* Products Section */
        .products {
            padding: 120px 5%;
            background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 35px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.06);
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            width: 100%;
            height: 320px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
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
            transition: transform 0.6s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.1);
        }

        .product-image i {
            font-size: 80px;
            color: rgba(0, 0, 0, 0.1);
        }

        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--accent);
            color: white;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-info {
            padding: 25px;
        }

        .product-category {
            font-size: 12px;
            color: var(--accent);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .product-price {
            font-size: 24px;
            font-weight: 800;
            color: var(--accent);
        }

        .product-btn {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .product-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.4);
        }

        /* Categories Section */
        .categories {
            padding: 120px 5%;
            background: white;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .category-card {
            position: relative;
            height: 400px;
            border-radius: 24px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.25);
        }

        .category-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .category-card:hover .category-image {
            transform: scale(1.15);
        }

        .category-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            font-size: 100px;
        }

        .category-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.85) 0%, transparent 100%);
            padding: 35px;
            transition: all 0.4s ease;
        }

        .category-card:hover .category-overlay {
            background: linear-gradient(to top, rgba(231, 76, 60, 0.95) 0%, rgba(192, 57, 43, 0.8) 100%);
        }

        .category-overlay h3 {
            color: white;
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .category-overlay p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* CTA Section */
        .cta {
            padding: 120px 5%;
            background: linear-gradient(135deg, var(--primary) 0%, #1a252f 100%);
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
            background: radial-gradient(circle, rgba(231, 76, 60, 0.15) 0%, transparent 70%);
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
            font-family: 'Playfair Display', serif;
            font-size: 56px;
            font-weight: 900;
            color: white;
            margin-bottom: 24px;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .cta p {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 50px;
            line-height: 1.7;
        }

        .btn-white {
            background: white;
            color: var(--accent);
            padding: 18px 45px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-white:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 60px;
                text-align: center;
            }

            .hero-content h1 {
                font-size: 52px;
            }

            .hero-content p {
                margin: 0 auto 40px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .floating-card {
                display: none;
            }

            .section-header h2 {
                font-size: 40px;
            }

            .hero-image {
                height: 450px;
            }
        }

        @media (max-width: 640px) {
            .hero {
                padding: 120px 20px 60px;
            }

            .hero-content h1 {
                font-size: 38px;
            }

            .hero-content p {
                font-size: 16px;
            }

            .features, .products, .categories, .cta {
                padding: 80px 20px;
            }

            .section-header h2 {
                font-size: 32px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .hero-stats {
                flex-direction: column;
                gap: 25px;
                align-items: center;
            }

            .stat {
                text-align: center;
            }

            .cta h2 {
                font-size: 36px;
            }

            .categories-grid,
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-star"></i>
                    Trusted by <?php echo $stats['total_customers']; ?>+ Fashion Enthusiasts
                </div>
                <h1>
                    Redefine Your<br>
                    <span class="highlight">Fashion</span> Journey
                </h1>
                <p>
                    Discover curated collections that blend contemporary style with timeless elegance. Every piece tells a story, yours to write.
                </p>
                <div class="hero-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/fashionhub/Collections.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag"></i>
                            <span>Explore Collection</span>
                        </a>
                    <?php else: ?>
                        <a href="#" onclick="document.getElementById('signupBtn').click(); return false;" class="btn btn-primary">
                            <i class="fas fa-arrow-right"></i>
                            <span>Get Started</span>
                        </a>
                        <a href="#" onclick="document.getElementById('loginBtn').click(); return false;" class="btn btn-secondary">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Sign In</span>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hero-stats">
                    <div class="stat">
                        <h3><?php echo $stats['total_products']; ?>+</h3>
                        <p>Premium Products</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $stats['total_categories']; ?>+</h3>
                        <p>Categories</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $stats['total_feedbacks']; ?>+</h3>
                        <p>Happy Reviews</p>
                    </div>
                </div>
            </div>

            <div class="hero-image">
                <div class="hero-visual">
                    <?php if (!empty($hero_image['product_photo'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo $hero_image['product_photo']; ?>" 
                             alt="Featured Product"
                             style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; border-radius: 30px;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <i class="fas fa-gem" style="display: none;"></i>
                    <?php else: ?>
                        <i class="fas fa-gem"></i>
                    <?php endif; ?>
                </div>
                
                <div class="floating-card card-1">
                    <div class="card-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <div class="card-text">
                        <h4>Fast</h4>
                        <p>Delivery</p>
                    </div>
                </div>

                <div class="floating-card card-2">
                    <div class="card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="card-text">
                        <h4>100%</h4>
                        <p>Secure</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="section-header">
            <span class="section-label">Why Choose Us</span>
            <h2>Experience Excellence in Every Detail</h2>
            <p>We're committed to providing you with an unparalleled shopping experience, from browsing to delivery</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h3>Lightning Fast Delivery</h3>
                <p>Experience swift and reliable shipping nationwide. Your style shouldn't have to wait.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure Payments</h3>
                <p>Shop with confidence using our encrypted payment gateway. Your security is our priority.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-award"></i>
                </div>
                <h3>Premium Quality</h3>
                <p>Every item is handpicked and quality-checked to ensure you receive nothing but the best.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3>24/7 Support</h3>
                <p>Our dedicated team is always here to assist you. Get help anytime, anywhere.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-undo-alt"></i>
                </div>
                <h3>Easy Returns</h3>
                <p>Changed your mind? No problem. Hassle-free returns within 30 days of purchase.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <h3>Best Value</h3>
                <p>Premium fashion at competitive prices. Enjoy exclusive deals and seasonal offers.</p>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <?php if ($featured_products->num_rows > 0): ?>
    <section class="products">
        <div class="section-header">
            <span class="section-label">New Arrivals</span>
            <h2>Trending This Season</h2>
            <p>Discover the latest additions to our collection, carefully curated for the modern trendsetter</p>
        </div>

        <div class="products-grid">
            <?php while ($product = $featured_products->fetch_assoc()): ?>
                <?php 
                    // Calculate final price after discount
                    $final_price = $product['price'] - ($product['price'] * $product['discount'] / 100);
                ?>
                <div class="product-card" onclick="window.location.href='/fashionhub/Collections.php'">
                    <div class="product-image">
                        <?php if (!empty($product['product_photo'])): ?>
                            <img src="data:image/jpeg;base64,<?php echo $product['product_photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <i class="fas fa-tshirt" style="display: none;"></i>
                        <?php else: ?>
                            <i class="fas fa-tshirt"></i>
                        <?php endif; ?>
                        <?php if ($product['stock_quantity'] > 0 && $product['stock_quantity'] <= 10): ?>
                            <div class="product-badge">Low Stock</div>
                        <?php elseif ($product['stock_quantity'] == 0): ?>
                            <div class="product-badge" style="background: #95a5a6;">Out of Stock</div>
                        <?php endif; ?>
                        <?php if ($product['discount'] > 0): ?>
                            <div class="product-badge" style="left: 15px; right: auto; background: #e74c3c;">
                                <?php echo $product['discount']; ?>% OFF
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                        <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <div class="product-footer">
                            <div>
                                <span class="product-price">Rs.<?php echo number_format($final_price, 2); ?></span>
                                <?php if ($product['discount'] > 0): ?>
                                    <div style="font-size: 14px; color: #999; text-decoration: line-through; margin-top: 4px;">
                                        Rs.<?php echo number_format($product['price'], 2); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <button class="product-btn" onclick="event.stopPropagation();">
                                    <i class="fas fa-shopping-cart"></i> View
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <div style="text-align: center; margin-top: 60px;">
            <a href="/fashionhub/Collections.php" class="btn btn-primary" style="display: inline-flex;">
                <span>View All Products</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Categories Section -->
    <section class="categories">
        <div class="section-header">
            <span class="section-label">Browse Collections</span>
            <h2>Shop by Category</h2>
            <p>Explore our diverse range of fashion categories, each thoughtfully curated for your unique style</p>
        </div>

        <div class="categories-grid">
            <?php if ($categories_result->num_rows > 0): ?>
                <?php while ($category = $categories_result->fetch_assoc()): ?>
                    <div class="category-card" onclick="window.location.href='/fashionhub/Collections.php?category=<?php echo $category['id']; ?>'">
                        <?php if (!empty($category['category_photo'])): ?>
                            <img src="data:image/jpeg;base64,<?php echo $category['category_photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($category['category_name']); ?>" 
                                 class="category-image"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="category-placeholder" style="display: none;">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        <?php else: ?>
                            <div class="category-placeholder">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        <?php endif; ?>
                        <div class="category-overlay">
                            <h3><?php echo htmlspecialchars($category['category_name']); ?></h3>
                            <p>
                                <i class="fas fa-box"></i>
                                <?php echo $category['product_count']; ?> Products Available
                            </p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="category-card">
                    <div class="category-placeholder">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="category-overlay">
                        <h3>Coming Soon</h3>
                        <p><i class="fas fa-clock"></i> New categories launching</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-container">
            <h2>Begin Your Style Revolution Today</h2>
            <p>Join our community of fashion-forward individuals who trust FashionHub for quality, style, and exceptional service. Your perfect wardrobe awaits.</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/fashionhub/Collections.php" class="btn-white">
                    <span>Start Shopping Now</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php else: ?>
                <a href="#" onclick="document.getElementById('signupBtn').click(); return false;" class="btn-white">
                    <span>Join FashionHub</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'Components/Footer.php'; ?>
</body>
</html>