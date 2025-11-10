<?php
// update_admin_profile.php
include '../db_connect.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['userrole'] != 1) {
    header("Location: /fashionhub/Homepage.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = intval($_POST['user_id']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Verify the user is updating their own profile
    if ($user_id != $_SESSION['user_id']) {
        $_SESSION['error'] = "Unauthorized action!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email address!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Check if email exists for another user
    $checkEmail = "SELECT id FROM users WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Update profile with or without password
    if (!empty($new_password)) {
        // Validate password
        if (strlen($new_password) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters long!";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }
        
        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match!";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }
        
        // Hash new password and update
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET fullname = ?, email = ?, phone = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $fullname, $email, $phone, $hashed_password, $user_id);
    } else {
        // Update without changing password
        $sql = "UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
    }
    
    if ($stmt->execute()) {
        // Update session variables
        $_SESSION['fullname'] = $fullname;
        $_SESSION['email'] = $email;
        $_SESSION['phone'] = $phone;
        
        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update profile: " . $conn->error;
    }
    
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
} else {
    header("Location: AdminDashboard.php");
    exit();
}
?>