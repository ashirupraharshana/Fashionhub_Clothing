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

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_contact'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $subject = $conn->real_escape_string(trim($_POST['subject']));
    $message = $conn->real_escape_string(trim($_POST['message']));
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['message'] = "Please fill in all required fields!";
        $_SESSION['message_type'] = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Please enter a valid email address!";
        $_SESSION['message_type'] = "error";
    } else {
        // Insert contact message
        if ($user_id) {
            $sql = "INSERT INTO feedback (user_id, name, email, message) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $combined_message = "Subject: $subject\n\nPhone: $phone\n\n$message";
            $stmt->bind_param("isss", $user_id, $name, $email, $combined_message);
        } else {
            $sql = "INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $combined_message = "Subject: $subject\n\nPhone: $phone\n\n$message";
            $stmt->bind_param("sss", $name, $email, $combined_message);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Thank you for contacting us! We'll get back to you soon.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error sending message. Please try again.";
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    }
    
    header("Location: ContactUs.php");
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_data = null;

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT fullname, email, phone FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_result->num_rows > 0) {
        $user_data = $user_result->fetch_assoc();
    }
    $stmt->close();
}

// Get cart data for logged-in users
$cart_count = 0;
$cart_total = 0;
$cart_items_array = [];

if ($is_logged_in) {
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
    
    // Get cart total
    $cart_total_sql = "SELECT SUM(price * quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_total_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_total_result = $stmt->get_result();
    $cart_total_row = $cart_total_result->fetch_assoc();
    $cart_total = $cart_total_row['total'] ?? 0;
    $stmt->close();
    
    // Get cart items
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
    
    while ($row = $result->fetch_assoc()) {
        $cart_items_array[] = $row;
    }
    $stmt->close();
}

