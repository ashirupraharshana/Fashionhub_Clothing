<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

// Check if product_id is provided
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    header("Location: Products.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($_GET['product_id']);

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
    header("Location: Products.php");
    exit;
}

$product = $product_result->fetch_assoc();

// Fetch all sizes with their details and photos
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
    // Fetch photos for each size
    $photos_sql = "SELECT photo FROM photos WHERE product_id = ? AND size_id = ?";
    $photo_stmt = $conn->prepare($photos_sql);
    $photo_stmt->bind_param("ii", $product_id, $size_row['id']);
    $photo_stmt->execute();
    $photos_result = $photo_stmt->get_result();
    
    $size_row['photos'] = [];
    while ($photo = $photos_result->fetch_assoc()) {
        $size_row['photos'][] = $photo['photo'];
    }
    
    $sizes[] = $size_row;
    $photo_stmt->close();
}

// Fetch user details
$user_sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Handle Add to Cart submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_to_cart'])) {
    $selected_size_id = intval($_POST['selected_size_id']);
    $quantity = intval($_POST['quantity']);
    
    // Get selected size details
    $size_sql = "SELECT * FROM product_sizes WHERE id = ? AND product_id = ?";
    $stmt = $conn->prepare($size_sql);
    $stmt->bind_param("ii", $selected_size_id, $product_id);
    $stmt->execute();
    $size_result = $stmt->get_result();
    $selected_size_data = $size_result->fetch_assoc();
    
    if (!$selected_size_data) {
        // Store error in session for navbar to display
        $_SESSION['message'] = 'Invalid size selected!';
        $_SESSION['messageType'] = 'error';
        echo json_encode(['success' => false, 'message' => 'Invalid size selected!']);
        exit;
    } elseif ($quantity < 1 || $quantity > $selected_size_data['quantity']) {
        $_SESSION['message'] = 'Invalid quantity selected!';
        $_SESSION['messageType'] = 'error';
        echo json_encode(['success' => false, 'message' => 'Invalid quantity selected!']);
        exit;
    }
    
    // Calculate price with discount
    $unit_price = $selected_size_data['price'] - ($selected_size_data['price'] * $selected_size_data['discount'] / 100);
    $total_price = $unit_price * $quantity;
    
    // Check if item already exists in cart
    $check_cart_sql = "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND size_id = ?";
    $stmt = $conn->prepare($check_cart_sql);
    $stmt->bind_param("iii", $user_id, $product_id, $selected_size_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    
    if ($cart_result->num_rows > 0) {
        // Update existing cart item
        $cart_item = $cart_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($new_quantity > $selected_size_data['quantity']) {
            $error_msg = 'Cannot add more items. Only ' . $selected_size_data['quantity'] . ' items available in stock.';
            $_SESSION['message'] = $error_msg;
            $_SESSION['messageType'] = 'error';
            echo json_encode([
                'success' => false, 
                'message' => $error_msg
            ]);
            exit;
        }
        
        $new_total = $unit_price * $new_quantity;
        $update_sql = "UPDATE cart SET quantity = ?, price = ?, total_price = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iddi", $new_quantity, $unit_price, $new_total, $cart_item['id']);
        
        if (!$stmt->execute()) {
            $_SESSION['message'] = 'Failed to update cart!';
            $_SESSION['messageType'] = 'error';
            echo json_encode(['success' => false, 'message' => 'Failed to update cart!']);
            exit;
        }
    } else {
        // Insert new cart item
        $insert_cart_sql = "INSERT INTO cart (user_id, product_id, size_id, size, quantity, price, total_price) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_cart_sql);
        $stmt->bind_param("iiisidd", $user_id, $product_id, $selected_size_id, $selected_size_data['size'], $quantity, $unit_price, $total_price);
        
        if (!$stmt->execute()) {
            $_SESSION['message'] = 'Failed to add to cart!';
            $_SESSION['messageType'] = 'error';
            echo json_encode(['success' => false, 'message' => 'Failed to add to cart!']);
            exit;
        }
    }
    
    // Get updated cart count
    $cart_count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_count_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count_result = $stmt->get_result();
    $cart_count_row = $cart_count_result->fetch_assoc();
    $cart_count = $cart_count_row['total'] ?? 0;
    
    // Get updated cart total
    $cart_total_sql = "SELECT SUM(total_price) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_total_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_total_result = $stmt->get_result();
    $cart_total_row = $cart_total_result->fetch_assoc();
    $cart_total = $cart_total_row['total'] ?? 0;
    
    // Get cart items for display
    $cart_items_sql = "SELECT 
                        c.id, 
                        c.product_id, 
                        c.size_id,
                        c.size,
                        c.quantity, 
                        c.price, 
                        c.total_price,
                        p.product_name,
                        (SELECT photo FROM photos WHERE product_id = c.product_id AND size_id = c.size_id LIMIT 1) as product_photo
                       FROM cart c 
                       INNER JOIN products p ON c.product_id = p.id 
                       WHERE c.user_id = ? 
                       ORDER BY c.id DESC";
    
    $stmt = $conn->prepare($cart_items_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_items_array = [];
    while ($row = $result->fetch_assoc()) {
        $cart_items_array[] = $row;
    }
    
    // Store success message in session
    $_SESSION['message'] = 'Product added to cart successfully!';
    $_SESSION['messageType'] = 'success';
    
    echo json_encode([
        'success' => true, 
        'message' => 'Product added to cart successfully!',
        'cart_count' => $cart_count,
        'cart_total' => $cart_total,
        'cart_items' => $cart_items_array
    ]);
    exit;
}
// Handle Place Order submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $selected_size_id = intval($_POST['selected_size_id']);
    $quantity = intval($_POST['quantity']);
    $delivery_address = trim($_POST['delivery_address']);
    $phone = trim($_POST['phone']);
    
    // Get selected size details
    $size_sql = "SELECT * FROM product_sizes WHERE id = ? AND product_id = ?";
    $stmt = $conn->prepare($size_sql);
    $stmt->bind_param("ii", $selected_size_id, $product_id);
    $stmt->execute();
    $size_result = $stmt->get_result();
    $selected_size_data = $size_result->fetch_assoc();
    
    if (!$selected_size_data) {
        $error = "Invalid size selected!";
    } elseif ($quantity < 1 || $quantity > $selected_size_data['quantity']) {
        $error = "Invalid quantity selected!";
    } else {
        // Calculate final price with discount
        $final_price = $selected_size_data['price'] - ($selected_size_data['price'] * $selected_size_data['discount'] / 100);
        $total_price = $final_price * $quantity;
        
        // Insert order with status 0 (Pending)
        $order_sql = "INSERT INTO orders (user_id, product_id, size, quantity, price, total_price, delivery_address, phone, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($order_sql);
        $stmt->bind_param("iisiddss", $user_id, $product_id, $selected_size_data['size'], $quantity, $final_price, $total_price, $delivery_address, $phone);
        
        if ($stmt->execute()) {
            // Update size quantity - ONLY for the selected size
            $update_qty_sql = "UPDATE product_sizes SET quantity = quantity - ? WHERE id = ?";
            $stmt = $conn->prepare($update_qty_sql);
            $stmt->bind_param("ii", $quantity, $selected_size_id);
            $stmt->execute();
            
            $success = "Order placed successfully! Order ID: #" . $conn->insert_id;
            
            // Redirect to products page after 2 seconds
            header("refresh:2;url=OrderNow.php");
        } else {
            $error = "Failed to place order. Please try again.";
        }
    }
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order - <?php echo htmlspecialchars($product['product_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fafafa 0%, #f0f0f0 100%);
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
        }

        .order-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }
