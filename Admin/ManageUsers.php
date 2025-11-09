<?php
include '../db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['userrole'] != 1) {
    header("Location: /fashionhub/Homepage.php");
    exit();
}

// Handle Add User
if (isset($_POST['add_user'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $userrole = intval($_POST['userrole']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address.";
        header("Location: ManageUsers.php");
        exit();
    }
    
    // Check if email already exists
    $checkEmail = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists!";
        header("Location: ManageUsers.php");
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $sql = "INSERT INTO users (fullname, email, phone, password, userrole) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $fullname, $email, $phone, $hashed_password, $userrole);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "User added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add user: " . $conn->error;
    }
    
    header("Location: ManageUsers.php");
    exit();
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $fullname = trim($_POST['edit_fullname']);
    $email = trim($_POST['edit_email']);
    $phone = trim($_POST['edit_phone']);
    $userrole = intval($_POST['edit_userrole']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address.";
        header("Location: ManageUsers.php");
        exit();
    }
    
    // Check if email exists for another user
    $checkEmail = "SELECT id FROM users WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists for another user!";
        header("Location: ManageUsers.php");
        exit();
    }
    
    // Update user
    $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?, userrole = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $fullname, $email, $phone, $userrole, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "User updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update user: " . $conn->error;
    }
    
    header("Location: ManageUsers.php");
    exit();
}

// Handle Reset Password
if (isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = trim($_POST['new_password']);
    
    if (strlen($new_password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
        header("Location: ManageUsers.php");
        exit();
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Password reset successfully!";
    } else {
        $_SESSION['error'] = "Failed to reset password.";
    }
    
    header("Location: ManageUsers.php");
    exit();
}

// Handle Delete User
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    // Prevent deleting own account
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account!";
        header("Location: ManageUsers.php");
        exit();
    }
    
    if ($conn->query("DELETE FROM users WHERE id = $user_id")) {
        $_SESSION['success'] = "User deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete user.";
    }
    
    header("Location: ManageUsers.php");
    exit();
}

// Handle Search and Filter
$search = "";
$roleFilter = "";
$whereClause = "WHERE 1=1";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $whereClause .= " AND (fullname LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}

if (isset($_GET['role']) && $_GET['role'] !== '') {
    $roleFilter = intval($_GET['role']);
    $whereClause .= " AND userrole = $roleFilter";
}

$query = "SELECT * FROM users $whereClause ORDER BY created_at DESC";
$result = $conn->query($query);

