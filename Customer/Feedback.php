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

// Handle Feedback Deletion - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_feedback'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = "You must be logged in to delete feedback!";
        $_SESSION['message_type'] = "error";
    } else {
        $feedback_id = intval($_POST['feedback_id']);
        $current_user_id = $_SESSION['user_id'];
        
        // Verify that the feedback belongs to the logged-in user
        $check_query = "SELECT user_id FROM feedback WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $feedback_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $feedback_data = $check_result->fetch_assoc();
            
            if ($feedback_data['user_id'] == $current_user_id) {
                // User owns this feedback, allow deletion
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

// Handle Feedback Submission - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_feedback'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    $name = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $message = $conn->real_escape_string(trim($_POST['message']));
    
    // Validation
    if (empty($name) || empty($email) || empty($message)) {
        $_SESSION['message'] = "All fields are required!";
        $_SESSION['message_type'] = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = "Please enter a valid email address!";
        $_SESSION['message_type'] = "error";
    } else {
        // Insert feedback
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

// Include navbar AFTER handling POST requests
if (isset($_SESSION['user_id'])) {
    include 'Components/CustomerNavBar.php';
}

// Fetch all feedbacks with user information (ordered by newest first)
$feedback_query = "SELECT f.*, u.fullname 
                   FROM feedback f 
                   LEFT JOIN users u ON f.user_id = u.id 
                   ORDER BY f.submitted_at DESC";
$feedback_result = $conn->query($feedback_query);

// Get feedback statistics
$stats_query = "SELECT 
    COUNT(*) as total_feedbacks,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_feedbacks
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
    <title>Feedback | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            min-height: 100vh;
            color: #2c3e50;
            <?php if (isset($_SESSION['user_id'])): ?>
            padding-top: 70px;
            <?php endif; ?>
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        /* Hero Section */
        .hero-section {
            text-align: center;
            margin-bottom: 60px;
            color: white;
        }

        .hero-section h1 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .hero-section p {
            font-size: 20px;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Alert Messages */
        .alert {
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
            animation: slideDown 0.4s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.4s ease;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            border-color: #e74c3c;
        }

        .stat-icon {
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

        .stat-card h3 {
            font-size: 42px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .stat-card p {
            font-size: 16px;
            color: #7f8c8d;
            font-weight: 600;
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        /* Feedback Form */
        .feedback-form-container {
            background: white;
            padding: 45px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            margin-bottom: 35px;
        }

        .form-header h2 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .form-header p {
            color: #7f8c8d;
            font-size: 15px;
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
        .form-group textarea {
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
        .form-group textarea:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
            background: white;
        }

        .form-group textarea {
            min-height: 180px;
            resize: vertical;
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
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.4);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        /* Recent Feedbacks */
        .recent-feedbacks-container {
            background: white;
            padding: 45px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-height: 750px;
            overflow-y: auto;
        }

        .recent-feedbacks-container h2 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .recent-feedbacks-container h2 i {
            color: #e74c3c;
        }

        .feedback-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 20px;
            border-left: 5px solid #e74c3c;
            transition: all 0.3s ease;
        }

        .feedback-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .feedback-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 16px;
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
            font-size: 15px;
            margin-bottom: 15px;
        }

        .feedback-actions {
            display: flex;
            justify-content: flex-end;
            padding-top: 15px;
            border-top: 1px solid #e8e8e8;
        }

        .delete-feedback-btn {
            padding: 8px 18px;
            background: transparent;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .delete-feedback-btn:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 16px;
        }

        /* All Feedbacks Section */
        .all-feedbacks-section {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .all-feedbacks-section h2 {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 40px;
            font-weight: 800;
            text-align: center;
        }

        .feedbacks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
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

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .recent-feedbacks-container {
                max-height: 600px;
            }
        }

        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 38px;
            }

            .hero-section p {
                font-size: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .feedback-form-container,
            .recent-feedbacks-container,
            .all-feedbacks-section {
                padding: 30px 25px;
            }

            .feedbacks-grid {
                grid-template-columns: 1fr;
            }

            .form-header h2,
            .recent-feedbacks-container h2,
            .all-feedbacks-section h2 {
                font-size: 24px;
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
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1>We Value Your Feedback</h1>
            <p>Help us improve by sharing your thoughts and experiences with FashionHub</p>
        </div>

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

        <!-- Statistics Cards -->
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
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Feedback Form -->
            <div class="feedback-form-container">
                <div class="form-header">
                    <h2>Share Your Feedback</h2>
                    <p>Your opinion matters to us. Let us know what you think!</p>
                </div>

                <form method="POST" action="Feedback.php">
                    <input type="hidden" name="submit_feedback" value="1">
                    
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

                    <div class="form-group">
                        <label for="message">Your Message *</label>
                        <textarea id="message" name="message" required 
                                  placeholder="Tell us about your experience with FashionHub..."></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Submit Feedback
                    </button>
                </form>
            </div>

            <!-- Recent Feedbacks -->
            <div class="recent-feedbacks-container">
                <h2><i class="fas fa-star"></i> Recent Feedbacks</h2>
                
                <?php if ($feedback_result->num_rows > 0): ?>
                    <?php 
                    $count = 0;
                    $current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                    $feedback_result->data_seek(0); // Reset pointer
                    while ($feedback = $feedback_result->fetch_assoc()): 
                        if ($count >= 5) break; // Show only 5 in this section
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

        <!-- All Feedbacks Section -->
        <?php if ($feedback_result->num_rows > 5): ?>
        <div class="all-feedbacks-section">
            <h2>All Customer Feedbacks</h2>
            
            <div class="feedbacks-grid">
                <?php 
                $current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $feedback_result->data_seek(5); // Start from 6th feedback
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

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
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
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        }
    </script>
      <?php include 'Components/Footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>