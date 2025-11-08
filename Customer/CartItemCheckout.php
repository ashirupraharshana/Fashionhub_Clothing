<?php
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

// Fetch cart items
$cart_query = "SELECT c.*, p.product_name, p.product_photo, p.price 
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
    $subtotal += $row['total_price'];
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding-top: 70px;
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
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f8f8f8;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #e74c3c;
            color: white;
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
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
        }

        .checkout-header p {
            color: #666;
            font-size: 16px;
        }

        .progress-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
            padding: 0 20px;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .progress-step::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            right: -50%;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }

        .progress-step:last-child::before {
            display: none;
        }

        .progress-step.active::before {
            background: #e74c3c;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            position: relative;
            z-index: 2;
            font-weight: bold;
        }

        .progress-step.active .step-icon {
            background: #e74c3c;
        }

        .step-label {
            font-size: 14px;
            color: #999;
            font-weight: 500;
        }

        .progress-step.active .step-label {
            color: #e74c3c;
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #e74c3c;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f8f8f8;
            border-radius: 8px;
            margin-bottom: 15px;
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
            color: #333;
            line-height: 1.3;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
        }

        .item-quantity {
            color: #999;
        }

        .item-price {
            font-weight: 600;
            color: #e74c3c;
            font-size: 16px;
        }

        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 90px;
        }

        .summary-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 15px;
            color: #666;
            border-bottom: 1px solid #f0f0f0;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            padding-top: 20px;
            margin-top: 10px;
            border-top: 2px solid #e74c3c;
        }

        .summary-row.total .amount {
            color: #e74c3c;
        }

        .place-order-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .place-order-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.4);
        }

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            color: #999;
            font-size: 13px;
        }

        .secure-badge i {
            color: #27ae60;
        }

        .payment-methods {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .payment-method {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method:hover {
            border-color: #e74c3c;
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method input[type="radio"]:checked + label {
            border-color: #e74c3c;
            background: #fff5f5;
        }

        .payment-method label {
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
                order: -1;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .navbar-content {
                padding: 0 20px;
            }
        }

        @media (max-width: 576px) {
            .checkout-header h1 {
                font-size: 28px;
            }

            .progress-bar {
                padding: 0;
            }

            .step-label {
                font-size: 12px;
            }

            .checkout-section {
                padding: 20px;
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

        <div class="progress-bar">
            <div class="progress-step active">
                <div class="step-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="step-label">Cart</div>
            </div>
            <div class="progress-step active">
                <div class="step-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="step-label">Checkout</div>
            </div>
            <div class="progress-step">
                <div class="step-icon"><i class="fas fa-credit-card"></i></div>
                <div class="step-label">Payment</div>
            </div>
            <div class="progress-step">
                <div class="step-icon"><i class="fas fa-check"></i></div>
                <div class="step-label">Complete</div>
            </div>
        </div>

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
                        Shipping Information
                    </h2>
                    
                    <form id="checkoutForm" method="POST" action="process_order.php">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullname">Full Name *</label>
                                <input type="text" id="fullname" name="fullname" 
                                       value="<?php echo htmlspecialchars($user_data['fullname']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user_data['phone']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Shipping Address *</label>
                            <textarea id="address" name="address" rows="3" 
                                      placeholder="Enter your full shipping address" required></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City *</label>
                                <input type="text" id="city" name="city" placeholder="Enter city" required>
                            </div>
                            <div class="form-group">
                                <label for="postal_code">Postal Code *</label>
                                <input type="text" id="postal_code" name="postal_code" placeholder="Enter postal code" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Order Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="3" 
                                      placeholder="Any special instructions for your order"></textarea>
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
                    <span class="amount">Rs. <?php echo number_format($total, 2); ?></span>
                </div>

                <div class="payment-methods">
                    <div class="payment-method">
                        <input type="radio" name="payment" id="cod" value="cod" checked>
                        <label for="cod">
                            <i class="fas fa-money-bill-wave"></i>
                            Cash on Delivery
                        </label>
                    </div>
                    <div class="payment-method">
                        <input type="radio" name="payment" id="card" value="card">
                        <label for="card">
                            <i class="fas fa-credit-card"></i>
                            Card Payment
                        </label>
                    </div>
                </div>

                <button type="submit" form="checkoutForm" class="place-order-btn">
                    <i class="fas fa-check-circle"></i>
                    Place Order
                </button>

                <div class="secure-badge">
                    <i class="fas fa-lock"></i>
                    Secure Checkout - Your information is safe
                </div>
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                document.querySelectorAll('.payment-method').forEach(m => {
                    m.style.borderColor = '#e0e0e0';
                    m.style.background = 'white';
                });
                
                this.style.borderColor = '#e74c3c';
                this.style.background = '#fff5f5';
            });
        });

        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#f44336';
                } else {
                    field.style.borderColor = '#e0e0e0';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    </script>
</body>
</html>