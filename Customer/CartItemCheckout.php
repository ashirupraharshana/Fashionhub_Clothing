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


$cart_query = "SELECT 
                c.id, 
                c.product_id, 
                c.size_id,
                c.color_id,
                c.size,
                c.quantity, 
                c.price, 
                c.total_price,
                p.product_name,
                ps.size as size_label,
                ps.discount
               FROM cart c 
               INNER JOIN products p ON c.product_id = p.id 
               LEFT JOIN product_sizes ps ON c.size_id = ps.id
               WHERE c.user_id = ? 
               ORDER BY c.id DESC";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$cart_items = [];
$total = 0;
while ($row = $cart_result->fetch_assoc()) {
    // Fetch photos for this specific size
    $photos_sql = "SELECT photo FROM photos WHERE product_id = ? AND size_id = ? LIMIT 1";
    $photo_stmt = $conn->prepare($photos_sql);
    $photo_stmt->bind_param("ii", $row['product_id'], $row['size_id']);
    $photo_stmt->execute();
    $photos_result = $photo_stmt->get_result();
    
    if ($photo_row = $photos_result->fetch_assoc()) {
        $row['product_photo'] = $photo_row['photo'];
    } else {
        // Fallback: try to get any photo for this product
        $fallback_sql = "SELECT photo FROM photos WHERE product_id = ? LIMIT 1";
        $fallback_stmt = $conn->prepare($fallback_sql);
        $fallback_stmt->bind_param("i", $row['product_id']);
        $fallback_stmt->execute();
        $fallback_result = $fallback_stmt->get_result();
        
        if ($fallback_row = $fallback_result->fetch_assoc()) {
            $row['product_photo'] = $fallback_row['photo'];
        } else {
            $row['product_photo'] = null; // No photo available
        }
        $fallback_stmt->close();
    }
    $photo_stmt->close();
    
    $cart_items[] = $row;
    $total += $row['total_price'];
}
$stmt->close();

// If cart is empty, redirect back
if (empty($cart_items)) {
    header("Location: /fashionhub/Customer/CustomerDashboard.php");
    exit;
}

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
          // First, validate stock availability for all items (size-specific and color-specific)
foreach ($cart_items as $item) {
    $stock_check_sql = "SELECT ps.quantity as size_stock, pc.quantity as color_stock, 
                        p.product_name, ps.size, pc.color_name
                        FROM product_sizes ps
                        INNER JOIN products p ON ps.product_id = p.id
                        LEFT JOIN product_colors pc ON pc.id = ? AND pc.product_id = p.id AND pc.size_id = ps.id
                        WHERE ps.id = ? AND ps.product_id = ?";
    $stmt = $conn->prepare($stock_check_sql);
    $stmt->bind_param("iii", $item['color_id'], $item['size_id'], $item['product_id']);
    $stmt->execute();
    $stock_result = $stmt->get_result();
    $product = $stock_result->fetch_assoc();
    $stmt->close();
    
    if (!$product) {
        throw new Exception("Product or size not found: " . htmlspecialchars($item['product_name']) . " (Size: " . htmlspecialchars($item['size']) . ")");
    }
    
    // Check size stock
    if ($product['size_stock'] < $item['quantity']) {
        throw new Exception("Insufficient stock for " . htmlspecialchars($product['product_name']) . 
                          " (Size: " . htmlspecialchars($product['size']) . 
                          "). Available: " . $product['size_stock'] . 
                          ", Requested: " . $item['quantity']);
    }
    
    // Check color stock
    if ($product['color_stock'] < $item['quantity']) {
        throw new Exception("Insufficient stock for " . htmlspecialchars($product['product_name']) . 
                          " (Size: " . htmlspecialchars($product['size']) . 
                          ", Color: " . htmlspecialchars($product['color_name']) . 
                          "). Available: " . $product['color_stock'] . 
                          ", Requested: " . $item['quantity']);
    }
}
            
