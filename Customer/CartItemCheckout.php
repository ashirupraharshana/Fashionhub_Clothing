<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Customer/CustomerDashboard.php");
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "fashionhubdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// Fetch user information
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$stmt->close();

// FIXED: Fetch cart items with calculated total_price
$cart_query = "SELECT c.id, c.product_id, c.quantity, c.price, 
               (c.price * c.quantity) as total_price,
               p.product_name, p.product_photo 
               FROM cart c 
               JOIN products p ON c.product_id = p.id 
               WHERE c.user_id = ? 
               ORDER BY c.id DESC";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$cart_items = [];
$subtotal = 0;
while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
    $subtotal += $row['total_price'];  // Now this exists because we calculated it in the query
}
$stmt->close();

// If cart is empty, redirect back
if (empty($cart_items)) {
    header("Location: /fashionhub/Customer/CustomerDashboard.php");
    exit;
}

// Calculate totals
$shipping = 250.00; // Fixed shipping cost
$tax_rate = 0.08; // 8% tax
$tax = $subtotal * $tax_rate;
$total = $subtotal + $shipping + $tax;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $phone = trim($_POST['phone']);
    $delivery_address = trim($_POST['delivery_address']);
    
    // Validate inputs
    if (empty($phone) || empty($delivery_address)) {
        $error = "Please fill in all required fields!";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // First, validate stock availability for all items
            foreach ($cart_items as $item) {
                $stock_check_sql = "SELECT stock_quantity, product_name FROM products WHERE id = ?";
                $stmt = $conn->prepare($stock_check_sql);
                $stmt->bind_param("i", $item['product_id']);
                $stmt->execute();
                $stock_result = $stmt->get_result();
                $product = $stock_result->fetch_assoc();
                $stmt->close();
                
                if (!$product) {
                    throw new Exception("Product not found: " . htmlspecialchars($item['product_name']));
                }
                
                if ($product['stock_quantity'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for " . htmlspecialchars($product['product_name']) . 
                                      ". Available: " . $product['stock_quantity'] . 
                                      ", Requested: " . $item['quantity']);
                }
                
                if ($product['stock_quantity'] - $item['quantity'] < 0) {
                    throw new Exception("Cannot process order. Stock would go below 0 for " . 
                                      htmlspecialchars($product['product_name']));
                }
            }
            
            // If all stock validations pass, proceed with orders
            foreach ($cart_items as $item) {
                // Calculate total_price for this order (redundant but explicit)
                $item_total = $item['price'] * $item['quantity'];
                
                $order_sql = "INSERT INTO orders (user_id, product_id, quantity, price, total_price, delivery_address, phone, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
                $stmt = $conn->prepare($order_sql);
                
                if (!$stmt) {
                    throw new Exception("Order prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("iiiddss", 
                    $user_id, 
                    $item['product_id'], 
                    $item['quantity'], 
                    $item['price'], 
                    $item_total,
                    $delivery_address, 
                    $phone
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Order insert failed: " . $stmt->error);
                }
                $stmt->close();
                
                // Update product stock - with additional safety check
                $update_stock_sql = "UPDATE products 
                                    SET stock_quantity = stock_quantity - ? 
                                    WHERE id = ? 
                                    AND stock_quantity >= ?";
                $stmt = $conn->prepare($update_stock_sql);
                
                if (!$stmt) {
                    throw new Exception("Stock update prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("iii", $item['quantity'], $item['product_id'], $item['quantity']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Stock update failed: " . $stmt->error);
                }
                
                // Check if the update actually affected any rows
                if ($stmt->affected_rows === 0) {
                    throw new Exception("Stock update failed - insufficient stock for " . 
                                      htmlspecialchars($item['product_name']));
                }
                
                $stmt->close();
            }
            
            // Clear user's cart
            $clear_cart_sql = "DELETE FROM cart WHERE user_id = ?";
            $stmt = $conn->prepare($clear_cart_sql);
            
            if (!$stmt) {
                throw new Exception("Cart clear prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Cart clear failed: " . $stmt->error);
            }
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success = "Orders placed successfully! Redirecting...";
            
            // Redirect to orders page after 2 seconds
            header("refresh:2;url=/fashionhub/Customer/CustomerDashboard.php");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Failed to place orders: " . $e->getMessage();
            // Log the error for debugging
            error_log("Checkout Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FashionHub</title>
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

        .navbar {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 70px;
        }

        .navbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 40px;
            height: 100%;
        }

        .logo {
            font-size: 26px;
            font-weight: bold;
            color: #e74c3c;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            font-size: 30px;
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
        }

        .back-btn:hover {
            background: #1a252f;
            transform: translateX(-5px);
        }

        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .checkout-header h1 {
            font-size: 42px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .checkout-header p {
            font-size: 16px;
            color: #7f8c8d;
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

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .checkout-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
        }

        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .section-title i {
            color: #e74c3c;
        }

        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e8e8e8;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .item-name {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            line-height: 1.3;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #7f8c8d;
        }

        .item-quantity {
            color: #7f8c8d;
        }

        .item-price {
            font-weight: 600;
            color: #e74c3c;
            font-size: 16px;
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

        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
            position: sticky;
            top: 90px;
        }

        .summary-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 15px;
            color: #7f8c8d;
            border-bottom: 1px solid #e8e8e8;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #e74c3c;
            padding-top: 12px;
            margin-top: 12px;
            border-top: 2px solid #e8e8e8;
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
            margin-top: 20px;
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

        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
                order: -1;
            }

            .navbar-content {
                padding: 0 20px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="/fashionhub/Customer/CustomerDashboard.php" class="logo">
                <i class="fas fa-shopping-bag"></i>
                <span>FashionHub</span>
            </a>
            <a href="/fashionhub/Customer/CustomerDashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Continue Shopping
            </a>
        </div>
    </nav>

    <div class="checkout-container">
        <div class="checkout-header">
            <h1>Checkout</h1>
            <p>Review your order and complete your purchase</p>
        </div>

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

        <div class="checkout-grid">
            <div>
                <div class="checkout-section">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-bag"></i>
                        Order Items (<?php echo count($cart_items); ?>)
                    </h2>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <img src="data:image/jpeg;base64,<?php echo $item['product_photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                 class="item-image">
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="item-meta">
                                    <span class="item-quantity">Quantity: <?php echo $item['quantity']; ?></span>
                                    <span class="item-price">Rs. <?php echo number_format($item['total_price'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="checkout-section" style="margin-top: 30px;">
                    <h2 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Delivery Information
                    </h2>
                    
                    <form id="checkoutForm" method="POST" action="">
                        <div class="form-group">
                            <label for="phone">Phone Number <span>*</span></label>
                            <input type="tel" name="phone" id="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="delivery_address">Delivery Address <span>*</span></label>
                            <textarea name="delivery_address" id="delivery_address" class="form-control" 
                                      placeholder="Enter your full delivery address" required><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <div class="order-summary">
                <h2 class="summary-title">
                    <i class="fas fa-receipt"></i>
                    Order Summary
                </h2>

                <div class="summary-row">
                    <span>Subtotal (<?php echo count($cart_items); ?> items)</span>
                    <span>Rs. <?php echo number_format($subtotal, 2); ?></span>
                </div>

                <div class="summary-row">
                    <span>Shipping Fee</span>
                    <span>Rs. <?php echo number_format($shipping, 2); ?></span>
                </div>

                <div class="summary-row">
                    <span>Tax (8%)</span>
                    <span>Rs. <?php echo number_format($tax, 2); ?></span>
                </div>

                <div class="summary-row total">
                    <span>Total</span>
                    <span>Rs. <?php echo number_format($total, 2); ?></span>
                </div>

                <button type="submit" name="place_order" form="checkoutForm" class="submit-btn">
                    <i class="fas fa-check-circle"></i>
                    Place Order
                </button>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value.trim();
            const address = document.getElementById('delivery_address').value.trim();

            if (!phone || !address) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
        });
    </script>
</body>
</html>