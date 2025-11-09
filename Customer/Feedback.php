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

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;

// Handle Feedback Deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_feedback'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = "You must be logged in to delete feedback!";
        $_SESSION['message_type'] = "error";
    } else {
        $feedback_id = intval($_POST['feedback_id']);
        $current_user_id = $_SESSION['user_id'];
        
        $check_query = "SELECT user_id FROM feedback WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $feedback_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $feedback_data = $check_result->fetch_assoc();
            
            if ($feedback_data['user_id'] == $current_user_id) {
                $delete_query = "DELETE FROM feedback WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($delete_query);
                $stmt->bind_param("ii", $feedback_id, $current_user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Feedback deleted successfully!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error deleting feedback. Please try again.";
                    $_SESSION['message_type'] = "error";
                }
            } else {
                $_SESSION['message'] = "You can only delete your own feedback!";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Feedback not found!";
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    }
    
    header("Location: Feedback.php");
    exit;
}

// Handle Feedback Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $message = $conn->real_escape_string(trim($_POST['message']));
    
    if (empty($name) || empty($email) || empty($message)) {
        $_SESSION['message'] = "All fields are required!";
        $_SESSION['message_type'] = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Please enter a valid email address!";
        $_SESSION['message_type'] = "error";
    } else {
        if ($user_id) {
            $sql = "INSERT INTO feedback (user_id, name, email, message) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $user_id, $name, $email, $message);
        } else {
            $sql = "INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $name, $email, $message);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Thank you for your feedback! We appreciate your input.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error submitting feedback. Please try again.";
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    }
    
    header("Location: Feedback.php");
    exit;
}

if (isset($_SESSION['user_id'])) {
    include 'Components/CustomerNavBar.php';
}

// Fetch all feedbacks
$feedback_query = "SELECT f.*, u.fullname 
                   FROM feedback f 
                   LEFT JOIN users u ON f.user_id = u.id 
                   ORDER BY f.submitted_at DESC";
$feedback_result = $conn->query($feedback_query);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_feedbacks,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_feedbacks,
    COUNT(CASE WHEN admin_reply IS NOT NULL THEN 1 END) as replied_feedbacks
    FROM feedback";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Pre-fill form data for logged-in users
