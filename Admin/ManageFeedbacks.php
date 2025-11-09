<?php
include '../db_connect.php';
session_start();

// Handle Delete Feedback
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM feedback WHERE id = $id";
    if ($conn->query($sql)) {
        $_SESSION['success'] = "Feedback deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete feedback: " . $conn->error;
    }
    header("Location: ManageFeedbacks.php");
    exit;
}

// Check if reply columns exist, if not create them
$check_columns = $conn->query("SHOW COLUMNS FROM feedback LIKE 'admin_reply'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE feedback ADD COLUMN admin_reply TEXT NULL");
}

$check_columns = $conn->query("SHOW COLUMNS FROM feedback LIKE 'replied_by'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE feedback ADD COLUMN replied_by INT NULL");
}

$check_columns = $conn->query("SHOW COLUMNS FROM feedback LIKE 'replied_at'");
if ($check_columns->num_rows == 0) {
    $conn->query("ALTER TABLE feedback ADD COLUMN replied_at DATETIME NULL");
}

// Handle Reply to Feedback
if (isset($_POST['add_reply'])) {
    $id = intval($_POST['feedback_id']);
    $reply = trim($_POST['reply']);
    $admin_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    if (empty($reply)) {
        $_SESSION['error'] = "Reply message cannot be empty!";
    } else {
        $reply_escaped = $conn->real_escape_string($reply);
        $sql = "UPDATE feedback SET admin_reply = '$reply_escaped', replied_by = $admin_id, replied_at = NOW() WHERE id = $id";
        
        if ($conn->query($sql)) {
            $_SESSION['success'] = "Reply added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add reply: " . $conn->error;
        }
    }
    
    header("Location: ManageFeedbacks.php");
    exit;
}

// Handle Delete Reply
if (isset($_POST['delete_reply'])) {
    $id = intval($_POST['feedback_id']);
    $sql = "UPDATE feedback SET admin_reply = NULL, replied_by = NULL, replied_at = NULL WHERE id = $id";
    
    if ($conn->query($sql)) {
        $_SESSION['success'] = "Reply deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete reply: " . $conn->error;
    }
    
    header("Location: ManageFeedbacks.php");
    exit;
}

// Handle Search and Filter
$search = "";
$reply_filter = "";
$date_filter = "";

if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}

if (isset($_GET['reply_filter']) && $_GET['reply_filter'] != '') {
    $reply_filter = $_GET['reply_filter'];
}

if (isset($_GET['date_filter']) && $_GET['date_filter'] != '') {
    $date_filter = $_GET['date_filter'];
}

// Build query
$query = "SELECT f.*, u.fullname, u.email as user_email 
          FROM feedback f 
          LEFT JOIN users u ON f.user_id = u.id 
          WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (f.name LIKE '%$search%' OR f.email LIKE '%$search%' OR f.message LIKE '%$search%')";
}

if ($reply_filter === 'replied') {
    $query .= " AND f.admin_reply IS NOT NULL";
} elseif ($reply_filter === 'not_replied') {
    $query .= " AND f.admin_reply IS NULL";
}

