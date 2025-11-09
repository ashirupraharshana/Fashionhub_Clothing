<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$servername = "localhost";
$username = "root";
$password = "";
$database = "fashionhubdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$messageType = ""; 

// ====== DELETE CART ITEM ======
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_cart_item') {
    if (isset($_SESSION['user_id']) && isset($_POST['cart_id'])) {
        $cart_id = intval($_POST['cart_id']);
        $user_id = $_SESSION['user_id'];
        
        $deleteQuery = "DELETE FROM cart WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("ii", $cart_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "Item removed from cart successfully!";
            $messageType = "success";
        } else {
            $message = "Error removing item from cart.";
            $messageType = "error";
        }
        $stmt->close();
    }
}

// ====== SIGNUP FORM ======
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'signup') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    if (empty($fullname) || empty($email) || empty($password)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
        $messageType = "error";
    } else {
        $checkQuery = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "This email is already registered. Please login.";
            $messageType = "error";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertQuery = "INSERT INTO users (fullname, email, phone, password) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);
            if ($stmt->execute()) {
                $message = "Account created successfully! You can now log in.";
                $messageType = "success";
            } else {
                $message = "Error creating account: " . $conn->error;
                $messageType = "error";
            }
        }
        $stmt->close();
    }
}

// ====== LOGIN FORM ======
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'login') {
    $email = trim($_POST['login_email']);
    $password = trim($_POST['login_password']);

    if (empty($email) || empty($password)) {
        $message = "Please enter both email and password.";
        $messageType = "error";
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['userrole'] = $user['userrole'];

                if ($user['userrole'] == 1) {
                    header("Location: /fashionhub/Admin/AdminDashboard.php");
                    exit;
                } else {
                    header("Location: /fashionhub/Customer/CustomerDashboard.php");
                    exit;
                }
            } else {
                $message = "Incorrect password.";
                $messageType = "error";
            }
        } else {
            $message = "No account found with that email.";
            $messageType = "error";
        }
        $stmt->close();
    }
}