// If all stock validations pass, proceed with orders
foreach ($cart_items as $item) {
    // Use the total_price from cart (already calculated)
    $item_total = $item['total_price'];
    
    $order_sql = "INSERT INTO orders (user_id, product_id, size, quantity, price, total_price, delivery_address, phone, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
    $stmt = $conn->prepare($order_sql);
    
    if (!$stmt) {
        throw new Exception("Order prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iisiddss", 
        $user_id, 
        $item['product_id'],
        $item['size'], 
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
                
                
// Update product size stock
    $update_stock_sql = "UPDATE product_sizes 
                        SET quantity = quantity - ? 
                        WHERE id = ? 
                        AND product_id = ?
                        AND quantity >= ?";
    $stmt = $conn->prepare($update_stock_sql);
    
    if (!$stmt) {
        throw new Exception("Stock update prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iiii", $item['quantity'], $item['size_id'], $item['product_id'], $item['quantity']);
    
    if (!$stmt->execute()) {
        throw new Exception("Stock update failed: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Stock update failed - insufficient stock for " . 
                          htmlspecialchars($item['product_name']) . 
                          " (Size: " . htmlspecialchars($item['size']) . ")");
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
            header("refresh:2;url=/fashionhub/Customer/CustomerOrders.php");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Failed to place orders: " . $e->getMessage();
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
            background: linear-gradient(135deg, #fef5f5 0%, #fdeef0 100%);
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            color: #e74c3c;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo i {
            font-size: 32px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 26px;
            background: #2c3e50;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            transition: all 0.3s;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #1a252f;
            transform: translateX(-5px);
            box-shadow: 0 6px 20px rgba(44, 62, 80, 0.3);
        }

        .checkout-container {
            max-width: 1300px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 35px;
        }

        .checkout-section {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(231, 76, 60, 0.08);
        }

        .section-title {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
        }

        .section-title i {
            color: #e74c3c;
            font-size: 24px;
        }

        .cart-item {
            display: flex;
            gap: 18px;
            padding: 18px;
            background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f5 100%);
            border-radius: 12px;
            margin-bottom: 18px;
            border: 1px solid #e8e8e8;
            transition: all 0.3s;
        }

        .cart-item:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }

        .item-image {
            width: 90px;
            height: 90px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .item-name {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.4;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #7f8c8d;
        }

        .item-quantity {
            color: #7f8c8d;
            font-weight: 600;
        }

        .item-price {
            font-weight: 800;
            color: #e74c3c;
            font-size: 18px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: #2c3e50;
            font-size: 15px;
        }

        .form-group label span {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 15px;
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
            min-height: 110px;
        }

        .order-summary {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(231, 76, 60, 0.08);
            position: sticky;
            top: 90px;
        }

        .summary-title {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
        }

        .summary-title i {
            color: #e74c3c;
            font-size: 24px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            font-size: 15px;
            color: #7f8c8d;
            font-weight: 600;
        }

        .summary-row.total {
            font-size: 26px;
            font-weight: 900;
            color: #e74c3c;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 3px solid #e8e8e8;
            letter-spacing: -0.5px;
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
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.35);
            margin-top: 25px;
        }

        .submit-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(231, 76, 60, 0.45);
        }

        .submit-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .items-count {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.1) 100%);
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700;
            color: #e74c3c;
            border: 2px solid rgba(231, 76, 60, 0.2);
        }

        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
                order: -1;
            }
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 100px;
            right: -400px;
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 350px;
            max-width: 500px;
            transition: right 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .toast.show {
            right: 20px;
        }

        .toast.success {
            border-left: 5px solid #27ae60;
        }

        .toast.error {
            border-left: 5px solid #e74c3c;
        }

        .toast-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .toast.success .toast-icon {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }

        .toast.error .toast-icon {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
            color: #2c3e50;
        }

        .toast-message {
            font-size: 14px;
            color: #7f8c8d;
        }

        .toast-close {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #7f8c8d;
            transition: all 0.3s;
            padding: 5px;
        }

        .toast-close:hover {
            color: #2c3e50;
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
            .toast {
                right: -100%;
                left: 10px;
                min-width: auto;
                max-width: calc(100% - 20px);
            }

            .toast.show {
                right: auto;
                left: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/CustomerNavBar.php'; ?>
    <div class="checkout-container">

       <?php if (isset($success)): ?>
            <div class="toast success">
                <div class="toast-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">Success!</div>
                    <div class="toast-message"><?php echo $success; ?></div>
                </div>
                <button class="toast-close">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="toast error">
                <div class="toast-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">Error!</div>
                    <div class="toast-message"><?php echo $error; ?></div>
                </div>
                <button class="toast-close">&times;</button>
            </div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div>
                <div class="checkout-section">
                    <h2 class="section-title">
                        <i class="fas fa-shopping-bag"></i>
                        Order Items
                    </h2>
                    
                    <div class="items-count">
                        <?php echo count($cart_items); ?> item<?php echo count($cart_items) > 1 ? 's' : ''; ?> in your cart
                    </div>
                    
                    <?php foreach ($cart_items as $item): ?>
    <div class="cart-item">
        <?php if (!empty($item['product_photo'])): ?>
            <img src="data:image/jpeg;base64,<?php echo $item['product_photo']; ?>" 
                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                 class="item-image">
        <?php else: ?>
            <div class="item-image" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);">
                <i class="fas fa-tshirt" style="font-size: 40px; color: #cbd5e0;"></i>
            </div>
        <?php endif; ?>
        <div class="item-details">
            <div class="item-name">
                <?php echo htmlspecialchars($item['product_name']); ?>
                <?php if (!empty($item['size'])): ?>
                    <span style="display: inline-block; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 800; margin-left: 8px;">
                        <?php echo htmlspecialchars($item['size']); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($item['discount']) && $item['discount'] > 0): ?>
                    <span style="display: inline-block; background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; margin-left: 5px;">
                        <?php echo $item['discount']; ?>% OFF
                    </span>
                <?php endif; ?>
            </div>
            <div class="item-meta">
                <span class="item-quantity">Qty: <?php echo $item['quantity']; ?> × Rs. <?php echo number_format($item['price'], 2); ?></span>
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
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" 
                                   placeholder="Enter your phone number" required>
                        </div>

                        <div class="form-group">
                            <label for="delivery_address">Delivery Address <span>*</span></label>
                            <textarea name="delivery_address" id="delivery_address" class="form-control" 
                                      placeholder="Enter your complete delivery address with street, city, and postal code" required><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                        </div>
                    </form>
                </div>
            </div>

            <div class="order-summary">
                <h2 class="summary-title">
                    <i class="fas fa-receipt"></i>
                    Order Summary
                </h2>

                <div class="summary-row total">
                    <span>Total Amount</span>
                    <span>Rs. <?php echo number_format($total, 2); ?></span>
                </div>

                <button type="submit" name="place_order" form="checkoutForm" class="submit-btn">
                    <i class="fas fa-check-circle"></i>
                    Place Order - Rs. <?php echo number_format($total, 2); ?>
                </button>

                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; text-align: center; font-size: 13px; color: #7f8c8d;">
                    <i class="fas fa-shield-alt"></i> Secure checkout
                </div>
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

            // Optional: Phone validation
            if (phone.length < 10) {
                e.preventDefault();
                alert('Please enter a valid phone number');
                return false;
            }
        });

        // Toast Notification Handler
        const toast = document.querySelector('.toast');
        if (toast) {
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            const closeBtn = toast.querySelector('.toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                });
            }

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentElement) toast.remove();
                }, 500);
            }, 5000);
        }
    </script>
    <?php include 'Components/Footer.php'; ?>
</body>
</html>