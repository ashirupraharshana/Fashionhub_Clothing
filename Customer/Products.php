<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../db_connect.php';

// ====== AJAX HANDLER - MUST BE FIRST, BEFORE ANY HTML OUTPUT ======
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_add_to_cart'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first!']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $price = $_POST['price'];
    $quantity = 1;

    // ====== CHECK PRODUCT STOCK QUANTITY ======
    $stock_check_sql = "SELECT stock_quantity, product_name FROM products WHERE id = ?";
    $stmt = $conn->prepare($stock_check_sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $stock_result = $stmt->get_result();
    $product = $stock_result->fetch_assoc();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found!']);
        exit;
    }
    
    $available_stock = $product['stock_quantity'];
    $product_name = $product['product_name'];
    $stmt->close();

    // Check if product already exists in cart
    $check_sql = "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Product exists in cart - check if we can add more
        $cart_item = $result->fetch_assoc();
        $current_cart_quantity = $cart_item['quantity'];
        $new_quantity = $current_cart_quantity + 1;
        
        // Validate against available stock
        if ($new_quantity > $available_stock) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot add more! Only {$available_stock} units available (you already have {$current_cart_quantity} in cart)."
            ]);
            exit;
        }
        
        // Update quantity
        $update_sql = "UPDATE cart 
                       SET quantity = quantity + 1
                       WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
    } else {
        // New product - check stock before adding
        if ($quantity > $available_stock) {
            echo json_encode([
                'success' => false, 
                'message' => "Cannot add to cart! Only {$available_stock} units available."
            ]);
            exit;
        }
        
        // Insert new product into cart
        $insert_sql = "INSERT INTO cart (user_id, product_id, quantity, price) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiid", $user_id, $product_id, $quantity, $price);
        $stmt->execute();
    }

    // Get updated cart count
    $cart_count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_count_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count_result = $stmt->get_result();
    $cart_count_row = $cart_count_result->fetch_assoc();
    $cart_count = $cart_count_row['total'] ?? 0;

    // Fetch cart items with calculated totals
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

    $cart_items = [];
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
    }

    // Get cart total - Calculate from price * quantity
    $cart_total_sql = "SELECT SUM(price * quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_total_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_total_result = $stmt->get_result();
    $cart_total_row = $cart_total_result->fetch_assoc();
    $cart_total = $cart_total_row['total'] ?? 0;

    echo json_encode([
        'success' => true, 
        'message' => 'Product added to cart successfully!', 
        'cart_count' => $cart_count,
        'cart_items' => $cart_items,
        'cart_total' => $cart_total
    ]);
    exit;
}

// ====== NOW INCLUDE NAVBAR - AFTER AJAX HANDLER ======
include 'Components/CustomerNavBar.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

// Handle Search and Filter
$search = "";
$category_filter = "";
$gender_filter = "";
$size_filter = "";
$where_conditions = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_conditions[] = "(p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category_filter = intval($_GET['category']);
    $where_conditions[] = "p.category_id = $category_filter";
}

if (isset($_GET['gender']) && $_GET['gender'] !== '') {
    $gender_filter = intval($_GET['gender']);
    $where_conditions[] = "p.gender = $gender_filter";
}