$prefill_name = "";
$prefill_email = "";
if (isset($_SESSION['user_id'])) {
    $user_query = "SELECT fullname, email FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user_result = $stmt->get_result();
    if ($user_data = $user_result->fetch_assoc()) {
        $prefill_name = $user_data['fullname'];
        $prefill_email = $user_data['email'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Feedback | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
            <?php if (isset($_SESSION['user_id'])): ?>
            padding-top: 70px;
            <?php endif; ?>
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 60px 40px;
            border-radius: 16px;
            margin-bottom: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: #e74c3c;
            opacity: 0.1;
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-section h1 {
            font-size: 48px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .hero-section p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.8);
            max-width: 600px;
            font-weight: 400;
        }

        .hero-highlight {
            color: #e74c3c;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideInRight 0.5s ease;
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 1000;
            max-width: 450px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .alert.success {
            background: #ffffff;
            color: #27ae60;
            border-left: 4px solid #27ae60;
        }

        .alert.error {
            background: #ffffff;
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-close {
            margin-left: auto;
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.2s;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOutRight {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100px);
            }
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #ffffff;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            border: 1px solid #e8e8e8;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            border-color: #e74c3c;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .stat-card h3 {
            font-size: 36px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .stat-card p {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 50px;
        }

        /* Feedback Form */
        .feedback-form-container {
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
        }

        .form-header {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-header h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 800;
        }

        .form-header p {
            color: #7f8c8d;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group label i {
            color: #e74c3c;
            margin-right: 6px;
            width: 16px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s ease;
            font-family: inherit;
            background: #ffffff;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .form-group textarea {
            min-height: 160px;
            resize: vertical;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Recent Feedbacks */
        .recent-feedbacks-container {
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            max-height: 750px;
            overflow-y: auto;
            border: 1px solid #e8e8e8;
        }

        .recent-feedbacks-container h2 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 24px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .recent-feedbacks-container h2 i {
            color: #e74c3c;
        }

        .feedback-item {
            background: #f8f9fa;
            padding: 24px;
            border-radius: 10px;
            margin-bottom: 16px;
            border: 1px solid #e8e8e8;
            transition: all 0.3s ease;
        }

        .feedback-item:hover {
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: #e74c3c;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .feedback-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 15px;
        }

        .user-email {
            font-size: 13px;
            color: #7f8c8d;
        }

        .feedback-date {
            font-size: 13px;
            color: #95a5a6;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .feedback-message {
            color: #2c3e50;
            line-height: 1.7;
            font-size: 14px;
            margin-bottom: 12px;
            padding: 16px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
        }

        .admin-reply {
            background: #fff9f8;
            padding: 16px;
            border-radius: 8px;
            margin-top: 12px;
            border-left: 3px solid #e74c3c;
        }

        .admin-reply-header {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #e74c3c;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .admin-reply-message {
            color: #2c3e50;
            line-height: 1.6;
            font-size: 14px;
        }

        .feedback-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 12px;
            border-top: 1px solid #e8e8e8;
            margin-top: 12px;
        }

        .delete-feedback-btn {
            padding: 8px 16px;
            background: transparent;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .delete-feedback-btn:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.4;
        }

        .empty-state p {
            font-size: 15px;
        }

        /* All Feedbacks Section */
        .all-feedbacks-section {
            background: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
        }

        .all-feedbacks-section h2 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 32px;
            font-weight: 800;
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .feedbacks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        /* Scrollbar Styling */
        .recent-feedbacks-container::-webkit-scrollbar {
            width: 8px;
        }

        .recent-feedbacks-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .recent-feedbacks-container::-webkit-scrollbar-thumb {
            background: #e74c3c;
            border-radius: 10px;
        }

        .recent-feedbacks-container::-webkit-scrollbar-thumb:hover {
            background: #c0392b;
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
            transition: all 0.3s ease;
            z-index: 999;
        }

        .back-to-top.show {
            display: flex;
        }

        .back-to-top:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.5);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .recent-feedbacks-container {
                max-height: 600px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }

            .hero-section {
                padding: 40px 24px;
            }

            .hero-section h1 {
                font-size: 32px;
            }

            .hero-section p {
                font-size: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .feedback-form-container,
            .recent-feedbacks-container,
            .all-feedbacks-section {
                padding: 24px;
            }

            .feedbacks-grid {
                grid-template-columns: 1fr;
            }

            .form-header h2,
            .recent-feedbacks-container h2,
            .all-feedbacks-section h2 {
                font-size: 24px;
            }

            .alert {
                top: 70px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="hero-section">
            <div class="hero-content">
                <h1>Customer <span class="hero-highlight">Feedback</span></h1>
                <p>Your feedback helps us improve our services and provide you with the best fashion experience possible.</p>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert <?php echo $_SESSION['message_type']; ?>">
                <i class="fas fa-<?php echo $_SESSION['message_type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $_SESSION['message']; ?></span>
                <button class="alert-close">&times;</button>
            </div>
            <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h3><?php echo $stats['total_feedbacks']; ?></h3>
                <p>Total Feedbacks</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?php echo $stats['unique_users']; ?></h3>
                <p>Unique Users</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $stats['recent_feedbacks']; ?></h3>
                <p>This Week</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-reply-all"></i>
                </div>
                <h3><?php echo $stats['replied_feedbacks']; ?></h3>
                <p>Admin Replies</p>
            </div>
        </div>

        <div class="content-grid">
            <div class="feedback-form-container">
                <div class="form-header">
                    <h2>Share Your Feedback</h2>
                    <p>We value your opinion and would love to hear from you</p>
                </div>

                <form method="POST" action="Feedback.php">
                    <input type="hidden" name="submit_feedback" value="1">
                    
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" id="name" name="name" required 
                               placeholder="Enter your full name"
                               value="<?php echo htmlspecialchars($prefill_name); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="your.email@example.com"
                               value="<?php echo htmlspecialchars($prefill_email); ?>">
                    </div>

                    <div class="form-group">
                        <label for="message"><i class="fas fa-comment-dots"></i> Your Message *</label>
                        <textarea id="message" name="message" required 
                                  placeholder="Share your thoughts, suggestions, or concerns..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Submit Feedback
                    </button>
                </form>
            </div>

            <div class="recent-feedbacks-container">
                <h2><i class="fas fa-star"></i> Recent Feedbacks</h2>
                
                <?php if ($feedback_result->num_rows > 0): ?>
                    <?php 
                    $count = 0;
                    $current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                    $feedback_result->data_seek(0);
                    while ($feedback = $feedback_result->fetch_assoc()): 
                        if ($count >= 5) break;
                        $count++;
                    ?>
                        <div class="feedback-item">
                            <div class="feedback-header">
                                <div class="feedback-user">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($feedback['name'], 0, 1)); ?>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($feedback['name']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($feedback['email']); ?></div>
                                    </div>
                                </div>
                                <div class="feedback-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y', strtotime($feedback['submitted_at'])); ?>
                                </div>
                            </div>
                            <div class="feedback-message">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                            
                            <?php if (!empty($feedback['admin_reply'])): ?>
                                <div class="admin-reply">
                                    <div class="admin-reply-header">
                                        <i class="fas fa-reply"></i>
                                        <span>Admin Response</span>
                                        <?php if (!empty($feedback['replied_at'])): ?>
                                            <span style="margin-left: auto; font-weight: normal; font-size: 12px;">
                                                <?php echo date('M d, Y', strtotime($feedback['replied_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-reply-message">
                                        <?php echo nl2br(htmlspecialchars($feedback['admin_reply'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($current_user && $feedback['user_id'] == $current_user): ?>
                                <div class="feedback-actions">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this feedback?')">
                                        <input type="hidden" name="delete_feedback" value="1">
                                        <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                        <button type="submit" class="delete-feedback-btn">
                                            <i class="fas fa-trash-alt"></i>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No feedback yet. Be the first to share!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($feedback_result->num_rows > 5): ?>
        <div class="all-feedbacks-section">
            <h2>All Customer Feedbacks</h2>
            
            <div class="feedbacks-grid">
                <?php 
                $current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $feedback_result->data_seek(5);
                while ($feedback = $feedback_result->fetch_assoc()): 
                ?>
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <div class="feedback-user">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($feedback['name'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($feedback['name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($feedback['email']); ?></div>
                                </div>
                            </div>
                            <div class="feedback-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M d, Y', strtotime($feedback['submitted_at'])); ?>
                            </div>
                        </div>
                        <div class="feedback-message">
                            <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                        </div>
                        
                        <?php if (!empty($feedback['admin_reply'])): ?>
                            <div class="admin-reply">
                                <div class="admin-reply-header">
                                    <i class="fas fa-reply"></i>
                                    <span>Admin Response</span>
                                    <?php if (!empty($feedback['replied_at'])): ?>
                                        <span style="margin-left: auto; font-weight: normal; font-size: 12px;">
                                            <?php echo date('M d, Y', strtotime($feedback['replied_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-reply-message">
                                    <?php echo nl2br(htmlspecialchars($feedback['admin_reply'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($current_user && $feedback['user_id'] == $current_user): ?>
                            <div class="feedback-actions">
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this feedback?')">
                                    <input type="hidden" name="delete_feedback" value="1">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <button type="submit" class="delete-feedback-btn">
                                        <i class="fas fa-trash-alt"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        (function() {
            'use strict';
            
            // Back to Top Button
            const backToTop = document.getElementById('backToTop');

            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTop.classList.add('show');
                } else {
                    backToTop.classList.remove('show');
                }
            });

            backToTop.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // Auto-hide alert after 5 seconds
            const alert = document.querySelector('.alert');
            if (alert) {
                const alertClose = alert.querySelector('.alert-close');
                
                // Close button functionality
                if (alertClose) {
                    alertClose.addEventListener('click', function() {
                        alert.style.animation = 'slideOutRight 0.5s ease';
                        setTimeout(function() {
                            alert.remove();
                        }, 500);
                    });
                }
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    if (alert && alert.parentElement) {
                        alert.style.animation = 'slideOutRight 0.5s ease';
                        setTimeout(function() {
                            if (alert && alert.parentElement) {
                                alert.remove();
                            }
                        }, 500);
                    }
                }, 5000);
            }

            // Form validation enhancement
            const feedbackForm = document.querySelector('form[action="Feedback.php"]');
            if (feedbackForm) {
                feedbackForm.addEventListener('submit', function(e) {
                    const name = document.getElementById('name').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const message = document.getElementById('message').value.trim();
                    
                    if (!name || !email || !message) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                        return false;
                    }
                    
                    // Email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        return false;
                    }
                    
                    // Show loading state
                    const submitBtn = feedbackForm.querySelector('.submit-btn');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                        submitBtn.disabled = true;
                    }
                });
            }

            // Smooth scroll for better UX
            document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

        })();
    </script>
    <?php include 'Components/Footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>