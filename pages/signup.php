<?php
session_start();
require_once "db.php";

// DEBUG: Check if POST is received
// echo "<pre>"; print_r($_POST); echo "</pre>";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form data safely
    $f_name   = trim($_POST["f_name"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $confirm  = trim($_POST["regConfirm"] ?? "");
    $role     = strtolower(trim($_POST["role"] ?? "customer"));

    // DEBUG: Show role received
    // echo "Role received: $role<br>";

    // Allowed roles
    $allowed_roles = ["customer", "seller", "staff"];
    if (!in_array($role, $allowed_roles)) {
        $role = "customer"; 
        // echo "Role fixed to default: customer<br>";
    }

    // Basic validations
    if (empty($f_name) || empty($email) || empty($password) || empty($confirm)) {
        die("Please fill in all fields.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) die("Invalid email.");
    if ($password !== $confirm) die("Passwords do not match.");
    if (strlen($password) < 6) die("Password too short.");

    // Email check
    $checkStmt = $conn->prepare("SELECT email FROM user WHERE email=?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $res = $checkStmt->get_result();

    if ($res->num_rows > 0) die("Email already registered.");

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO user (f_name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $f_name, $email, $hashed_password, $role);

    if ($stmt->execute()) {

        // Save session
        $_SESSION["user_id"] = $stmt->insert_id;
        $_SESSION["user_name"] = $f_name;
        $_SESSION["role"] = $role;

        // FINAL REDIRECT
        if ($role === "seller") {
            // echo "Redirecting seller..."; // DEBUG
            header("Location: Seller-dashboard.php");
        } 
        else if ($role === "staff") {
            // echo "Redirecting staff..."; // DEBUG
            header("Location: staff-dashboard.html");
        } 
        else {
            // echo "Redirecting customer..."; // DEBUG
            header("Location: user-dashboard.php");
        }

        exit;
    } else {
        die("DB Error: " . $stmt->error);
    }
}
?>
