<!-- AdminNavbar.php -->
<?php
// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Include database connection if not already included
if (!isset($conn)) {
    // Try different possible paths for db_connect.php
    if (file_exists('../db_connect.php')) {
        include_once '../db_connect.php';
    } elseif (file_exists('db_connect.php')) {
        include_once 'db_connect.php';
    } elseif (file_exists('../../db_connect.php')) {
        include_once '../../db_connect.php';
    }
}

// Fetch admin details from session
$admin_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Admin User';
$admin_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$admin_phone = isset($_SESSION['phone']) ? $_SESSION['phone'] : '';

// Get current time for greeting
$current_hour = date('G');
if ($current_hour < 12) {
    $greeting = "Good Morning";
    $greeting_icon = "fa-sun";
} elseif ($current_hour < 18) {
    $greeting = "Good Afternoon";
    $greeting_icon = "fa-cloud-sun";
} else {
    $greeting = "Good Evening";
    $greeting_icon = "fa-moon";
}

// Fetch pending notifications count from database
$pending_orders = 0;
$unanswered_feedback = 0;

if (isset($conn)) {
    // Get pending orders count
    $pending_orders_query = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 0");
    if ($pending_orders_query) {
        $pending_orders = $pending_orders_query->fetch_assoc()['count'];
    }
    
    // Get unanswered feedback count
    $unanswered_feedback_query = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE admin_reply IS NULL");
    if ($unanswered_feedback_query) {
        $unanswered_feedback = $unanswered_feedback_query->fetch_assoc()['count'];
    }
}

