<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "fashionhubdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get statistics from database
$stats = [
    'customers' => 0,
    'products' => 0,
    'orders' => 0
];

// Get total customers count (excluding admins)
$customers_query = "SELECT COUNT(*) as total FROM users WHERE userrole = 0";
$customers_result = $conn->query($customers_query);
if ($customers_result) {
    $customers_data = $customers_result->fetch_assoc();
    $stats['customers'] = $customers_data['total'];
}

// Get total products count
$products_query = "SELECT COUNT(*) as total FROM products WHERE stock_quantity > 0";
$products_result = $conn->query($products_query);
if ($products_result) {
    $products_data = $products_result->fetch_assoc();
    $stats['products'] = $products_data['total'];
}

// Get total orders count
$orders_query = "SELECT COUNT(*) as total FROM orders";
$orders_result = $conn->query($orders_query);
if ($orders_result) {
    $orders_data = $orders_result->fetch_assoc();
    $stats['orders'] = $orders_data['total'];
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_data = null;

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT fullname, email, userrole FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
    }
    $stmt->close();
}

// Get cart data for logged-in users
$cart_count = 0;
$cart_total = 0;
$cart_items_array = [];

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    
    // Get cart count
    $cart_count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_count_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count_result = $stmt->get_result();
    $cart_count_row = $cart_count_result->fetch_assoc();
    $cart_count = $cart_count_row['total'] ?? 0;
    $stmt->close();
    
    // Get cart total
    $cart_total_sql = "SELECT SUM(price * quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_total_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_total_result = $stmt->get_result();
    $cart_total_row = $cart_total_result->fetch_assoc();
    $cart_total = $cart_total_row['total'] ?? 0;
    $stmt->close();
    
    // Get cart items
    $cart_items_sql = "SELECT c.id, c.product_id, c.quantity, c.price, 
                       (c.price * c.quantity) as item_total,
                       p.product_name, p.product_photo 
                       FROM cart c 
                       JOIN products p ON c.product_id = p.id 
                       WHERE c.user_id = ? 
                       ORDER BY c.id DESC";
    $stmt = $conn->prepare($cart_items_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $cart_items_array[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | FashionHub</title>
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
            min-height: 100vh;
            color: #2c3e50;
            line-height: 1.6;
            <?php if ($is_logged_in): ?>
            padding-top: 70px;
            <?php endif; ?>
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 120px 20px 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-30px); }
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-section h1 {
            font-size: 56px;
            font-weight: 900;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .hero-section p {
            font-size: 20px;
            opacity: 0.95;
            line-height: 1.8;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: -60px auto 0;
            padding: 0 20px 80px;
            position: relative;
            z-index: 2;
        }

        /* Introduction Card */
        .intro-card {
            background: white;
            border-radius: 24px;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }

        .intro-card h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 25px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .intro-card h2 i {
            color: #e74c3c;
        }

        .intro-card p {
            font-size: 17px;
            color: #555;
            line-height: 1.9;
            margin-bottom: 20px;
        }

        .intro-card p:last-child {
            margin-bottom: 0;
        }

        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .stat-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            border: 3px solid transparent;
        }

        .stat-box:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            border-color: #e74c3c;
        }

        .stat-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        .stat-box h3 {
            font-size: 42px;
            font-weight: 900;
            color: #e74c3c;
            margin-bottom: 10px;
        }

        .stat-box p {
            font-size: 16px;
            color: #7f8c8d;
            font-weight: 600;
        }

        /* Objectives Section */
        .objectives-section {
            background: white;
            border-radius: 24px;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }

        .objectives-section h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 40px;
            font-weight: 800;
            text-align: center;
        }

        .objectives-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .objective-card {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            border-radius: 18px;
            padding: 35px;
            border-left: 5px solid #e74c3c;
            transition: all 0.3s ease;
        }

        .objective-card:hover {
            transform: translateX(10px);
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.2);
        }

        .objective-card h3 {
            font-size: 20px;
            color: #e74c3c;
            margin-bottom: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .objective-card h3 i {
            font-size: 24px;
        }

        .objective-card p {
            font-size: 15px;
            color: #555;
            line-height: 1.7;
        }

        /* Problems & Solutions */
        .problems-section {
            background: white;
            border-radius: 24px;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }

        .problems-section h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 800;
            text-align: center;
        }

        .problem-item {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            border-left: 5px solid #e74c3c;
            transition: all 0.3s ease;
        }

        .problem-item:hover {
            transform: translateX(8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .problem-item h4 {
            font-size: 18px;
            color: #e74c3c;
            margin-bottom: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .problem-item p {
            font-size: 15px;
            color: #555;
        }

        /* Technologies Section */
        .tech-section {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border-radius: 24px;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(231, 76, 60, 0.3);
            color: white;
            margin-bottom: 50px;
        }

        .tech-section h2 {
            font-size: 36px;
            margin-bottom: 40px;
            font-weight: 800;
            text-align: center;
        }

        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
        }

        .tech-card {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .tech-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-8px);
        }

        .tech-card i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .tech-card h4 {
            font-size: 18px;
            font-weight: 700;
        }

        /* Features Section */
        .features-section {
            background: white;
            border-radius: 24px;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }

        .features-section h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 40px;
            font-weight: 800;
            text-align: center;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            border-radius: 18px;
            padding: 35px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .feature-card:hover {
            border-color: #e74c3c;
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(231, 76, 60, 0.2);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .feature-card p {
            font-size: 15px;
            color: #555;
            line-height: 1.7;
        }

        /* Mission Section */
        .mission-section {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            border-radius: 24px;
            padding: 60px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            color: white;
            text-align: center;
        }

        .mission-section h2 {
            font-size: 36px;
            margin-bottom: 25px;
            font-weight: 800;
        }

        .mission-section p {
            font-size: 18px;
            line-height: 1.9;
            max-width: 900px;
            margin: 0 auto;
            opacity: 0.95;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 20px 60px;
            }

            .hero-section h1 {
                font-size: 38px;
            }

            .hero-section p {
                font-size: 16px;
            }

            .intro-card,
            .objectives-section,
            .problems-section,
            .tech-section,
            .features-section,
            .mission-section {
                padding: 40px 30px;
            }

            .intro-card h2,
            .objectives-section h2,
            .problems-section h2,
            .tech-section h2,
            .features-section h2,
            .mission-section h2 {
                font-size: 28px;
            }

            .stats-section,
            .objectives-grid,
            .tech-grid,
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scroll Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-to-top.show {
            display: flex;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.5);
        }
    </style>
</head>
<body>
    <?php 
    if ($is_logged_in) {
        // Include the navbar component that was in the document
        include 'Components/CustomerNavBar.php';
    }
    ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>About FashionHub</h1>
            <p>Transforming the fashion retail experience through innovative digital solutions and exceptional customer service</p>
        </div>
    </section>

    <!-- Main Container -->
    <div class="container">
        <!-- Introduction -->
        <div class="intro-card fade-in">
            <h2><i class="fas fa-store"></i> Our Story</h2>
            <p>
                A website is a collection of interconnected web pages under a single domain, accessible via the internet. It typically contains text, images, videos, and other multimedia content that serve specific purposes for businesses and individuals.
            </p>
            <p>
                In today's digital world, an online presence is crucial for any business to thrive. <strong>FashionHub Clothing Store</strong> aims to expand its reach and improve customer engagement through a comprehensive website platform. This digital solution allows customers to browse products, view prices, check availability, and receive timely updates on new collections and promotions.
            </p>
            <p>
                The purpose of this project is to design and develop a responsive, user-friendly website for our clothing store to attract more customers, enhance their shopping experience, and ultimately boost sales while establishing a strong digital footprint in the competitive fashion industry.
            </p>
        </div>

        <!-- Stats Section -->
        <div class="stats-section fade-in">
            <div class="stat-box">
                <div class="stat-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h3>24/7</h3>
                <p>Online Availability</p>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo number_format($stats['customers']); ?>+</h3>
                <p>Happy Customers</p>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <i class="fas fa-tshirt"></i>
                </div>
                <h3><?php echo number_format($stats['products']); ?>+</h3>
                <p>Fashion Products</p>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3><?php echo number_format($stats['orders']); ?>+</h3>
                <p>Orders Completed</p>
            </div>
        </div>

        <!-- Objectives Section -->
        <div class="objectives-section fade-in">
            <h2>Our Objectives</h2>
            <p style="text-align: center; font-size: 17px; color: #555; max-width: 800px; margin: 0 auto 40px;">
                The primary goal is to create a platform that increases sales and improves customer interaction through strategic digital initiatives.
            </p>

            <div class="objectives-grid">
                <div class="objective-card">
                    <h3><i class="fas fa-bullseye"></i> Online Presence</h3>
                    <p>Establish a strong digital footprint by showcasing our clothing collections, categories, and latest arrivals to reach a wider audience beyond physical boundaries.</p>
                </div>

                <div class="objective-card">
                    <h3><i class="fas fa-hand-holding-heart"></i> Customer Convenience</h3>
                    <p>Allow customers to view products, sizes, and prices online at their convenience, enabling informed purchase decisions from anywhere, anytime.</p>
                </div>

                <div class="objective-card">
                    <h3><i class="fas fa-chart-line"></i> Increase Sales</h3>
                    <p>Provide easy access to product information and seamless online purchase options to drive revenue growth and expand market reach.</p>
                </div>

                <div class="objective-card">
                    <h3><i class="fas fa-comments"></i> Customer Engagement</h3>
                    <p>Include interactive features like feedback forms, newsletter subscriptions, and social media integration to build lasting relationships with customers.</p>
                </div>
            </div>
        </div>

        <!-- Problems We Solve -->
        <div class="problems-section fade-in">
            <h2>Challenges We Address</h2>
            
            <div class="problem-item">
                <h4><i class="fas fa-map-marked-alt"></i> Limited Physical Reach</h4>
                <p>Traditional brick-and-mortar stores are restricted to serving only local customers who can physically visit. Our online platform breaks geographical barriers, allowing customers from anywhere to access our collections.</p>
            </div>

            <div class="problem-item">
                <h4><i class="fas fa-clock"></i> Time Constraints</h4>
                <p>Physical stores operate on fixed schedules, limiting accessibility. Our website provides 24/7 access, enabling customers to browse and shop at their convenience, regardless of time zones or work schedules.</p>
            </div>

            <div class="problem-item">
                <h4><i class="fas fa-trophy"></i> Competitive Advantage</h4>
                <p>Many clothing stores already have established online presence. Our modern, user-friendly website ensures we remain competitive and relevant in the digital marketplace.</p>
            </div>

            <div class="problem-item">
                <h4><i class="fas fa-boxes"></i> Product Showcase Limitations</h4>
                <p>Physical space constraints make it difficult to display the entire product catalog efficiently. Our digital platform allows comprehensive product showcasing with detailed information, multiple images, and better organization.</p>
            </div>
        </div>

        <!-- Features Section -->
        <div class="features-section fade-in">
            <h2>Platform Features</h2>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Responsive Design</h3>
                    <p>Optimized for seamless experience across all devices - mobile phones, tablets, and desktop computers.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h3>Product Catalog</h3>
                    <p>Comprehensive online catalog featuring high-quality images, detailed descriptions, prices, and size availability.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comment-dots"></i>
                    </div>
                    <h3>Customer Feedback</h3>
                    <p>Dedicated section for customers to share their experiences and help us continuously improve our services.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Newsletter Updates</h3>
                    <p>Stay informed about new arrivals, exclusive promotions, and special offers through our newsletter subscription.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h3>Social Media Integration</h3>
                    <p>Connect with us across multiple platforms for broader engagement and real-time updates on fashion trends.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3>Easy Shopping</h3>
                    <p>User-friendly interface with intuitive navigation, making the shopping experience smooth and enjoyable.</p>
                </div>
            </div>
        </div>

        <!-- Technology Stack -->
        <div class="tech-section fade-in">
            <h2>Technologies Powering FashionHub</h2>
            
            <div class="tech-grid">
                <div class="tech-card">
                    <i class="fab fa-html5"></i>
                    <h4>HTML5</h4>
                </div>

                <div class="tech-card">
                    <i class="fab fa-css3-alt"></i>
                    <h4>CSS3</h4>
                </div>

                <div class="tech-card">
                    <i class="fab fa-js"></i>
                    <h4>JavaScript</h4>
                </div>

                <div class="tech-card">
                    <i class="fab fa-php"></i>
                    <h4>PHP</h4>
                </div>

                <div class="tech-card">
                    <i class="fas fa-database"></i>
                    <h4>MySQL</h4>
                </div>
            </div>

            <p style="text-align: center; margin-top: 30px; font-size: 16px; opacity: 0.95;">
                Our platform is built using modern web technologies including HTML for structure, CSS for styling, JavaScript for interactivity, and PHP with MySQL for robust backend operations including online ordering and inventory management.
            </p>
        </div>

        <!-- Mission Section -->
        <div class="mission-section fade-in">
            <h2>Our Mission</h2>
            <p>
                At FashionHub, we are committed to revolutionizing the fashion retail experience by combining style, quality, and convenience. Our mission is to provide customers with a seamless digital shopping platform that not only showcases the latest fashion trends but also delivers exceptional service and value. We strive to build lasting relationships with our customers through innovation, reliability, and a genuine passion for fashion.
            </p>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Scroll Animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(element => {
            observer.observe(element);
        });

        // Back to Top Button
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>