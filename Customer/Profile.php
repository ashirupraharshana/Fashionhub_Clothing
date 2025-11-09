<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// CRITICAL FIX: Store and clear profile messages BEFORE any includes
$profile_message = "";
$profile_messageType = "";

if (isset($_SESSION['profile_message'])) {
    $profile_message = $_SESSION['profile_message'];
    $profile_messageType = $_SESSION['profile_messageType'];
    
    // Clear immediately
    unset($_SESSION['profile_message']);
    unset($_SESSION['profile_messageType']);
}

// Also clear any navbar messages to prevent conflicts
if (isset($_SESSION['message'])) {
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

include '../db_connect.php';

// Fetch user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    if (empty($fullname) || empty($email)) {
        $_SESSION['profile_message'] = "Name and email are required fields.";
        $_SESSION['profile_messageType'] = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['profile_message'] = "Invalid email address.";
        $_SESSION['profile_messageType'] = "error";
    } else {
        // Check if email already exists for another user
        $checkQuery = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['profile_message'] = "This email is already registered to another account.";
            $_SESSION['profile_messageType'] = "error";
        } else {
            $updateQuery = "UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['fullname'] = $fullname;
                $_SESSION['profile_message'] = "Profile updated successfully!";
                $_SESSION['profile_messageType'] = "success";
            } else {
                $_SESSION['profile_message'] = "Error updating profile.";
                $_SESSION['profile_messageType'] = "error";
            }
        }
        $stmt->close();
    }
    header("Location: Profile.php");
    exit();
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['profile_message'] = "All password fields are required.";
        $_SESSION['profile_messageType'] = "error";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['profile_message'] = "New passwords do not match.";
        $_SESSION['profile_messageType'] = "error";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['profile_message'] = "Password must be at least 6 characters long.";
        $_SESSION['profile_messageType'] = "error";
    } else {
        // Verify current password
        $query = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (password_verify($current_password, $user_data['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['profile_message'] = "Password changed successfully!";
                $_SESSION['profile_messageType'] = "success";
            } else {
                $_SESSION['profile_message'] = "Error changing password.";
                $_SESSION['profile_messageType'] = "error";
            }
        } else {
            $_SESSION['profile_message'] = "Current password is incorrect.";
            $_SESSION['profile_messageType'] = "error";
        }
        $stmt->close();
    }
    header("Location: Profile.php");
    exit();
}

$conn->close();