if (isset($_GET['size']) && !empty($_GET['size'])) {
    $size_filter = $conn->real_escape_string($_GET['size']);
    $where_conditions[] = "p.size = '$size_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch products with categories
$sql = "SELECT p.*, c.category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        $where_clause
        ORDER BY p.id DESC";
$result = $conn->query($sql);

// Get categories for filter
$categories = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Products</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .toolbar {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 40px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid #e8e8e8;
        }

        .search-box {
            flex: 1;
            min-width: 280px;
        }

        .search-box form {
            position: relative;
        }

        .search-box input[type="text"] {
            width: 100%;
            padding: 14px 55px 14px 20px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .search-box input[type="text"]:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .search-box button {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            border: none;
            color: white;
            padding: 11px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .search-box button:hover {
            background: #c0392b;
            transform: translateY(-50%) scale(1.02);
        }

        .filter-select {
            padding: 14px 18px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            background: white;
            transition: all 0.3s;
            min-width: 160px;
            font-weight: 500;
            color: #2c3e50;
        }

        .filter-select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .clear-filters {
            padding: 14px 28px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-filters:hover {
            background: #1a252f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 62, 80, 0.3);
        }

        .product-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 35px;
        }

        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            border: 1px solid #f0f0f0;
        }

        .product-card:hover {
            transform: translateY(-12px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .product-image {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 3px solid #e8e8e8;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .product-card:hover .product-image img {
            transform: scale(1.12) rotate(2deg);
        }

        .product-info {
            padding: 22px 20px;
        }

        .product-category {
            font-size: 10px;
            text-transform: uppercase;
            color: #e74c3c;
            letter-spacing: 1.3px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .product-name {
            font-size: 17px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.35;
            height: 46px;
            overflow: hidden;
        }

        .product-attributes {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .attribute-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 11px;
            color: #2c3e50;
            font-weight: 600;
            border: 1px solid #e8e8e8;
        }

        .product-price-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 24px;
            font-weight: 800;
            color: #e74c3c;
        }

        .product-original-price {
            font-size: 15px;
            color: #95a5a6;
            text-decoration: line-through;
            font-weight: 500;
        }

        .product-stock {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            padding: 6px 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e8e8e8;
            width: fit-content;
        }

        .product-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }

        .action-button {
            padding: 11px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .add-cart-btn {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
        }

        .add-cart-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(44, 62, 80, 0.35);
        }

        .order-now-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .order-now-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .action-button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .discount-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: #e74c3c;
            color: white;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            z-index: 10;
        }

        .gender-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            z-index: 10;
        }

        .gender-badge.men {
            background: #3498db;
            color: white;
        }

        .gender-badge.women {
            background: #e91e63;
            color: white;
        }

        .stock-badge {
            position: absolute;
            bottom: 12px;
            left: 12px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            z-index: 10;
        }

        .stock-badge.in-stock {
            background: #2ecc71;
            color: white;
        }

        .stock-badge.low-stock {
            background: #f1c40f;
            color: #2c3e50;
        }

        .stock-badge.out-of-stock {
            background: #95a5a6;
            color: white;
        }

        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 18px 24px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(450px);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 3000;
            min-width: 320px;
            max-width: 450px;
            border: 1px solid #e8e8e8;
            opacity: 0;
            visibility: hidden;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
            visibility: visible;
        }

        .toast.success {
            border-left: 4px solid #27ae60;
        }

        .toast.error {
            border-left: 4px solid #e74c3c;
        }

        .toast i {
            font-size: 24px;
        }

        .toast.success i {
            color: #27ae60;
        }

        .toast.error i {
            color: #e74c3c;
        }

        .toast-message {
            flex: 1;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .toast-close {
            background: transparent;
            border: none;
            color: #95a5a6;
            font-size: 20px;
            cursor: pointer;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 100px 20px;
            grid-column: 1 / -1;
            background: white;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 80px;
            color: #e8e8e8;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        @media (max-width: 768px) {
            .product-gallery {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 25px;
            }
            
            .toolbar {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="toolbar">
            <div class="search-box">
                <form method="GET" action="Products.php">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender_filter); ?>">
                    <input type="hidden" name="size" value="<?php echo htmlspecialchars($size_filter); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <form method="GET" action="Products.php" style="display: contents;">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="category" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="gender" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Genders</option>
                    <option value="0" <?php echo $gender_filter === '0' || $gender_filter === 0 ? 'selected' : ''; ?>>Men</option>
                    <option value="1" <?php echo $gender_filter === '1' || $gender_filter === 1 ? 'selected' : ''; ?>>Women</option>
                </select>

                <select name="size" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Sizes</option>
                    <option value="S" <?php echo $size_filter == 'S' ? 'selected' : ''; ?>>S</option>
                    <option value="M" <?php echo $size_filter == 'M' ? 'selected' : ''; ?>>M</option>
                    <option value="L" <?php echo $size_filter == 'L' ? 'selected' : ''; ?>>L</option>
                    <option value="XL" <?php echo $size_filter == 'XL' ? 'selected' : ''; ?>>XL</option>
                    <option value="XXL" <?php echo $size_filter == 'XXL' ? 'selected' : ''; ?>>XXL</option>
                    <option value="XXXL" <?php echo $size_filter == 'XXXL' ? 'selected' : ''; ?>>XXXL</option>
                </select>
            </form>

            <?php if ($search || $category_filter || $gender_filter !== '' || $size_filter): ?>
                <button class="clear-filters" onclick="window.location.href='Products.php'">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            <?php endif; ?>
        </div>

        <div class="product-gallery">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php 
                    $stock_status = '';
                    $stock_class = '';
                    $is_out_of_stock = false;
                    
                    if ($row['stock_quantity'] == 0) {
                        $stock_status = 'Out of Stock';
                        $stock_class = 'out-of-stock';
                        $is_out_of_stock = true;
                    } elseif ($row['stock_quantity'] <= 10) {
                        $stock_status = 'Low Stock';
                        $stock_class = 'low-stock';
                    } else {
                        $stock_status = 'In Stock';
                        $stock_class = 'in-stock';
                    }
                    
                    $final_price = $row['price'] - ($row['price'] * $row['discount'] / 100);
                    ?>
                    <div class="product-card">
                        <?php if ($row['discount'] > 0): ?>
                            <div class="discount-badge"><?php echo $row['discount']; ?>% OFF</div>
                        <?php endif; ?>
                        
                        <?php if ($row['gender'] !== null): ?>
                            <div class="gender-badge <?php echo $row['gender'] == 0 ? 'men' : 'women'; ?>">
                                <?php echo $row['gender'] == 0 ? 'Men' : 'Women'; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-image">
                            <div class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_status; ?></div>
                            
                            <?php if (!empty($row['product_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $row['product_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>">
                            <?php else: ?>
                                <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($row['category_name']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                            
                            <?php if ($row['gender'] !== null || $row['size'] !== null): ?>
                            <div class="product-attributes">
                                <?php if ($row['gender'] !== null): ?>
                                <span class="attribute-tag">
                                    <i class="fas fa-venus-mars"></i>
                                    <?php echo $row['gender'] == 0 ? 'Men' : 'Women'; ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($row['size'] !== null): ?>
                                <span class="attribute-tag">
                                    <i class="fas fa-ruler"></i>
                                    <?php echo htmlspecialchars($row['size']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="product-price-section">
                                <div class="product-price">Rs. <?php echo number_format($final_price, 2); ?></div>
                                <?php if ($row['discount'] > 0): ?>
                                    <div class="product-original-price">Rs. <?php echo number_format($row['price'], 2); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-stock">
                                <i class="fas fa-cube"></i> <?php echo $row['stock_quantity']; ?> units available
                            </div>
                            
                            <div class="product-actions">
                                <button type="button" 
                                        class="action-button add-cart-btn" 
                                        onclick="<?php echo $is_out_of_stock ? '' : 'addToCart(' . $row['id'] . ', ' . $final_price . ', this)'; ?>"
                                        <?php echo $is_out_of_stock ? 'disabled' : ''; ?>
                                        data-product-id="<?php echo $row['id']; ?>">
                                    <i class="fas fa-<?php echo $is_out_of_stock ? 'ban' : 'shopping-cart'; ?>"></i>
                                    <span><?php echo $is_out_of_stock ? 'Unavailable' : 'Add to Cart'; ?></span>
                                </button>
                                
                                <button type="button" 
                                        class="action-button order-now-btn" 
                                        onclick="<?php echo $is_out_of_stock ? '' : 'orderNow(' . $row['id'] . ', ' . $final_price . ')'; ?>"
                                        <?php echo $is_out_of_stock ? 'disabled' : ''; ?>>
                                    <i class="fas fa-<?php echo $is_out_of_stock ? 'ban' : 'bolt'; ?>"></i>
                                    <span><?php echo $is_out_of_stock ? 'Unavailable' : 'Order Now'; ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Products Found</h3>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <div class="toast-message"></div>
        <button class="toast-close" onclick="hideToast()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <script>
        function addToCart(productId, price, button) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = '../index.php';
                return;
            <?php endif; ?>

            button.disabled = true;
            const originalHTML = button.innerHTML;
            button.innerHTML = '<span class="spinner"></span> Adding...';

            const formData = new FormData();
            formData.append('ajax_add_to_cart', '1');
            formData.append('product_id', productId);
            formData.append('price', price);

            fetch('Products.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    updateCartBadge(data.cart_count);
                    updateCartDropdown(data.cart_items, data.cart_total, data.cart_count);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to add product to cart', 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHTML;
            });
        }

        function updateCartBadge(count) {
            const badge = document.querySelector('.icon-button .badge');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'block' : 'none';
            } else if (count > 0) {
                const cartButton = document.getElementById('cartToggle');
                if (cartButton) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge';
                    newBadge.id = 'cartBadge';
                    newBadge.textContent = count;
                    cartButton.appendChild(newBadge);
                }
            }
        }

        function updateCartDropdown(cartItems, cartTotal, cartCount) {
            if (typeof window.updateCartDisplay === 'function') {
                window.updateCartDisplay({
                    cart_count: cartCount,
                    cart_items: cartItems,
                    cart_total: cartTotal
                });
                return;
            }
            
            const cartBody = document.querySelector('.cart-dropdown-body');
            const cartFooter = document.querySelector('.cart-dropdown-footer');
            const cartCountBadge = document.querySelector('.cart-count-badge');
            const cartBadge = document.getElementById('cartBadge');
            
            if (!cartBody) return;
            
            if (cartCountBadge) {
                cartCountBadge.textContent = cartCount;
                cartCountBadge.style.display = cartCount > 0 ? 'inline-block' : 'none';
            }
            
            if (cartBadge) {
                cartBadge.textContent = cartCount;
                cartBadge.style.display = cartCount > 0 ? 'block' : 'none';
            }
            
            cartBody.innerHTML = '';
            
            if (!cartItems || cartItems.length === 0) {
                cartBody.innerHTML = `
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p><strong>Your cart is empty</strong></p>
                        <p>Add some products to get started!</p>
                    </div>
                `;
                if (cartFooter) cartFooter.style.display = 'none';
            } else {
                cartItems.forEach(item => {
                    const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                    
                    const cartItem = document.createElement('div');
                    cartItem.className = 'cart-item';
                    cartItem.innerHTML = `
                        <img src="data:image/jpeg;base64,${item.product_photo}" 
                             alt="${escapeHtml(item.product_name)}" 
                             class="cart-item-image">
                        <div class="cart-item-details">
                            <div class="cart-item-name">${escapeHtml(item.product_name)}</div>
                            <div class="cart-item-info">
                                <span class="cart-item-quantity">Qty: ${item.quantity}</span>
                                <span class="cart-item-price">Rs. ${itemTotal.toFixed(2)}</span>
                            </div>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_cart_item">
                            <input type="hidden" name="cart_id" value="${item.id}">
                            <button type="submit" class="delete-cart-item" title="Remove item" onclick="return confirm('Remove this item from cart?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    `;
                    cartBody.appendChild(cartItem);
                });
                
                if (cartFooter) {
                    cartFooter.style.display = 'block';
                    const totalAmountSpan = cartFooter.querySelector('.cart-subtotal-amount');
                    if (totalAmountSpan) {
                        totalAmountSpan.textContent = 'Rs. ' + parseFloat(cartTotal).toFixed(2);
                    }
                }
            }
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        let toastTimeout;

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('i');
            const messageEl = toast.querySelector('.toast-message');

            console.log('showToast called:', message, type);

            // Clear any existing timeout
            if (toastTimeout) {
                clearTimeout(toastTimeout);
                toastTimeout = null;
            }

            // Force hide first
            toast.classList.remove('show');
            
            // Force browser reflow to ensure animation restarts
            void toast.offsetWidth;

            // Update content after a brief moment
            setTimeout(() => {
                messageEl.textContent = message;
                toast.className = `toast ${type}`;

                if (type === 'success') {
                    icon.className = 'fas fa-check-circle';
                } else if (type === 'error') {
                    icon.className = 'fas fa-exclamation-circle';
                }

                // Show toast
                toast.classList.add('show');
                console.log('Toast shown with class:', toast.className);

                // Auto-hide after 5 seconds
                toastTimeout = setTimeout(() => {
                    console.log('Auto-hiding toast');
                    hideToast();
                }, 5000);
            }, 50);
        }

        function hideToast() {
            console.log('hideToast called');
            const toast = document.getElementById('toast');
            toast.classList.remove('show');
            
            // Clear timeout when manually closed
            if (toastTimeout) {
                clearTimeout(toastTimeout);
                toastTimeout = null;
            }
        }

        function orderNow(productId, price) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = '/fashionhub/Homepage.php';
                return;
            <?php endif; ?>
            
            window.location.href = 'OrderNow.php?product_id=' + productId;
        }
    </script>
</body>
</html>