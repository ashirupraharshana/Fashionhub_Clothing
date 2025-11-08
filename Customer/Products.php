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

    // Check if product already exists in cart
    $check_sql = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update quantity and total price
        $update_sql = "UPDATE cart 
                       SET quantity = quantity + 1, 
                           total_price = price * (quantity + 1)
                       WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
    } else {
        // Insert new product into cart
        $total_price = $quantity * $price;
        $insert_sql = "INSERT INTO cart (user_id, product_id, quantity, price, total_price) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiidd", $user_id, $product_id, $quantity, $price, $total_price);
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

    // Fetch cart items
    $cart_items_sql = "SELECT c.*, p.product_name, p.product_photo 
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

    // Get cart total
    $cart_total_sql = "SELECT SUM(total_price) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_total_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_total_result = $stmt->get_result();
    $cart_total_row = $cart_total_result->fetch_assoc();
    $cart_total = $cart_total_row['total'] ?? 0;

    echo json_encode([
        'success' => true, 
        'message' => 'Product added to cart!', 
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding-top: 70px;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 16px;
            color: #7f8c8d;
        }

        /* Toolbar - Search & Filters */
        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box form {
            position: relative;
        }

        .search-box input[type="text"] {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input[type="text"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #3498db;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .search-box button:hover {
            background: #2980b9;
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            background: white;
            transition: all 0.3s;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .clear-filters {
            padding: 12px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-filters:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        /* Product Gallery */
        .product-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }

        /* Product Badges */
        .discount-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.4);
        }

        .gender-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .gender-badge.men {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .gender-badge.women {
            background: linear-gradient(135deg, #e91e63, #c2185b);
            color: white;
        }

        .stock-badge {
            position: absolute;
            bottom: 15px;
            left: 15px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .stock-badge.in-stock {
            background: rgba(46, 204, 113, 0.9);
            color: white;
        }

        .stock-badge.low-stock {
            background: rgba(241, 196, 15, 0.9);
            color: white;
        }

        .stock-badge.out-of-stock {
            background: rgba(231, 76, 60, 0.9);
            color: white;
        }

        /* Product Image */
        .product-image {
            position: relative;
            width: 100%;
            height: 320px;
            overflow: hidden;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
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
            font-size: 64px;
            color: #bdc3c7;
        }

        /* Product Info */
        .product-info {
            padding: 20px;
        }

        .product-category {
            font-size: 11px;
            text-transform: uppercase;
            color: #3498db;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .product-name {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            line-height: 1.4;
            height: 50px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-description {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.5;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* Product Attributes */
        .product-attributes {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .attribute-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: #ecf0f1;
            border-radius: 6px;
            font-size: 12px;
            color: #34495e;
            font-weight: 500;
        }

        .attribute-tag i {
            font-size: 11px;
            color: #7f8c8d;
        }

        /* Price Section */
        .product-price-section {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .product-price {
            font-size: 26px;
            font-weight: 700;
            color: #27ae60;
        }

        .product-original-price {
            font-size: 16px;
            color: #95a5a6;
            text-decoration: line-through;
        }

        .product-stock {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .product-stock i {
            color: #3498db;
        }

        /* Product Actions */
        .product-actions {
            display: flex;
            gap: 10px;
        }

        .add-cart-btn {
            flex: 1;
            padding: 14px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-cart-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
        }

        .add-cart-btn:disabled {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            cursor: not-allowed;
            transform: none;
        }

        .add-cart-btn.out-of-stock {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            grid-column: 1 / -1;
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 80px;
            color: #dfe6e9;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 16px;
            color: #7f8c8d;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 18px 24px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 3000;
            min-width: 320px;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left: 5px solid #27ae60;
        }

        .toast.error {
            border-left: 5px solid #e74c3c;
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
        }

        .toast-close {
            background: transparent;
            border: none;
            color: #95a5a6;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            transition: color 0.3s;
        }

        .toast-close:hover {
            color: #7f8c8d;
        }

        /* Loading Spinner */
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

        /* Responsive */
        @media (max-width: 768px) {
            .product-gallery {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .toolbar {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .toast {
                right: 15px;
                left: 15px;
                bottom: 15px;
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Toolbar - Search & Filters -->
        <div class="toolbar">
            <div class="search-box">
                <form method="GET" action="Products.php" id="searchForm">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender_filter); ?>">
                    <input type="hidden" name="size" value="<?php echo htmlspecialchars($size_filter); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <form method="GET" action="Products.php" id="filterForm" style="display: contents;">
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

        <!-- Product Gallery -->
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
                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.querySelector('i').style.display='block';">
                                <i class="fas fa-box" style="display: none;"></i>
                            <?php else: ?>
                                <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($row['category_name']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                            <div class="product-description">
                                <?php echo htmlspecialchars($row['description'] ?: 'Discover this amazing product'); ?>
                            </div>
                            
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
                                        class="add-cart-btn <?php echo $is_out_of_stock ? 'out-of-stock' : ''; ?>" 
                                        onclick="<?php echo $is_out_of_stock ? '' : 'addToCart(' . $row['id'] . ', ' . $final_price . ', this)'; ?>"
                                        <?php echo $is_out_of_stock ? 'disabled' : ''; ?>
                                        data-product-id="<?php echo $row['id']; ?>">
                                    <i class="fas fa-<?php echo $is_out_of_stock ? 'ban' : 'shopping-cart'; ?>"></i>
                                    <span><?php echo $is_out_of_stock ? 'Out of Stock' : 'Add to Cart'; ?></span>
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

    <!-- Toast Notification -->
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

            // Disable button and show loading
            button.disabled = true;
            const originalHTML = button.innerHTML;
            button.innerHTML = '<span class="spinner"></span> Adding...';

            // Create FormData
            const formData = new FormData();
            formData.append('ajax_add_to_cart', '1');
            formData.append('product_id', productId);
            formData.append('price', price);

            // Send AJAX request
            fetch('Products.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Update cart badge
                    updateCartBadge(data.cart_count);
                    
                    // Update cart dropdown dynamically
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
                // Re-enable button
                button.disabled = false;
                button.innerHTML = originalHTML;
            });
        }

        function updateCartBadge(count) {
            const badge = document.querySelector('.icon-button .badge');
            if (badge) {
                badge.textContent = count;
            } else if (count > 0) {
                const cartButton = document.getElementById('cartToggle');
                if (cartButton) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge';
                    newBadge.textContent = count;
                    cartButton.appendChild(newBadge);
                }
            }
        }

        function updateCartDropdown(cartItems, cartTotal, cartCount) {
            const cartBody = document.querySelector('.cart-dropdown-body');
            const cartFooter = document.querySelector('.cart-dropdown-footer');
            const cartCountBadge = document.querySelector('.cart-count-badge');
            
            if (!cartBody) return;
            
            // Update cart count badge in dropdown header
            if (cartCountBadge) {
                cartCountBadge.textContent = cartCount;
            }
            
            // Clear existing items
            cartBody.innerHTML = '';
            
            if (cartItems.length === 0) {
                cartBody.innerHTML = `
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart"></i>
                        <p><strong>Your cart is empty</strong></p>
                        <p>Add some products to get started!</p>
                    </div>
                `;
                if (cartFooter) cartFooter.style.display = 'none';
            } else {
                // Add cart items
                cartItems.forEach(item => {
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
                                <span class="cart-item-price">Rs. ${parseFloat(item.total_price).toFixed(2)}</span>
                            </div>
                        </div>
                    `;
                    cartBody.appendChild(cartItem);
                });
                
                // Update footer with total
                if (cartFooter) {
                    cartFooter.style.display = 'block';
                    cartFooter.innerHTML = `
                        <div class="cart-subtotal">
                            <span class="cart-subtotal-label">Total:</span>
                            <span class="cart-subtotal-amount">Rs. ${parseFloat(cartTotal).toFixed(2)}</span>
                        </div>
                    `;
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

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('i');
            const messageEl = toast.querySelector('.toast-message');

            // Set message and type
            messageEl.textContent = message;
            toast.className = `toast ${type}`;

            // Set icon
            if (type === 'success') {
                icon.className = 'fas fa-check-circle';
            } else {
                icon.className = 'fas fa-exclamation-circle';
            }

            // Show toast
            toast.classList.add('show');

            // Hide after 3 seconds
            setTimeout(() => {
                hideToast();
            }, 3000);
        }

        function hideToast() {
            const toast = document.getElementById('toast');
            toast.classList.remove('show');
        }
    </script>
</body>
</html>