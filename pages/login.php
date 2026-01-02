<?php
session_start();
require_once "db.php";

// Get form data
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

// If no email/password was submitted, redirect to login page
if (empty($email) && empty($password)) {
    header("Location: login.html");
    exit();
}

// Basic validation
if (empty($email) || empty($password)) {
    echo "<script>alert('Please fill in all fields.'); window.history.back();</script>";
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Invalid email format.'); window.history.back();</script>";
    exit();
}

// Database query
$stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
if (!$stmt) {
    die("Server error.");
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo "<script>alert('No account found with that email.'); window.history.back();</script>";
    exit();
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user["password"])) {
    echo "<script>alert('Incorrect password.'); window.history.back();</script>";
    exit();
}

// Concatenate first and last name
$fullName = $user["f_name"] . " " . $user["l_name"];

// Store session
$_SESSION["user_id"] = $user["user_id"];
$_SESSION["user_name"] = $fullName;  // Store full name
$_SESSION["role"] = strtolower($user["role"]);

// Redirect based on role
$role = $_SESSION["role"];
if ($role === "seller") {
    header("Location: Seller-dashboard.php");
} elseif ($role === "staff") {
    header("Location: staff-dashboard.html");
} else {
    header("Location: user-dashboard.php");
}

exit();
?>