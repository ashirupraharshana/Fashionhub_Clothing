<!-- AdminNavbar.php -->
<?php
// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
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

        /* Top Navbar Styles */
        .top-navbar {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
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
            background: transparent;
            border: none;
            font-size: 24px;
            color: #333;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .menu-toggle-btn:hover {
            background: #f5f7fa;
        }

        .search-bar {
            position: relative;
            width: 350px;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-bar i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        /* Right Section */
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .icon-btn {
            position: relative;
            background: transparent;
            border: none;
            font-size: 20px;
            color: #666;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .icon-btn:hover {
            background: #f5f7fa;
            color: #667eea;
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
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .admin-profile:hover {
            background: #f5f7fa;
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #667eea;
            object-fit: cover;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            color: #333;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.2;
        }

        .profile-role {
            color: #999;
            font-size: 12px;
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

        .menu-badge {
            margin-left: auto;
            background: #ff4757;
            color: white;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .sidebar.collapsed .menu-badge {
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

        /* Responsive Design */
        @media (max-width: 1024px) {
            .search-bar {
                width: 250px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }

            .sidebar.mobile-active {
                left: 0;
            }

            .navbar-container {
                margin-left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .search-bar {
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
        }

        @media (max-width: 480px) {
            .navbar-container {
                padding: 0 15px;
            }

            .main-content {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
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
                <a href="ManageProducts.php" class="menu-item <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tshirt"></i>
                    <span class="menu-text">Products</span>
                    <span class="menu-badge">245</span>
                </a>
                <a href="ManageCategory.php" class="menu-item <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span class="menu-text">Categories</span>
                </a>
                <a href="orders.php" class="menu-item <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="menu-text">Orders</span>
                    <span class="menu-badge">12</span>
                </a>
                <a href="customers.php" class="menu-item <?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span class="menu-text">Customers</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-title">Management</div>
                <a href="inventory.php" class="menu-item <?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span class="menu-text">Inventory</span>
                </a>
                <a href="reports.php" class="menu-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span class="menu-text">Reports</span>
                </a>
                <a href="promotions.php" class="menu-item <?php echo ($current_page == 'promotions.php') ? 'active' : ''; ?>">
                    <i class="fas fa-percent"></i>
                    <span class="menu-text">Promotions</span>
                </a>
                <a href="reviews.php" class="menu-item <?php echo ($current_page == 'reviews.php') ? 'active' : ''; ?>">
                    <i class="fas fa-star"></i>
                    <span class="menu-text">Reviews</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-title">Settings</div>
                <a href="settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span class="menu-text">Settings</span>
                </a>
                <a href="help.php" class="menu-item <?php echo ($current_page == 'help.php') ? 'active' : ''; ?>">
                    <i class="fas fa-question-circle"></i>
                    <span class="menu-text">Help Center</span>
                </a>
                <a href="logout.php" class="menu-item <?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
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
                <div class="search-bar">
                    <input type="text" placeholder="Search products, orders, customers...">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="navbar-right">
                <button class="icon-btn">
                    <i class="fas fa-bell"></i>
                    <span class="badge">5</span>
                </button>
                <button class="icon-btn">
                    <i class="fas fa-envelope"></i>
                    <span class="badge">3</span>
                </button>
                <div class="admin-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=667eea&color=fff" alt="Admin" class="profile-img">
                    <div class="profile-info">
                        <span class="profile-name">Admin User</span>
                        <span class="profile-role">Administrator</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </nav>

    <!-- Overlay for mobile -->
    <div class="overlay" id="overlay"></div>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const navbarContainer = document.getElementById('navbarContainer');
        const overlay = document.getElementById('overlay');

        menuToggle.addEventListener('click', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
                if (mainContent) mainContent.classList.toggle('expanded');
                navbarContainer.classList.toggle('expanded');
            } else {
                sidebar.classList.toggle('mobile-active');
                overlay.classList.toggle('active');
            }
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-active');
            overlay.classList.remove('active');
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-active');
                overlay.classList.remove('active');
            } else {
                sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('expanded');
                navbarContainer.classList.remove('expanded');
            }
        });
    </script>