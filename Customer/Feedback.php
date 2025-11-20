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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-red: #e74c3c;
            --primary-white: #ffffff;
            --dark-red: #c0392b;
            --light-red: #ff6b6b;
            --bg-light: #fafafa;
            --text-dark: #2d3436;
            --text-light: #636e72;
            --shadow-sm: 0 2px 10px rgba(231, 76, 60, 0.1);
            --shadow-md: 0 4px 20px rgba(231, 76, 60, 0.15);
            --shadow-lg: 0 8px 30px rgba(231, 76, 60, 0.2);
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

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .animated-bg::before,
        .animated-bg::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            opacity: 0.03;
            animation: float 20s infinite ease-in-out;
        }

        .animated-bg::before {
            width: 600px;
            height: 600px;
            top: -300px;
            right: -200px;
        }

        .animated-bg::after {
            width: 800px;
            height: 800px;
            bottom: -400px;
            left: -300px;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        /* Hero Section with Modern Design */
        .hero-section {
            position: relative;
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
            padding: 80px 60px;
            border-radius: 30px;
            margin-bottom: 60px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.1;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(255,255,255,.1) 35px, rgba(255,255,255,.1) 70px);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .hero-icon {
            font-size: 60px;
            color: var(--primary-white);
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .hero-section h1 {
            font-size: 56px;
            font-weight: 800;
            color: var(--primary-white);
            margin-bottom: 20px;
            letter-spacing: -1px;
            text-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }

        .hero-section p {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.95);
            max-width: 700px;
            margin: 0 auto;
            font-weight: 400;
            line-height: 1.8;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 100px;
            right: -400px;
            background: var(--primary-white);
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: var(--shadow-lg);
            z-index: 9999;
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
            border-left: 5px solid var(--primary-red);
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
            color: var(--primary-red);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
        }

        .toast-message {
            font-size: 14px;
            color: var(--text-light);
        }

        .toast-close {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-light);
            transition: all 0.3s;
            padding: 5px;
        }

        .toast-close:hover {
            color: var(--text-dark);
            transform: rotate(90deg);
        }

        /* Stats Cards with Modern Glass Effect */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .stat-card {
            background: var(--primary-white);
            padding: 40px 35px;
            border-radius: 25px;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary-red), var(--light-red));
            opacity: 0.05;
            border-radius: 0 25px 0 100%;
            transition: all 0.4s;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-red);
        }

        .stat-card:hover::before {
            opacity: 0.1;
            width: 150px;
            height: 150px;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
            position: relative;
            z-index: 1;
        }

        .stat-card h3 {
            font-size: 44px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .stat-card p {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        /* Feedback Form with Modern Card */
        .feedback-form-container {
            background: var(--primary-white);
            padding: 50px;
            border-radius: 30px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .feedback-form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-red), var(--light-red));
        }

        .form-header {
            margin-bottom: 40px;
            text-align: center;
        }

        .form-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        .form-header p {
            color: var(--text-light);
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 30px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 12px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label i {
            color: var(--primary-red);
            font-size: 16px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e8e8e8;
            border-radius: 15px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
            background: #fafafa;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-red);
            background: var(--primary-white);
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
        }

        .form-group textarea {
            min-height: 160px;
            resize: vertical;
        }

        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.4);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        /* Recent Feedbacks with Modern Card */
        .recent-feedbacks-container {
            background: var(--primary-white);
            padding: 50px;
            border-radius: 30px;
            box-shadow: var(--shadow-sm);
            max-height: 850px;
            overflow-y: auto;
            position: relative;
        }

        .recent-feedbacks-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-red), var(--light-red));
        }

        .recent-feedbacks-container h2 {
            font-size: 28px;
            color: var(--text-dark);
            margin-bottom: 35px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .recent-feedbacks-container h2 i {
            color: var(--primary-red);
            font-size: 30px;
        }

        /* Feedback Items with Hover Effects */
        .feedback-item {
            background: #fafafa;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 20px;
            border: 2px solid transparent;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
        }

        .feedback-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 5px;
            height: 0;
            background: linear-gradient(180deg, var(--primary-red), var(--light-red));
            border-radius: 20px 0 0 20px;
            transition: height 0.4s;
        }

        .feedback-item:hover {
            background: var(--primary-white);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-red);
            transform: translateX(5px);
        }

        .feedback-item:hover::before {
            height: 100%;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .feedback-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 55px;
            height: 55px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 22px;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
            position: relative;
        }

        .user-avatar::after {
            content: '';
            position: absolute;
            width: 15px;
            height: 15px;
            background: #27ae60;
            border: 3px solid var(--primary-white);
            border-radius: 50%;
            bottom: -2px;
            right: -2px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
            margin-bottom: 3px;
        }

        .user-email {
            font-size: 13px;
            color: var(--text-light);
        }

        .feedback-date {
            font-size: 13px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 6px;
            background: #f0f0f0;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .feedback-message {
            color: var(--text-dark);
            line-height: 1.8;
            font-size: 15px;
            margin-bottom: 15px;
            padding: 20px;
            background: var(--primary-white);
            border-radius: 15px;
            border: 1px solid #e8e8e8;
        }

        /* Admin Reply with Beautiful Design */
        .admin-reply {
            background: linear-gradient(135deg, #fff5f4 0%, #ffe8e6 100%);
            padding: 20px;
            border-radius: 15px;
            margin-top: 15px;
            border-left: 4px solid var(--primary-red);
            position: relative;
            overflow: hidden;
        }

        .admin-reply::before {
            content: '\f3e5';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 40px;
            color: var(--primary-red);
            opacity: 0.1;
        }

        .admin-reply-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-red);
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .admin-reply-header i {
            font-size: 16px;
        }

        .admin-reply-message {
            color: var(--text-dark);
            line-height: 1.7;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }

        /* Delete Button */
        .feedback-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 15px;
            border-top: 1px solid #e8e8e8;
            margin-top: 15px;
        }

        .delete-feedback-btn {
            padding: 10px 20px;
            background: transparent;
            color: var(--primary-red);
            border: 2px solid var(--primary-red);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .delete-feedback-btn:hover {
            background: var(--primary-red);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
            color: var(--primary-red);
        }

        .empty-state p {
            font-size: 16px;
        }

        /* All Feedbacks Section */
        .all-feedbacks-section {
            background: var(--primary-white);
            padding: 60px 50px;
            border-radius: 30px;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .all-feedbacks-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-red), var(--light-red));
            border-radius: 30px 30px 0 0;
        }

        .all-feedbacks-section h2 {
            font-size: 36px;
            color: var(--text-dark);
            margin-bottom: 50px;
            font-weight: 800;
            text-align: center;
            position: relative;
            padding-bottom: 20px;
        }

        .all-feedbacks-section h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-red), var(--light-red));
            border-radius: 2px;
        }

        .feedbacks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 30px;
        }

        /* Scrollbar */
        .recent-feedbacks-container::-webkit-scrollbar {
            width: 10px;
        }

        .recent-feedbacks-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .recent-feedbacks-container::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--primary-red), var(--dark-red));
            border-radius: 10px;
        }

        .recent-feedbacks-container::-webkit-scrollbar-thumb:hover {
            background: var(--dark-red);
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 40px;
            right: 40px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-red), var(--dark-red));
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.4);
            transition: all 0.3s;
            z-index: 999;
        }

        .back-to-top.show {
            display: flex;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.5);
        }

        .back-to-top::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--primary-white);
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s;
        }

        .back-to-top:hover::before {
            opacity: 0.2;
            transform: scale(1.2);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .recent-feedbacks-container {
                max-height: 700px;
            }

            .feedbacks-grid {
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 70px;
            }

            .page-container {
                padding: 30px 15px;
            }

            .hero-section {
                padding: 50px 30px;
                border-radius: 20px;
                margin-bottom: 40px;
            }

            .hero-icon {
                font-size: 45px;
            }

            .hero-section h1 {
                font-size: 36px;
            }

            .hero-section p {
                font-size: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .stat-card {
                padding: 30px 25px;
            }

            .feedback-form-container,
            .recent-feedbacks-container,
            .all-feedbacks-section {
                padding: 30px 25px;
                border-radius: 20px;
            }

            .form-header h2,
            .recent-feedbacks-container h2 {
                font-size: 24px;
            }

            .all-feedbacks-section h2 {
                font-size: 28px;
            }

            .feedbacks-grid {
                grid-template-columns: 1fr;
            }

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

            .back-to-top {
                width: 50px;
                height: 50px;
                bottom: 25px;
                right: 25px;
                font-size: 18px;
            }

            .user-avatar {
                width: 48px;
                height: 48px;
                font-size: 18px;
            }

            .feedback-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .hero-section {
                padding: 40px 20px;
            }

            .hero-section h1 {
                font-size: 28px;
            }

            .stat-card h3 {
                font-size: 36px;
            }

            .feedback-form-container,
            .recent-feedbacks-container,
            .all-feedbacks-section {
                padding: 25px 20px;
            }
        }

        /* Loading Animation */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        /* Fade In Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .feedback-item {
            animation: fadeInUp 0.5s ease forwards;
        }

        .feedback-item:nth-child(1) { animation-delay: 0.1s; }
        .feedback-item:nth-child(2) { animation-delay: 0.2s; }
        .feedback-item:nth-child(3) { animation-delay: 0.3s; }
        .feedback-item:nth-child(4) { animation-delay: 0.4s; }
        .feedback-item:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="animated-bg"></div>
    
    <div class="page-container">
        <div class="hero-section">
            <div class="hero-pattern"></div>
            <div class="hero-content">
                <div class="hero-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h1>We Value Your Feedback</h1>
                <p>Your thoughts help us create a better fashion experience. Share your ideas, suggestions, or concerns with us!</p>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="toast <?php echo $_SESSION['message_type']; ?>">
                <div class="toast-icon">
                    <i class="fas fa-<?php echo $_SESSION['message_type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title"><?php echo $_SESSION['message_type'] == 'success' ? 'Success!' : 'Error!'; ?></div>
                    <div class="toast-message"><?php echo $_SESSION['message']; ?></div>
                </div>
                <button class="toast-close">&times;</button>
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
                    <p>We're listening and ready to improve</p>
                </div>

                <form method="POST" action="Feedback.php">
                    <input type="hidden" name="submit_feedback" value="1">
                    
                    <div class="form-group">
                        <label for="name">
                            <i class="fas fa-user"></i>
                            Full Name *
                        </label>
                        <input type="text" id="name" name="name" required 
                               placeholder="Enter your full name"
                               value="<?php echo htmlspecialchars($prefill_name); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address *
                        </label>
                        <input type="email" id="email" name="email" required 
                               placeholder="your.email@example.com"
                               value="<?php echo htmlspecialchars($prefill_email); ?>">
                    </div>

                    <div class="form-group">
                        <label for="message">
                            <i class="fas fa-comment-dots"></i>
                            Your Message *
                        </label>
                        <textarea id="message" name="message" required 
                                  placeholder="Share your thoughts, suggestions, or concerns..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Submit Feedback
                    </button>
                </form>
            </div>

            <div class="recent-feedbacks-container">
                <h2>
                    <i class="fas fa-star"></i>
                    Recent Feedbacks
                </h2>
                
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
            
            // Toast Notification
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

            // Form validation and loading state
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
                    
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        return false;
                    }
                    
                    const submitBtn = feedbackForm.querySelector('.submit-btn');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.7';
                    }
                });
            }

            // Smooth scroll for anchor links
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

            // Add hover effect to feedback items
            const feedbackItems = document.querySelectorAll('.feedback-item');
            feedbackItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });

        })();
    </script>
    <?php include 'Components/Footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>