// Profile avatar URL
$user_avatar = "https://static.vecteezy.com/system/resources/previews/009/749/751/large_2x/avatar-man-icon-cartoon-male-profile-mascot-illustration-head-face-business-user-logo-free-vector.jpg";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fef5f5 0%, #fdeef0 100%);
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px 60px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            color: #e74c3c;
            padding: 12px 24px;
            border-radius: 14px;
            text-decoration: none;
            margin-bottom: 30px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 2px solid transparent;
        }

        .back-button:hover {
            border-color: #e74c3c;
            transform: translateX(-5px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.2);
        }

        .alert {
            padding: 18px 24px;
            border-radius: 14px;
            margin-bottom: 30px;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 100;
        }

        .alert.success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .alert.error {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .alert i {
            font-size: 20px;
        }

        .profile-header {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.97) 0%, rgba(192, 57, 43, 0.97) 100%),
                        url('https://static.vecteezy.com/system/resources/thumbnails/049/048/199/small_2x/red-silk-fabric-with-folds-and-folds-free-photo.jpg');
            background-size: cover;
            background-position: center;
            background-blend-mode: multiply;
            border-radius: 24px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(231, 76, 60, 0.35);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 65%);
            border-radius: 50%;
        }

        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
            border: 6px solid rgba(255, 255, 255, 0.4);
            position: relative;
            z-index: 1;
            overflow: hidden;
            background-image: url('<?php echo $user_avatar; ?>');
            background-size: cover;
            background-position: center;
        }

        .profile-avatar-fallback {
            font-size: 55px;
            color: #e74c3c;
            font-weight: 800;
            display: none;
        }

        .profile-header h1 {
            font-size: 36px;
            font-weight: 900;
            color: white;
            margin-bottom: 12px;
            position: relative;
            z-index: 1;
            text-shadow: 0 3px 15px rgba(0,0,0,0.2);
            letter-spacing: -0.5px;
        }

        .profile-header p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
        }

        .profile-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(231, 76, 60, 0.08);
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.12);
            border-color: rgba(231, 76, 60, 0.15);
        }

        .profile-card h2 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .profile-card h2 i {
            color: #e74c3c;
            font-size: 26px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e8eef3;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
        }

        .form-group input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
            color: #7f8c8d;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            padding: 16px 36px;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(231, 76, 60, 0.45);
        }

        .btn-danger {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(192, 57, 43, 0.35);
        }

        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(192, 57, 43, 0.45);
        }

        .info-box {
            background: linear-gradient(135deg, #fef5f5 0%, #fdeef0 100%);
            border-left: 5px solid #e74c3c;
            padding: 20px 24px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .info-box p {
            margin: 8px 0;
            color: #2c3e50;
            font-size: 14px;
            font-weight: 600;
        }

        .info-box strong {
            color: #e74c3c;
            font-weight: 800;
        }

        .account-info-card {
            grid-column: 1 / -1;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: linear-gradient(135deg, #fef5f5 0%, #fdeef0 100%);
            padding: 20px;
            border-radius: 14px;
            border: 2px solid rgba(231, 76, 60, 0.1);
        }

        .info-item-label {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .info-item-value {
            font-size: 18px;
            color: #2c3e50;
            font-weight: 800;
        }

        @media (max-width: 1024px) {
            .profile-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px 50px;
            }

            .profile-header {
                padding: 40px 25px;
            }

            .profile-header h1 {
                font-size: 28px;
            }

            .profile-avatar {
                width: 110px;
                height: 110px;
            }

            .profile-card {
                padding: 30px 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/CustomerNavBar.php'; ?>

    <div class="container">
        <?php if (!empty($profile_message)): ?>
            <div class="alert <?php echo htmlspecialchars($profile_messageType); ?>" id="profileAlert">
                <i class="fas fa-<?php echo $profile_messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($profile_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-header">
            <div class="profile-avatar">
                <span class="profile-avatar-fallback">
                    <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                </span>
            </div>
            <h1><?php echo htmlspecialchars($user['fullname']); ?></h1>
            <p>Member since <?php echo date('F Y', strtotime($user['created_at'] ?? 'now')); ?></p>
        </div>

        <div class="profile-cards-grid">
            <!-- Personal Information -->
            <div class="profile-card">
                <h2>
                    <i class="fas fa-user-edit"></i>
                    Personal Information
                </h2>
                
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

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Account ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['id']); ?>" disabled>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-card">
                <h2>
                    <i class="fas fa-lock"></i>
                    Change Password
                </h2>

                <div class="info-box">
                    <p><strong>Password Requirements:</strong></p>
                    <p>• Minimum 6 characters</p>
                    <p>• Use a strong and unique password</p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" 
                               placeholder="Enter your current password" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" 
                                   placeholder="Enter new password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-key"></i>
                        Change Password
                    </button>
                </form>
            </div>

            <!-- Account Information -->
            <div class="profile-card account-info-card">
                <h2>
                    <i class="fas fa-info-circle"></i>
                    Account Information
                </h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-item-label">Account Type</div>
                        <div class="info-item-value">
                            <?php echo $user['userrole'] == 1 ? 'Administrator' : 'Customer'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Account Status</div>
                        <div class="info-item-value">Active</div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Member Since</div>
                        <div class="info-item-value">
                            <?php echo date('F d, Y', strtotime($user['created_at'] ?? 'now')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>

    <script>
        // ONLY hide the profile alert, not navbar alert
        document.addEventListener('DOMContentLoaded', function() {
            const profileAlert = document.getElementById('profileAlert');
            
            if (profileAlert) {
                let hideTimeout = setTimeout(function() {
                    profileAlert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    profileAlert.style.opacity = '0';
                    profileAlert.style.transform = 'translateY(-20px)';
                    
                    setTimeout(function() {
                        if (profileAlert && profileAlert.parentNode) {
                            profileAlert.remove();
                        }
                    }, 300);
                }, 5000);
            }
        });

        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
        }

        // Handle user avatar fallback
        const profileAvatar = document.querySelector('.profile-avatar');
        const fallback = document.querySelector('.profile-avatar-fallback');
        
        if (profileAvatar && fallback) {
            const img = new Image();
            img.src = '<?php echo $user_avatar; ?>';
            img.onerror = function() {
                profileAvatar.style.backgroundImage = 'none';
                profileAvatar.style.background = 'white';
                fallback.style.display = 'block';
            };
        }
    </script>
</body>
</html>