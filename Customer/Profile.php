<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_profile') {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        if (empty($fullname) || empty($email)) {
            $_SESSION['profile_message'] = "Name and email are required!";
            $_SESSION['profile_messageType'] = "error";
        } else {
            $update_query = "UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['fullname'] = $fullname;
                $_SESSION['profile_message'] = "Profile updated successfully!";
                $_SESSION['profile_messageType'] = "success";
            } else {
                $_SESSION['profile_message'] = "Error updating profile. Please try again.";
                $_SESSION['profile_messageType'] = "error";
            }
            $stmt->close();
        }
        header("Location: Profile.php");
        exit;
    }
    
    if ($_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($new_password) < 6) {
            $_SESSION['profile_message'] = "Password must be at least 6 characters!";
            $_SESSION['profile_messageType'] = "error";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['profile_message'] = "Passwords do not match!";
            $_SESSION['profile_messageType'] = "error";
        } else {
            // Verify current password
            $verify_query = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($verify_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $stmt->close();
            
            if (password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['profile_message'] = "Password changed successfully!";
                    $_SESSION['profile_messageType'] = "success";
                } else {
                    $_SESSION['profile_message'] = "Error changing password. Please try again.";
                    $_SESSION['profile_messageType'] = "error";
                }
                $stmt->close();
            } else {
                $_SESSION['profile_message'] = "Current password is incorrect!";
                $_SESSION['profile_messageType'] = "error";
            }
        }
        header("Location: Profile.php");
        exit;
    }
}

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get flash messages
$profile_message = $_SESSION['profile_message'] ?? '';
$profile_messageType = $_SESSION['profile_messageType'] ?? '';
unset($_SESSION['profile_message'], $_SESSION['profile_messageType']);

// Default avatar
$user_avatar = 'https://static.vecteezy.com/system/resources/previews/009/749/751/large_2x/avatar-man-icon-cartoon-male-profile-mascot-illustration-head-face-business-user-logo-free-vector.jpg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
            padding-top: 70px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        /* Grid Layout */
        .profile-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            height: fit-content;
            position: sticky;
            top: 90px;
        }

        .avatar-container {
            margin-bottom: 25px;
        }

        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid #e74c3c;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.2);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .profile-email {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }

        .stat-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #e8e8e8;
        }

        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 800;
            color: #e74c3c;
        }

        /* Form Sections */
        .form-section {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .section-header {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            font-size: 24px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header h2 i {
            color: #e74c3c;
        }

        .section-header p {
            font-size: 14px;
            color: #7f8c8d;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }

        .form-group input:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
        }

        .form-group input:disabled {
            background: #f8f9fa;
            color: #7f8c8d;
            cursor: not-allowed;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #2c3e50;
            border: 2px solid #e8e8e8;
        }

        .btn-secondary:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .profile-card {
                position: static;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }

            .container {
                padding: 20px 15px;
            }

 

            .form-section {
                padding: 25px 20px;
            }

            .profile-stats {
                grid-template-columns: 1fr;
            }
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 100px;
            right: -400px;
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            z-index: 10000;
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
            border-left: 5px solid #e74c3c;
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
            color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
            color: #2c3e50;
        }

        .toast-message {
            font-size: 14px;
            color: #7f8c8d;
        }

        .toast-close {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #7f8c8d;
            transition: all 0.3s;
            padding: 5px;
        }

        .toast-close:hover {
            color: #2c3e50;
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
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
        }
    </style>
</head>
<body>
    <?php include 'Components/CustomerNavBar.php'; ?>

    <div class="container">
       

       <?php if (!empty($profile_message)): ?>
            <div class="toast <?php echo $profile_messageType; ?>">
                <div class="toast-icon">
                    <i class="fas fa-<?php echo $profile_messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title"><?php echo $profile_messageType === 'success' ? 'Success!' : 'Error!'; ?></div>
                    <div class="toast-message"><?php echo htmlspecialchars($profile_message); ?></div>
                </div>
                <button class="toast-close">&times;</button>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Profile Card -->
            <aside class="profile-card">
                <div class="avatar-container">
                    <div class="avatar">
                        <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Profile Avatar">
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>

                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-label">Role</div>
                        <div class="stat-value"><?php echo $user['userrole'] == 1 ? 'Admin' : 'Customer'; ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">User ID</div>
                        <div class="stat-value">#<?php echo htmlspecialchars($user['id']); ?></div>
                    </div>
                    <div class="stat-item" style="grid-column: 1 / -1;">
                        <div class="stat-label">Member Since</div>
                        <div class="stat-value" style="font-size: 16px;">
                            <?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Forms Section -->
            <div>
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-user"></i> Personal Information</h2>
                        <p>Update your name, email and phone number</p>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullname">Full Name *</label>
                                <input type="text" id="fullname" name="fullname" 
                                       value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Account ID</label>
                                <input type="text" value="#<?php echo htmlspecialchars($user['id']); ?>" disabled>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" 
                                    onclick="window.scrollTo({top: document.getElementById('passwordSection').offsetTop - 90, behavior: 'smooth'})">
                                <i class="fas fa-lock"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="form-section" id="passwordSection">
                    <div class="section-header">
                        <h2><i class="fas fa-lock"></i> Change Password</h2>
                        <p>Update your password to keep your account secure</p>
                    </div>

                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" 
                                       minlength="6" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       minlength="6" required>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>

    <script>
        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (confirmPassword && newPassword) {
            confirmPassword.addEventListener('input', function() {
                if (confirmPassword.value !== newPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
        }

        // Prevent double submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }, 3000);
                }
            });
        });

        // Toast Notification Handler
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
    </script>
</body>
</html>