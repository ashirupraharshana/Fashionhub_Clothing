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

$fillname = $user['fullname'] ?? 'Guest';

// Fetch all categories from database with photos
$categories_query = "SELECT id, category_name, category_photo FROM categories ORDER BY category_name ASC";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($cat = $categories_result->fetch_assoc()) {
    $categories[] = $cat;
}

// Fetch trending/latest products with their minimum price
$trending_query = "SELECT p.id, p.product_name, p.category_id, c.category_name,
                   (SELECT MIN(ps.price - (ps.price * ps.discount / 100)) 
                    FROM product_sizes ps 
                    WHERE ps.product_id = p.id) as min_price,
                   (SELECT ps.discount 
                    FROM product_sizes ps 
                    WHERE ps.product_id = p.id 
                    ORDER BY ps.discount DESC 
                    LIMIT 1) as max_discount
                   FROM products p
                   LEFT JOIN categories c ON p.category_id = c.id
                   ORDER BY p.id DESC
                   LIMIT 8";
$trending_result = $conn->query($trending_query);
$trending_products = [];
while ($product = $trending_result->fetch_assoc()) {
    // Get first photo for this product
    $photo_query = "SELECT photo FROM photos WHERE product_id = ? LIMIT 1";
    $photo_stmt = $conn->prepare($photo_query);
    $photo_stmt->bind_param("i", $product['id']);
    $photo_stmt->execute();
    $photo_result = $photo_stmt->get_result();
    $photo = $photo_result->fetch_assoc();
    $product['photo'] = $photo['photo'] ?? null;
    $photo_stmt->close();
    
    $trending_products[] = $product;
}

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM products) as total_products,
    (SELECT COUNT(*) FROM categories) as total_categories,
    (SELECT COUNT(DISTINCT product_id) FROM product_sizes WHERE quantity > 0) as available_products";
