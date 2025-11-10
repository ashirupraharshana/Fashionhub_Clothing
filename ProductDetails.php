<?php
include 'db_connect.php';
include 'Components/CustomerNavBar.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: /fashionhub/Collections.php");
    exit;
}

// Fetch product details
$product_query = "SELECT p.*, c.category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.id = ?";
$stmt = $conn->prepare($product_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: /fashionhub/Collections.php");
    exit;
}

$product = $result->fetch_assoc();
$final_price = $product['price'] - ($product['price'] * $product['discount'] / 100);

// Fetch related products from same category
$related_query = "SELECT p.*, c.category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.category_id = ? AND p.id != ? AND p.stock_quantity > 0 
                  LIMIT 4";
$stmt_related = $conn->prepare($related_query);
$stmt_related->bind_param("ii", $product['category_id'], $product_id);
$stmt_related->execute();
$related_result = $stmt_related->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> | FashionHub</title>
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
            background: var(--white);
            padding-top: 70px;
        }

        /* Breadcrumb */
        .breadcrumb-section {
            background: var(--bg-light);
            padding: 20px 5%;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 1400px;
            margin: 0 auto;
            font-size: 14px;
            color: var(--text-light);
        }

        .breadcrumb a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .breadcrumb a:hover {
            color: var(--accent-dark);
        }

        /* Main Container */
        .product-container {
            max-width: 1400px;
            margin: 60px auto;
            padding: 0 5%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
        }

        /* Product Gallery */
        .product-gallery {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .main-image-container {
            position: relative;
            width: 100%;
            padding-bottom: 120%;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 24px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }

        .main-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .main-image i {
            font-size: 120px;
            color: rgba(0, 0, 0, 0.08);
        }

        .product-badges {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            z-index: 2;
        }

        .badge {
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
        }

        .badge-discount {
            background: rgba(231, 76, 60, 0.95);
            color: white;
        }

        .badge-stock {
            background: rgba(255, 193, 7, 0.95);
            color: #333;
        }

        .badge-new {
            background: rgba(46, 204, 113, 0.95);
            color: white;
        }

        /* Product Info */
        .product-info {
            padding-top: 20px;
        }

        .product-category {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(231, 76, 60, 0.1);
            color: var(--accent);
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }

        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            font-weight: 900;
            color: var(--primary);
            line-height: 1.2;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .stars {
            display: flex;
            gap: 5px;
            color: #ffd700;
            font-size: 18px;
        }

        .rating-text {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 600;
        }

        .price-section {
            background: linear-gradient(135deg, #fef5f5 0%, #ffffff 100%);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 40px;
            border: 2px solid rgba(231, 76, 60, 0.1);
        }

        .current-price {
            font-size: 42px;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .price-details {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .original-price {
            font-size: 24px;
            color: var(--text-light);
            text-decoration: line-through;
        }

        .discount-badge {
            padding: 6px 14px;
            background: var(--accent);
            color: white;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
        }

        .savings-text {
            font-size: 14px;
            color: #27ae60;
            font-weight: 600;
        }

        /* Product Attributes */
        .product-attributes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 40px;
        }

        .attribute-item {
            background: white;
            padding: 20px;
            border-radius: 16px;
            border: 2px solid rgba(0, 0, 0, 0.06);
            text-align: center;
            transition: all 0.3s ease;
        }

        .attribute-item:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.15);
        }

        .attribute-item i {
            font-size: 24px;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .attribute-label {
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .attribute-value {
            font-size: 16px;
            color: var(--text);
            font-weight: 700;
        }

        /* Quantity Selector */
        .quantity-section {
            margin-bottom: 30px;
        }

        .section-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            background: white;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }

        .quantity-btn {
            width: 45px;
            height: 45px;
            background: transparent;
            border: none;
            font-size: 18px;
            color: var(--text);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: var(--accent);
            color: white;
        }

        .quantity-input {
            width: 60px;
            height: 45px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            background: transparent;
        }

        .stock-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .stock-indicator.in-stock {
            color: #27ae60;
        }

        .stock-indicator.low-stock {
            color: #f39c12;
        }

        .stock-indicator i {
            font-size: 12px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
        }

        .btn-primary, .btn-secondary {
            flex: 1;
            padding: 18px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--accent);
            border: 2px solid var(--accent);
        }

        .btn-secondary:hover {
            background: rgba(231, 76, 60, 0.05);
            transform: translateY(-2px);
        }

        /* Product Details Tabs */
        .product-tabs {
            margin-top: 60px;
            padding-top: 60px;
            border-top: 2px solid rgba(0, 0, 0, 0.05);
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .tab-btn {
            padding: 15px 30px;
            background: transparent;
            border: none;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: var(--accent);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .description-content {
            font-size: 15px;
            line-height: 1.8;
            color: var(--text);
        }

        .description-content p {
            margin-bottom: 15px;
        }

        .features-list {
            list-style: none;
            display: grid;
            gap: 15px;
        }

        .features-list li {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: var(--bg-light);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
        }

        .features-list i {
            color: var(--accent);
            font-size: 18px;
        }

        /* Related Products */
        .related-products {
            max-width: 1400px;
            margin: 80px auto;
            padding: 0 5%;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .section-subtitle {
            font-size: 16px;
            color: var(--text-light);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
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
            transform: translateY(-10px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .product-image-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 120%;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.12);
        }

        .product-image i {
            font-size: 60px;
            color: rgba(0, 0, 0, 0.08);
        }

        .card-info {
            padding: 20px;
        }

        .card-category {
            font-size: 11px;
            color: var(--accent);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .card-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-price {
            font-size: 20px;
            font-weight: 800;
            color: var(--accent);
        }

        /* Members Only Modal Styles */
        .login-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .login-modal.active {
            display: flex;
        }

        .login-modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-modal-header {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            padding: 50px 40px 40px;
            text-align: center;
            position: relative;
            color: white;
        }

        .close-login-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-login-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .login-modal-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: white;
            backdrop-filter: blur(10px);
        }

        .login-modal-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 10px;
            color: white;
        }

        .login-modal-header p {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.95);
            font-weight: 500;
        }

        .login-modal-body {
            padding: 40px;
        }

        .login-modal-body p {
            font-size: 15px;
            color: var(--text-light);
            line-height: 1.8;
            margin-bottom: 30px;
            text-align: center;
        }

        .login-modal-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn-login-modal,
        .btn-signup-modal {
            padding: 16px 30px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-login-modal {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-login-modal:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.4);
        }

        .btn-signup-modal {
            background: white;
            color: var(--accent);
            border: 2px solid var(--accent);
        }

        .btn-signup-modal:hover {
            background: rgba(231, 76, 60, 0.05);
            transform: translateY(-2px);
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 40px;
                margin: 40px auto;
            }

            .product-gallery {
                position: relative;
                top: 0;
            }

            .product-title {
                font-size: 36px;
            }

            .current-price {
                font-size: 32px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
        }

        @media (max-width: 640px) {
            .product-title {
                font-size: 28px;
            }

            .product-attributes {
                grid-template-columns: 1fr 1fr;
            }

            .section-title {
                font-size: 32px;
            }

            .login-modal-header {
                padding: 40px 30px 30px;
            }

            .login-modal-header h2 {
                font-size: 26px;
            }

            .login-modal-body {
                padding: 30px;
            }

            .login-modal-icon {
                width: 70px;
                height: 70px;
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- Breadcrumb -->
    <section class="breadcrumb-section">
        <div class="breadcrumb">
            <a href="/fashionhub/index.php">Home</a>
            <i class="fas fa-chevron-right"></i>
            <a href="/fashionhub/Collections.php">Collections</a>
            <i class="fas fa-chevron-right"></i>
            <a href="/fashionhub/Collections.php?category=<?php echo $product['category_id']; ?>">
                <?php echo htmlspecialchars($product['category_name']); ?>
            </a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
        </div>
    </section>

    <!-- Product Details -->
    <div class="product-container">
        <!-- Product Gallery -->
        <div class="product-gallery">
            <div class="main-image-container">
                <div class="main-image">
                    <?php if (!empty($product['product_photo'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo $product['product_photo']; ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-tshirt"></i>
                    <?php endif; ?>
                </div>

                <div class="product-badges">
                    <?php if ($product['discount'] > 0): ?>
                        <span class="badge badge-discount"><?php echo $product['discount']; ?>% OFF</span>
                    <?php endif; ?>
                    
                    <?php if ($product['stock_quantity'] <= 10): ?>
                        <span class="badge badge-stock">Only <?php echo $product['stock_quantity']; ?> Left</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Product Info -->
        <div class="product-info">
            <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
            
            <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>

            <div class="product-rating">
                <div class="stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star-half-alt"></i>
                </div>
                <span class="rating-text">4.5 (128 reviews)</span>
            </div>

            <!-- Price Section -->
            <div class="price-section">
                <div class="current-price">Rs.<?php echo number_format($final_price, 2); ?></div>
                <div class="price-details">
                    <?php if ($product['discount'] > 0): ?>
                        <span class="original-price">Rs.<?php echo number_format($product['price'], 2); ?></span>
                        <span class="discount-badge">Save <?php echo $product['discount']; ?>%</span>
                        <span class="savings-text">
                            You save Rs.<?php echo number_format($product['price'] - $final_price, 2); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Attributes -->
            <div class="product-attributes">
                <?php if ($product['gender'] !== null): ?>
                <div class="attribute-item">
                    <i class="fas fa-venus-mars"></i>
                    <div class="attribute-label">Gender</div>
                    <div class="attribute-value"><?php echo $product['gender'] == 0 ? 'Men' : 'Women'; ?></div>
                </div>
                <?php endif; ?>

                <?php if ($product['size'] !== null): ?>
                <div class="attribute-item">
                    <i class="fas fa-ruler"></i>
                    <div class="attribute-label">Size</div>
                    <div class="attribute-value"><?php echo htmlspecialchars($product['size']); ?></div>
                </div>
                <?php endif; ?>

                <div class="attribute-item">
                    <i class="fas fa-box"></i>
                    <div class="attribute-label">Stock</div>
                    <div class="attribute-value"><?php echo $product['stock_quantity']; ?> Units</div>
                </div>

                <div class="attribute-item">
                    <i class="fas fa-tag"></i>
                    <div class="attribute-label">SKU</div>
                    <div class="attribute-value">#<?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>

            <!-- Quantity Selector -->
            <div class="quantity-section">
                <div class="section-label">Quantity</div>
                <div class="quantity-selector">
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="decreaseQuantity()">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" readonly>
                        <button class="quantity-btn" onclick="increaseQuantity()">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="stock-indicator <?php echo $product['stock_quantity'] > 10 ? 'in-stock' : 'low-stock'; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo $product['stock_quantity'] > 10 ? 'In Stock' : 'Low Stock'; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn-primary" onclick="addToCart(<?php echo $product['id']; ?>)">
                    <i class="fas fa-shopping-cart"></i>
                    Add to Cart
                </button>
                <button class="btn-secondary" onclick="buyNow(<?php echo $product['id']; ?>)">
                    <i class="fas fa-bolt"></i>
                    Buy Now
                </button>
            </div>

            <!-- Product Tabs -->
            <div class="product-tabs">
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="switchTab('description')">Description</button>
                    <button class="tab-btn" onclick="switchTab('features')">Features</button>
                    <button class="tab-btn" onclick="switchTab('delivery')">Delivery</button>
                </div>

                <div class="tab-content active" id="description">
                    <div class="description-content">
                        <?php if (!empty($product['description'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        <?php else: ?>
                            <p>Experience premium quality with this exceptional product from our exclusive collection. Crafted with attention to detail and designed for comfort, this piece represents the perfect blend of style and functionality.</p>
                            <p>Made from high-quality materials that ensure durability and long-lasting wear. The design incorporates modern aesthetics while maintaining timeless appeal, making it a versatile addition to any wardrobe.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tab-content" id="features">
                    <ul class="features-list">
                        <li><i class="fas fa-check-circle"></i> Premium quality materials</li>
                        <li><i class="fas fa-check-circle"></i> Comfortable and breathable fabric</li>
                        <li><i class="fas fa-check-circle"></i> Perfect fit guaranteed</li>
                        <li><i class="fas fa-check-circle"></i> Easy care and maintenance</li>
                        <li><i class="fas fa-check-circle"></i> Durable construction</li>
                        <li><i class="fas fa-check-circle"></i> Fashion-forward design</li>
                    </ul>
                </div>

<div class="tab-content" id="delivery">
    <ul class="features-list">
        <li><i class="fas fa-truck"></i> Free delivery on orders over Rs.5,000</li>
        <li><i class="fas fa-clock"></i> Delivery within 3-5 business days</li>
        <li><i class="fas fa-map-marker-alt"></i> Nationwide delivery available</li>
        <li><i class="fas fa-box"></i> Secure packaging guaranteed</li>
        <li><i class="fas fa-mobile-alt"></i> Track your order in real-time</li>
        <li><i class="fas fa-undo"></i> 30-day easy return policy</li>
        <li><i class="fas fa-headset"></i> 24/7 customer support</li>
    </ul>
</div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if ($related_result->num_rows > 0): ?>
    <section class="related-products">
        <div class="section-header">
            <h2 class="section-title">You May Also Like</h2>
            <p class="section-subtitle">Discover more amazing products from our collection</p>
        </div>

        <div class="products-grid">
            <?php while ($related = $related_result->fetch_assoc()): ?>
                <?php 
                $related_final_price = $related['price'] - ($related['price'] * $related['discount'] / 100);
                ?>
                <div class="product-card" onclick="window.location.href='/fashionhub/ProductDetails.php?id=<?php echo $related['id']; ?>'">
                    <div class="product-image-wrapper">
                        <div class="product-image">
                            <?php if (!empty($related['product_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $related['product_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($related['product_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-tshirt"></i>
                            <?php endif; ?>
                        </div>

                        <?php if ($related['discount'] > 0): ?>
                        <div class="product-badges">
                            <span class="badge badge-discount"><?php echo $related['discount']; ?>% OFF</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-info">
                        <div class="card-category"><?php echo htmlspecialchars($related['category_name']); ?></div>
                        <h3 class="card-name"><?php echo htmlspecialchars($related['product_name']); ?></h3>
                        <div class="card-price">Rs.<?php echo number_format($related_final_price, 2); ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Members Only Modal -->
    <div class="login-modal" id="membersOnlyModal">
        <div class="login-modal-content">
            <div class="login-modal-header">
                <button class="close-login-modal" onclick="closeMembersOnlyModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="login-modal-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h2>Members Only</h2>
                <p>Exclusive access for registered members</p>
            </div>
            <div class="login-modal-body">
                <p>To add items to your cart and enjoy exclusive member benefits, please log in to your account or create a new one.</p>
                <div class="login-modal-actions">
                    <button class="btn-login-modal" onclick="openLoginFromMembersModal()">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </button>
                    <button class="btn-signup-modal" onclick="openSignupFromMembersModal()">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>

<script>

// Quantity Controls (for ProductDetails.php)
function increaseQuantity() {
    const input = document.getElementById('quantity');
    if (!input) return;
    const max = parseInt(input.max);
    let value = parseInt(input.value);
    
    if (value < max) {
        input.value = value + 1;
    }
}

function decreaseQuantity() {
    const input = document.getElementById('quantity');
    if (!input) return;
    let value = parseInt(input.value);
    
    if (value > 1) {
        input.value = value - 1;
    }
}

// Tab Switching (for ProductDetails.php)
function switchTab(tabName) {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => btn.classList.remove('active'));
    tabContents.forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.classList.add('active');
    }
}

// Add to Cart - ALWAYS shows modal for public pages
function addToCart(productId) {
    console.log('addToCart called with productId:', productId);
    console.log('Opening members only modal...');
    openMembersOnlyModal();
}

// Buy Now - ALWAYS shows modal for public pages
function buyNow(productId) {
    console.log('buyNow called with productId:', productId);
    console.log('Opening members only modal...');
    openMembersOnlyModal();
}

// Quick View (for Collections page)
function quickView(productId) {
    window.location.href = '/fashionhub/ProductDetails.php?id=' + productId;
}

// Members Only Modal Functions
function openMembersOnlyModal() {
    console.log('openMembersOnlyModal called');
    const modal = document.getElementById('membersOnlyModal');
    console.log('Modal element found:', !!modal);
    
    if (!modal) {
        console.error('Members Only Modal element not found in DOM!');
        alert('Please login or signup to continue');
        window.location.href = '/fashionhub/Homepage.php';
        return;
    }
    
    // Force display
    modal.style.display = 'flex';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    console.log('Modal is now visible');
}

function closeMembersOnlyModal() {
    console.log('closeMembersOnlyModal called');
    const modal = document.getElementById('membersOnlyModal');
    
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
        document.body.style.overflow = '';
        console.log('Modal closed');
    }
}

// Open login from members modal
function openLoginFromMembersModal() {
    console.log('openLoginFromMembersModal called');
    closeMembersOnlyModal();
    
    setTimeout(function() {
        const navLoginBtn = document.getElementById('loginBtn');
        const loginModal = document.getElementById('loginModal');
        
        console.log('Login button found:', !!navLoginBtn);
        console.log('Login modal found:', !!loginModal);
        
        if (navLoginBtn) {
            navLoginBtn.click();
        } else if (loginModal) {
            loginModal.style.display = 'flex';
            loginModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            console.log('No login method found, redirecting to homepage');
            window.location.href = '/fashionhub/Homepage.php';
        }
    }, 350);
}

// Open signup from members modal
function openSignupFromMembersModal() {
    console.log('openSignupFromMembersModal called');
    closeMembersOnlyModal();
    
    setTimeout(function() {
        const navSignupBtn = document.getElementById('signupBtn');
        const signupModal = document.getElementById('signupModal');
        
        console.log('Signup button found:', !!navSignupBtn);
        console.log('Signup modal found:', !!signupModal);
        
        if (navSignupBtn) {
            navSignupBtn.click();
        } else if (signupModal) {
            signupModal.style.display = 'flex';
            signupModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            console.log('No signup method found, redirecting to homepage');
            window.location.href = '/fashionhub/Homepage.php';
        }
    }, 350);
}

// View Toggle (for Collections page)
function setView(view) {
    const grid = document.getElementById('productsGrid');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    const cards = document.querySelectorAll('.product-card');

    if (!grid) return;

    if (view === 'grid') {
        grid.classList.remove('list-view');
        if (gridBtn) gridBtn.classList.add('active');
        if (listBtn) listBtn.classList.remove('active');
        cards.forEach(card => card.classList.remove('list-view'));
    } else {
        grid.classList.add('list-view');
        if (listBtn) listBtn.classList.add('active');
        if (gridBtn) gridBtn.classList.remove('active');
        cards.forEach(card => card.classList.add('list-view'));
    }

    localStorage.setItem('preferredView', view);
}

// Sort Change (for Collections page)
function changeSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    window.location.href = url.toString();
}

// Size Selection (for Collections page)
function selectSize(size) {
    const sizeInput = document.getElementById('sizeInput');
    if (!sizeInput) return;
    
    const currentSize = sizeInput.value;
    
    if (currentSize === size) {
        sizeInput.value = '';
    } else {
        sizeInput.value = size;
    }
    
    const form = document.getElementById('filtersForm');
    if (form) form.submit();
}

function clearSize() {
    const sizeInput = document.getElementById('sizeInput');
    if (sizeInput) {
        sizeInput.value = '';
        const form = document.getElementById('filtersForm');
        if (form) form.submit();
    }
}

// Mobile Filter Toggle (for Collections page)
function toggleFilters() {
    const sidebar = document.getElementById('filtersSidebar');
    const overlay = document.getElementById('filterOverlay');
    
    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
    
    document.body.style.overflow = (sidebar && sidebar.classList.contains('active')) ? 'hidden' : '';
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Check if modal exists
    const membersModal = document.getElementById('membersOnlyModal');
    console.log('Members modal exists on page load:', !!membersModal);
    
    if (membersModal) {
        // Make sure modal is hidden initially
        membersModal.style.display = 'none';
        
        // Click outside to close
        membersModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMembersOnlyModal();
            }
        });
    }
    
    // Load preferred view for Collections page
    const preferredView = localStorage.getItem('preferredView') || 'grid';
    if (document.getElementById('productsGrid')) {
        setView(preferredView);
    }
    
    // Smooth scroll for filter form
    const filtersForm = document.getElementById('filtersForm');
    if (filtersForm) {
        filtersForm.addEventListener('submit', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const membersModal = document.getElementById('membersOnlyModal');
        if (membersModal && membersModal.classList.contains('active')) {
            closeMembersOnlyModal();
        }
        
        // Also close filters sidebar on Collections page
        const sidebar = document.getElementById('filtersSidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            toggleFilters();
        }
    }
});

// Page load animation (for ProductDetails page)
window.addEventListener('load', function() {
    // Fade in animation
    if (document.body.style.opacity !== undefined) {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
            document.body.style.opacity = '1';
        }, 100);
    }
});
</script>
</body>
</html>