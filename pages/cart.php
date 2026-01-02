<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: login.html");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$cartFlash = $_SESSION['cart_flash'] ?? null;
unset($_SESSION['cart_flash']);

function ensureCart(mysqli $conn, int $userId): int
{
    $cartId = 0;
    $stmt = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $cartId = (int) $row['cart_id'];
        }
        $stmt->close();
    }

    if ($cartId === 0) {
        $create = $conn->prepare("INSERT INTO cart (user_id) VALUES (?)");
        if ($create) {
            $create->bind_param("i", $userId);
            if ($create->execute()) {
                $cartId = $create->insert_id;
            }
            $create->close();
        }
    }

    return $cartId;
}

function setCartFlash(string $type, string $message): void
{
    $_SESSION['cart_flash'] = ['type' => $type, 'message' => $message];
}

function redirectBack(string $preferred = 'cart.php'): void
{
    $target = $_POST['redirect'] ?? '';
    if ($target && strpos($target, 'http') !== 0 && strpos($target, '//') !== 0) {
        header("Location: " . $target);
    } else {
        header("Location: " . $preferred);
    }
    exit();
}

$cartId = ensureCart($conn, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        // Check stock quantity before adding
        $itemStmt = $conn->prepare("SELECT stock_quantity FROM items WHERE item_id = ? LIMIT 1");
        if ($itemStmt) {
            $itemStmt->bind_param("i", $itemId);
            $itemStmt->execute();
            $result = $itemStmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $availableStock = $row['stock_quantity'];
                
                // Check if item is out of stock
                if ($availableStock <= 0) {
                    setCartFlash('error', 'This item is out of stock.');
                    redirectBack();
                }
                
                // Check if we're trying to add more than available
                if ($quantity > $availableStock) {
                    setCartFlash('error', "Only $availableStock item(s) available in stock.");
                    redirectBack();
                }
                
                // Check existing quantity in cart
                $existing = $conn->prepare("SELECT cart_item_id, quantity FROM cart_item WHERE cart_id = ? AND item_id = ? LIMIT 1");
                if ($existing) {
                    $existing->bind_param("ii", $cartId, $itemId);
                    $existing->execute();
                    $result2 = $existing->get_result();
                    if ($result2 && $row2 = $result2->fetch_assoc()) {
                        // Check total quantity (existing + new)
                        $totalQuantity = $row2['quantity'] + $quantity;
                        if ($totalQuantity > $availableStock) {
                            setCartFlash('error', "Only $availableStock item(s) available. You already have {$row2['quantity']} in cart.");
                            redirectBack();
                        }
                        
                        // Update quantity
                        $newQuantity = $row2['quantity'] + $quantity;
                        $update = $conn->prepare("UPDATE cart_item SET quantity = ? WHERE cart_item_id = ?");
                        if ($update) {
                            $update->bind_param("ii", $newQuantity, $row2['cart_item_id']);
                            $update->execute();
                            $update->close();
                        }
                    } else {
                        // Add new item to cart
                        $insert = $conn->prepare("INSERT INTO cart_item (cart_id, item_id, quantity) VALUES (?, ?, ?)");
                        if ($insert) {
                            $insert->bind_param("iii", $cartId, $itemId, $quantity);
                            $insert->execute();
                            $insert->close();
                        }
                    }
                    $existing->close();
                }
            } else {
                setCartFlash('error', 'Selected product is unavailable.');
                redirectBack();
            }
            $itemStmt->close();
        }

        setCartFlash('success', 'Item added to your cart.');
        redirectBack();
    }

    if ($action === 'update_item') {
        $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        // Check stock before updating
        $checkStmt = $conn->prepare("
            SELECT it.stock_quantity 
            FROM cart_item ci 
            JOIN items it ON ci.item_id = it.item_id 
            WHERE ci.cart_item_id = ?
        ");
        if ($checkStmt) {
            $checkStmt->bind_param("i", $cartItemId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                if ($quantity > $row['stock_quantity']) {
                    setCartFlash('error', "Only {$row['stock_quantity']} item(s) available in stock.");
                    header("Location: cart.php");
                    exit();
                }
            }
            $checkStmt->close();
        }

        $stmt = $conn->prepare("UPDATE cart_item SET quantity = ? WHERE cart_item_id = ? AND cart_id = ?");
        if ($stmt) {
            $stmt->bind_param("iii", $quantity, $cartItemId, $cartId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                setCartFlash('success', 'Cart updated.');
            } else {
                setCartFlash('error', 'Could not update this item.');
            }
            $stmt->close();
        }
        header("Location: cart.php");
        exit();
    }

    if ($action === 'remove_item') {
        $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM cart_item WHERE cart_item_id = ? AND cart_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $cartItemId, $cartId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                setCartFlash('success', 'Item removed.');
            } else {
                setCartFlash('error', 'Unable to remove item.');
            }
            $stmt->close();
        }
        header("Location: cart.php");
        exit();
    }
}