$stats = $conn->query($stats_query)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
            padding-top: 70px;
        }

        /* Hero Banner */
        .hero-banner {
            position: relative;
            height: 600px;
            background: linear-gradient(rgba(231, 76, 60, 0.35), rgba(231, 76, 60, 0.35)),
                        url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1600') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            overflow: hidden;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 0 20px;
            animation: fadeInUp 1s ease;
        }

        .hero-content h1 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 2px 4px 8px rgba(0, 0, 0, 0.3);
            letter-spacing: -1px;
        }

        .hero-content p {
            font-size: 20px;
            margin-bottom: 35px;
            font-weight: 400;
            text-shadow: 1px 2px 4px rgba(0, 0, 0, 0.3);
            opacity: 0.95;
        }

        .cta-button {
            display: inline-block;
            padding: 18px 50px;
            background: white;
            color: #e74c3c;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.4s ease;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .cta-button:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(231, 76, 60, 0.4);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
        }

        /* Section Styling */
        .section {
            padding: 80px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header h2 {
            font-size: 42px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .section-header h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #e74c3c, #c0392b);
            border-radius: 2px;
        }

        .section-header p {
            font-size: 16px;
            color: #7f8c8d;
            max-width: 600px;
            margin: 20px auto 0;
        }

        /* Stats Bar */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            color: #e74c3c;
            display: block;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Categories Section */
        .categories-scroll {
            display: flex;
            gap: 25px;
            overflow-x: auto;
            overflow-y: hidden;
            padding: 20px 0 30px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .categories-scroll::-webkit-scrollbar {
            height: 8px;
        }

        .categories-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .categories-scroll::-webkit-scrollbar-thumb {
            background: #e74c3c;
            border-radius: 10px;
        }
        .category-card {
    min-width: 280px;
    width: 280px;
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.4s ease;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    flex-direction: column;
}

.category-card:hover {
    transform: translateY(-12px);
    box-shadow: 0 20px 50px rgba(231, 76, 60, 0.2);
}

.category-photo-wrapper {
    width: 100%;
    height: 200px;
    overflow: hidden;
    position: relative;
}

.category-card img {
    width: 100%;
    height: 100%;    
    object-fit: cover;
    transition: transform 0.4s ease;
}

.category-card:hover img {
    transform: scale(1.1);
}

.category-name {
    padding: 20px;
    text-align: center;
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    background: white;
}
        /* Offers Section */
        .offers-section {
            background: linear-gradient(135deg, #e74c3c 0%, #ff6b6b 100%);
            border-radius: 25px;
            padding: 80px 60px;
            text-align: center;
            color: white;
            box-shadow: 0 15px 50px rgba(231, 76, 60, 0.3);
            position: relative;
            overflow: hidden;
        }

        .offers-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .offers-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite reverse;
        }

        .offers-content {
            position: relative;
            z-index: 2;
        }

        .offers-content h2 {
            font-size: 52px;
            font-weight: 900;
            margin-bottom: 25px;
            text-shadow: 2px 4px 10px rgba(0, 0, 0, 0.2);
        }

        .offers-content p {
            font-size: 22px;
            margin-bottom: 35px;
            opacity: 0.95;
        }

        .offer-cta {
            display: inline-block;
            padding: 18px 50px;
            background: white;
            color: #e74c3c;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.4s ease;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .offer-cta:hover {
            background: #2c3e50;
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(231, 76, 60, 0.15);
        }

        .product-image {
            width: 100%;
            height: 300px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: #e74c3c;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #e74c3c;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            z-index: 2;
        }

        .product-info {
            padding: 25px;
        }

        .product-category {
            font-size: 12px;
            color: #e74c3c;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .product-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-price {
            font-size: 24px;
            font-weight: 800;
            color: #e74c3c;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 18px;
            font-weight: 500;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(5deg);
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .hero-content h1 {
                font-size: 42px;
            }

            .section-header h2 {
                font-size: 36px;
            }

            .offers-section {
                padding: 60px 40px;
            }

            .offers-content h2 {
                font-size: 42px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }

            .hero-banner {
                height: 450px;
            }

            .hero-content h1 {
                font-size: 32px;
            }

            .hero-content p {
                font-size: 16px;
            }

            .section {
                padding: 50px 0;
            }

            .section-header h2 {
                font-size: 28px;
            }

            .stats-bar {
                gap: 40px;
            }

            .stat-number {
                font-size: 36px;
            }

            .category-card {
                min-width: 240px;
                height: 180px;
            }

            .category-icon {
                font-size: 48px;
            }

            .category-name {
                font-size: 18px;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }

            .offers-section {
                padding: 40px 25px;
                border-radius: 20px;
            }

            .offers-content h2 {
                font-size: 32px;
            }

            .offers-content p {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .hero-content h1 {
                font-size: 28px;
            }

            .cta-button,
            .offer-cta {
                padding: 14px 35px;
                font-size: 16px;
            }

            .category-card {
                min-width: 200px;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/CustomerNavBar.php'; ?>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="hero-content">
            <h1>Welcome back, <?php echo htmlspecialchars($fillname); ?>! 👋</h1>
            <p>Discover the latest trends and exclusive collections curated just for you</p>
            <a href="Products.php" class="cta-button">Shop Now</a>
        </div>
    </section>

    <!-- Stats Bar -->
    <section class="section" style="padding: 60px 0 40px;">
        <div class="container">
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['total_products']); ?></span>
                    <span class="stat-label">Products</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['total_categories']); ?></span>
                    <span class="stat-label">Categories</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['available_products']); ?></span>
                    <span class="stat-label">In Stock</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="section" style="padding-top: 40px;">
        <div class="container">
            <div class="section-header">
                <h2>Shop by Category</h2>
                <p>Explore our diverse collection of fashion essentials</p>
            </div>
            
                <?php if (count($categories) > 0): ?>
    <div class="categories-scroll">
        <?php foreach ($categories as $category): ?>
            <a href="Products.php?category=<?php echo $category['id']; ?>" class="category-card">
                <div class="category-photo-wrapper">
                    <?php if (!empty($category['category_photo'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo $category['category_photo']; ?>" 
                             alt="<?php echo htmlspecialchars($category['category_name']); ?>">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #e74c3c, #c0392b); display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="category-name"><?php echo htmlspecialchars($category['category_name']); ?></div>
            </a>
        <?php endforeach; ?>
    </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No categories available at the moment</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Offers Section -->
    <section class="section" style="background: #f8f9fa;">
        <div class="container">
            <div class="offers-section">
                <div class="offers-content">
                    <h2>🔥 New Arrivals Daily!</h2>
                    <p>Check out our latest collection and trending items</p>
                    <a href="Products.php" class="offer-cta">Explore Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest Products -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2>Latest Arrivals</h2>
                <p>Fresh picks from our newest collection</p>
            </div>

            <?php if (count($trending_products) > 0): ?>
                <div class="products-grid">
                    <?php foreach ($trending_products as $product): ?>
                        <a href="OrderNow.php?product_id=<?php echo $product['id']; ?>" class="product-card">
                            <div class="product-image">
                                <?php if ($product['photo']): ?>
                                    <img src="data:image/jpeg;base64,<?php echo $product['photo']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-tshirt"></i>
                                <?php endif; ?>
                                
                                <?php if ($product['max_discount'] > 0): ?>
                                    <span class="product-badge" style="background: #27ae60;">
                                        <?php echo number_format($product['max_discount']); ?>% OFF
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <?php if ($product['category_name']): ?>
                                    <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                <div class="product-price">
                                    Rs. <?php echo number_format($product['min_price'], 2); ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No products available at the moment. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'Components/Footer.php'; ?>
</body>
</html>