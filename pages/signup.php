<?php
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit();
}

// Get form data
$f_name = trim($_POST["f_name"] ?? "");
$l_name = trim($_POST["l_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$confirm_password = $_POST["confirm_password"] ?? "";
$role = $_POST["role"] ?? "customer";

// Validation
$errors = [];

if (empty($f_name)) $errors[] = "First name is required.";
if (empty($l_name)) $errors[] = "Last name is required.";
if (empty($email)) {
    $errors[] = "Email is required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}
if (empty($password)) {
    $errors[] = "Password is required.";
} elseif (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters.";
}
if (empty($confirm_password)) {
    $errors[] = "Please confirm your password.";
} elseif ($password !== $confirm_password) {
    $errors[] = "Passwords do not match.";
}

// If there are validation errors, show them
if (!empty($errors)) {
    echo "<script>";
    echo "alert('" . addslashes(implode("\\n", $errors)) . "');";
    echo "window.history.back();";
    echo "</script>";
    exit();
}

// Check if email already exists
$checkStmt = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    echo "<script>";
    echo "alert('Email already registered. Please use a different email or login.');";
    echo "window.history.back();";
    echo "</script>";
    $checkStmt->close();
    exit();
}
$checkStmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert new user
$stmt = $conn->prepare("INSERT INTO user (f_name, l_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    die("Server error: " . $conn->error);
}

$stmt->bind_param("sssss", $f_name, $l_name, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    $stmt->close();
    
    echo "<script>";
    echo "alert('Registration successful! You can now login with your credentials.');";
    echo "window.location.href = 'login.html';";
    echo "</script>";
} else {
    echo "<script>";
    echo "alert('Registration failed. Please try again.');";
    echo "window.history.back();";
    echo "</script>";
}

$conn->close();
?>