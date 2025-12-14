<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Valued Customer';
$userInitial = strtoupper(substr($userName, 0, 1));

$flash = $_SESSION['user_flash'] ?? null;
unset($_SESSION['user_flash']);

function setUserFlash(string $type, string $message): void
{
    $_SESSION['user_flash'] = ['type' => $type, 'message' => $message];
}

function formatCurrency(float $amount): string
{
    return 'Rs ' . number_format($amount, 2);
}

function ensureCart(mysqli $conn, int $userId): int
{
    $cartId = null;
    $cartStmt = $conn->prepare("SELECT cart_id FROM cart WHERE user_id = ? LIMIT 1");
    if ($cartStmt) {
        $cartStmt->bind_param("i", $userId);
        $cartStmt->execute();
        $result = $cartStmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $cartId = (int) $row['cart_id'];
        }
        $cartStmt->close();
    }

    if (!$cartId) {
        $createStmt = $conn->prepare("INSERT INTO cart (user_id) VALUES (?)");
        if ($createStmt) {
            $createStmt->bind_param("i", $userId);
            if ($createStmt->execute()) {
                $cartId = $createStmt->insert_id;
            }
            $createStmt->close();
        }
    }

    return $cartId ?? 0;
}

$cartId = ensureCart($conn, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_cart') {
        $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        if ($cartItemId > 0 && $cartId > 0) {
            $stmt = $conn->prepare("UPDATE cart_item SET quantity = ? WHERE cart_item_id = ? AND cart_id = ?");
            if ($stmt) {
                $stmt->bind_param("iii", $quantity, $cartItemId, $cartId);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    setUserFlash('success', 'Cart updated successfully.');
                } else {
                    setUserFlash('error', 'Unable to update cart item.');
                }
                $stmt->close();
            } else {
                setUserFlash('error', 'Server error: unable to update cart.');
            }
        }
        header("Location: user-dashboard.php");
        exit();
    }

    if ($action === 'remove_cart_item') {
        $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
        if ($cartItemId > 0 && $cartId > 0) {
            $stmt = $conn->prepare("DELETE FROM cart_item WHERE cart_item_id = ? AND cart_id = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $cartItemId, $cartId);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    setUserFlash('success', 'Item removed from cart.');
                } else {
                    setUserFlash('error', 'Unable to remove cart item.');
                }
                $stmt->close();
            } else {
                setUserFlash('error', 'Server error: unable to remove cart item.');
            }
        }
        header("Location: user-dashboard.php");
        exit();
    }
}

$stats = [
    'total_orders' => 0,
    'total_spent' => 0.0,
    'active_orders' => 0,
    'cart_items' => 0,
    
];

$statsStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_orders,
        IFNULL(SUM(total_amount), 0) AS total_spent,
        SUM(CASE WHEN status <> 'delivered' THEN 1 ELSE 0 END) AS active_orders
    FROM shop_order
    WHERE customer_id = ?
");
if ($statsStmt) {
    $statsStmt->bind_param("i", $userId);
    $statsStmt->execute();
    $result = $statsStmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_orders'] = (int) $row['total_orders'];
        $stats['total_spent'] = (float) $row['total_spent'];
        $stats['active_orders'] = (int) ($row['active_orders'] ?? 0);
    }
    $statsStmt->close();
}