$cartItems = [];
$cartSubtotal = 0.0;
$cartItemStmt = $conn->prepare("
    SELECT ci.cart_item_id, ci.quantity, it.item_id, it.name, it.category, it.price, it.image_url, it.stock_quantity
    FROM cart_item ci
    JOIN items it ON ci.item_id = it.item_id
    WHERE ci.cart_id = ?
    ORDER BY ci.cart_item_id DESC
");
if ($cartItemStmt) {
    $cartItemStmt->bind_param("i", $cartId);
    $cartItemStmt->execute();
    $result = $cartItemStmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['line_total'] = $row['price'] * $row['quantity'];
            $cartSubtotal += $row['line_total'];
            $cartItems[] = $row;
        }
    }
    $cartItemStmt->close();
}

$tax = $cartSubtotal * 0.05;
$grandTotal = $cartSubtotal + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - ThriftVibe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        :root { --primary:#2a9d8f; --secondary:#e76f51; --dark:#264653; --light:#f8f9fa; --gray:#6c757d; }
        body { background:#f5f7fa; color:#333; }
        .container { max-width:1200px; margin:0 auto; padding:0 20px; }
        header { background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.1); position:sticky; top:0; z-index:100; }
        .header-content { display:flex; justify-content:space-between; align-items:center; padding:15px 0; }
        .logo { font-size:28px; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:10px; }
        .user-actions { display:flex; align-items:center; gap:18px; }
        .user-actions a { text-decoration:none; color:#333; display:inline-flex; align-items:center; gap:6px; }
        .cart-count { background:var(--secondary); color:#fff; border-radius:50%; padding:2px 8px; font-size:12px; font-weight:600; }
        .login-btn { background:var(--secondary); color:#fff; padding:10px 20px; border-radius:30px; }
        nav { background:var(--dark); padding:12px 0; }
        .nav-links { display:flex; justify-content:center; list-style:none; }
        .nav-links a { color:#fff; text-decoration:none; padding:8px 15px; border-radius:4px; }
        .nav-links a.active, .nav-links a:hover { background:rgba(255,255,255,0.1); }
        .page-header { background:linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=1500&q=80'); background-size:cover; background-position:center; height:220px; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#fff; text-align:center; margin-bottom:40px; }
        .page-header h1 { font-size:38px; margin-bottom:8px; }
        .cart-content { display:grid; grid-template-columns:2fr 1fr; gap:30px; margin-bottom:50px; }
        .cart-items { background:#fff; border-radius:16px; padding:30px; box-shadow:0 5px 15px rgba(0,0,0,0.08); }
        .cart-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:12px; }
        .cart-item { display:flex; gap:20px; border-bottom:1px solid #eee; padding:20px 0; }
        .cart-item:last-child { border-bottom:none; }
        .cart-item img { width:110px; height:110px; border-radius:12px; object-fit:cover; }
        .item-info { flex:1; }
        .item-info h3 { margin-bottom:6px; }
        .item-meta { color:var(--gray); font-size:14px; margin-bottom:10px; }
        .item-actions { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .item-actions input[type="number"] { width:80px; padding:6px 10px; border:1px solid #ccc; border-radius:6px; }
        .item-actions button { border:none; cursor:pointer; border-radius:6px; padding:8px 14px; }
        .btn-outline { border:1px solid #ddd; background:#fff; }
        .btn-danger { background:var(--secondary); color:#fff; }
        .summary-card { background:#fff; border-radius:16px; padding:25px; box-shadow:0 5px 15px rgba(0,0,0,0.08); position:sticky; top:120px; }
        .summary-card h3 { margin-bottom:20px; }
        .summary-row { display:flex; justify-content:space-between; margin-bottom:10px; }
        .summary-total { font-size:20px; font-weight:700; margin-top:10px; border-top:1px solid #eee; padding-top:15px; }
        .btn-primary { background:var(--primary); color:#fff; border:none; border-radius:30px; padding:12px 25px; width:100%; cursor:pointer; font-size:16px; }
        .empty-state { text-align:center; padding:40px 0; color:var(--gray); }
        .alert { border-radius:8px; padding:12px 16px; margin-bottom:20px; }
        .alert-success { background:rgba(40,167,69,0.15); border:1px solid rgba(40,167,69,0.4); color:#1f5130; }
        .alert-error { background:rgba(220,53,69,0.15); border:1px solid rgba(220,53,69,0.4); color:#6e1b23; }
        .stock-info { font-size:13px; color:var(--gray); margin-top:5px; }
        @media (max-width:992px) { .cart-content { grid-template-columns:1fr; } .summary-card { position:static; } }
        @media (max-width:600px) { .cart-item { flex-direction:column; } .item-actions { flex-direction:column; align-items:flex-start; } }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <a href="../index.html" style="text-decoration:none;color:inherit;">ThriftVibe</a>
                </div>
                <div class="user-actions">
                    <a href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Cart</span>
                        <span class="cart-count" id="cartCount"><?php echo array_sum(array_column($cartItems, 'quantity')); ?></span>
                    </a>
                    <a href="login.html" class="login-btn"><i class="fas fa-user"></i> <span>Login</span></a>
                </div>
            </div>
        </div>
        <nav>
            <div class="container">
                <ul class="nav-links">
                    <li><a href="../index.html">Home</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="cart.php" class="active">Cart</a></li>
                    <li><a href="user-dashboard.php">Dashboard</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <div class="page-header">
        <h1>Your Shopping Cart</h1>
        <p>Review items before completing your purchase</p>
    </div>

    <div class="container">
        <?php if ($cartFlash): ?>
            <div class="alert <?php echo $cartFlash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($cartFlash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="cart-content">
            <div class="cart-items">
                <div class="cart-header">
                    <h2>Items (<?php echo count($cartItems); ?>)</h2>
                    <a href="products.php" style="color:var(--primary); text-decoration:none;"><i class="fas fa-plus"></i> Continue shopping</a>
                </div>

                <?php if (empty($cartItems)): ?>
                    <div class="empty-state">
                        <p>Your cart is empty. Browse products to add items.</p>
                        <a href="products.php" class="btn-primary" style="display:inline-block;width:auto;margin-top:15px;">Browse Products</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cartItems as $item): ?>
                        <?php 
                            $image = $item['image_url'] ?: 'https://via.placeholder.com/120x120?text=Thrift';
                            $stockLeft = $item['stock_quantity'] - $item['quantity'];
                            $stockClass = $stockLeft <= 0 ? 'style="color:red;"' : 'style="color:var(--gray);"';
                        ?>
                        <div class="cart-item">
                            <img src="../<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <div class="item-info">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <div class="item-meta"><?php echo htmlspecialchars(ucwords($item['category'] ?? '')); ?></div>
                                <div style="font-weight:600;">Rs <?php echo number_format($item['price'], 2); ?></div>
                                <div class="stock-info" <?php echo $stockClass; ?>>
                                    Stock: <?php echo $item['stock_quantity']; ?> | 
                                    In cart: <?php echo $item['quantity']; ?> | 
                                    Left: <?php echo max(0, $stockLeft); ?>
                                </div>
                            </div>
                            <div class="item-actions">
                                <form method="post" style="display:flex; align-items:center; gap:10px;">
                                    <input type="hidden" name="action" value="update_item">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <input type="number" name="quantity" min="1" max="<?php echo $item['stock_quantity']; ?>" value="<?php echo $item['quantity']; ?>">
                                    <button class="btn-outline" type="submit">Update</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Remove this item?');">
                                    <input type="hidden" name="action" value="remove_item">
                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                    <button class="btn-danger" type="submit"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                            <div style="text-align:right;">
                                <div style="color:var(--gray); font-size:13px;">Line total</div>
                                <div style="font-size:18px; font-weight:600;">Rs <?php echo number_format($item['line_total'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="summary-card">
                <h3>Order Summary</h3>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>Rs <?php echo number_format($cartSubtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Estimated tax (5%)</span>
                    <span>Rs <?php echo number_format($tax, 2); ?></span>
                </div>
                <div class="summary-total">
                    <span>Total</span>
                    <span>Rs <?php echo number_format($grandTotal, 2); ?></span>
                </div>
                <div style="margin-top:20px;">
                    <?php if (!empty($cartItems)): ?>
                        <a href="checkout.php" class="btn-primary" style="text-decoration:none; display:block; text-align:center;">Proceed to Checkout</a>
                    <?php else: ?>
                        <button class="btn-primary" disabled style="opacity:0.6;">Proceed to Checkout</button>
                    <?php endif; ?>
                </div>
                <p style="font-size:13px; color:var(--gray); margin-top:12px;">Secure checkout powered by ThriftVibe.</p>
            </div>
        </div>
    </div>

    <script>
        // Simple JavaScript to prevent adding more than stock
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max'));
                const value = parseInt(this.value);
                if (value > max) {
                    alert('Only ' + max + ' item(s) available in stock.');
                    this.value = max;
                }
            });
        });
    </script>
</body>
</html>