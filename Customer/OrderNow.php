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

// Fetch product details
$product_sql = "SELECT p.*, c.category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
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

// Calculate final price with discount
$final_price = $product['price'] - ($product['price'] * $product['discount'] / 100);

// Check if out of stock
$is_out_of_stock = $product['stock_quantity'] == 0;

// Fetch user details
$user_sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $quantity = intval($_POST['quantity']);
    $delivery_address = trim($_POST['delivery_address']);
    $phone = trim($_POST['phone']);
    
    // Validate quantity
    if ($quantity < 1 || $quantity > $product['stock_quantity']) {
        $error = "Invalid quantity selected!";
    } else {
        // Calculate total
        $total_price = $final_price * $quantity;
        
        // Insert order with status 0 (Pending)
        $order_sql = "INSERT INTO orders (user_id, product_id, quantity, price, total_price, delivery_address, phone, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($order_sql);
        $stmt->bind_param("iiiddss", $user_id, $product_id, $quantity, $final_price, $total_price, $delivery_address, $phone);
        
        if ($stmt->execute()) {
            // Update product stock
            $update_stock_sql = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
            $stmt = $conn->prepare($update_stock_sql);
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();
            
            $success = "Order placed successfully! Order ID: #" . $conn->insert_id;
            
            // Redirect to orders page after 2 seconds
            header("refresh:2;url=Products.php");
        } else {
            $error = "Failed to place order. Please try again.";
        }
    }
}

