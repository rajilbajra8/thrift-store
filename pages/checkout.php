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

// Get user details from database
$userDetails = [];
$userStmt = $conn->prepare("SELECT email, phone, address FROM user WHERE user_id = ?");
if ($userStmt) {
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $result = $userStmt->get_result();
    if ($result->num_rows > 0) {
        $userDetails = $result->fetch_assoc();
    }
    $userStmt->close();
}

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
$total = $subtotal; // No tax

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
    // Nepali phone validation: must start with 9 and be 10 digits
    if ($phone === '' || !preg_match('/^9[0-9]{9}$/', $phone)) {
        $errors[] = 'Valid Nepali phone number is required (10 digits starting with 9).';
    }
    if ($address === '') $errors[] = 'Address is required.';

    // If no errors, process order
    if (empty($errors)) {
        // Also update user details in database if they were changed
        if (!empty($userDetails) && ($userDetails['phone'] !== $phone || $userDetails['address'] !== $address || $userDetails['email'] !== $email)) {
            $updateStmt = $conn->prepare("UPDATE user SET phone = ?, address = ?, email = ? WHERE user_id = ?");
            $updateStmt->bind_param("sssi", $phone, $address, $email, $userId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        $conn->begin_transaction();
        try {
            // Create order
            $orderStmt = $conn->prepare("
                INSERT INTO shop_order (customer_id, total_amount, payment_status, status, shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $paymentStatus = ($paymentMethod === 'online') ? 'completed' : 'pending';
            $orderStatus = 'processing';
            $orderStmt->bind_param(
                "idsssssss",
                $userId,
                $total,
                $paymentStatus,
                $orderStatus,
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

            // Show success alert and redirect
            echo "<script>
                alert('Checkout successful!\\n\\nYour order #$orderId has been placed.\\nThank you for shopping with us!');
                window.location.href = 'products.php';
            </script>";
            exit();
           
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing order. Please try again.";
        }
    }
}

// Set default values for form
$defaultName = $userName;
$defaultEmail = $userDetails['email'] ?? '';
$defaultPhone = $userDetails['phone'] ?? '';
$defaultAddress = $userDetails['address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ThriftVibe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
   <style>
body { background: #f5f5f5; margin: 0; font-family: sans-serif; }
.container { max-width: 1100px; margin: auto; padding: 20px; }

/* Header - Same as index.php */
header { background-color: #fff; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); position: sticky; top: 0; z-index: 100; }
.logo { font-size: 28px; font-weight: 700; color: #2a9d8f; display: flex; align-items: center; }
.logo i { margin-right: 10px; }
.header-content { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; }
.user-actions a { margin-left: 20px; color: #333; text-decoration: none; font-size: 16px; display: flex; align-items: center; position: relative; }
.user-actions a i { margin-right: 5px; }

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
.help-text { font-size: 12px; color: #666; margin-top: 5px; }
@media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
    <!-- Header with Logo and User Info Only -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <a href="../index.php" style="color: #2a9d8f; text-decoration: none;">ThriftVibe</a>
                </div>
                
                <div class="user-actions">
                    <span style="font-size: 16px; color: #333;">Welcome, <?php echo htmlspecialchars($userName); ?></span>
                </div>
            </div>
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
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($defaultName); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($defaultEmail); ?>" required>
                        <?php if (empty($defaultEmail)): ?>
                            <div class="help-text">Please enter your email address</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" 
                               pattern="9[0-9]{9}" 
                               title="Nepali mobile number starting with 9 (e.g., 9841234567)"
                               placeholder="9841234567"
                               maxlength="10"
                               value="<?php echo htmlspecialchars($defaultPhone); ?>"
                               required>
                        <?php if (empty($defaultPhone)): ?>
                            <div class="help-text">Enter your Nepali mobile number starting with 9</div>
                        <?php else: ?>
                            <div class="help-text">Your saved phone number</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="3" required><?php echo htmlspecialchars($defaultAddress); ?></textarea>
                        <?php if (empty($defaultAddress)): ?>
                            <div class="help-text">Please enter your complete delivery address</div>
                        <?php else: ?>
                            <div class="help-text">Your saved delivery address</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <input type="text" value="Cash on Delivery" readonly style="background: #f8f9fa;">
                        <input type="hidden" name="payment_method" value="cod">
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
                
                <div class="summary-row total">
                    <span>Total Amount</span>
                    <span>Rs <?php echo number_format($total, 2); ?></span>
                </div>
                
                <div style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 14px;">
                    <p><strong>Note:</strong> Your saved information has been pre-filled. You can modify it if needed.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>