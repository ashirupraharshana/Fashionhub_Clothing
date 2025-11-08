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

// Get logged-in user details
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, email, phone FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>FashionHub | Home</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'Components/CustomerNavBar.php'; ?>  

<div class="user-profile" style="background:#fff; padding:20px; border-radius:10px; width:400px; margin:20px auto; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
    <h2 style="color:#333; text-align:center;">Welcome, <?php echo htmlspecialchars($user['fullname']); ?> 👋</h2>
    <hr style="margin:10px 0;">
    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
    <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
</div>


</body>
</html>