// Get user statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'admins' => $conn->query("SELECT COUNT(*) as count FROM users WHERE userrole = 1")->fetch_assoc()['count'],
    'customers' => $conn->query("SELECT COUNT(*) as count FROM users WHERE userrole = 0")->fetch_assoc()['count'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | FashionHub</title>
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

        /* Alert Messages */
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

        /* Page Header */
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            gap: 20px;
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

        .stat-icon.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.admins {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.customers {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #999;
            font-size: 14px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .search-filter-group {
            display: flex;
            gap: 15px;
            flex: 1;
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

        .add-user-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .add-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Users Table */
        .users-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .users-table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .users-table td {
            padding: 18px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #333;
        }

        .users-table tbody tr {
            transition: all 0.3s ease;
        }

        .users-table tbody tr:hover {
            background: #f8f9fa;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .user-details h4 {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }

        .user-details p {
            font-size: 13px;
            color: #999;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-badge.admin {
            background: rgba(255, 71, 87, 0.1);
            color: #ff4757;
        }

        .role-badge.customer {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .edit-btn {
            color: #667eea;
        }

        .edit-btn:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .reset-btn {
            color: #f39c12;
        }

        .reset-btn:hover {
            background: rgba(243, 156, 18, 0.1);
        }

        .delete-btn {
            color: #ff4757;
        }

        .delete-btn:hover {
            background: rgba(255, 71, 87, 0.1);
        }

        /* Modal Styles */
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
            max-width: 500px;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .alert {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: calc(100% - 20px);
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-filter-group {
                flex-direction: column;
            }

            .search-box {
                min-width: 100%;
            }

            .add-user-btn {
                width: 100%;
                justify-content: center;
            }

            .users-table-container {
                overflow-x: auto;
            }

            .users-table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Alert Messages -->
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

        <!-- Page Header -->
        <div class="page-header">
            <h2>Manage Users</h2>
            <div class="breadcrumb">
                <a href="AdminDashboard.php">Dashboard</a> / Users
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon admins">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['admins']; ?></h3>
                    <p>Administrators</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon customers">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['customers']; ?></h3>
                    <p>Customers</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-filter-group">
                <div class="search-box">
                    <form method="GET" action="ManageUsers.php">
                        <input type="text" name="search" placeholder="Search by name, email, or phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!empty($roleFilter)): ?>
                            <input type="hidden" name="role" value="<?php echo $roleFilter; ?>">
                        <?php endif; ?>
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <select class="filter-select" onchange="filterByRole(this.value)">
                    <option value="">All Roles</option>
                    <option value="1" <?php echo $roleFilter === 1 ? 'selected' : ''; ?>>Administrators</option>
                    <option value="0" <?php echo $roleFilter === 0 ? 'selected' : ''; ?>>Customers</option>
                </select>
            </div>
            <button class="add-user-btn" onclick="openAddModal()">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <?php if ($result->num_rows > 0): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($row['fullname'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo htmlspecialchars($row['fullname']); ?></h4>
                                            <p><?php echo htmlspecialchars($row['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['phone'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $row['userrole'] == 1 ? 'admin' : 'customer'; ?>">
                                        <?php echo $row['userrole'] == 1 ? 'Administrator' : 'Customer'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn edit-btn" 
                                                onclick='openEditModal(<?php echo json_encode($row); ?>)' 
                                                title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn reset-btn" 
                                                onclick="openResetModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['fullname']); ?>')" 
                                                title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                            <button class="action-btn delete-btn" 
                                                    onclick="deleteUser(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['fullname']); ?>')" 
                                                    title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Users Found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeAddModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Add New User</h3>
            </div>
            <form method="POST" onsubmit="return validateAddForm()">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="fullname" required placeholder="Enter full name">
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="Enter email address">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="Enter phone number">
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" id="add_password" required 
                           placeholder="Enter password (min. 6 characters)">
                </div>

                <div class="form-group">
                    <label>User Role *</label>
                    <select name="userrole" required>
                        <option value="0">Customer</option>
                        <option value="1">Administrator</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="add_user" class="btn-primary">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Edit User</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="edit_fullname" id="edit_fullname" required>
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="edit_email" id="edit_email" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="edit_phone" id="edit_phone">
                </div>

                <div class="form-group">
                    <label>User Role *</label>
                    <select name="edit_userrole" id="edit_userrole" required>
                        <option value="0">Customer</option>
                        <option value="1">Administrator</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="edit_user" class="btn-primary">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal" id="resetModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeResetModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Reset Password</h3>
            </div>
            <form method="POST" onsubmit="return validateResetForm()">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="reset_user_name" disabled>
                </div>

                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" id="reset_password" required 
                           placeholder="Enter new password (min. 6 characters)">
                </div>

                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" id="reset_confirm_password" required 
                           placeholder="Confirm new password">
                </div>

                <div class="modal-actions">
                    <button type="submit" name="reset_password" class="btn-primary">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeResetModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter by role
        function filterByRole(role) {
            const url = new URL(window.location.href);
            if (role) {
                url.searchParams.set('role', role);
            } else {
                url.searchParams.delete('role');
            }
            const search = url.searchParams.get('search');
            if (search) {
                url.searchParams.set('search', search);
            }
            window.location.href = url.toString();
        }

        // Add User Modal
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.querySelector('#addModal form').reset();
        }

        function validateAddForm() {
            const password = document.getElementById('add_password').value;
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            return true;
        }

        // Edit User Modal
        function openEditModal(data) {
            document.getElementById('editModal').classList.add('active');
            document.getElementById('edit_user_id').value = data.id;
            document.getElementById('edit_fullname').value = data.fullname;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_phone').value = data.phone || '';
            document.getElementById('edit_userrole').value = data.userrole;
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.querySelector('#editModal form').reset();
        }

        // Reset Password Modal
        function openResetModal(userId, userName) {
            document.getElementById('resetModal').classList.add('active');
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_name').value = userName;
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
            document.querySelector('#resetModal form').reset();
        }

        function validateResetForm() {
            const password = document.getElementById('reset_password').value;
            const confirmPassword = document.getElementById('reset_confirm_password').value;
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            return true;
        }

        // Delete User
        function deleteUser(userId, userName) {
            if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
                window.location.href = 'ManageUsers.php?delete=' + userId;
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const resetModal = document.getElementById('resetModal');
            
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == resetModal) {
                closeResetModal();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Manual close alerts
        document.querySelectorAll('.alert-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const alert = this.parentElement;
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        });

        // Add slideOut animation
        const style = document.createElement('style');
        style.textContent = `
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
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>