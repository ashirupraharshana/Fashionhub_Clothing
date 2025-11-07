<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "fashionhubdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    // Validation
    if (empty($fullname) || empty($email) || empty($password)) {
        echo "<script>alert('Please fill in all required fields.'); window.history.back();</script>";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format.'); window.history.back();</script>";
        exit;
    }

    // Check if email already exists
    $checkQuery = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Email already registered. Please use another email.'); window.history.back();</script>";
        exit;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $insertQuery = "INSERT INTO users (fullname, email, phone, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! You can now log in.'); window.location.href='login.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register | FashionHub</title>
    <style>
        body {
            font-family: Arial;
            background-color: #f4f4f4;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        form {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px #ccc;
            width: 350px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 7px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #222;
            color: white;
            padding: 10px;
            border: none;
            cursor: pointer;
            width: 100%;
            border-radius: 4px;
        }
        button:hover {
            background-color: #444;
        }
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>

<form action="register.php" method="POST">
    <h2>Register</h2>
    <input type="text" name="fullname" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email Address" required>
    <input type="text" name="phone" placeholder="Phone Number">
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Register</button>
</form>

</body>
</html>