if (!empty($date_filter)) {
    switch($date_filter) {
        case 'today':
            $query .= " AND DATE(f.submitted_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND f.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND f.submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

$query .= " ORDER BY f.submitted_at DESC";
$result = $conn->query($query);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN admin_reply IS NULL THEN 1 END) as not_replied,
    COUNT(CASE WHEN admin_reply IS NOT NULL THEN 1 END) as replied,
    COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent,
    COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today
    FROM feedback";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Feedback | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 70px);
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            color: inherit;
            opacity: 0.5;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @keyframes slideDown {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .breadcrumb {
            color: #999;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.pending { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.reviewed { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-icon.replied { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-icon.recent { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 14px;
            color: #666;
        }

        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .toolbar-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box button:hover {
            background: #5568d3;
        }

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .clear-filters-btn {
            background: #f0f0f0;
            color: #666;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .clear-filters-btn:hover {
            background: #e0e0e0;
        }

        .feedback-grid {
            display: grid;
            gap: 20px;
        }

        .feedback-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .feedback-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 15px;
        }

        .feedback-user {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }

        .user-info h4 {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }

        .user-info p {
            font-size: 13px;
            color: #999;
        }

        .feedback-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .feedback-date {
            font-size: 13px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.resolved {
            background: #d4edda;
            color: #155724;
        }

        .status-badge i {
            margin-right: 4px;
        }

        .feedback-message {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.6;
            color: #333;
            border-left: 4px solid #667eea;
        }

        .feedback-reply {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #43e97b;
        }

        .feedback-reply h5 {
            font-size: 14px;
            color: #2e7d32;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feedback-reply p {
            font-size: 14px;
            color: #1b5e20;
            line-height: 1.6;
        }

        .feedback-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .reply-btn {
            background: #667eea;
            color: white;
        }

        .reply-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .status-btn {
            background: #4facfe;
            color: white;
        }

        .status-btn:hover {
            background: #3d9ae2;
            transform: translateY(-2px);
        }

        .delete-btn {
            background: #ff4757;
            color: white;
        }

        .delete-btn:hover {
            background: #e63946;
            transform: translateY(-2px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            margin-bottom: 25px;
        }

        .modal-header h3 {
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-primary,
        .btn-secondary {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #666;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
                margin-top: 70px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .toolbar-row {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .feedback-header {
                flex-direction: column;
            }

            .feedback-meta {
                align-items: flex-start;
            }

            .feedback-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <div class="main-content" id="mainContentFeedback">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Manage Customer Feedback</h2>
            <div class="breadcrumb">
                <a href="AdminDashboard.php">Dashboard</a> / Feedback
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Feedback</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['not_replied']; ?></h3>
                    <p>Not Replied</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon replied">
                    <i class="fas fa-reply"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['replied']; ?></h3>
                    <p>Replied</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon recent">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['recent']; ?></h3>
                    <p>This Week</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon reviewed">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['today']; ?></h3>
                    <p>Today</p>
                </div>
            </div>
        </div>

        <div class="toolbar">
            <form method="GET" action="ManageFeedbacks.php">
                <div class="toolbar-row">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search feedback by name, email, or message..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </div>
                    
                    <select name="reply_filter" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Feedback</option>
                        <option value="replied" <?php echo $reply_filter == 'replied' ? 'selected' : ''; ?>>Replied</option>
                        <option value="not_replied" <?php echo $reply_filter == 'not_replied' ? 'selected' : ''; ?>>Not Replied</option>
                    </select>

                    <select name="date_filter" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Time</option>
                        <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>

                    <?php if (!empty($search) || !empty($reply_filter) || !empty($date_filter)): ?>
                        <a href="ManageFeedbacks.php" class="clear-filters-btn">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="feedback-grid">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="feedback-card">
                        <div class="feedback-header">
                            <div class="feedback-user">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($row['email']); ?></p>
                                    <?php if ($row['user_id']): ?>
                                        <p style="color: #667eea; font-size: 12px;">
                                            <i class="fas fa-user-check"></i> Registered User
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="feedback-meta">
                                <div class="feedback-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y - h:i A', strtotime($row['submitted_at'])); ?>
                                </div>
                                <?php if (!empty($row['admin_reply'])): ?>
                                    <span class="status-badge resolved">
                                        <i class="fas fa-check"></i> Replied
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge pending">
                                        <i class="fas fa-clock"></i> No Reply
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="feedback-message">
                            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                        </div>

                        <?php if (!empty($row['admin_reply'])): ?>
                            <div class="feedback-reply">
                                <h5>
                                    <i class="fas fa-reply"></i>
                                    Admin Reply 
                                    <span style="font-weight: normal; font-size: 12px;">
                                        (<?php echo date('M d, Y', strtotime($row['replied_at'])); ?>)
                                    </span>
                                </h5>
                                <p><?php echo nl2br(htmlspecialchars($row['admin_reply'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="feedback-actions">
                            <button class="action-btn reply-btn" 
                                    data-id="<?php echo $row['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                    data-message="<?php echo htmlspecialchars($row['message']); ?>"
                                    data-reply="<?php echo htmlspecialchars($row['admin_reply'] ?? ''); ?>">
                                <i class="fas fa-reply"></i> <?php echo !empty($row['admin_reply']) ? 'Update Reply' : 'Reply'; ?>
                            </button>
                            
                            <?php if (!empty($row['admin_reply'])): ?>
                                <button type="button" class="action-btn status-btn" data-feedback-id="<?php echo $row['id']; ?>" data-action="delete-reply">
                                    <i class="fas fa-eraser"></i> Delete Reply
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="action-btn delete-btn" data-feedback-id="<?php echo $row['id']; ?>" data-action="delete-feedback">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Feedback Found</h3>
                    <p>There are no feedback messages matching your criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="replyModal">
        <div class="modal-content">
            <button class="close-modal" type="button">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Reply to Feedback</h3>
            </div>
            <form method="POST" action="ManageFeedbacks.php">
                <input type="hidden" name="feedback_id" id="reply_feedback_id">
                
                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" id="reply_customer_name" readonly style="background: #f8f9fa;">
                </div>

                <div class="form-group">
                    <label>Original Message</label>
                    <textarea id="reply_original_message" readonly style="background: #f8f9fa;"></textarea>
                </div>

                <div class="form-group">
                    <label>Your Reply *</label>
                    <textarea name="reply" id="reply_text" required placeholder="Type your reply here..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="add_reply" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                    <button type="button" class="btn-secondary" id="cancelModalBtn">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Use IIFE to avoid global scope conflicts
        (function() {
            'use strict';
            
            // Get modal elements
            const replyModal = document.getElementById('replyModal');
            const closeModalBtn = document.querySelector('.close-modal');
            const cancelModalBtn = document.getElementById('cancelModalBtn');
            
            // Function to open reply modal
            function openReplyModal(button) {
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const message = button.getAttribute('data-message');
                const reply = button.getAttribute('data-reply');
                
                console.log('Opening modal:', {id, name, message, reply});
                
                document.getElementById('reply_feedback_id').value = id;
                document.getElementById('reply_customer_name').value = name;
                document.getElementById('reply_original_message').value = message;
                document.getElementById('reply_text').value = reply;
                
                replyModal.classList.add('active');
            }
            
            // Function to close reply modal
            function closeReplyModal() {
                replyModal.classList.remove('active');
                document.querySelector('#replyModal form').reset();
            }
            
            // Function to delete reply
            function deleteReply(id) {
                if (confirm('Are you sure you want to delete this reply?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'ManageFeedbacks.php';
                    
                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_reply';
                    deleteInput.value = '1';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'feedback_id';
                    idInput.value = id;
                    
                    form.appendChild(deleteInput);
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
            
            // Function to delete feedback
            function deleteFeedback(id) {
                if (confirm('Are you sure you want to delete this feedback? This action cannot be undone.')) {
                    window.location.href = 'ManageFeedbacks.php?delete=' + id;
                }
            }
            
            // Event listeners for reply buttons
            document.querySelectorAll('.reply-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    openReplyModal(this);
                });
            });
            
            // Event listeners for action buttons
            document.querySelectorAll('[data-action]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    const feedbackId = this.getAttribute('data-feedback-id');
                    
                    if (action === 'delete-reply') {
                        deleteReply(feedbackId);
                    } else if (action === 'delete-feedback') {
                        deleteFeedback(feedbackId);
                    }
                });
            });
            
            // Close modal button
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', closeReplyModal);
            }
            
            if (cancelModalBtn) {
                cancelModalBtn.addEventListener('click', closeReplyModal);
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === replyModal) {
                    closeReplyModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && replyModal.classList.contains('active')) {
                    closeReplyModal();
                }
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.alert').forEach(function(alert) {
                    alert.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                });
            }, 5000);
            
            // Manual close alert
            document.querySelectorAll('.alert-close').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const alert = this.parentElement;
                    alert.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                });
            });
            
        })();
    </script>
</body>
</html>