$recentOrders = [];
$orderStmt = $conn->prepare("
    SELECT order_id, total_amount, status, payment_status, created_at
    FROM shop_order
    WHERE customer_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
if ($orderStmt) {
    $orderStmt->bind_param("i", $userId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    if ($orderResult) {
        $recentOrders = $orderResult->fetch_all(MYSQLI_ASSOC);
    }
    $orderStmt->close();
}

$orderItemsMap = [];
if (!empty($recentOrders)) {
    $orderItemStmt = $conn->prepare("
        SELECT oi.order_id, oi.quantity, oi.price, it.name, it.image_url
        FROM order_item oi
        JOIN items it ON oi.item_id = it.item_id
        WHERE oi.order_id = ?
    ");
    if ($orderItemStmt) {
        foreach ($recentOrders as $order) {
            $orderId = (int) $order['order_id'];
            $orderItemStmt->bind_param("i", $orderId);
            $orderItemStmt->execute();
            $itemResult = $orderItemStmt->get_result();
            $orderItemsMap[$orderId] = $itemResult ? $itemResult->fetch_all(MYSQLI_ASSOC) : [];
        }
        $orderItemStmt->close();
    }
}

$cartItems = [];
$cartSubtotal = 0.0;
if ($cartId > 0) {
    $cartItemStmt = $conn->prepare("
        SELECT ci.cart_item_id, ci.quantity, it.item_id, it.name, it.category, it.price, it.image_url
        FROM cart_item ci
        JOIN items it ON ci.item_id = it.item_id
        WHERE ci.cart_id = ?
        ORDER BY ci.cart_item_id DESC
    ");
    if ($cartItemStmt) {
        $cartItemStmt->bind_param("i", $cartId);
        $cartItemStmt->execute();
        $cartResult = $cartItemStmt->get_result();
        if ($cartResult) {
            while ($row = $cartResult->fetch_assoc()) {
                $row['line_total'] = $row['price'] * $row['quantity'];
                $cartSubtotal += $row['line_total'];
                $cartItems[] = $row;
            }
        }
        $cartItemStmt->close();
    }
}
$stats['cart_items'] = array_sum(array_column($cartItems, 'quantity'));

$recommendedItems = [];
$recommendStmt = $conn->prepare("
    SELECT item_id, name, category, price, image_url
    FROM items
    ORDER BY created_at DESC
    LIMIT 4
");
if ($recommendStmt) {
    $recommendStmt->execute();
    $recommendResult = $recommendStmt->get_result();
    if ($recommendResult) {
        $recommendedItems = $recommendResult->fetch_all(MYSQLI_ASSOC);
    }
    $recommendStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ThriftVibe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #2a9d8f;
            --secondary: #e76f51;
            --dark: #264653;
            --light: #f8f9fa;
            --gray: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .logout-btn {
            background: var(--secondary);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #d65a3c;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            margin: 20px 0;
            min-height: calc(100vh - 100px);
        }
        
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .sidebar h3 {
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: #333;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .content-header h2 {
            color: var(--dark);
            font-size: 28px;
        }
        
        .content-header .btn {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .content-header .btn:hover {
            background: #21867a;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        
        .stat-card.orders { border-left-color: var(--success); }
        .stat-card.favorites { border-left-color: var(--warning); }
        .stat-card.savings { border-left-color: var(--secondary); }
        .stat-card.points { border-left-color: var(--primary); }
        
        .stat-card:hover { transform: translateY(-2px); }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            font-size: 24px;
            color: var(--gray);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 14px;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .order-id { font-weight: 600; color: var(--dark); }
        .order-date { color: var(--gray); font-size: 14px; }
        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .order-status.delivered { background: #e7f5f3; color: var(--success); }
        .order-status.processing { background: #fff3e0; color: var(--warning); }
        .order-status.pending { background: #ffebee; color: var(--danger); }
        .order-status.shipped { background: #e0f0ff; color: #1b6dc1; }
        
        .order-items {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .order-item img {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            object-fit: cover;
        }
        
        .order-item-info h4 {
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .order-item-info p {
            font-size: 12px;
            color: var(--gray);
        }
        
        .order-total {
            text-align: right;
            font-weight: 600;
            color: var(--dark);
        }
        
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .wishlist-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .wishlist-item:hover { transform: translateY(-5px); }
        
        .wishlist-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .wishlist-item-info {
            padding: 15px;
        }
        
        .wishlist-item h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--dark);
        }
        
        .wishlist-item .price {
            font-size: 18px;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 10px;
        }
        
        .wishlist-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-secondary { background: var(--gray); color: white; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .profile-section, .address-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        
        .profile-section h3, .address-section h3 {
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .button-group {
            display: flex;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            margin: 15px 0;
            font-size: 14px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.12);
            border: 1px solid rgba(40, 167, 69, 0.35);
            color: #1f6f3d;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.12);
            border: 1px solid rgba(220, 53, 69, 0.35);
            color: #842029;
        }
        
        @media (max-width: 992px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
        }
        
        @media (max-width: 600px) {
            .order-items {
                flex-direction: column;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    ThriftVibe
                </div>
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar"><?php echo htmlspecialchars($userInitial); ?></div>
                        <div>
                            <div><?php echo htmlspecialchars($userName); ?></div>
                            <div style="font-size: 12px; color: var(--gray);">Customer</div>
                        </div>
                    </div>
                    <button class="logout-btn" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </div>
            </div>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="container">
            <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="container">
        <div class="dashboard">
            <div class="sidebar">
                <h3>My Account</h3>
                <ul class="sidebar-menu">
                    <li><a href="#" class="active" onclick="showSection(event, 'dashboard')"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#" onclick="showSection(event, 'orders')"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                    <li><a href="#" onclick="showSection(event, 'wishlist')"><i class="fas fa-heart"></i> Saved Items</a></li>
                    
                </ul>
            </div>
            
            <div class="main-content">
                <div id="dashboard-section" class="content-section active">
                    <div class="content-header">
                        <h2>Welcome back, <?php echo htmlspecialchars($userName); ?>!</h2>
                        <button class="btn" onclick="showSection(event, 'orders')">
                            <i class="fas fa-receipt"></i> View Orders
                        </button>
                    </div>
                    
                    <div class="stats">
                        <div class="stat-card orders">
                            <div class="stat-header">
                                <h3>Total Orders</h3>
                                <i class="fas fa-shopping-bag stat-icon"></i>
                            </div>
                            <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                            <div class="stat-label">Completed purchases</div>
                        </div>
                        
                        <div class="stat-card favorites">
                            <div class="stat-header">
                                <h3>Items in Cart</h3>
                                <i class="fas fa-heart stat-icon"></i>
                            </div>
                            <div class="stat-value"><?php echo $stats['cart_items']; ?></div>
                            <div class="stat-label">Ready to checkout</div>
                        </div>
                        
                        <div class="stat-card savings">
                            <div class="stat-header">
                                <h3>Total Spent</h3>
                                <i class="fas fa-wallet stat-icon"></i>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($stats['total_spent']); ?></div>
                            <div class="stat-label">Across all orders</div>
                        </div>
                        
                       
                    </div>
                    
                    <div class="order-history">
                        <div class="content-header" style="padding-top: 0;">
                            <h2>Recent Orders</h2>
                            <button class="btn" onclick="showSection(event, 'orders')">View Order History</button>
                        </div>
                        
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <p>You haven't placed any orders yet.</p>
                                <p><a href="products.php">Start shopping now</a></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <?php
                                    $status = strtolower($order['status']);
                                    $statusClass = in_array($status, ['delivered', 'processing', 'pending', 'shipped']) ? $status : 'processing';
                                    $items = $orderItemsMap[$order['order_id']] ?? [];
                                ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <div class="order-id">Order #<?php echo str_pad((string) $order['order_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                            <div class="order-date"><?php echo date('d M Y', strtotime($order['created_at'])); ?> · <?php echo ucfirst($order['payment_status']); ?></div>
                                        </div>
                                        <span class="order-status <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                    </div>
                                    <div class="order-items">
                                        <?php if (empty($items)): ?>
                                            <div class="order-item-info" style="color: var(--gray); font-size: 14px;">
                                                Items for this order are unavailable.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($items as $item): ?>
                                                <?php $image = $item['image_url'] ?: 'https://via.placeholder.com/60x60?text=Thrift'; ?>
                                                <div class="order-item">
                                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                    <div class="order-item-info">
                                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                                        <p><?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['price']); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-total">
                                        Total Paid: <?php echo formatCurrency($order['total_amount']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="orders-section" class="content-section" style="display: none;">
                    <div class="content-header">
                        <h2>Order History</h2>
                        
                    </div>
                    <?php if (empty($recentOrders)): ?>
                        <div class="empty-state">
                            <p>Your order list is empty right now.</p>
                            <a class="btn btn-primary" href="products.php">Browse Products</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <?php
                                $status = strtolower($order['status']);
                                $statusClass = in_array($status, ['delivered', 'processing', 'pending', 'shipped']) ? $status : 'processing';
                                $items = $orderItemsMap[$order['order_id']] ?? [];
                            ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div>
                                        <div class="order-id">Order #<?php echo str_pad((string) $order['order_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                        <div class="order-date">
                                            <?php echo date('d M Y · h:i A', strtotime($order['created_at'])); ?>
                                        </div>
                                    </div>
                                    <span class="order-status <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                </div>
                                <div class="order-items">
                                    <?php foreach ($items as $item): ?>
                                        <?php $image = $item['image_url'] ?: 'https://via.placeholder.com/60x60?text=Thrift'; ?>
                                        <div class="order-item">
                                            <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                            <div class="order-item-info">
                                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                                <p><?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['price']); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="order-total">
                                    Grand Total: <?php echo formatCurrency($order['total_amount']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div id="wishlist-section" class="content-section" style="display: none;">
                    <div class="content-header">
                        <h2>Your Cart</h2>
                        <div>
                            <strong>Subtotal:</strong> <?php echo formatCurrency($cartSubtotal); ?>
                        </div>
                    </div>
                    <?php if (empty($cartItems)): ?>
                        <div class="empty-state">
                            <p>Your cart is empty. Add items from the shop to start your purchase.</p>
                            <a class="btn btn-primary" href="products.php">Continue Shopping</a>
                        </div>
                    <?php else: ?>
                        <div class="wishlist-grid">
                            <?php foreach ($cartItems as $item): ?>
                                <?php $image = $item['image_url'] ?: 'https://via.placeholder.com/200x200?text=Item'; ?>
                                <div class="wishlist-item">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="wishlist-item-info">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <div class="price"><?php echo formatCurrency($item['price']); ?></div>
                                        <p style="margin-bottom: 12px;">Category: <?php echo htmlspecialchars(ucwords($item['category'] ?? 'general')); ?></p>
                                        <div class="wishlist-actions">
                                            <form method="post" style="display:flex; gap:8px; width:100%; align-items:center;">
                                                <input type="hidden" name="action" value="update_cart">
                                                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                                <input type="number" name="quantity" min="1" value="<?php echo $item['quantity']; ?>" style="flex:1;">
                                                <button class="btn btn-primary" type="submit">Update</button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Remove this item?');">
                                                <input type="hidden" name="action" value="remove_cart_item">
                                                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                                <button class="btn btn-secondary" type="submit">Remove</button>
                                            </form>
                                        </div>
                                        <p class="muted-text" style="margin-top:10px;">Line Total: <?php echo formatCurrency($item['line_total']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="button-group" style="margin-top: 20px; justify-content: flex-end;">
                            <a class="btn btn-primary" href="checkout.php">Proceed to Checkout</a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="content-header" style="margin-top: 40px;">
                        <h2>Recommended For You</h2>
                    </div>
                    <div class="wishlist-grid">
                        <?php if (empty($recommendedItems)): ?>
                            <div class="empty-state" style="grid-column: 1 / -1;">
                                No recommendations available right now.
                            </div>
                        <?php else: ?>
                            <?php foreach ($recommendedItems as $item): ?>
                                <?php $image = $item['image_url'] ?: 'https://via.placeholder.com/200x200?text=Thrift'; ?>
                                <div class="wishlist-item">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <div class="wishlist-item-info">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <div class="price"><?php echo formatCurrency($item['price']); ?></div>
                                        <div class="wishlist-actions">
                                            <a class="btn btn-primary" href="products.php">View Product</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div id="addresses-section" class="content-section" style="display: none;">
                    <div class="content-header">
                        <h2>Saved Addresses</h2>
                        <button class="btn" onclick="alert('Address management coming soon!')">
                            <i class="fas fa-plus"></i> Add Address
                        </button>
                    </div>
                    <div class="address-section">
                        <p>Address management features will be available soon. For now, you can provide delivery details during checkout.</p>
                    </div>
                </div>
                
                <div id="settings-section" class="content-section" style="display: none;">
                    <div class="content-header">
                        <h2>Account Settings</h2>
                    </div>
                    
                    <div class="profile-section">
                        <h3>Profile Information</h3>
                        <form id="profileForm" onsubmit="handleProfileSubmit(event)">
                            <div class="form-group">
                                <label for="userNameInput">Full Name</label>
                                <input type="text" id="userNameInput" value="<?php echo htmlspecialchars($userName); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="userEmail">Email Address</label>
                                <input type="email" id="userEmail" value="">
                            </div>
                            <div class="button-group">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <button type="button" class="btn btn-secondary" onclick="resetProfileForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="profile-section">
                        <h3>Security</h3>
                        <form id="passwordForm" onsubmit="handlePasswordChange(event)">
                            <div class="form-group">
                                <label for="currentPassword">Current Password</label>
                                <input type="password" id="currentPassword" required>
                            </div>
                            <div class="form-group">
                                <label for="newPassword">New Password</label>
                                <input type="password" id="newPassword" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <input type="password" id="confirmPassword" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showSection(e, section) {
            if (e) e.preventDefault();
            document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
            document.getElementById(section + '-section').style.display = 'block';
            document.querySelectorAll('.sidebar-menu a').forEach(link => link.classList.remove('active'));
            if (e && e.currentTarget) {
                e.currentTarget.classList.add('active');
            }
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        function handleProfileSubmit(e) {
            e.preventDefault();
            alert('Profile updates will be available soon.');
        }
        
        function resetProfileForm() {
            document.getElementById('profileForm').reset();
        }
        
        function handlePasswordChange(e) {
            e.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match.');
                return;
            }
            alert('Password change is coming soon.');
            document.getElementById('passwordForm').reset();
        }
    </script>
    <script src="../script.js"></script>
</body>
</html>