// Pre-fill form data
$prefill_name = $user_data['fullname'] ?? "";
$prefill_email = $user_data['email'] ?? "";
$prefill_phone = $user_data['phone'] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            min-height: 100vh;
            color: #2c3e50;
            line-height: 1.6;
            <?php if ($is_logged_in): ?>
            padding-top: 70px;
            <?php endif; ?>
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 120px 20px 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-30px); }
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-section h1 {
            font-size: 56px;
            font-weight: 900;
            margin-bottom: 20px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .hero-section p {
            font-size: 20px;
            opacity: 0.95;
            line-height: 1.8;
        }

        /* Alert Messages */
        .alert {
            position: fixed;
            top: 90px;
            left: 50%;
            transform: translateX(-50%);
            padding: 18px 30px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
            animation: slideDown 0.4s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            max-width: 500px;
        }

        .alert.success {
            background: white;
            color: #27ae60;
            border-left: 5px solid #27ae60;
        }

        .alert.error {
            background: white;
            color: #e74c3c;
            border-left: 5px solid #e74c3c;
        }

        .alert i {
            font-size: 24px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translate(-50%, -30px);
            }
            to {
                opacity: 1;
                transform: translate(-50%, 0);
            }
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: -60px auto 0;
            padding: 0 20px 80px;
            position: relative;
            z-index: 2;
        }

        /* Contact Info Cards */
        .contact-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .contact-info-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: all 0.4s ease;
            border: 3px solid transparent;
        }

        .contact-info-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            border-color: #e74c3c;
        }

        .contact-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        .contact-info-card h3 {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .contact-info-card p {
            font-size: 16px;
            color: #7f8c8d;
            line-height: 1.8;
        }

        .contact-info-card a {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .contact-info-card a:hover {
            color: #c0392b;
            text-decoration: underline;
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }

        /* Contact Form */
        .contact-form-container {
            background: white;
            padding: 50px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            margin-bottom: 35px;
        }

        .form-header h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: 800;
        }

        .form-header p {
            color: #7f8c8d;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: #f8f9fa;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
            background: white;
        }

        .form-group textarea {
            min-height: 160px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.4);
        }

        /* Info Sidebar */
        .info-sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .info-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .info-card h3 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-card h3 i {
            color: #e74c3c;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-item-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #e74c3c;
            flex-shrink: 0;
        }

        .info-item-content h4 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .info-item-content p {
            font-size: 14px;
            color: #7f8c8d;
            line-height: 1.6;
        }

        /* Social Links */
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border-radius: 50%;
            text-decoration: none;
            font-size: 20px;
            color: #2c3e50;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        /* Recent Feedback Section */
        .recent-feedback-section {
            background: white;
            border-radius: 24px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }

        .recent-feedback-section h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 800;
            text-align: center;
        }

        .feedbacks-showcase {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .feedback-showcase-card {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            border-radius: 16px;
            padding: 30px;
            transition: all 0.3s ease;
            border-left: 4px solid #e74c3c;
        }

        .feedback-showcase-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(231, 76, 60, 0.2);
        }

        .feedback-showcase-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .feedback-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
            flex-shrink: 0;
        }

        .feedback-name {
            font-size: 17px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .feedback-showcase-date {
            font-size: 13px;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .feedback-showcase-message {
            color: #555;
            line-height: 1.7;
            font-size: 15px;
        }

        .empty-feedback-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }

        .empty-feedback-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-feedback-state p {
            font-size: 18px;
        }

        /* Map Section */
        .map-section {
            background: white;
            border-radius: 24px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }

        .map-section h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 800;
            text-align: center;
        }

        .map-container {
            width: 100%;
            height: 450px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 20px 60px;
            }

            .hero-section h1 {
                font-size: 38px;
            }

            .hero-section p {
                font-size: 16px;
            }

            .contact-form-container,
            .info-card,
            .map-section,
            .recent-feedback-section {
                padding: 30px 25px;
            }

            .form-header h2,
            .map-section h2,
            .recent-feedback-section h2 {
                font-size: 28px;
            }

            .contact-info-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-to-top.show {
            display: flex;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.5);
        }

        /* Fade In Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <?php 
    if ($is_logged_in) {
        include 'Components/CustomerNavBar.php';
    }
    ?>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert <?php echo $_SESSION['message_type']; ?>">
            <i class="fas fa-<?php echo $_SESSION['message_type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <span><?php echo $_SESSION['message']; ?></span>
        </div>
        <?php 
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        ?>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Get In Touch With Us</h1>
            <p>Have questions or feedback? We'd love to hear from you. Our team is here to help!</p>
        </div>
    </section>

    <!-- Main Container -->
    <div class="container">
        <!-- Contact Info Cards -->
        <div class="contact-info-grid fade-in">
            <div class="contact-info-card">
                <div class="contact-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3>Visit Us</h3>
                <p>123 Fashion Street<br>Colombo 00100<br>Sri Lanka</p>
            </div>

            <div class="contact-info-card">
                <div class="contact-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <h3>Call Us</h3>
                <p>
                    <a href="tel:+94112345678">+94 11 234 5678</a><br>
                    <a href="tel:+94771234567">+94 77 123 4567</a><br>
                    Mon - Sat: 9:00 AM - 8:00 PM
                </p>
            </div>

            <div class="contact-info-card">
                <div class="contact-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3>Email Us</h3>
                <p>
                    <a href="mailto:info@fashionhub.lk">info@fashionhub.lk</a><br>
                    <a href="mailto:support@fashionhub.lk">support@fashionhub.lk</a><br>
                    We reply within 24 hours
                </p>
            </div>

            <div class="contact-info-card">
                <div class="contact-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>Business Hours</h3>
                <p>
                    Monday - Friday: 9:00 AM - 9:00 PM<br>
                    Saturday: 9:00 AM - 8:00 PM<br>
                    Sunday: 10:00 AM - 6:00 PM
                </p>
            </div>
        </div>

        <!-- Contact Form & Info -->
        <div class="content-grid fade-in">
            <div class="contact-form-container">
                <div class="form-header">
                    <h2>Send Us a Message</h2>
                    <p>Fill out the form below and we'll get back to you as soon as possible</p>
                </div>

                <form method="POST" action="ContactUs.php">
                    <input type="hidden" name="submit_contact" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Your Name *</label>
                            <input type="text" id="name" name="name" required 
                                   placeholder="Enter your full name"
                                   value="<?php echo htmlspecialchars($prefill_name); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   placeholder="your.email@example.com"
                                   value="<?php echo htmlspecialchars($prefill_email); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   placeholder="+94 77 123 4567"
                                   value="<?php echo htmlspecialchars($prefill_phone); ?>">
                        </div>

                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <select id="subject" name="subject" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry">General Inquiry</option>
                                <option value="Product Question">Product Question</option>
                                <option value="Order Status">Order Status</option>
                                <option value="Technical Support">Technical Support</option>
                                <option value="Feedback">Feedback</option>
                                <option value="Partnership">Partnership Opportunity</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Your Message *</label>
                        <textarea id="message" name="message" required 
                                  placeholder="Tell us what's on your mind..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>

            <div class="info-sidebar">
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> Quick Info</h3>
                    
                    <div class="info-item">
                        <div class="info-item-icon">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="info-item-content">
                            <h4>Free Shipping</h4>
                            <p>On orders over Rs. 5,000 within Sri Lanka</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-item-icon">
                            <i class="fas fa-undo"></i>
                        </div>
                        <div class="info-item-content">
                            <h4>Easy Returns</h4>
                            <p>30-day return policy on all products</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-item-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="info-item-content">
                            <h4>24/7 Support</h4>
                            <p>Our customer service team is always ready to help</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-item-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="info-item-content">
                            <h4>Secure Payment</h4>
                            <p>100% secure payment processing</p>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-share-alt"></i> Follow Us</h3>
                    <p style="margin-bottom: 20px; color: #7f8c8d;">Stay connected with us on social media for the latest updates and offers</p>
                    <div class="social-links">
                        <a href="#" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Feedback Section -->
        <div class="recent-feedback-section fade-in">
            <h2>What Our Customers Say</h2>
            <p style="text-align: center; color: #7f8c8d; margin-bottom: 40px; font-size: 16px;">
                Read feedback from our valued customers
            </p>

            <div class="feedbacks-showcase">
                <?php
                // Fetch recent feedbacks for display
                $recent_feedback_query = "SELECT f.*, u.fullname 
                                         FROM feedback f 
                                         LEFT JOIN users u ON f.user_id = u.id 
                                         ORDER BY f.submitted_at DESC 
                                         LIMIT 6";
                $recent_feedback_result = $conn->query($recent_feedback_query);
                
                if ($recent_feedback_result && $recent_feedback_result->num_rows > 0):
                    while ($feedback = $recent_feedback_result->fetch_assoc()):
                ?>
                    <div class="feedback-showcase-card">
                        <div class="feedback-showcase-header">
                            <div class="feedback-avatar">
                                <?php echo strtoupper(substr($feedback['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="feedback-name"><?php echo htmlspecialchars($feedback['name']); ?></div>
                                <div class="feedback-showcase-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($feedback['submitted_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="feedback-showcase-message">
                            <?php 
                            $message = htmlspecialchars($feedback['message']);
                            echo strlen($message) > 150 ? substr($message, 0, 150) . '...' : $message;
                            ?>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <div class="empty-feedback-state">
                        <i class="fas fa-comments"></i>
                        <p>Be the first to share your feedback!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Map Section -->
        <div class="map-section fade-in">
            <h2>Find Us On Map</h2>
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3960.798467128587!2d79.8612462!3d6.9270786!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae253d10f7a7003%3A0x320b2e4d32d3838d!2sColombo%2C%20Sri%20Lanka!5e0!3m2!1sen!2s!4v1234567890123!5m2!1sen!2s" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>

    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Scroll Animation
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(element => {
            observer.observe(element);
        });

        // Back to Top Button
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Auto-hide alert after 5 seconds
        const alert = document.querySelector('.alert');
        if (alert) {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translate(-50%, -20px)';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        }

        // Form validation
        const contactForm = document.querySelector('form');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                const phone = document.getElementById('phone').value;
                if (phone && !/^\+?[\d\s-]{10,}$/.test(phone)) {
                    e.preventDefault();
                    alert('Please enter a valid phone number');
                    return false;
                }
            });
        }
    </script>
      <?php include 'Components/Footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>