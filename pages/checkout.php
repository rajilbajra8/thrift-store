<?php
session_start();
require_once "db.php";

// Check if customer is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: login.html");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Customer';

// Get cart items
$cartStmt = $conn->prepare("
    SELECT ci.cart_item_id, ci.quantity, it.item_id, it.name, it.price
    FROM cart_item ci
    JOIN items it ON ci.item_id = it.item_id
    JOIN cart c ON ci.cart_id = c.cart_id
    WHERE c.user_id = ?
");
if ($cartStmt) {
    $cartStmt->bind_param("i", $userId);
    $cartStmt->execute();
    $result = $cartStmt->get_result();
    $cartItems = $result->fetch_all(MYSQLI_ASSOC);
    $cartStmt->close();
} else {
    $cartItems = [];
}

// Check if cart is empty
if (empty($cartItems)) {
    header("Location: cart.php");
    exit();
}

// Calculate totals
$subtotal = 0.0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.05;
$total = $subtotal + $tax;

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'cod');

    // Basic validation
    if ($fullName === '') $errors[] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($phone === '') $errors[] = 'Phone number is required.';
    if ($address === '') $errors[] = 'Address is required.';
    if ($city === '') $errors[] = 'City is required.';

    // If no errors, process order
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Create order
            $orderStmt = $conn->prepare("
                INSERT INTO shop_order (customer_id, total_amount, payment_status, status, shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $paymentStatus = ($paymentMethod === 'online') ? 'completed' : 'pending';
            $orderStmt->bind_param(
                "idsssssss",
                $userId,
                $total,
                $paymentStatus,
                'processing',
                $fullName,
                $email,
                $phone,
                $address,
                $city
            );
            $orderStmt->execute();
            $orderId = $orderStmt->insert_id;
            $orderStmt->close();

            // Add order items
            $itemStmt = $conn->prepare("
                INSERT INTO order_item (order_id, item_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($cartItems as $item) {
                $itemStmt->bind_param("iiid", $orderId, $item['item_id'], $item['quantity'], $item['price']);
                $itemStmt->execute();
            }
            $itemStmt->close();

            // Clear cart
            $deleteStmt = $conn->prepare("
                DELETE ci FROM cart_item ci
                JOIN cart c ON ci.cart_id = c.cart_id
                WHERE c.user_id = ?
            ");
            $deleteStmt->bind_param("i", $userId);
            $deleteStmt->execute();
            $deleteStmt->close();

            $conn->commit();

            $_SESSION['order_success'] = true;
            header("Location: order-success.php");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing order. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ThriftVibe</title>
    <link rel="stylesheet" href="../styles.css">
   <style>
body { background: #f5f5f5; margin: 0; font-family: sans-serif; }
.container { max-width: 1100px; margin: auto; padding: 20px; }
header { background: white; padding: 15px; }
.logo { font-size: 22px; color: #2a9d8f; font-weight: bold; }
.checkout-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
.card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
h1, h2 { margin-bottom: 15px; }
.form-group { margin-bottom: 15px; }
label { display: block; margin-bottom: 5px; }
input, textarea, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
.order-item { display: flex; justify-content: space-between; margin-bottom: 10px; }
.summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
.total { font-weight: bold; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px; }
.btn { background: #2a9d8f; color: white; padding: 10px; width: 100%; border: none; border-radius: 4px; cursor: pointer; }
.error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
@media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">ThriftVibe</div>
            <div>Welcome, <?php echo htmlspecialchars($userName); ?></div>
        </div>
    </header>

    <div class="container">
        <h1>Checkout</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div class="card">
                <h2>Shipping Information</h2>
                <form method="post">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($userName); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method">
                            <option value="cod">Cash on Delivery</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Place Order</button>
                </form>
            </div>

            <div class="card">
                <h2>Order Summary</h2>
                <?php foreach ($cartItems as $item): ?>
                    <div class="order-item">
                        <span><?php echo htmlspecialchars($item['name']); ?> × <?php echo $item['quantity']; ?></span>
                        <span>Rs <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>Rs <?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Tax (5%)</span>
                    <span>Rs <?php echo number_format($tax, 2); ?></span>
                </div>
                
                <div class="summary-row total">
                    <span>Total Amount</span>
                    <span>Rs <?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>