<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "fashionhubdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$size_filter = isset($_GET['size']) ? $_GET['size'] : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build query conditions
$where_conditions = ["p.stock_quantity > 0"];

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = $category_filter";
}

if ($gender_filter !== '') {
    $gender_value = intval($gender_filter);
    $where_conditions[] = "p.gender = $gender_value";
}

if ($size_filter !== '') {
    $where_conditions[] = "p.size = '$size_filter'";
}

if (!empty($search)) {
    $where_conditions[] = "(p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Sort options
$order_by = "ORDER BY p.id DESC";
switch ($sort) {
    case 'price_low':
        $order_by = "ORDER BY (p.price - (p.price * p.discount / 100)) ASC";
        break;
    case 'price_high':
        $order_by = "ORDER BY (p.price - (p.price * p.discount / 100)) DESC";
        break;
    case 'name':
        $order_by = "ORDER BY p.product_name ASC";
        break;
    case 'discount':
        $order_by = "ORDER BY p.discount DESC";
        break;
}

// Get products
$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          $where_clause 
          $order_by";
$products_result = $conn->query($query);

// Get categories for filter
$categories_query = "SELECT * FROM categories ORDER BY category_name ASC";
$categories_result = $conn->query($categories_query);

// Get product count
$count_query = "SELECT COUNT(*) as total FROM products p $where_clause";
$count_result = $conn->query($count_query);
$total_products = $count_result->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Collections | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #e74c3c;
            --primary-dark: #c0392b;
            --secondary: #2c2c2c;
            --accent: #e74c3c;
            --text-dark: #1a1a1a;
            --text-light: #666;
            --background: #fafafa;
            --white: #ffffff;
            --border: #e5e5e5;
            --shadow: rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Luxury Hero Section */
        .hero-section {
            position: relative;
            height: 75vh;
            min-height: 600px;
            background: linear-gradient(135deg, rgba(26, 26, 26, 0.85) 0%, rgba(44, 44, 44, 0.85) 100%),
                        url('https://img.freepik.com/premium-photo/luxury-wardrobe-designer-suits-highend-clothing-rack-varied-color-palette-soft-lighting-wooden-close_1308352-1991.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.08;
            background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.4"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            max-width: 900px;
            padding: 0 30px;
            animation: fadeInUp 1s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-subtitle {
            font-size: 14px;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 600;
        }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 72px;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 25px;
            line-height: 1.1;
            letter-spacing: -2px;
        }

        .hero-description {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 40px;
            line-height: 1.8;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 60px;
            margin-top: 50px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 42px;
            font-weight: 900;
            color: var(--primary);
            display: block;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Main Container */
        .container {
            max-width: 1600px;
            margin: -80px auto 0;
            padding: 0 40px 100px;
            position: relative;
            z-index: 3;
        }

        /* Premium Filter Section */
        .filter-section {
            background: var(--white);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 50px;
            box-shadow: 0 20px 60px var(--shadow);
            border: 1px solid var(--border);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 2px solid var(--border);
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .filter-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 20px;
        }

        .filter-text h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .product-count {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 500;
        }

        .view-toggle {
            display: flex;
            gap: 10px;
            background: var(--background);
            padding: 5px;
            border-radius: 12px;
        }

        .view-btn {
            width: 45px;
            height: 45px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--text-light);
        }

        .view-btn.active {
            background: var(--white);
            color: var(--primary);
            box-shadow: 0 2px 8px var(--shadow);
        }

        .filter-controls {
            display: grid;
            grid-template-columns: 2fr repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .filter-group {
            position: relative;
        }

        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            background: var(--white);
            color: var(--text-dark);
            font-family: inherit;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(212, 165, 116, 0.1);
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding-left: 18px;
            padding-right: 70px;
        }

.search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #a93226 100%);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }

        .search-btn:active {
            transform: scale(0.95);
        }

        .filter-actions {
            display: flex;
            gap: 15px;
        }

        .btn-clear {
            padding: 15px 30px;
            background: var(--background);
            color: var(--text-dark);
            border: 2px solid var(--border);
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .btn-clear:hover {
            background: var(--white);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Premium Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 40px;
        }

        .product-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            border: 1px solid var(--border);
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            opacity: 0;
            transition: opacity 0.5s;
            z-index: 1;
            pointer-events: none;
        }

        .product-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        .product-card:hover::before {
            opacity: 0.03;
        }

        .product-image-container {
            position: relative;
            width: 100%;
            height: 400px;
            overflow: hidden;
            background: linear-gradient(135deg, #f5f5f5 0%, #e9e9e9 100%);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-image {
            transform: scale(1.08);
        }

        .product-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            color: var(--border);
        }

        .discount-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, var(--accent) 0%, #c0392b 100%);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            z-index: 2;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
            letter-spacing: 0.5px;
        }

        .stock-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            z-index: 2;
            text-transform: uppercase;
            letter-spacing: 1px;
            backdrop-filter: blur(10px);
        }

        .stock-badge.low-stock {
            background: rgba(255, 193, 7, 0.95);
            color: #1a1a1a;
            box-shadow: 0 8px 20px rgba(255, 193, 7, 0.4);
        }

        .stock-badge.in-stock {
            background: rgba(40, 167, 69, 0.95);
            color: var(--white);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }

        .product-wishlist {
            display: none;
        }

        .product-info {
            padding: 30px;
            position: relative;
            z-index: 2;
        }

        .product-category {
            font-size: 11px;
            color: var(--primary);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .product-name {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 15px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-description {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 20px;
            line-height: 1.7;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-attributes {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .attribute-badge {
            padding: 8px 16px;
            background: var(--background);
            border-radius: 20px;
            font-size: 12px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            border: 1px solid var(--border);
        }

        .attribute-badge i {
            color: var(--primary);
            font-size: 13px;
        }

        .product-price-section {
            display: flex;
            align-items: baseline;
            gap: 15px;
            margin-bottom: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .product-price {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .product-original-price {
            font-size: 18px;
            color: var(--text-light);
            text-decoration: line-through;
        }

        .savings-badge {
            padding: 4px 12px;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: var(--white);
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .view-product-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .view-product-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(212, 165, 116, 0.4);
        }

        /* Premium Login Modal */
        .login-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.4s;
        }

        .login-modal.active {
            display: flex;
        }

        .login-modal-content {
            background: var(--white);
            border-radius: 30px;
            width: 90%;
            max-width: 550px;
            overflow: hidden;
            animation: modalSlideUp 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
        }

        @keyframes modalSlideUp {
            from {
                transform: translateY(60px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .login-modal-header {
            background: linear-gradient(135deg, var(--secondary) 0%, #1a1a1a 100%);
            padding: 50px 40px;
            text-align: center;
            position: relative;
        }

        .login-modal-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 40px;
            color: var(--white);
            box-shadow: 0 15px 40px rgba(212, 165, 116, 0.3);
        }

        .close-login-modal {
            position: absolute;
            top: 25px;
            right: 25px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--white);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s;
            backdrop-filter: blur(10px);
        }

        .close-login-modal:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .login-modal-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            color: var(--white);
            margin-bottom: 12px;
            font-weight: 700;
        }

        .login-modal-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
        }

        .login-modal-body {
            padding: 50px 40px;
            text-align: center;
        }

        .login-modal-body p {
            font-size: 16px;
            color: var(--text-light);
            line-height: 1.9;
            margin-bottom: 35px;
        }

        .login-modal-actions {
            display: flex;
            gap: 15px;
        }

        .btn-login-modal, .btn-signup-modal {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn-login-modal {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
        }

        .btn-login-modal:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(212, 165, 116, 0.4);
        }

        .btn-signup-modal {
            background: var(--background);
            color: var(--text-dark);
            border: 2px solid var(--border);
        }

        .btn-signup-modal:hover {
            background: var(--white);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            grid-column: 1 / -1;
        }

        .empty-state-icon {
            font-size: 100px;
            color: var(--border);
            margin-bottom: 30px;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: var(--text-dark);
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 16px;
            color: var(--text-light);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .filter-controls {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 968px) {
            .hero-title {
                font-size: 48px;
            }

            .hero-section {
                background-attachment: scroll;
            }

            .hero-stats {
                gap: 40px;
            }

            .container {
                padding: 0 20px 60px;
            }

            .filter-controls {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 25px;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 36px;
            }

            .hero-stats {
                flex-direction: column;
                gap: 30px;
            }

            .filter-section {
                padding: 25px;
            }

            .login-modal-content {
                margin: 20px;
            }

            .login-modal-body {
                padding: 35px 25px;
            }

            .login-modal-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Premium Hero Section -->
    <section class="hero-section">
        <div class="hero-background"></div>
        <div class="hero-content">
            <div class="hero-subtitle">Curated Excellence</div>
            <h1 class="hero-title">Premium Collections</h1>
            <p class="hero-description">Discover our handpicked selection of luxury fashion pieces. Each item is carefully chosen to bring sophistication and style to your wardrobe.</p>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo $total_products; ?>+</span>
                    <span class="stat-label">Products</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $categories_result->num_rows; ?>+</span>
                    <span class="stat-label">Categories</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <span class="stat-label">Authentic</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Container -->
    <div class="container">
        <!-- Premium Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <div class="filter-title">
                    <div class="filter-icon">
                        <i class="fas fa-sliders-h"></i>
                    </div>
                    <div class="filter-text">
                        <h2>Refine Your Search</h2>
                        <div class="product-count"><?php echo $total_products; ?> exclusive pieces available</div>
                    </div>
                </div>
                <div class="view-toggle">
                    <button class="view-btn active" title="Grid View">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" title="List View">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>

            <form method="GET" action="Collections.php" id="filterForm">
                <div class="filter-controls">
                    <div class="filter-group search-box">
                        <label>Search Products</label>
                        <input type="text" 
                               name="search" 
                               id="searchInput"
                               placeholder="Search by name or description..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               autocomplete="off">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>

                    <div class="filter-group">
                        <label>Category</label>
                        <select name="category" onchange="this.form.submit()">
                            <option value="0">All Categories</option>
                            <?php 
                            $categories_result->data_seek(0);
                            while ($cat = $categories_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Gender</label>
                        <select name="gender" onchange="this.form.submit()">
                            <option value="">All Genders</option>
                            <option value="0" <?php echo $gender_filter === '0' ? 'selected' : ''; ?>>Men's Collection</option>
                            <option value="1" <?php echo $gender_filter === '1' ? 'selected' : ''; ?>>Women's Collection</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Size</label>
                        <select name="size" onchange="this.form.submit()">
                            <option value="">All Sizes</option>
                            <option value="S" <?php echo $size_filter == 'S' ? 'selected' : ''; ?>>Small (S)</option>
                            <option value="M" <?php echo $size_filter == 'M' ? 'selected' : ''; ?>>Medium (M)</option>
                            <option value="L" <?php echo $size_filter == 'L' ? 'selected' : ''; ?>>Large (L)</option>
                            <option value="XL" <?php echo $size_filter == 'XL' ? 'selected' : ''; ?>>Extra Large (XL)</option>
                            <option value="XXL" <?php echo $size_filter == 'XXL' ? 'selected' : ''; ?>>2XL</option>
                            <option value="XXXL" <?php echo $size_filter == 'XXXL' ? 'selected' : ''; ?>>3XL</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Sort By</label>
                        <select name="sort" onchange="this.form.submit()">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest Arrivals</option>
                            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Alphabetical</option>
                            <option value="discount" <?php echo $sort == 'discount' ? 'selected' : ''; ?>>Best Deals</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <a href="Collections.php" class="btn-clear">
                        <i class="fas fa-redo-alt"></i>
                        Reset All Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Premium Products Grid -->
        <div class="products-grid">
            <?php if ($products_result->num_rows > 0): ?>
                <?php while ($product = $products_result->fetch_assoc()): ?>
                    <?php
                    $final_price = $product['price'] - ($product['price'] * $product['discount'] / 100);
                    $savings = $product['price'] - $final_price;
                    $stock_class = $product['stock_quantity'] <= 10 ? 'low-stock' : 'in-stock';
                    $stock_text = $product['stock_quantity'] <= 10 ? 'Only ' . $product['stock_quantity'] . ' Left' : 'In Stock';
                    ?>
                    <div class="product-card" onclick="showLoginModal()">
                        <div class="product-image-container">
                            <?php if ($product['discount'] > 0): ?>
                                <div class="discount-badge">-<?php echo $product['discount']; ?>% OFF</div>
                            <?php endif; ?>
                            <div class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_text; ?></div>
                            
                            <?php if (!empty($product['product_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $product['product_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     class="product-image"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="product-placeholder" style="display: none;">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                            <?php else: ?>
                                <div class="product-placeholder">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-description">
                                <?php echo htmlspecialchars($product['description'] ?: 'Exquisite quality and timeless design. Crafted for those who appreciate the finer things in life.'); ?>
                            </div>

                            <?php if ($product['gender'] !== null || $product['size'] !== null): ?>
                            <div class="product-attributes">
                                <?php if ($product['gender'] !== null): ?>
                                <span class="attribute-badge">
                                    <i class="fas fa-venus-mars"></i>
                                    <?php echo $product['gender'] == 0 ? "Men's" : "Women's"; ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($product['size'] !== null): ?>
                                <span class="attribute-badge">
                                    <i class="fas fa-ruler"></i>
                                    Size <?php echo htmlspecialchars($product['size']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="product-price-section">
                                <div class="product-price">Rs.<?php echo number_format($final_price, 2); ?></div>
                                <?php if ($product['discount'] > 0): ?>
                                    <div class="product-original-price">Rs.<?php echo number_format($product['price'], 2); ?></div>
                                    <span class="savings-badge">Save Rs.<?php echo number_format($savings, 2); ?></span>
                                <?php endif; ?>
                            </div>

                            <button class="view-product-btn">
                                <i class="fas fa-eye"></i>
                                View Details
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No Products Found</h3>
                    <p>We couldn't find any products matching your criteria. Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Premium Login Modal -->
    <div class="login-modal" id="loginModal">
        <div class="login-modal-content">
            <div class="login-modal-header">
                <button class="close-login-modal" onclick="closeLoginModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="login-modal-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h2>Members Only</h2>
                <p>Exclusive access for registered members</p>
            </div>
            <div class="login-modal-body">
                <p>To view complete product details, add items to your wishlist, and enjoy exclusive member benefits, please log in to your account or create a new one.</p>
                <div class="login-modal-actions">
                    <a href="Homepage.php" class="btn-login-modal">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </a>
                    <a href="Homepage.php" class="btn-signup-modal">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-search functionality with debounce (optional - can still use button)
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const filterForm = document.getElementById('filterForm');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                // Wait 800ms after user stops typing before submitting
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 800);
            });

            // Also submit on Enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    filterForm.submit();
                }
            });
        }

        function showLoginModal() {
            document.getElementById('loginModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('loginModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLoginModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLoginModal();
            }
        });

        // View toggle functionality
        const viewBtns = document.querySelectorAll('.view-btn');
        const productsGrid = document.querySelector('.products-grid');

        viewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                viewBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                if (this.querySelector('.fa-list')) {
                    productsGrid.style.gridTemplateColumns = '1fr';
                } else {
                    productsGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(340px, 1fr))';
                }
            });
        });

        // Smooth scroll animation
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.product-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            }, {
                threshold: 0.1
            });

            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>