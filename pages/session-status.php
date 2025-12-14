<?php
session_start();

$response = [
    'loggedIn' => false,
    'role' => null,
    'name' => null,
    'dashboardUrl' => null,
    'cartCount' => 0
];

if (isset($_SESSION['user_id'])) {
    $response['loggedIn'] = true;
    $response['role'] = $_SESSION['role'] ?? null;
    $response['name'] = $_SESSION['user_name'] ?? null;

    if ($response['role'] === 'seller') {
        $response['dashboardUrl'] = 'pages/Seller-dashboard.php';
    } elseif ($response['role'] === 'staff') {
        $response['dashboardUrl'] = 'pages/staff-dashboard.php';
    } else {
        $response['dashboardUrl'] = 'pages/user-dashboard.php';
    }

    if ($response['role'] === 'customer') {
        require_once "db.php";
        $stmt = $conn->prepare("
            SELECT IFNULL(SUM(ci.quantity), 0) AS total_items
            FROM cart c
            LEFT JOIN cart_item ci ON c.cart_id = ci.cart_id
            WHERE c.user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $response['cartCount'] = (int) $row['total_items'];
            }
            $stmt->close();
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
?>