$total_notifications = $pending_orders + $unanswered_feedback;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            overflow-x: hidden;
        }

        /* Alert Messages */
        .alert-container {
            position: fixed;
            top: 85px;
            right: 20px;
            z-index: 2000;
            max-width: 400px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            animation: slideInRight 0.4s ease-out;
            position: relative;
            overflow: hidden;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .alert.hiding {
            animation: slideOutRight 0.4s ease-out;
        }

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        .alert-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .alert-success .alert-icon {
            color: #28a745;
        }

        .alert-error .alert-icon {
            color: #dc3545;
        }

        .alert-message {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-close {
            background: transparent;
            border: none;
            font-size: 18px;
            color: inherit;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            flex-shrink: 0;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Top Navbar Styles */
        .top-navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            height: 70px;
        }

        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 30px;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .navbar-container.expanded {
            margin-left: 80px;
        }

        /* Left Section */
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .menu-toggle-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            font-size: 24px;
            color: #fff;
            cursor: pointer;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .menu-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .greeting-section {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
        }

        .greeting-icon {
            font-size: 24px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .greeting-text h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .greeting-text p {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Right Section */
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quick-stats {
            display: flex;
            gap: 15px;
            margin-right: 10px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .stat-item i {
            font-size: 16px;
        }

        .icon-btn {
            position: relative;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            font-size: 20px;
            color: white;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #ff4757;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
            min-width: 18px;
            text-align: center;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .admin-profile:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            color: white;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
        }

        .profile-role {
            color: rgba(255, 255, 255, 0.8);
            font-size: 12px;
        }

        .profile-dropdown-icon {
            color: white;
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
            transition: all 0.3s ease;
            z-index: 1001;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }

        /* Logo Section */
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 0 25px 30px 25px;
            color: white;
            text-decoration: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .sidebar.collapsed .sidebar-logo {
            justify-content: center;
            padding: 0 10px 30px 10px;
        }

        .logo-icon {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 12px;
            font-size: 24px;
            min-width: 48px;
            text-align: center;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
            white-space: nowrap;
        }

        .sidebar.collapsed .logo-text {
            display: none;
        }

        .sidebar-close-btn {
            position: absolute;
            right: 10px;
            top: 10px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .sidebar-close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Menu Items */
        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            margin-bottom: 30px;
        }

        .menu-title {
            color: rgba(255, 255, 255, 0.6);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 25px;
            margin-bottom: 10px;
        }

        .sidebar.collapsed .menu-title {
            display: none;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar.collapsed .menu-item {
            justify-content: center;
            padding: 15px 0;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left: 4px solid white;
        }

        .menu-item i {
            font-size: 20px;
            min-width: 20px;
            text-align: center;
        }

        .menu-text {
            font-size: 15px;
            font-weight: 500;
            white-space: nowrap;
        }

        .sidebar.collapsed .menu-text {
            display: none;
        }

        /* Main Content Area */
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

        /* Edit Profile Modal */
        .profile-modal {
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

        .profile-modal.active {
            display: flex;
        }

        .profile-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .profile-modal-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .profile-modal-header h3 {
            color: #333;
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-modal-header h3 i {
            color: #667eea;
        }

        .close-profile-modal {
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

        .close-profile-modal:hover {
            color: #333;
            transform: rotate(90deg);
        }

        .profile-form-group {
            margin-bottom: 20px;
        }

        .profile-form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .profile-form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .profile-form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .profile-form-group input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .profile-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-profile-primary,
        .btn-profile-secondary {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-profile-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-profile-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-profile-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-profile-secondary:hover {
            background: #e0e0e0;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .quick-stats {
                display: none;
            }

            .greeting-text p {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }

            .sidebar.mobile-active {
                left: 0;
            }

            .sidebar-close-btn {
                display: flex;
            }

            .navbar-container {
                margin-left: 0;
                padding: 0 15px;
            }

            .main-content {
                margin-left: 0;
            }

            .greeting-section {
                display: none;
            }

            .profile-info {
                display: none;
            }

            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .overlay.active {
                display: block;
            }

            .alert-container {
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .navbar-container {
                padding: 0 15px;
            }

            .main-content {
                padding: 20px 15px;
            }

            .stat-item {
                padding: 6px 12px;
                font-size: 12px;
            }
        }

        @media (min-width: 769px) {
            .menu-toggle-btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle-btn {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Alert Messages Container -->
    <div class="alert-container" id="alertContainer">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle alert-icon"></i>
                <span class="alert-message"><?php echo htmlspecialchars($_SESSION['success']); ?></span>
                <button class="alert-close" onclick="closeAlert(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <span class="alert-message"><?php echo htmlspecialchars($_SESSION['error']); ?></span>
                <button class="alert-close" onclick="closeAlert(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-close-btn" id="sidebarCloseBtn">
            <i class="fas fa-times"></i>
        </button>
        
        <a href="AdminDashboard.php" class="sidebar-logo">
            <div class="logo-icon">
                <i class="fas fa-store"></i>
            </div>
            <span class="logo-text">FashionHub</span>
        </a>

        <nav class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">Main Menu</div>
                <a href="AdminDashboard.php" class="menu-item <?php echo ($current_page == 'AdminDashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
                <a href="ManageProducts.php" class="menu-item <?php echo ($current_page == 'ManageProducts.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tshirt"></i>
                    <span class="menu-text">Products</span>
                </a>
                <a href="ManageCategory.php" class="menu-item <?php echo ($current_page == 'ManageCategory.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span class="menu-text">Categories</span>
                </a>
                <a href="ManageOrders.php" class="menu-item <?php echo ($current_page == 'ManageOrders.php') ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="menu-text">Orders</span>
                </a>
                <a href="ManageFeedbacks.php" class="menu-item <?php echo ($current_page == 'ManageFeedbacks.php') ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    <span class="menu-text">Feedbacks</span>
                </a>
                <a href="ManageUsers.php" class="menu-item <?php echo ($current_page == 'ManageUsers.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span class="menu-text">Customers</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-title">Settings</div>
                <a href="/fashionhub/Homepage.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Top Navbar -->
    <nav class="top-navbar">
        <div class="navbar-container" id="navbarContainer">
            <div class="navbar-left">
                <button class="menu-toggle-btn" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="greeting-section">
                    <i class="fas <?php echo $greeting_icon; ?> greeting-icon"></i>
                    <div class="greeting-text">
                        <h3><?php echo $greeting; ?>!</h3>
                        <p><?php echo date('l, F j, Y'); ?></p>
                    </div>
                </div>
            </div>

            <div class="navbar-right">
                <div class="quick-stats">
                    <a href="ManageOrders.php?status=0" class="stat-item" title="Pending Orders">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $pending_orders; ?> Pending</span>
                    </a>
                    <a href="ManageFeedbacks.php?reply_filter=not_replied" class="stat-item" title="Unanswered Feedback">
                        <i class="fas fa-comment-dots"></i>
                        <span><?php echo $unanswered_feedback; ?> Feedback</span>
                    </a>
                </div>

                

                <div class="admin-profile" id="adminProfile">
                    <div class="profile-img">
                        <?php echo strtoupper(substr($admin_name, 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <span class="profile-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="profile-role">Administrator</span>
                    </div>
                    <i class="fas fa-chevron-down profile-dropdown-icon"></i>
                </div>
            </div>
        </div>
    </nav>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <!-- Edit Profile Modal -->
    <div class="profile-modal" id="profileModal">
        <div class="profile-modal-content">
            <button class="close-profile-modal" id="closeProfileModal">
                <i class="fas fa-times"></i>
            </button>
            <div class="profile-modal-header">
                <h3>
                    <i class="fas fa-user-edit"></i>
                    Edit Profile
                </h3>
            </div>
            <form method="POST" action="update_admin_profile.php" id="profileForm">
                <div class="profile-form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" id="profile_fullname" value="<?php echo htmlspecialchars($admin_name); ?>" required>
                </div>

                <div class="profile-form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="profile_email" value="<?php echo htmlspecialchars($admin_email); ?>" required>
                </div>

                <div class="profile-form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" id="profile_phone" value="<?php echo htmlspecialchars($admin_phone); ?>">
                </div>

                <div class="profile-form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="new_password" id="profile_password" placeholder="Enter new password">
                </div>

                <div class="profile-form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="profile_confirm_password" placeholder="Confirm new password">
                </div>

                <input type="hidden" name="user_id" value="<?php echo $admin_id; ?>">

                <div class="profile-modal-actions">
                    <button type="submit" class="btn-profile-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn-profile-secondary" id="cancelProfileBtn">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
        const adminProfile = document.getElementById('adminProfile');
        const profileModal = document.getElementById('profileModal');
        const closeProfileModal = document.getElementById('closeProfileModal');
        const cancelProfileBtn = document.getElementById('cancelProfileBtn');
        const profileForm = document.getElementById('profileForm');

        // Toggle sidebar for mobile view
        menuToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-active');
            overlay.classList.toggle('active');
        });

        // Close sidebar button
        sidebarCloseBtn.addEventListener('click', function () {
            sidebar.classList.remove('mobile-active');
            overlay.classList.remove('active');
        });

        // Close sidebar when clicking overlay
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('mobile-active');
            overlay.classList.remove('active');
        });

        // Open profile modal
        adminProfile.addEventListener('click', function () {
            profileModal.classList.add('active');
        });

        // Close profile modal
        closeProfileModal.addEventListener('click', function () {
            profileModal.classList.remove('active');
        });

        cancelProfileBtn.addEventListener('click', function () {
            profileModal.classList.remove('active');
        });

        // Close modal when clicking outside
        profileModal.addEventListener('click', function (e) {
            if (e.target === profileModal) {
                profileModal.classList.remove('active');
            }
        });

        // Validate profile form
        profileForm.addEventListener('submit', function (e) {
            const password = document.getElementById('profile_password').value;
            const confirmPassword = document.getElementById('profile_confirm_password').value;

            if (password || confirmPassword) {
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
            }
        });

        // Ensure sidebar resets properly on resize
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-active');
                overlay.classList.remove('active');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && profileModal.classList.contains('active')) {
                profileModal.classList.remove('active');
            }
        });
    </script>
</body>
</html>