include 'Components/CustomerNavBar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Order - <?php echo htmlspecialchars($product['product_name']); ?></title>
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

        .order-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 42px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 16px;
            color: #7f8c8d;
        }

        .order-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .product-preview {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
        }

        .product-image-wrapper {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .product-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image-wrapper i {
            font-size: 80px;
            color: #cbd5e0;
        }

        .discount-badge-large {
            position: absolute;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }

        .product-details h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .product-category-tag {
            display: inline-block;
            background: #e74c3c;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }

        .product-attributes {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .attribute-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #f8f9fa;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 13px;
            color: #2c3e50;
            font-weight: 600;
        }

        .attribute-badge i {
            color: #e74c3c;
            font-size: 12px;
        }

        .price-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #e8e8e8;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .price-label {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 500;
        }

        .original-price {
            font-size: 18px;
            color: #95a5a6;
            text-decoration: line-through;
        }

        .discount-amount {
            font-size: 16px;
            color: #27ae60;
            font-weight: 600;
        }

        .final-price {
            font-size: 32px;
            color: #e74c3c;
            font-weight: 800;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stock-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            margin-bottom: 20px;
        }

        .stock-info i {
            color: #e74c3c;
            font-size: 14px;
        }

        .stock-info span {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 600;
        }

        .product-description {
            font-size: 14px;
            color: #7f8c8d;
            line-height: 1.6;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #e74c3c;
        }

        .order-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
        }

        .form-header {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e8e8e8;
        }

        .form-header h3 {
            font-size: 24px;
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .form-header p {
            font-size: 14px;
            color: #7f8c8d;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-group label span {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover:not(:disabled) {
            background: #c0392b;
            transform: scale(1.05);
        }

        .qty-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.5;
        }

        .qty-input {
            width: 80px;
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
        }

        .order-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 2px solid #e8e8e8;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #e74c3c;
            padding-top: 12px;
            border-top: 2px solid #e8e8e8;
            margin-top: 12px;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .submit-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .submit-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .alert i {
            font-size: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #2c3e50;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: #1a252f;
            transform: translateX(-5px);
        }

        @media (max-width: 968px) {
            .order-content {
                grid-template-columns: 1fr;
            }

            .product-image-wrapper {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="order-container">
        <a href="Products.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($is_out_of_stock): ?>
            <div class="alert alert-error">
                <i class="fas fa-ban"></i>
                <span>This product is currently out of stock and cannot be ordered.</span>
            </div>
        <?php endif; ?>

        <div class="order-content">
            <!-- Product Preview -->
            <div class="product-preview">
                <div class="product-image-wrapper">
                    <?php if ($product['discount'] > 0): ?>
                        <div class="discount-badge-large"><?php echo $product['discount']; ?>% OFF</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($product['product_photo'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo $product['product_photo']; ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                             onerror="this.style.display='none'; this.parentElement.querySelector('i').style.display='block';">
                        <i class="fas fa-box" style="display: none;"></i>
                    <?php else: ?>
                        <i class="fas fa-box"></i>
                    <?php endif; ?>
                </div>

                <div class="product-details">
                    <span class="product-category-tag"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>

                    <div class="product-attributes">
                        <?php if ($product['gender'] !== null): ?>
                        <div class="attribute-badge">
                            <i class="fas fa-venus-mars"></i>
                            <span><?php echo $product['gender'] == 0 ? 'Men' : 'Women'; ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($product['size']): ?>
                        <div class="attribute-badge">
                            <i class="fas fa-ruler"></i>
                            <span>Size: <?php echo htmlspecialchars($product['size']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="price-section">
                        <?php if ($product['discount'] > 0): ?>
                        <div class="price-row">
                            <span class="price-label">Original Price:</span>
                            <span class="original-price">Rs. <?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span class="price-label">Discount:</span>
                            <span class="discount-amount">- Rs. <?php echo number_format($product['price'] * $product['discount'] / 100, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="price-row">
                            <span class="price-label">Final Price:</span>
                            <span class="final-price">Rs. <?php echo number_format($final_price, 2); ?></span>
                        </div>
                    </div>

                    <div class="stock-info">
                        <i class="fas fa-cube"></i>
                        <span><?php echo $product['stock_quantity']; ?> units available</span>
                    </div>

                    <?php if (!empty($product['description'])): ?>
                    <div class="product-description">
                        <strong>Description:</strong><br>
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Form -->
            <div class="order-form">
                <div class="form-header">
                    <h3>Order Details</h3>
                    <p>Fill in the information below to complete your order</p>
                </div>

                <form method="POST" action="" id="orderForm">
                    <div class="form-group">
                        <label>Quantity <span>*</span></label>
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn" onclick="decreaseQuantity()" id="decreaseBtn">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" class="form-control qty-input" onchange="updateTotal()" readonly>
                            <button type="button" class="qty-btn" onclick="increaseQuantity()" id="increaseBtn" <?php echo $product['stock_quantity'] <= 1 ? 'disabled' : ''; ?>>
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
                            <span>Rs. <?php echo number_format($final_price, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Quantity:</span>
                            <span id="summaryQuantity">1</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="totalPrice">Rs. <?php echo number_format($final_price, 2); ?></span>
                        </div>
                    </div>

                    <button type="submit" name="place_order" class="submit-btn" <?php echo $is_out_of_stock ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-circle"></i>
                        <?php echo $is_out_of_stock ? 'Out of Stock' : 'Place Order'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const maxQuantity = <?php echo $product['stock_quantity']; ?>;
        const unitPrice = <?php echo $final_price; ?>;

        function increaseQuantity() {
            const qtyInput = document.getElementById('quantity');
            let currentQty = parseInt(qtyInput.value);
            
            if (currentQty < maxQuantity) {
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
            const quantity = parseInt(document.getElementById('quantity').value);
            const total = unitPrice * quantity;
            
            document.getElementById('summaryQuantity').textContent = quantity;
            document.getElementById('totalPrice').textContent = 'Rs. ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function updateButtons() {
            const quantity = parseInt(document.getElementById('quantity').value);
            const decreaseBtn = document.getElementById('decreaseBtn');
            const increaseBtn = document.getElementById('increaseBtn');
            
            decreaseBtn.disabled = quantity <= 1;
            increaseBtn.disabled = quantity >= maxQuantity;
        }

        // Prevent manual input
        document.getElementById('quantity').addEventListener('keydown', function(e) {
            e.preventDefault();
        });

        // Initialize buttons state
        updateButtons();
    </script>
      <?php include 'Components/Footer.php'; ?>
</body>
</html>