// ====== FETCH CART DATA ======
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get cart count
    $cart_count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_count_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count_result = $stmt->get_result();
    $cart_count_row = $cart_count_result->fetch_assoc();
    $cart_count = $cart_count_row['total'] ?? 0;
    $stmt->close();
    
    // Get cart total - FIXED: Calculate from (price * quantity) for each item
    $cart_total_sql = "SELECT SUM(price * quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_total_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_total_result = $stmt->get_result();
    $cart_total_row = $cart_total_result->fetch_assoc();
    $cart_total = $cart_total_row['total'] ?? 0;
    $stmt->close();
    
    // Store cart items in an array
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
    
    $cart_items_array = [];
    while ($row = $result->fetch_assoc()) {
        $cart_items_array[] = $row;
    }
    $stmt->close();
} else {
    $cart_count = 0;
    $cart_total = 0;
    $cart_items_array = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub Clothing Store</title>
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

        .alert {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 500;
            z-index: 9999;
            animation: slideDown 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            text-align: center;
        }

        .alert.success {
            background: #4caf50;
            color: white;
        }

        .alert.error {
            background: #f44336;
            color: white;
        }

        @keyframes slideDown {
            from {
                transform: translate(-50%, -20px);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
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

        .nav-links {
            display: flex;
            gap: 35px;
            list-style: none;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.3s;
            position: relative;
            padding: 5px 0;
        }

        .nav-links a:hover {
            color: #e74c3c;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: #e74c3c;
            transition: width 0.3s;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .icon-button {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #333;
            transition: all 0.3s;
            position: relative;
            padding: 8px;
        }

        .icon-button:hover {
            color: #e74c3c;
            transform: translateY(-2px);
        }

        .icon-button .badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 10px;
            font-weight: bold;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            flex-direction: column;
            gap: 5px;
        }

        .menu-toggle span {
            display: block;
            width: 25px;
            height: 3px;
            background: #333;
            transition: 0.3s;
            border-radius: 2px;
        }

        .menu-toggle.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-toggle.active span:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }

        .modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            padding: 0;
            position: relative;
            animation: slideUp 0.3s;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        @keyframes slideUp {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px 12px 0 0;
            position: relative;
        }

        .modal-header h2 {
            font-size: 24px;
            margin: 0;
        }

        .modal-header p {
            font-size: 14px;
            margin-top: 5px;
            opacity: 0.9;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 25px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 35px 30px;
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

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .form-group input::placeholder {
            color: #999;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 14px;
            color: #666;
        }

        .form-footer a {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .sidebar {
            position: fixed;
            top: 70px;
            left: -320px;
            width: 300px;
            height: calc(100vh - 70px);
            background: #fff;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            transition: left 0.3s ease;
            overflow-y: auto;
            z-index: 999;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 30px 25px;
            border-bottom: 2px solid #f0f0f0;
            background: linear-gradient(135deg, #ff4e50 0%, #e74c3c 100%);
            color: white;
        }

        .sidebar-header h3 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .sidebar-menu {
            list-style: none;
            padding: 15px 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 16px 25px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            font-weight: 500;
            gap: 15px;
        }

        .sidebar-menu a:hover {
            background: #f8f8f8;
            color: #e74c3c;
            padding-left: 35px;
        }

        .sidebar-menu a i {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }

        .sidebar-section {
            padding: 25px;
            border-top: 2px solid #f0f0f0;
        }

        .sidebar-section h4 {
            color: #333;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 15px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .newsletter-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .newsletter-form input {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .newsletter-form input:focus {
            outline: none;
            border-color: #e74c3c;
        }

        .newsletter-form button {
            padding: 12px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .newsletter-form button:hover {
            background: #c0392b;
        }

        .social-links {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }

        .social-links a {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f8f8;
            border-radius: 50%;
            text-decoration: none;
            font-size: 18px;
            color: #333;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-3px);
        }

        .overlay {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 998;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .cart-dropdown-container {
            position: relative;
        }

        .cart-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 15px;
            width: 420px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            display: none !important;
            flex-direction: column;
            z-index: 9999;
            max-height: 600px;
        }

        .cart-dropdown.show {
            display: flex !important;
            animation: dropdownSlide 0.3s ease;
        }

        @keyframes dropdownSlide {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cart-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 20px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-bottom: 8px solid white;
        }

        .cart-dropdown-header {
            padding: 20px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .cart-dropdown-header h3 {
            font-size: 18px;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cart-count-badge {
            background: #e74c3c;
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .close-cart-dropdown {
            background: transparent;
            border: none;
            font-size: 20px;
            color: #999;
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s;
        }

        .close-cart-dropdown:hover {
            color: #333;
        }

        .cart-dropdown-body {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            max-height: 400px;
        }

        .cart-dropdown-body::-webkit-scrollbar {
            width: 6px;
        }

        .cart-dropdown-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .cart-dropdown-body::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 3px;
        }

        .cart-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 10px;
            position: relative;
        }

        .cart-item:hover {
            background: #f8f8f8;
        }

        .cart-item-image {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .cart-item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .cart-item-name {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .cart-item-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #666;
        }

        .cart-item-quantity {
            color: #999;
        }

        .cart-item-price {
            font-weight: 600;
            color: #e74c3c;
        }

        .delete-cart-item {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ff4757;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.3s;
            opacity: 0;
        }

        .cart-item:hover .delete-cart-item {
            opacity: 1;
        }

        .delete-cart-item:hover {
            background: #ee5a6f;
            transform: scale(1.1);
        }

        .cart-empty {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .cart-empty i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }

        .cart-empty p {
            margin: 10px 0;
            font-size: 14px;
        }

        .cart-dropdown-footer {
            padding: 20px;
            border-top: 2px solid #f0f0f0;
            background: #fafafa;
            border-radius: 0 0 12px 12px;
            flex-shrink: 0;
        }

        .cart-subtotal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .cart-subtotal-label {
            color: #666;
            font-weight: 600;
        }

        .cart-subtotal-amount {
            font-size: 22px;
            font-weight: 700;
            color: #e74c3c;
        }

        .checkout-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        @media (max-width: 968px) {
            .nav-links {
                display: none;
            }

            .menu-toggle {
                display: flex;
            }

            .navbar-content {
                padding: 0 20px;
            }

            .nav-actions {
                gap: 15px;
            }
        }

        @media (max-width: 576px) {
            .logo span {
                display: none;
            }

            .modal-content {
                width: 95%;
                margin: 0 10px;
            }

            .modal-body {
                padding: 25px 20px;
            }

            .cart-dropdown {
                width: calc(100vw - 30px);
                right: -80px;
            }
            
            .cart-dropdown::before {
                right: 90px;
            }
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $messageType; ?>" id="alertMessage">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <nav class="navbar">
        <div class="navbar-content">
            <button class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a href="#" class="logo">
                <i class="fas fa-shopping-bag"></i>
                <span>FashionHub</span>
            </a>

            <ul class="nav-links">
                <li><a href="/fashionhub/Customer/CustomerDashboard.php">Home</a></li>
                <li><a href="/fashionhub/Customer/Products.php">Products</a></li>
                <li><a href="/fashionhub/Customer/CustomerOrders.php">My Orders</a></li>
                <li><a href="/fashionhub/Customer/Feedback.php">Feedbacks</a></li>
                <li><a href="/fashionhub/Customer/AboutUs.php">About</a></li>
                <li><a href="/fashionhub/Customer/ContactUs.php">Contact</a></li>
                <li><button class="icon-button" id="profileButton" title="Profile">
  <i class="fas fa-user-circle"></i>
</button></li>
            </ul>

            <div class="nav-actions">
                <div class="cart-dropdown-container">
                    <button class="icon-button" id="cartToggle" title="Shopping Cart">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if (isset($_SESSION['user_id']) && $cart_count > 0): ?>
                            <span class="badge" id="cartBadge"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="cart-dropdown" id="cartDropdown">
                        <div class="cart-dropdown-header">
                            <h3>
                                <i class="fas fa-shopping-bag"></i>
                                Shopping Cart
                                <?php if (isset($_SESSION['user_id']) && $cart_count > 0): ?>
                                    <span class="cart-count-badge" id="cartCountBadge"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </h3>
                            <button class="close-cart-dropdown" id="closeCartDropdown">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="cart-dropdown-body" id="cartDropdownBody">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <?php if (!empty($cart_items_array)): ?>
                                    <?php foreach($cart_items_array as $item): ?>
                                        <div class="cart-item">
                                            <img src="data:image/jpeg;base64,<?php echo $item['product_photo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                 class="cart-item-image">
                                            <div class="cart-item-details">
                                                <div class="cart-item-name">
                                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                                </div>
                                                <div class="cart-item-info">
                                                    <span class="cart-item-quantity">Qty: <?php echo $item['quantity']; ?></span>
                                                    <span class="cart-item-price">Rs. <?php echo number_format($item['item_total'], 2); ?></span>
                                                </div>
                                            </div>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_cart_item">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="delete-cart-item" title="Remove item" onclick="return confirm('Remove this item from cart?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="cart-empty">
                                        <i class="fas fa-shopping-cart"></i>
                                        <p><strong>Your cart is empty</strong></p>
                                        <p>Add some products to get started!</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="cart-empty">
                                    <i class="fas fa-sign-in-alt"></i>
                                    <p><strong>Please login to view cart</strong></p>
                                    <p>Login to add and view your cart items</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (isset($_SESSION['user_id']) && $cart_count > 0): ?>
                            <div class="cart-dropdown-footer" id="cartDropdownFooter">
                                <div class="cart-subtotal">
                                    <span class="cart-subtotal-label">Total:</span>
                                    <span class="cart-subtotal-amount" id="cartTotalAmount">Rs. <?php echo number_format($cart_total, 2); ?></span>
                                </div>
                                <form method="GET" action="/fashionhub/Customer/CartItemCheckout.php">
                                    <button type="submit" class="checkout-btn">
                                        <i class="fas fa-credit-card"></i>
                                        Proceed to Checkout
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

<?php if (isset($_SESSION['user_id'])): ?>
<button class="icon-button" id="logoutButton" title="Logout">
    <i class="fas fa-sign-out-alt"></i>
</button>

                <?php else: ?>
                    <button class="icon-button" id="loginBtn" title="Login">
                        <i class="fas fa-sign-in-alt"></i>
                    </button>
                    <button class="icon-button" id="signupBtn" title="Sign Up">
                        <i class="fas fa-user-plus"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Welcome Back!</h2>
                <p>Login to your account</p>
                <button class="close-modal" id="closeLogin">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input type="email" id="loginEmail" name="login_email" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input type="password" id="loginPassword" name="login_password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="submit-btn">Login</button>
                </form>
                <div class="form-footer">
                    Don't have an account? <a id="switchToSignup">Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="signupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Account</h2>
                <p>Join FashionHub today</p>
                <button class="close-modal" id="closeSignup">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="signup">
                    <div class="form-group">
                        <label for="signupName">Full Name</label>
                        <input type="text" id="signupName" name="fullname" placeholder="Enter your full name" required>
                    </div>
                    <div class="form-group">
                        <label for="signupEmail">Email Address</label>
                        <input type="email" id="signupEmail" name="email" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group">
                        <label for="signupPhone">Phone Number</label>
                        <input type="tel" id="signupPhone" name="phone" placeholder="Enter your phone number" required>
                    </div>
                    <div class="form-group">
                        <label for="signupPassword">Password</label>
                        <input type="password" id="signupPassword" name="password" placeholder="Create a password" required>
                    </div>
                    <button type="submit" class="submit-btn">Sign Up</button>
                </form>
                <div class="form-footer">
                    Already have an account? <a id="switchToLogin">Login</a>
                </div>
            </div>
        </div>
    </div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Welcome to FashionHub</h3>
            <p>Discover your style</p>
        </div>

        <ul class="sidebar-menu">
             <li><a href="/fashionhub/Customer/CustomerDashboard.php">Home</a></li>
                <li><a href="/fashionhub/Customer/Products.php">Products</a></li>
                <li><a href="/fashionhub/Customer/CustomerOrders.php">My Orders</a></li>
                <li><a href="/fashionhub/Customer/Feedback.php">Feedbacks</a></li>
                <li><a href="/fashionhub/Customer/AboutUs.php">About</a></li>
                <li><a href="/fashionhub/Customer/ContactUs.php">Contact</a></li>
                <li><a href="/fashionhub/Customer/Profile.php">
    <i class="fas fa-user-cog"></i>Profile Settings
</a></li>
                <li><a href="/fashionhub/logout.php" onclick="return confirm('Are you sure you want to logout?');">
    <i class="fas fa-sign-out-alt"></i>LogOut
</a></li>
        </ul>
        <div class="sidebar-section">
            <h4>Follow Us</h4>
            <div class="social-links">
                <a href="#facebook" title="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#instagram" title="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#twitter" title="Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#pinterest" title="Pinterest">
                    <i class="fab fa-pinterest-p"></i>
                </a>
            </div>
        </div>
    </aside>

    <div class="overlay" id="overlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded - Initializing...');
            
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const loginBtn = document.getElementById('loginBtn');
            const signupBtn = document.getElementById('signupBtn');
            const loginModal = document.getElementById('loginModal');
            const signupModal = document.getElementById('signupModal');
            const closeLogin = document.getElementById('closeLogin');
            const closeSignup = document.getElementById('closeSignup');
            const switchToSignup = document.getElementById('switchToSignup');
            const switchToLogin = document.getElementById('switchToLogin');
            const cartToggle = document.getElementById('cartToggle');
            const cartDropdown = document.getElementById('cartDropdown');
            const closeCartDropdown = document.getElementById('closeCartDropdown');

            console.log('Cart Toggle:', cartToggle);
            console.log('Cart Dropdown:', cartDropdown);

            const alertMessage = document.getElementById('alertMessage');
            if (alertMessage) {
                setTimeout(() => {
                    alertMessage.style.animation = 'slideUp 0.3s ease';
                    setTimeout(() => alertMessage.remove(), 300);
                }, 5000);
            }

            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    this.classList.toggle('active');
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                });
            }

            if (overlay) {
                overlay.addEventListener('click', function() {
                    menuToggle.classList.remove('active');
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    if (cartDropdown) cartDropdown.classList.remove('show');
                });
            }

            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.addEventListener('click', function() {
                    menuToggle.classList.remove('active');
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            });

            if (loginBtn) {
                loginBtn.addEventListener('click', () => loginModal.classList.add('active'));
            }

            if (signupBtn) {
                signupBtn.addEventListener('click', () => signupModal.classList.add('active'));
            }

            if (closeLogin) {
                closeLogin.addEventListener('click', () => loginModal.classList.remove('active'));
            }

            if (closeSignup) {
                closeSignup.addEventListener('click', () => signupModal.classList.remove('active'));
            }

            if (switchToSignup) {
                switchToSignup.addEventListener('click', function() {
                    loginModal.classList.remove('active');
                    signupModal.classList.add('active');
                });
            }

            if (switchToLogin) {
                switchToLogin.addEventListener('click', function() {
                    signupModal.classList.remove('active');
                    loginModal.classList.add('active');
                });
            }

            if (loginModal) {
                loginModal.addEventListener('click', e => {
                    if (e.target === loginModal) loginModal.classList.remove('active');
                });
            }

            if (signupModal) {
                signupModal.addEventListener('click', e => {
                    if (e.target === signupModal) signupModal.classList.remove('active');
                });
            }

            const newsletterForm = document.querySelector('.newsletter-form');
            if (newsletterForm) {
                newsletterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    alert('Thank you for subscribing! We will send updates to: ' + this.querySelector('input').value);
                    this.reset();
                });
            }

            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (sidebar && sidebar.classList.contains('active')) {
                        menuToggle.classList.remove('active');
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                    if (loginModal && loginModal.classList.contains('active')) {
                        loginModal.classList.remove('active');
                    }
                    if (signupModal && signupModal.classList.contains('active')) {
                        signupModal.classList.remove('active');
                    }
                    if (cartDropdown && cartDropdown.classList.contains('show')) {
                        cartDropdown.classList.remove('show');
                    }
                }
            });

            // CART DROPDOWN
            if (cartToggle && cartDropdown) {
                cartToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const isShowing = cartDropdown.classList.contains('show');
                    console.log('Cart clicked! Current:', isShowing ? 'OPEN' : 'CLOSED');
                    
                    if (isShowing) {
                        cartDropdown.classList.remove('show');
                        console.log('Closing cart');
                    } else {
                        cartDropdown.classList.add('show');
                        console.log('Opening cart');
                    }
                });
                
                console.log('Cart toggle listener attached!');
            } else {
                console.error('Cart elements missing!', { cartToggle, cartDropdown });
            }

            if (closeCartDropdown && cartDropdown) {
                closeCartDropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Close cart clicked!');
                    cartDropdown.classList.remove('show');
                });
                
                console.log('Close cart button attached!');
            }

            document.addEventListener('click', function(e) {
                if (cartDropdown && cartToggle) {
                    if (!cartDropdown.contains(e.target) && !cartToggle.contains(e.target)) {
                        if (cartDropdown.classList.contains('show')) {
                            console.log('Clicked outside - closing cart');
                            cartDropdown.classList.remove('show');
                        }
                    }
                }
            });

            if (cartDropdown) {
                cartDropdown.addEventListener('click', function(e) {
                    e.stopPropagation();
                    console.log('Clicked inside cart');
                });
            }

            console.log('All event listeners initialized!');
        });

        // GLOBAL FUNCTION TO UPDATE CART FROM OTHER PAGES
        window.updateCartDisplay = function(cartData) {
            console.log('Updating cart display:', cartData);
            
            // Update badge
            const cartBadge = document.getElementById('cartBadge');
            const cartCountBadge = document.getElementById('cartCountBadge');
            
            if (cartData.cart_count > 0) {
                if (cartBadge) {
                    cartBadge.textContent = cartData.cart_count;
                    cartBadge.style.display = 'block';
                } else {
                    // Create badge if it doesn't exist
                    const cartToggle = document.getElementById('cartToggle');
                    if (cartToggle) {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'badge';
                        newBadge.id = 'cartBadge';
                        newBadge.textContent = cartData.cart_count;
                        cartToggle.appendChild(newBadge);
                    }
                }
                
                if (cartCountBadge) {
                    cartCountBadge.textContent = cartData.cart_count;
                    cartCountBadge.style.display = 'inline-block';
                } else {
                    // Create count badge in header
                    const headerH3 = document.querySelector('.cart-dropdown-header h3');
                    if (headerH3) {
                        const newCountBadge = document.createElement('span');
                        newCountBadge.className = 'cart-count-badge';
                        newCountBadge.id = 'cartCountBadge';
                        newCountBadge.textContent = cartData.cart_count;
                        headerH3.appendChild(newCountBadge);
                    }
                }
            } else {
                if (cartBadge) cartBadge.style.display = 'none';
                if (cartCountBadge) cartCountBadge.style.display = 'none';
            }
            
            // Update cart body
            const cartBody = document.getElementById('cartDropdownBody');
            if (cartBody) {
                cartBody.innerHTML = '';
                
                if (cartData.cart_items && cartData.cart_items.length > 0) {
                    cartData.cart_items.forEach(item => {
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
                                    <span class="cart-item-price">Rs. ${parseFloat(item.price * item.quantity).toFixed(2)}</span>
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
                } else {
                    cartBody.innerHTML = `
                        <div class="cart-empty">
                            <i class="fas fa-shopping-cart"></i>
                            <p><strong>Your cart is empty</strong></p>
                            <p>Add some products to get started!</p>
                        </div>
                    `;
                }
            }
            
            // Update footer
            const cartFooter = document.getElementById('cartDropdownFooter');
            const cartTotalAmount = document.getElementById('cartTotalAmount');
            
            if (cartData.cart_count > 0) {
                if (cartTotalAmount) {
                    cartTotalAmount.textContent = 'Rs. ' + parseFloat(cartData.cart_total).toFixed(2);
                }
                
                if (cartFooter) {
                    cartFooter.style.display = 'block';
                } else {
                    // Create footer if it doesn't exist
                    const cartDropdown = document.getElementById('cartDropdown');
                    if (cartDropdown) {
                        const newFooter = document.createElement('div');
                        newFooter.className = 'cart-dropdown-footer';
                        newFooter.id = 'cartDropdownFooter';
                        newFooter.innerHTML = `
                            <div class="cart-subtotal">
                                <span class="cart-subtotal-label">Total:</span>
                                <span class="cart-subtotal-amount" id="cartTotalAmount">Rs. ${parseFloat(cartData.cart_total).toFixed(2)}</span>
                            </div>
                            <form method="GET" action="/fashionhub/Customer/CartItemCheckout.php">
                                <button type="submit" class="checkout-btn">
                                    <i class="fas fa-credit-card"></i>
                                    Proceed to Checkout
                                </button>
                            </form>
                        `;
                        cartDropdown.appendChild(newFooter);
                    }
                }
            } else {
                if (cartFooter) cartFooter.style.display = 'none';
            }
        };

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
        
const profileButton = document.getElementById('profileButton');
if (profileButton) {
    profileButton.addEventListener('click', function() {
        window.location.href = '/fashionhub/Customer/Profile.php';
    });
}

      
const logoutButton = document.getElementById('logoutButton');
if (logoutButton) {
    logoutButton.addEventListener('click', function() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = '/fashionhub/logout.php';
        }
    });
}
    </script>
</body>
</html>