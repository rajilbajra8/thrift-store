<?php
$dbname     = "thrift";
$servername = "localhost";
$username   = "root";
$password   = "12345";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Stop execution immediately if we cannot talk to MySQL
    die("Database connection failed: " . $conn->connect_error);
}
?>