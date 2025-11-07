<?php
session_start();

// Database connection
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
}
 else {
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

        /* Message Alert */
        .alert {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 500;
            z-index: 3000;
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

        /* Top Navigation Bar */
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

        .auth-buttons {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-login, .btn-signup {
            padding: 10px 22px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-login {
            background: transparent;
            color: #333;
            border: 2px solid #e74c3c;
        }

        .btn-login:hover {
            background: #e74c3c;
            color: white;
        }

        .btn-signup {
            background: #e74c3c;
            color: white;
        }

        .btn-signup:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
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

        /* Modal Styles */
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

        /* Sidebar */
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        /* Overlay */
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

        /* Mobile Responsive */
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

            .auth-buttons {
                gap: 8px;
            }

            .btn-login, .btn-signup {
                padding: 8px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 576px) {
            .logo span {
                display: none;
            }

            .btn-login span, .btn-signup span {
                display: none;
            }

            .btn-login i, .btn-signup i {
                font-size: 16px;
            }

            .modal-content {
                width: 95%;
                margin: 0 10px;
            }

            .modal-body {
                padding: 25px 20px;
            }
        }

        /* Scrollbar Styling */
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

    <!-- Top Navigation Bar -->
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
                <li><a href="#home">Home</a></li>
                <li><a href="#collections">Collections</a></li>
                <li><a href="#new-arrivals">New Arrivals</a></li>
                <li><a href="#sale">Sale</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>

            <div class="nav-actions">
                <button class="icon-button" title="Shopping Cart">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="badge">2</span>
                </button>
                <div class="auth-buttons">
                    <button class="btn-login" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </button>
                    <button class="btn-signup" id="signupBtn">
                        <i class="fas fa-user-plus"></i>
                        <span>Sign Up</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Login Modal -->
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

    <!-- Signup Modal -->
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

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Welcome to FashionHub</h3>
            <p>Discover your style</p>
        </div>

        <ul class="sidebar-menu">
            <li><a href="#home"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="#collections"><i class="fas fa-th-large"></i> Collections</a></li>
            <li><a href="#men"><i class="fas fa-male"></i> Men's Fashion</a></li>
            <li><a href="#women"><i class="fas fa-female"></i> Women's Fashion</a></li>
            <li><a href="#accessories"><i class="fas fa-gem"></i> Accessories</a></li>
            <li><a href="#new-arrivals"><i class="fas fa-star"></i> New Arrivals</a></li>
            <li><a href="#sale"><i class="fas fa-tags"></i> Sale</a></li>
            <li><a href="#about"><i class="fas fa-info-circle"></i> About Us</a></li>
            <li><a href="#contact"><i class="fas fa-envelope"></i> Contact</a></li>
            <li><a href="#feedback"><i class="fas fa-comment-dots"></i> Feedback</a></li>
        </ul>

        <div class="sidebar-section">
            <h4>Newsletter</h4>
            <form class="newsletter-form">
                <input type="email" placeholder="Enter your email" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>

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

    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <script>
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

        // Auto-hide alert message after 5 seconds
        const alertMessage = document.getElementById('alertMessage');
        if (alertMessage) {
            setTimeout(() => {
                alertMessage.style.animation = 'slideUp 0.3s ease';
                setTimeout(() => {
                    alertMessage.remove();
                }, 300);
            }, 5000);
        }

        // Toggle sidebar
        menuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function() {
            menuToggle.classList.remove('active');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Close sidebar when clicking on a link
        const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                menuToggle.classList.remove('active');
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        });

        // Open Login Modal
        loginBtn.addEventListener('click', function() {
            loginModal.classList.add('active');
        });

        // Open Signup Modal
        signupBtn.addEventListener('click', function() {
            signupModal.classList.add('active');
        });

        // Close Login Modal
        closeLogin.addEventListener('click', function() {
            loginModal.classList.remove('active');
        });

        // Close Signup Modal
        closeSignup.addEventListener('click', function() {
            signupModal.classList.remove('active');
        });

        // Switch from Login to Signup
        if (switchToSignup) {
            switchToSignup.addEventListener('click', function() {
                loginModal.classList.remove('active');
                signupModal.classList.add('active');
            });
        }

        // Switch from Signup to Login
        if (switchToLogin) {
            switchToLogin.addEventListener('click', function() {
                signupModal.classList.remove('active');
                loginModal.classList.add('active');
            });
        }

        // Close modals when clicking outside
        loginModal.addEventListener('click', function(e) {
            if (e.target === loginModal) {
                loginModal.classList.remove('active');
            }
        });

        signupModal.addEventListener('click', function(e) {
            if (e.target === signupModal) {
                signupModal.classList.remove('active');
            }
        });

        // Newsletter form submission
        const newsletterForm = document.querySelector('.newsletter-form');
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input').value;
            alert('Thank you for subscribing! We will send updates to: ' + email);
            this.reset();
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Close modals and sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('active')) {
                    menuToggle.classList.remove('active');
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                }
                if (loginModal.classList.contains('active')) {
                    loginModal.classList.remove('active');
                }
                if (signupModal.classList.contains('active')) {
                    signupModal.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>