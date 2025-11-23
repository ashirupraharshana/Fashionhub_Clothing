<?php
include 'db_connect.php';
include 'Components/CustomerNavBar.php';

// Check if product_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: Collections.php");
    exit;
}

$product_id = intval($_GET['id']);

// Fetch product details with category
$product_sql = "SELECT p.*, c.category_name, s.subcategory_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN subcategories s ON p.subcategory_id = s.id 
                WHERE p.id = ?";
$stmt = $conn->prepare($product_sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product_result = $stmt->get_result();

if ($product_result->num_rows === 0) {
    header("Location: Collections.php");
    exit;
}

$product = $product_result->fetch_assoc();

// Fetch all sizes with their details, colors, and photos
$sizes_sql = "SELECT ps.* FROM product_sizes ps WHERE ps.product_id = ? ORDER BY 
              CASE 
                  WHEN ps.size = 'XS' THEN 0
                  WHEN ps.size = 'S' THEN 1
                  WHEN ps.size = 'M' THEN 2
                  WHEN ps.size = 'L' THEN 3
                  WHEN ps.size = 'XL' THEN 4
                  WHEN ps.size = 'XXL' THEN 5
                  WHEN ps.size = 'XXXL' THEN 6
                  ELSE 7
              END";
$stmt = $conn->prepare($sizes_sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$sizes_result = $stmt->get_result();
$sizes = [];
while ($size_row = $sizes_result->fetch_assoc()) {
    // Fetch colors for this size
    $colors_sql = "SELECT id, color_name, hex_code, quantity FROM product_colors WHERE product_id = ? AND size_id = ?";
    $color_stmt = $conn->prepare($colors_sql);
    $color_stmt->bind_param("ii", $product_id, $size_row['id']);
    $color_stmt->execute();
    $colors_result = $color_stmt->get_result();
    
    $size_row['colors'] = [];
    while ($color = $colors_result->fetch_assoc()) {
        // Fetch photos for this specific color
        $photos_sql = "SELECT photo FROM photos WHERE product_id = ? AND size_id = ? AND color_id = ?";
        $photo_stmt = $conn->prepare($photos_sql);
        $photo_stmt->bind_param("iii", $product_id, $size_row['id'], $color['id']);
        $photo_stmt->execute();
        $photos_result = $photo_stmt->get_result();
        
        $color['photos'] = [];
        while ($photo = $photos_result->fetch_assoc()) {
            $color['photos'][] = $photo['photo'];
        }
        $photo_stmt->close();
        
        $size_row['colors'][] = $color;
    }
    $color_stmt->close();
    
    $sizes[] = $size_row;
}

// Determine gender label
$genderLabel = '';
if ($product['gender'] == 0) {
    $genderLabel = 'Unisex';
} elseif ($product['gender'] == 1) {
    $genderLabel = 'Men';
} elseif ($product['gender'] == 2) {
    $genderLabel = 'Women';
}

// Get related products (same category, excluding current product)
$related_sql = "SELECT p.id, p.product_name, 
                MIN(ps.price) as min_price,
                MAX(ps.discount) as max_discount
                FROM products p 
                INNER JOIN product_sizes ps ON p.id = ps.product_id
                WHERE p.category_id = ? AND p.id != ? AND ps.quantity > 0
                GROUP BY p.id, p.product_name
                LIMIT 4";
$stmt = $conn->prepare($related_sql);
$stmt->bind_param("ii", $product['category_id'], $product_id);
$stmt->execute();
$related_result = $stmt->get_result();
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
            min-height: 100vh;
        }

        .product-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 30px;
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

        .breadcrumb i {
            font-size: 10px;
        }

        .product-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 80px;
        }

        /* Left Side - Product Images */
        .product-images-section {
            position: sticky;
            top: 90px;
            height: fit-content;
        }

        .image-gallery {
            position: relative;
            width: 100%;
            aspect-ratio: 1;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 20px;
            border: 2px solid rgba(231, 76, 60, 0.1);
        }

        .gallery-image {
            display: none;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-image.active {
            display: block;
        }

        .gallery-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .gallery-placeholder i {
            font-size: 100px;
            color: #cbd5e0;
        }

        .gallery-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 15px;
            pointer-events: none;
        }

        .gallery-nav-btn {
            pointer-events: all;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .gallery-nav-btn:hover:not(:disabled) {
            background: var(--accent);
            color: white;
            transform: scale(1.1);
        }

        .gallery-nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .gallery-counter {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 700;
            backdrop-filter: blur(10px);
        }

        /* Right Side - Product Info */
        .product-info-section {
            padding: 20px 0;
        }

        .product-category-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 20px;
        }

        .product-title {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .product-attributes {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e8e8e8;
        }

        .attribute-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border: 2px solid #e8e8e8;
            border-radius: 50px;
            font-size: 14px;
            color: var(--text);
            font-weight: 700;
        }

        .attribute-badge i {
            color: var(--accent);
            font-size: 16px;
        }

        .product-description {
            margin-bottom: 40px;
        }

        .product-description h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-description h3 i {
            color: var(--accent);
        }

        .product-description p {
            font-size: 15px;
            color: var(--text-light);
            line-height: 1.8;
        }

        /* Size Selection */
        .sizes-section {
            margin-bottom: 40px;
        }

        .sizes-header {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sizes-header i {
            color: var(--accent);
        }

        .sizes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .size-card {
            background: white;
            border: 3px solid #e8e8e8;
            border-radius: 16px;
            padding: 18px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
            position: relative;
        }

        .size-card:hover:not(.out-of-stock) {
            border-color: var(--accent);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.2);
            transform: translateY(-3px);
        }

        .size-card.selected {
            border-color: var(--accent);
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .size-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8f9fa;
        }

        .size-card.out-of-stock::before {
            content: 'OUT OF STOCK';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-size: 10px;
            font-weight: 900;
            color: rgba(231, 76, 60, 0.4);
            z-index: 1;
            letter-spacing: 0.5px;
        }

        .size-label {
            font-size: 28px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .size-price {
            font-size: 18px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
        }

        .size-original-price {
            font-size: 13px;
            color: #95a5a6;
            text-decoration: line-through;
            display: block;
            margin-bottom: 6px;
        }

        .size-discount {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .size-quantity {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
        }

        .size-quantity i {
            color: var(--accent);
        }

        .size-quantity .stock-count {
            color: var(--primary);
            font-weight: 800;
        }

        .selected-size-info {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            color: #155724;
            margin-bottom: 30px;
        }

        .selected-size-info i {
            font-size: 20px;
        }

        /* Price Display */
        .price-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border: 2px solid #e8e8e8;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .price-label {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .price-value {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .price-original {
            font-size: 24px;
            color: #95a5a6;
            text-decoration: line-through;
            font-weight: 600;
        }

        .savings-badge {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 800;
            margin-top: 15px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .btn {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(231, 76, 60, 0.5);
        }

        .btn-secondary {
            background: white;
            color: var(--accent);
            border: 3px solid var(--accent);
        }

        .btn-secondary:hover {
            background: rgba(231, 76, 60, 0.05);
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Stock Status */
        .stock-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .stock-status.in-stock {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
        }

        .stock-status.low-stock {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 2px solid #ffc107;
        }

        .stock-status.out-of-stock {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        /* Related Products */
        .related-section {
            padding: 60px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 30px;
            margin-top: 60px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .section-header p {
            font-size: 16px;
            color: var(--text-light);
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            padding: 0 30px;
        }

        .related-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s ease;
            border: 2px solid rgba(0, 0, 0, 0.06);
            cursor: pointer;
        }

        .related-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .related-image {
            width: 100%;
            aspect-ratio: 1;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .related-image i {
            font-size: 60px;
            color: #cbd5e0;
        }

        .related-info {
            padding: 20px;
        }

        .related-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .related-price {
            font-size: 20px;
            font-weight: 800;
            color: var(--accent);
        }

        /* Members Only Modal */
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
        @media (max-width: 1024px) {
            .product-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .product-images-section {
                position: static;
            }

            .sizes-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .product-container {
                padding: 0 20px;
            }

            .product-title {
                font-size: 36px;
            }

            .price-value {
                font-size: 32px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .related-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
                padding: 0 20px;
            }
        }

        @media (max-width: 640px) {
            .sizes-grid {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            }

            .size-label {
                font-size: 22px;
            }

            .size-price {
                font-size: 15px;
            }
        }

        /* Color Selection Styles */
        .color-card {
            background: white;
            border: 3px solid #e8e8e8;
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-align: center;
            position: relative;
        }

        .color-card:hover:not(.out-of-stock) {
            border-color: var(--accent);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.2);
            transform: translateY(-2px);
        }

        .color-card.selected {
            border-color: var(--accent);
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        .color-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8f9fa;
        }

        .color-swatch {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: 0 auto 10px;
            border: 3px solid #e8e8e8;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .color-card.selected .color-swatch {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .color-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .color-quantity {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 11px;
            color: var(--text-light);
            font-weight: 600;
        }

        .color-quantity i {
            color: var(--accent);
        }

        .color-quantity .stock-count {
            color: var(--primary);
            font-weight: 800;
        }
    </style>
</head>
<body>
    <div class="product-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="Homepage.php">Home</a>
            <i class="fas fa-chevron-right"></i>
            <a href="Collections.php">Collections</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
        </div>

        <!-- Product Content -->
        <div class="product-content">
            <!-- Left Side - Product Images -->
            <div class="product-images-section">
                <div class="image-gallery" id="imageGallery">
                    <div class="gallery-placeholder" id="galleryPlaceholder">
                        <i class="fas fa-tshirt"></i>
                    </div>
                    
                    <div class="gallery-nav">
                        <button class="gallery-nav-btn" id="prevBtn" onclick="navigateGallery(-1)" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="gallery-nav-btn" id="nextBtn" onclick="navigateGallery(1)" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="gallery-counter" id="galleryCounter" style="display: none;">
                        <span id="currentImageIndex">1</span> / <span id="totalImages">1</span>
                    </div>
                </div>
            </div>

            <!-- Right Side - Product Info -->
            <div class="product-info-section">
                <span class="product-category-badge"><?php echo htmlspecialchars($product['category_name']); ?></span>
                
                <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>

                <div class="product-attributes">
                    <?php if ($genderLabel): ?>
                    <div class="attribute-badge">
                        <i class="fas fa-venus-mars"></i>
                        <span><?php echo $genderLabel; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($product['subcategory_name']): ?>
                    <div class="attribute-badge">
                        <i class="fas fa-tag"></i>
                        <span><?php echo htmlspecialchars($product['subcategory_name']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="attribute-badge">
                        <i class="fas fa-layer-group"></i>
                        <span><?php echo count($sizes); ?> Size<?php echo count($sizes) > 1 ? 's' : ''; ?></span>
                    </div>
                </div>

                <?php if (!empty($product['description'])): ?>
                <div class="product-description">
                    <h3><i class="fas fa-info-circle"></i> Description</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
                <?php endif; ?>

                <!-- Size Selection -->
                <div class="sizes-section">
                    <h3 class="sizes-header">
                        <i class="fas fa-ruler-combined"></i>
                        Select Size
                    </h3>
                    <div class="sizes-grid">
                        <?php foreach ($sizes as $index => $size): ?>
                            <?php 
                            $is_out_of_stock = $size['quantity'] == 0;
                            $final_price = $size['price'] - ($size['price'] * $size['discount'] / 100);
                            ?>
                            <div class="size-card <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?> <?php echo $index === 0 && !$is_out_of_stock ? 'selected' : ''; ?>" 
     data-size-id="<?php echo $size['id']; ?>"
     data-size="<?php echo htmlspecialchars($size['size']); ?>"
     data-price="<?php echo $final_price; ?>"
     data-original-price="<?php echo $size['price']; ?>"
     data-discount="<?php echo $size['discount']; ?>"
     data-max-qty="<?php echo $size['quantity']; ?>"
     data-colors='<?php echo htmlspecialchars(json_encode($size['colors']), ENT_QUOTES); ?>'
     onclick="<?php echo !$is_out_of_stock ? 'selectSize(this)' : ''; ?>">
                                <div class="size-label"><?php echo htmlspecialchars($size['size']); ?></div>
                                <div class="size-price">Rs. <?php echo number_format($final_price, 2); ?></div>
                                <?php if ($size['discount'] > 0): ?>
                                    <div class="size-original-price">Rs. <?php echo number_format($size['price'], 2); ?></div>
                                    <div class="size-discount"><?php echo $size['discount']; ?>% OFF</div>
                                <?php endif; ?>
                                <div class="size-quantity">
                                    <i class="fas fa-cube"></i>
                                    <span class="stock-count"><?php echo $size['quantity']; ?></span>
                                    <span><?php echo $is_out_of_stock ? 'Out' : 'left'; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Color Selection -->
                    <div id="colorSelectionSection" style="display: none; margin-top: 25px;">
                        <h4 style="font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-palette" style="color: var(--accent);"></i>
                            Select Color
                        </h4>
                        <div id="colorsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px;">
                            <!-- Colors will be dynamically inserted here -->
                        </div>
                    </div>

                    <div class="selected-size-info" id="selectedSizeInfo">
                        <i class="fas fa-check-circle"></i>
                        <span>Size <strong id="selectedSizeDisplay"></strong> selected</span>
                        <span id="selectedColorDisplay" style="display: none;"> - Color: <strong id="selectedColorName"></strong></span>
                    </div>
                </div>

                <!-- Price Display -->
                <div class="price-section" id="priceSection">
                    <div class="price-label">Price</div>
                    <div class="price-value" id="displayPrice">Rs. 0.00</div>
                    <div id="originalPriceDisplay" style="display: none;">
                        <span class="price-original" id="displayOriginalPrice">Rs. 0.00</span>
                    </div>
                    <div id="savingsDisplay" style="display: none;">
                        <span class="savings-badge">
                            <i class="fas fa-tags"></i>
                            You save Rs. <span id="savingsAmount">0.00</span>
                        </span>
                    </div>
                </div>

                <!-- Stock Status -->
                <div class="stock-status in-stock" id="stockStatus">
                    <i class="fas fa-check-circle"></i>
                    <span id="stockStatusText">In Stock</span>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-primary" id="buyNowBtn" onclick="handleBuyNow()">
                        <i class="fas fa-shopping-bag"></i>
                        Buy Now
                    </button>
                    <button class="btn btn-secondary" id="addToCartBtn" onclick="handleAddToCart()">
                        <i class="fas fa-cart-plus"></i>
                        Add to Cart
                    </button>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if ($related_result->num_rows > 0): ?>
        <div class="related-section">
            <div class="section-header">
                <h2>You May Also Like</h2>
                <p>Discover more products from the same collection</p>
            </div>
            <div class="related-grid">
                <?php while ($related = $related_result->fetch_assoc()): ?>
                    <?php
                    $final_price = $related['min_price'] - ($related['min_price'] * $related['max_discount'] / 100);
                    
                    // Get first photo for related product
                    $related_photo_sql = "SELECT photo FROM photos WHERE product_id = ? LIMIT 1";
                    $photo_stmt = $conn->prepare($related_photo_sql);
                    $photo_stmt->bind_param("i", $related['id']);
                    $photo_stmt->execute();
                    $photo_result = $photo_stmt->get_result();
                    $related_photo = $photo_result->fetch_assoc();
                    $photo_stmt->close();
                    ?>
                    <div class="related-card" onclick="window.location.href='ProductDetails.php?id=<?php echo $related['id']; ?>'">
                        <div class="related-image">
                            <?php if ($related_photo): ?>
                                <img src="data:image/jpeg;base64,<?php echo $related_photo['photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($related['product_name']); ?>"
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-tshirt"></i>
                            <?php endif; ?>
                        </div>
                        <div class="related-info">
                            <h4 class="related-name"><?php echo htmlspecialchars($related['product_name']); ?></h4>
                            <div class="related-price">Rs. <?php echo number_format($final_price, 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

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
                <p>To purchase this product or add it to your cart, please log in to your account or create a new one.</p>
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
        let currentImageIndex = 0;
        let currentPhotos = [];
        let selectedSizeData = null;
        const isLoggedIn = false; 

        // Initialize with first available size on page load
        document.addEventListener('DOMContentLoaded', function() {
            const firstAvailableSize = document.querySelector('.size-card:not(.out-of-stock)');
            if (firstAvailableSize) {
                selectSize(firstAvailableSize);
            }
        });

        let selectedColorData = null;

        function selectSize(element) {
            // Remove selection from all size cards
            document.querySelectorAll('.size-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Add selection to clicked card
            element.classList.add('selected');

            // Get size data
            const sizeId = element.getAttribute('data-size-id');
            const size = element.getAttribute('data-size');
            const price = parseFloat(element.getAttribute('data-price'));
            const originalPrice = parseFloat(element.getAttribute('data-original-price'));
            const discount = parseFloat(element.getAttribute('data-discount'));
            const maxQty = parseInt(element.getAttribute('data-max-qty'));
            const colors = JSON.parse(element.getAttribute('data-colors'));

            // Store selected size data
            selectedSizeData = {
                id: sizeId,
                size: size,
                price: price,
                originalPrice: originalPrice,
                discount: discount,
                maxQuantity: maxQty,
                colors: colors
            };

            // Reset selected color
            selectedColorData = null;
            document.getElementById('selectedColorDisplay').style.display = 'none';

            // Update selected size display
            document.getElementById('selectedSizeDisplay').textContent = size;

            // Display colors if available
            if (colors && colors.length > 0) {
                displayColors(colors);
                document.getElementById('colorSelectionSection').style.display = 'block';
            } else {
                document.getElementById('colorSelectionSection').style.display = 'none';
                // If no colors, update display with size data
                updatePriceDisplay();
                updateStockStatus(maxQty);
                updateGallery([]);
            }

            // Smooth scroll to price section on mobile
            if (window.innerWidth <= 1024) {
                document.getElementById('priceSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function displayColors(colors) {
            const colorsGrid = document.getElementById('colorsGrid');
            colorsGrid.innerHTML = '';

            colors.forEach((color, index) => {
                const isOutOfStock = color.quantity === 0;
                const colorCard = document.createElement('div');
                colorCard.className = `color-card ${isOutOfStock ? 'out-of-stock' : ''} ${index === 0 && !isOutOfStock ? 'selected' : ''}`;
                colorCard.setAttribute('data-color-id', color.id);
                colorCard.setAttribute('data-color-name', color.color_name);
                colorCard.setAttribute('data-color-quantity', color.quantity);
                colorCard.setAttribute('data-color-photos', JSON.stringify(color.photos));
                
                if (!isOutOfStock) {
                    colorCard.onclick = function() { selectColor(this); };
                }

                const hexCode = color.hex_code || '#cccccc';
                
                colorCard.innerHTML = `
                    <div class="color-swatch" style="background: ${hexCode};"></div>
                    <div class="color-name">${color.color_name}</div>
                    <div class="color-quantity">
                        <i class="fas fa-cube"></i>
                        <span class="stock-count">${color.quantity}</span>
                        <span>${isOutOfStock ? 'Out' : 'left'}</span>
                    </div>
                `;

                colorsGrid.appendChild(colorCard);
            });

            // Auto-select first available color
            const firstAvailableColor = colorsGrid.querySelector('.color-card:not(.out-of-stock)');
            if (firstAvailableColor) {
                selectColor(firstAvailableColor);
            }
        }

        function selectColor(element) {
            // Remove selection from all color cards
            document.querySelectorAll('.color-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Add selection to clicked card
            element.classList.add('selected');

            // Get color data
            const colorId = element.getAttribute('data-color-id');
            const colorName = element.getAttribute('data-color-name');
            const colorQuantity = parseInt(element.getAttribute('data-color-quantity'));
            const colorPhotos = JSON.parse(element.getAttribute('data-color-photos'));

            // Store selected color data
            selectedColorData = {
                id: colorId,
                name: colorName,
                quantity: colorQuantity,
                photos: colorPhotos
            };

            // Update selected color display
            document.getElementById('selectedColorName').textContent = colorName;
            document.getElementById('selectedColorDisplay').style.display = 'inline';

            // Update photos in gallery
            updateGallery(colorPhotos);

            // Update price display (use size price)
            updatePriceDisplay();

            // Update stock status (use color quantity)
            updateStockStatus(colorQuantity);

            // Smooth scroll to price section on mobile
            if (window.innerWidth <= 1024) {
                document.getElementById('priceSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function updateGallery(photos) {
            const gallery = document.getElementById('imageGallery');
            const placeholder = document.getElementById('galleryPlaceholder');
            const counter = document.getElementById('galleryCounter');
            
            // Clear existing images
            const existingImages = gallery.querySelectorAll('.gallery-image');
            existingImages.forEach(img => img.remove());

            currentPhotos = photos;
            currentImageIndex = 0;

            if (photos && photos.length > 0) {
                // Hide placeholder
                placeholder.style.display = 'none';
                
                // Create image elements
                photos.forEach((photo, index) => {
                    const img = document.createElement('img');
                    img.src = `data:image/jpeg;base64,${photo}`;
                    img.classList.add('gallery-image');
                    if (index === 0) {
                        img.classList.add('active');
                    }
                    gallery.appendChild(img);
                });

                // Update counter
                document.getElementById('currentImageIndex').textContent = '1';
                document.getElementById('totalImages').textContent = photos.length;
                counter.style.display = photos.length > 1 ? 'block' : 'none';

                // Update navigation buttons
                updateNavigationButtons();
            } else {
                // Show placeholder
                placeholder.style.display = 'flex';
                counter.style.display = 'none';
                document.getElementById('prevBtn').disabled = true;
                document.getElementById('nextBtn').disabled = true;
            }
        }

        function navigateGallery(direction) {
            if (currentPhotos.length === 0) return;

            const images = document.querySelectorAll('.gallery-image');
            
            // Remove active class from current image
            images[currentImageIndex].classList.remove('active');

            // Update index
            currentImageIndex += direction;

            // Add active class to new image
            images[currentImageIndex].classList.add('active');

            // Update counter
            document.getElementById('currentImageIndex').textContent = currentImageIndex + 1;

            // Update navigation buttons
            updateNavigationButtons();
        }

        function updateNavigationButtons() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');

            prevBtn.disabled = currentImageIndex === 0;
            nextBtn.disabled = currentImageIndex === currentPhotos.length - 1;
        }

        function updatePriceDisplay() {
            if (!selectedSizeData) return;

            document.getElementById('displayPrice').textContent = 'Rs. ' + selectedSizeData.price.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");

            if (selectedSizeData.discount > 0) {
                document.getElementById('displayOriginalPrice').textContent = 'Rs. ' + selectedSizeData.originalPrice.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                document.getElementById('originalPriceDisplay').style.display = 'block';

                const savings = selectedSizeData.originalPrice - selectedSizeData.price;
                document.getElementById('savingsAmount').textContent = savings.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                document.getElementById('savingsDisplay').style.display = 'block';
            } else {
                document.getElementById('originalPriceDisplay').style.display = 'none';
                document.getElementById('savingsDisplay').style.display = 'none';
            }
        }

        function updateStockStatus(quantity) {
            const statusDiv = document.getElementById('stockStatus');
            const statusText = document.getElementById('stockStatusText');
            const buyBtn = document.getElementById('buyNowBtn');
            const cartBtn = document.getElementById('addToCartBtn');

            if (quantity === 0) {
                statusDiv.className = 'stock-status out-of-stock';
                statusDiv.innerHTML = '<i class="fas fa-times-circle"></i><span>Out of Stock</span>';
                buyBtn.disabled = true;
                cartBtn.disabled = true;
            } else if (quantity <= 10) {
                statusDiv.className = 'stock-status low-stock';
                statusDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i><span>Only ${quantity} left in stock</span>`;
                buyBtn.disabled = false;
                cartBtn.disabled = false;
            } else {
                statusDiv.className = 'stock-status in-stock';
                statusDiv.innerHTML = '<i class="fas fa-check-circle"></i><span>In Stock</span>';
                buyBtn.disabled = false;
                cartBtn.disabled = false;
            }
        }

function handleBuyNow() {
    if (!selectedSizeData) {
        alert('Please select a size');
        return;
    }
    if (selectedSizeData.colors && selectedSizeData.colors.length > 0 && !selectedColorData) {
        alert('Please select a color');
        return;
    }
    openMembersOnlyModal();
}

function handleAddToCart() {
    if (!selectedSizeData) {
        alert('Please select a size');
        return;
    }
    if (selectedSizeData.colors && selectedSizeData.colors.length > 0 && !selectedColorData) {
        alert('Please select a color');
        return;
    }
    openMembersOnlyModal();
}

        function addToCart(sizeId) {
            const formData = new FormData();
            formData.append('add_to_cart', '1');
            formData.append('selected_size_id', sizeId);
            formData.append('quantity', 1);
            
            const btn = document.getElementById('addToCartBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            fetch('OrderNow.php?product_id=<?php echo $product_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // Update cart display if function exists
                    if (typeof window.updateCartDisplay === 'function') {
                        window.updateCartDisplay({
                            cart_count: data.cart_count,
                            cart_total: data.cart_total,
                            cart_items: data.cart_items
                        });
                    }
                    
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                    }, 1000);
                } else {
                    showNotification(data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to add to cart. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
            });
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 90px;
                right: 30px;
                padding: 18px 24px;
                border-radius: 12px;
                background: ${type === 'success' ? 'linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%)' : 'linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%)'};
                color: ${type === 'success' ? '#155724' : '#721c24'};
                border: 2px solid ${type === 'success' ? '#28a745' : '#dc3545'};
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideInRight 0.4s ease;
            `;
            
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}" style="font-size: 20px;"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.4s ease';
                setTimeout(() => notification.remove(), 400);
            }, 3000);
        }

        // Members Only Modal Functions
        function openMembersOnlyModal() {
            const modal = document.getElementById('membersOnlyModal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMembersOnlyModal() {
            const modal = document.getElementById('membersOnlyModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        function openLoginFromMembersModal() {
            closeMembersOnlyModal();
            setTimeout(function() {
                const navLoginBtn = document.getElementById('loginBtn');
                const loginModal = document.getElementById('loginModal');
                
                if (navLoginBtn) {
                    navLoginBtn.click();
                } else if (loginModal) {
                    loginModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    window.location.href = '/fashionhub/Homepage.php?action=login';
                }
            }, 300);
        }

        function openSignupFromMembersModal() {
            closeMembersOnlyModal();
            setTimeout(function() {
                const navSignupBtn = document.getElementById('signupBtn');
                const signupModal = document.getElementById('signupModal');
                
                if (navSignupBtn) {
                    navSignupBtn.click();
                } else if (signupModal) {
                    signupModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    window.location.href = '/fashionhub/Homepage.php?action=signup';
                }
            }, 300);
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const membersModal = document.getElementById('membersOnlyModal');
                if (membersModal && membersModal.classList.contains('active')) {
                    closeMembersOnlyModal();
                }
            }
        });

        // Close members only modal when clicking outside
        document.getElementById('membersOnlyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMembersOnlyModal();
            }
        });

        // Keyboard navigation for gallery
        document.addEventListener('keydown', function(e) {
            if (currentPhotos.length <= 1) return;

            if (e.key === 'ArrowLeft') {
                if (currentImageIndex > 0) {
                    navigateGallery(-1);
                }
            } else if (e.key === 'ArrowRight') {
                if (currentImageIndex < currentPhotos.length - 1) {
                    navigateGallery(1);
                }
            }
        });

        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>