.alert {
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    padding: 18px 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 14px;
    font-weight: 600;
    animation: slideInDown 0.5s ease;
    z-index: 10000; /* Higher than navbar */
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: opacity 0.3s, transform 0.3s;
}

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .alert i {
            font-size: 22px;
        }

        .order-content {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        /* Product Images Section - Left Side */
        .product-images-section {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(231, 76, 60, 0.08);
            border: 2px solid rgba(231, 76, 60, 0.1);
            position: sticky;
            top: 90px;
            height: fit-content;
        }

        .image-gallery {
            position: relative;
            width: 100%;
            aspect-ratio: 1;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 20px;
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
            font-size: 80px;
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
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .gallery-nav-btn:hover:not(:disabled) {
            background: #e74c3c;
            color: white;
            transform: scale(1.1);
        }

        .gallery-nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .gallery-counter {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .product-basic-info {
            text-align: center;
            padding-top: 10px;
        }

        .product-category-tag {
            display: inline-block;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 12px;
        }

        .product-name {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: 800;
            line-height: 1.3;
        }

        .product-attributes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .attribute-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border: 2px solid #e8e8e8;
            border-radius: 50px;
            font-size: 12px;
            color: #2c3e50;
            font-weight: 700;
        }

        .attribute-badge i {
            color: #e74c3c;
            font-size: 13px;
        }

        .product-description {
            margin-top: 20px;
            padding: 18px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border-radius: 12px;
            border-left: 4px solid #e74c3c;
            text-align: left;
        }

        .product-description strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .product-description p {
            font-size: 13px;
            color: #7f8c8d;
            line-height: 1.6;
        }

        /* Right Side - Sizes and Order Form */
        .right-section {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .sizes-section {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(231, 76, 60, 0.08);
            border: 2px solid rgba(231, 76, 60, 0.1);
        }

        .sizes-header {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e8e8e8;
        }

        .sizes-header i {
            color: #e74c3c;
        }

        .sizes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        .size-card {
            background: white;
            border: 3px solid #e8e8e8;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            text-align: center;
        }

        .size-card:hover:not(.out-of-stock) {
            border-color: #e74c3c;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.2);
            transform: translateY(-3px);
        }

        .size-card.selected {
            border-color: #e74c3c;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

            .size-card.out-of-stock {
    opacity: 0.7;
    cursor: pointer;
    background: #f8f9fa;
    border-color: #dee2e6;
}

.size-card.out-of-stock:hover {
    border-color: #adb5bd;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.size-card.out-of-stock.selected {
    border-color: #dc3545;
    background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
}

        .size-card.out-of-stock::before {
            content: 'OUT OF STOCK';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-size: 12px;
            font-weight: 900;
            color: rgba(231, 76, 60, 0.3);
            z-index: 1;
            letter-spacing: 1px;
        }

        .size-label {
            font-size: 32px;
            font-weight: 900;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        .size-price {
            font-size: 20px;
            font-weight: 800;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .size-original-price {
            font-size: 14px;
            color: #95a5a6;
            text-decoration: line-through;
            display: block;
            margin-bottom: 8px;
        }

        .size-discount {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .size-quantity {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .size-quantity i {
            color: #e74c3c;
        }

        .size-quantity .stock-count {
            color: #2c3e50;
            font-weight: 800;
        }

        /* Order Form */
        .order-form {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(231, 76, 60, 0.08);
            border: 2px solid rgba(231, 76, 60, 0.1);
        }

        .form-header {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e8e8e8;
        }

        .form-header h3 {
            font-size: 24px;
            color: #2c3e50;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-group label span {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
            font-weight: 500;
        }

        .form-control:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .qty-btn {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .qty-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: scale(1.08);
            box-shadow: 0 6px 18px rgba(231, 76, 60, 0.4);
        }

        .qty-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.5;
            box-shadow: none;
        }

        .qty-input {
            width: 90px;
            text-align: center;
            font-size: 20px;
            font-weight: 800;
            color: #2c3e50;
        }

        .size-selection-status {
            padding: 14px 18px;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-radius: 12px;
            color: #856404;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .size-selection-status.selected {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-color: #28a745;
            color: #155724;
        }
        .size-selection-status.out-of-stock {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border-color: #dc3545;
    color: #721c24;
}

        .order-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            padding: 22px;
            border-radius: 15px;
            margin-bottom: 22px;
            border: 2px solid #e8e8e8;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
            color: #2c3e50;
            font-weight: 600;
        }

        .summary-row.total {
            font-size: 24px;
            font-weight: 900;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            padding-top: 15px;
            border-top: 2px solid #e8e8e8;
            margin-top: 12px;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .submit-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.5);
        }

        .submit-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .order-content {
                grid-template-columns: 1fr;
            }

            .product-images-section {
                position: static;
            }

            .sizes-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .order-container {
                padding: 0 15px;
            }

            .sizes-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 12px;
            }

            .size-label {
                font-size: 24px;
            }

            .size-price {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
     <?php include 'Components/CustomerNavBar.php'; ?>
    <div class="order-container">

<?php if (isset($success) && !isset($_POST['add_to_cart'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success; ?></span>
    </div>
<?php endif; ?>

<?php if (isset($error) && !isset($_POST['add_to_cart'])): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
<?php endif; ?>

        <div class="order-content">
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

                <div class="product-basic-info">
                    <span class="product-category-tag"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <h1 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h1>

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
                        <strong><i class="fas fa-info-circle"></i> Description</strong>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side - Sizes and Order Form -->
            <div class="right-section">
                <!-- Sizes Section -->
                <div class="sizes-section">
                    <h3 class="sizes-header">
                        <i class="fas fa-ruler-combined"></i>
                        Available Sizes
                    </h3>
                    <div class="sizes-grid">
                        <?php foreach ($sizes as $index => $size): ?>
                            <?php 
                            $is_out_of_stock = $size['quantity'] == 0;
                            $final_price = $size['price'] - ($size['price'] * $size['discount'] / 100);
                            ?>
                        <div class="size-card <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?> <?php echo $index === 0 ? 'selected' : ''; ?>" 
     data-size-id="<?php echo $size['id']; ?>"
     data-size="<?php echo htmlspecialchars($size['size']); ?>"
     data-price="<?php echo $final_price; ?>"
     data-original-price="<?php echo $size['price']; ?>"
     data-discount="<?php echo $size['discount']; ?>"
     data-max-qty="<?php echo $size['quantity']; ?>"
     data-photos='<?php echo json_encode($size['photos']); ?>'
     onclick="selectSize(this)">
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
                </div>

                <!-- Order Form -->
                <div class="order-form">
                    <div class="form-header">
                        <h3>Complete Your Order</h3>
                        <p>Fill in the details below to place your order</p>
                    </div>

                    <div id="sizeSelectionStatus" class="size-selection-status selected">
    <i class="fas fa-check-circle"></i>
    <span id="sizeStatusText">Size <strong id="selectedSizeDisplay"></strong> selected</span>
</div>

                    <form method="POST" action="" id="orderForm">
                        <input type="hidden" name="selected_size_id" id="selectedSizeIdInput" required>

                        <div class="form-group">
                            <label>Quantity <span>*</span></label>
                            <div class="quantity-selector">
                                <button type="button" class="qty-btn" onclick="decreaseQuantity()" id="decreaseBtn">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" name="quantity" id="quantity" value="1" min="1" max="1" class="form-control qty-input" readonly>
                                <button type="button" class="qty-btn" onclick="increaseQuantity()" id="increaseBtn">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number <span>*</span></label>
                            <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="delivery_address">Delivery Address <span>*</span></label>
                            <textarea name="delivery_address" id="delivery_address" class="form-control" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="order-summary">
                            <div class="summary-row">
                                <span>Unit Price:</span>
                                <span id="summaryUnitPrice">Rs. 0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Quantity:</span>
                                <span id="summaryQuantity">1</span>
                            </div>
                            <div class="summary-row total">
                                <span>Total:</span>
                                <span id="totalPrice">Rs. 0.00</span>
                            </div>
                        </div>

                        <button type="submit" name="place_order" class="submit-btn" id="submitBtn">
                            <i class="fas fa-shopping-cart"></i>
                            Place Order
                        </button>

                        <button type="button" id="addToCartBtn" class="submit-btn" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); margin-top: 15px;">
    <i class="fas fa-cart-plus"></i>
    Add to Cart
</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentImageIndex = 0;
        let currentPhotos = [];
        let selectedSizeData = null;

        // Initialize with first available size on page load
        document.addEventListener('DOMContentLoaded', function() {
            const firstAvailableSize = document.querySelector('.size-card:not(.out-of-stock)');
            if (firstAvailableSize) {
                selectSize(firstAvailableSize, true);
            }
        });

        function selectSize(element, isInitial = false) {
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
    const photos = JSON.parse(element.getAttribute('data-photos'));
    const isOutOfStock = maxQty === 0;

    // Store selected size data
    selectedSizeData = {
        id: sizeId,
        size: size,
        price: price,
        originalPrice: originalPrice,
        discount: discount,
        maxQuantity: maxQty,
        photos: photos,
        isOutOfStock: isOutOfStock
    };

    // Update photos in gallery
    updateGallery(photos);

    // Update hidden input
    document.getElementById('selectedSizeIdInput').value = sizeId;

    // Update status message
    const statusDiv = document.getElementById('sizeSelectionStatus');
    const statusText = document.getElementById('sizeStatusText');
    document.getElementById('selectedSizeDisplay').textContent = size;
    
    if (isOutOfStock) {
        statusDiv.classList.remove('selected');
        statusDiv.classList.add('out-of-stock');
        statusText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Size <strong>' + size + '</strong> is out of stock';
        
        // Disable form controls
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('addToCartBtn').disabled = true;
        document.getElementById('decreaseBtn').disabled = true;
        document.getElementById('increaseBtn').disabled = true;
    } else {
        statusDiv.classList.remove('out-of-stock');
        statusDiv.classList.add('selected');
        statusText.innerHTML = '<i class="fas fa-check-circle"></i> Size <strong>' + size + '</strong> selected';
        
        // Reset quantity to 1
        document.getElementById('quantity').value = 1;
        document.getElementById('quantity').max = maxQty;

        // Enable form controls
        document.getElementById('submitBtn').disabled = false;
        document.getElementById('addToCartBtn').disabled = false;
        updateButtons();
    }

    // Update summary
    updateTotal();

    // Smooth scroll to form on mobile if not initial load
    if (!isInitial && window.innerWidth <= 1024) {
        document.querySelector('.order-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

        function increaseQuantity() {
            if (!selectedSizeData) return;
            
            const qtyInput = document.getElementById('quantity');
            let currentQty = parseInt(qtyInput.value);
            
            if (currentQty < selectedSizeData.maxQuantity) {
                qtyInput.value = currentQty + 1;
                updateTotal();
                updateButtons();
            }
        }

        function decreaseQuantity() {
            const qtyInput = document.getElementById('quantity');
            let currentQty = parseInt(qtyInput.value);
            
            if (currentQty > 1) {
                qtyInput.value = currentQty - 1;
                updateTotal();
                updateButtons();
            }
        }

        function updateTotal() {
            if (!selectedSizeData) return;

            const quantity = parseInt(document.getElementById('quantity').value);
            const total = selectedSizeData.price * quantity;
            
            document.getElementById('summaryUnitPrice').textContent = 'Rs. ' + selectedSizeData.price.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            document.getElementById('summaryQuantity').textContent = quantity;
            document.getElementById('totalPrice').textContent = 'Rs. ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function updateButtons() {
            if (!selectedSizeData) return;

            const quantity = parseInt(document.getElementById('quantity').value);
            const decreaseBtn = document.getElementById('decreaseBtn');
            const increaseBtn = document.getElementById('increaseBtn');
            
            decreaseBtn.disabled = quantity <= 1;
            increaseBtn.disabled = quantity >= selectedSizeData.maxQuantity;
        }

        // Prevent manual input in quantity field
        document.getElementById('quantity').addEventListener('keydown', function(e) {
            e.preventDefault();
        });

        // Form validation
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            if (!selectedSizeData) {
                e.preventDefault();
                alert('Please select a size before placing your order.');
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

        let isAddingToCart = false; // Add this flag at the top

        document.getElementById('addToCartBtn').addEventListener('click', function() {
    if (isAddingToCart) {
        return;
    }
    
    if (!selectedSizeData) {
        alert('Please select a size before adding to cart.');
        return;
    }
    
    if (selectedSizeData.isOutOfStock) {
        showAlert('This size is out of stock and cannot be added to cart.', 'error');
        return;
    }
    
    isAddingToCart = true; // Set flag
    const quantity = parseInt(document.getElementById('quantity').value);
    const formData = new FormData();
    formData.append('add_to_cart', '1');
    formData.append('selected_size_id', selectedSizeData.id);
    formData.append('quantity', quantity);
    
    // Disable button during request
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showAlert(data.message, 'success');
            
            // Update cart display
            if (typeof window.updateCartDisplay === 'function') {
                window.updateCartDisplay({
                    cart_count: data.cart_count,
                    cart_total: data.cart_total,
                    cart_items: data.cart_items
                });
            }
            
            // Reset button
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                isAddingToCart = false; // Reset flag
            }, 1000);
        } else {
            showAlert(data.message, 'error');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
            isAddingToCart = false; // Reset flag
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to add to cart. Please try again.', 'error');
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
        isAddingToCart = false; // Reset flag
    });
});

function showAlert(message, type) {
    // Remove any existing alerts first
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    

    document.body.insertBefore(alert, document.body.firstChild);
    

    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translate(-50%, -20px)';
        setTimeout(() => alert.remove(), 300);
    }, 3000);
}
    </script>
    <?php include 'Components/Footer.php'; ?>
</body>
</html>