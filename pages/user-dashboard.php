<?php
session_start();
require_once "db.php";

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Customer';
$userInitial = strtoupper(substr($userName, 0, 1));

// Simple flash message system
$flash = '';
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Simple currency formatting
function formatCurrency($amount) {
    return 'Rs ' . number_format($amount, 2);
}

// Simple image URL function
function getImageUrl($imageUrl) {
    if (empty($imageUrl)) {
        return 'https://via.placeholder.com/200x200?text=No+Image';
    }
    
    // If it's already a full URL, return it
    if (strpos($imageUrl, 'http') === 0) {
        return $imageUrl;
    }
    
    // For local paths, just return as-is
    return $imageUrl;
}

// Check if cart exists, if not create one
$cartId = 0;
$checkCart = $conn->query("SELECT cart_id FROM cart WHERE user_id = $userId LIMIT 1");
if ($checkCart && $checkCart->num_rows > 0) {
    $cart = $checkCart->fetch_assoc();
    $cartId = $cart['cart_id'];
} else {
    $conn->query("INSERT INTO cart (user_id) VALUES ($userId)");
    $cartId = $conn->insert_id;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_cart') {
        $cartItemId = $_POST['cart_item_id'] ?? 0;
        $quantity = $_POST['quantity'] ?? 1;
        
        if ($quantity < 1) $quantity = 1;
        
        if ($cartItemId > 0) {
            $conn->query("UPDATE cart_item SET quantity = $quantity WHERE cart_item_id = $cartItemId");
            $_SESSION['flash_message'] = 'Cart updated successfully';
            header("Location: user-dashboard.php");
            exit();
        }
    }
    
    if ($action === 'remove_cart_item') {
        $cartItemId = $_POST['cart_item_id'] ?? 0;
        
        if ($cartItemId > 0) {
            $conn->query("DELETE FROM cart_item WHERE cart_item_id = $cartItemId");
            $_SESSION['flash_message'] = 'Item removed from cart';
            header("Location: user-dashboard.php");
            exit();
        }
    }
    
    // Handle user profile update
    if ($action === 'update_profile') {
        $f_name = $conn->real_escape_string($_POST['f_name'] ?? '');
        $l_name = $conn->real_escape_string($_POST['l_name'] ?? '');
        $phone = $conn->real_escape_string($_POST['phone'] ?? '');
        $address = $conn->real_escape_string($_POST['address'] ?? '');
        
        // Update user details
        $updateQuery = "UPDATE user SET 
                        f_name = '$f_name',
                        l_name = '$l_name',
                        phone = '$phone',
                        address = '$address'
                        WHERE user_id = $userId";
        
        if ($conn->query($updateQuery)) {
            $_SESSION['flash_message'] = 'Profile updated successfully';
            $_SESSION['user_name'] = $f_name . ' ' . $l_name;
            header("Location: user-dashboard.php");
            exit();
        } else {
            $_SESSION['flash_message'] = 'Error updating profile: ' . $conn->error;
        }
    }
}

// Get current user details
$userDetails = [];
$userQuery = $conn->query("SELECT * FROM user WHERE user_id = $userId LIMIT 1");
if ($userQuery && $userQuery->num_rows > 0) {
    $userDetails = $userQuery->fetch_assoc();
}

// Get user stats
$stats = [
    'total_orders' => 0,
    'total_spent' => 0,
    'cart_items' => 0
];

// Total orders and amount spent
$orderStats = $conn->query("
    SELECT COUNT(*) as total_orders, 
           IFNULL(SUM(total_amount), 0) as total_spent 
    FROM shop_order 
    WHERE customer_id = $userId
");
if ($orderStats && $orderStats->num_rows > 0) {
    $row = $orderStats->fetch_assoc();
    $stats['total_orders'] = $row['total_orders'];
    $stats['total_spent'] = $row['total_spent'];
}

// Get recent orders (last 5)
$recentOrders = [];
$orders = $conn->query("
    SELECT order_id, total_amount, status, payment_status, created_at
    FROM shop_order
    WHERE customer_id = $userId
    ORDER BY created_at DESC
    LIMIT 5
");
if ($orders) {
    while ($order = $orders->fetch_assoc()) {
        $recentOrders[] = $order;
    }
}

// Get items for each order
$orderItems = [];
foreach ($recentOrders as $order) {
    $orderId = $order['order_id'];
    $items = $conn->query("
        SELECT oi.quantity, oi.price, it.name, it.image_url
        FROM order_item oi
        JOIN items it ON oi.item_id = it.item_id
        WHERE oi.order_id = $orderId
    ");
    
    $orderItems[$orderId] = [];
    if ($items) {
        while ($item = $items->fetch_assoc()) {
            $orderItems[$orderId][] = $item;
        }
    }
}

// Get cart items
$cartItems = [];
$cartSubtotal = 0;
if ($cartId > 0) {
    $cartQuery = $conn->query("
        SELECT ci.cart_item_id, ci.quantity, it.item_id, it.name, it.category, it.price, it.image_url
        FROM cart_item ci
        JOIN items it ON ci.item_id = it.item_id
        WHERE ci.cart_id = $cartId
        ORDER BY ci.cart_item_id DESC
    ");
    
    if ($cartQuery) {
        while ($item = $cartQuery->fetch_assoc()) {
            $item['line_total'] = $item['price'] * $item['quantity'];
            $cartSubtotal += $item['line_total'];
            $cartItems[] = $item;
            $stats['cart_items'] += $item['quantity'];
        }
    }
}

// Get recommended items
$recommendedItems = [];
$recommended = $conn->query("
    SELECT item_id, name, category, price, image_url
    FROM items
    ORDER BY created_at DESC
    LIMIT 4
");
if ($recommended) {
    while ($item = $recommended->fetch_assoc()) {
        $recommendedItems[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - ThriftVibe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        html, body {
            height: 100%;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
            width: 100%;
        }
        
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #2a9d8f;
        }
        
        .logo a {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
        }
        
        .logo a:hover {
            color: #21867a;
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
            background: #2a9d8f;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .logout-btn {
            background: #e76f51;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: #d65a3c;
        }
        
        .main-wrapper {
            flex: 1;
            padding: 20px 0;
            width: 100%;
        }
        
        .dashboard {
            display: flex;
            gap: 20px;
            min-height: calc(100vh - 150px);
            width: 100%;
        }
        
        .sidebar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            width: 250px;
            min-width: 250px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .sidebar h3 {
            color: #264653;
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
            background: #2a9d8f;
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
            flex: 1;
            min-width: 0; /* Prevents flex overflow */
            width: 100%;
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .content-header h2 {
            color: #264653;
            font-size: 28px;
        }
        
        .btn {
            background: #2a9d8f;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #21867a;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #2a9d8f;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.orders { border-left-color: #28a745; }
        .stat-card.savings { border-left-color: #e76f51; }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            font-size: 24px;
            color: #6c757d;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #2a9d8f;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .order-id { font-weight: 600; color: #264653; }
        .order-date { color: #6c757d; font-size: 14px; }
        
        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .order-status.delivered { background: #e7f5f3; color: #28a745; }
        .order-status.processing { background: #fff3e0; color: #ffc107; }
        .order-status.pending { background: #ffebee; color: #dc3545; }
        
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
            min-width: 200px;
        }
        
        .order-item img {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            object-fit: cover;
            border: 1px solid #eee;
        }
        
        .order-item-info h4 {
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .order-item-info p {
            font-size: 12px;
            color: #6c757d;
        }
        
        .order-total {
            text-align: right;
            font-weight: 600;
            color: #264653;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .wishlist-item {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .wishlist-item:hover {
            transform: translateY(-5px);
        }
        
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
            color: #264653;
        }
        
        .wishlist-item .price {
            font-size: 18px;
            font-weight: 600;
            color: #e76f51;
            margin-bottom: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            grid-column: 1 / -1;
        }
        
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            margin: 15px 0;
            background: rgba(40, 167, 69, 0.12);
            border: 1px solid rgba(40, 167, 69, 0.35);
            color: #1f6f3d;
            font-size: 14px;
        }
        
        .alert.error {
            background: rgba(220, 53, 69, 0.12);
            border-color: rgba(220, 53, 69, 0.35);
            color: #721c24;
        }
        
        .form-inline {
            display: flex;
            gap: 8px;
            align-items: center;
            margin: 10px 0;
        }
        
        .form-inline input[type="number"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 70px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-gray {
            background: #6c757d;
        }
        
        .btn-gray:hover {
            background: #5a6268;
        }
        
        /* Profile Form Styles */
        .profile-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #264653;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            color: #264653;
            font-weight: 500;
        }
        
        /* Make the page responsive */
        @media (max-width: 1100px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: static;
                margin-bottom: 20px;
            }
            
            .sidebar-menu {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .sidebar-menu li {
                margin-bottom: 0;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .wishlist-grid {
                grid-template-columns: 1fr;
            }
            
            .order-items {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .content-header h2 {
                font-size: 24px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .form-inline {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
   <header class="header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="../index.php">
                    <i class="fas fa-recycle"></i>
                    <span>ThriftVibe</span>
                </a>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar"><?php echo htmlspecialchars($userInitial); ?></div>
                    <div>
                        <div><?php echo htmlspecialchars($userName); ?></div>
                        <div style="font-size: 12px; color: #6c757d;">Customer</div>
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
            <div class="alert <?php echo strpos($flash, 'Error') !== false ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($flash); ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="main-wrapper">
        <div class="container">
            <div class="dashboard">
                <div class="sidebar">
                    <h3>My Account</h3>
                    <ul class="sidebar-menu">
                        <li><a href="#" class="active" onclick="showSection('dashboard')"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a href="#" onclick="showSection('orders')"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                        <li><a href="#" onclick="showSection('wishlist')"><i class="fas fa-shopping-cart"></i> My Cart</a></li>
                        <li><a href="#" onclick="showSection('profile')"><i class="fas fa-user"></i> My Profile</a></li>
                    </ul>
                </div>
                
                <div class="main-content">
                    <!-- Dashboard Section -->
                    <div id="dashboard-section" class="content-section">
                        <div class="content-header">
                            <h2>Welcome, <?php echo htmlspecialchars($userName); ?>!</h2>
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
                            
                            <div class="stat-card">
                                <div class="stat-header">
                                    <h3>Items in Cart</h3>
                                    <i class="fas fa-shopping-cart stat-icon"></i>
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
                            <div class="content-header">
                                <h2>Recent Orders</h2>
                                <button class="btn" onclick="showSection('orders')">View All Orders</button>
                            </div>
                            
                            <?php if (empty($recentOrders)): ?>
                                <div class="empty-state">
                                    <p>You haven't placed any orders yet.</p>
                                    <p><a href="products.php" class="btn">Start shopping now</a></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
                                    <?php
                                        $status = strtolower($order['status']);
                                        $statusClass = '';
                                        if ($status == 'delivered') $statusClass = 'delivered';
                                        elseif ($status == 'processing') $statusClass = 'processing';
                                        else $statusClass = 'pending';
                                    ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-id">Order #<?php echo str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                                <div class="order-date"><?php echo date('d M Y', strtotime($order['created_at'])); ?> · <?php echo ucfirst($order['payment_status']); ?></div>
                                            </div>
                                            <span class="order-status <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                        </div>
                                        <div class="order-items">
                                            <?php if (isset($orderItems[$order['order_id']]) && !empty($orderItems[$order['order_id']])): ?>
                                                <?php foreach ($orderItems[$order['order_id']] as $item): ?>
                                                    <?php $image = getImageUrl($item['image_url']); ?>
                                                    <div class="order-item">
                                                        <img src="../<?php echo htmlspecialchars($image); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                        <div class="order-item-info">
                                                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                                            <p><?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['price']); ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div>No items found for this order.</div>
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
                    
                    <!-- Orders Section -->
                    <div id="orders-section" class="content-section" style="display: none;">
                        <div class="content-header">
                            <h2>Order History</h2>
                            <a href="#" class="btn" onclick="showSection('dashboard')"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        </div>
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state">
                                <p>Your order list is empty right now.</p>
                                <a class="btn" href="products.php">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <?php
                                    $status = strtolower($order['status']);
                                    $statusClass = '';
                                    if ($status == 'delivered') $statusClass = 'delivered';
                                    elseif ($status == 'processing') $statusClass = 'processing';
                                    else $statusClass = 'pending';
                                ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <div class="order-id">Order #<?php echo str_pad($order['order_id'], 4, '0', STR_PAD_LEFT); ?></div>
                                            <div class="order-date">
                                                <?php echo date('d M Y · h:i A', strtotime($order['created_at'])); ?>
                                            </div>
                                        </div>
                                        <span class="order-status <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                    </div>
                                    <div class="order-items">
                                        <?php if (isset($orderItems[$order['order_id']])): ?>
                                            <?php foreach ($orderItems[$order['order_id']] as $item): ?>
                                                <?php $image = getImageUrl($item['image_url']); ?>
                                                <div class="order-item">
                                                    <img src="../<?php echo htmlspecialchars($image); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                    <div class="order-item-info">
                                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                                        <p><?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['price']); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-total">
                                        Grand Total: <?php echo formatCurrency($order['total_amount']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Cart Section -->
                    <div id="wishlist-section" class="content-section" style="display: none;">
                        <div class="content-header">
                            <h2>Your Cart</h2>
                            <div>
                                <strong>Subtotal:</strong> <?php echo formatCurrency($cartSubtotal); ?>
                                <a href="#" class="btn" onclick="showSection('dashboard')" style="margin-left: 15px;">Back to Dashboard</a>
                            </div>
                        </div>
                        <?php if (empty($cartItems)): ?>
                            <div class="empty-state">
                                <p>Your cart is empty. Add items from the shop to start your purchase.</p>
                                <a class="btn" href="products.php">Continue Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="wishlist-grid">
                                <?php foreach ($cartItems as $item): ?>
                                    <?php $image = getImageUrl($item['image_url']); ?>
                                    <div class="wishlist-item">
                                        <img src="../<?php echo htmlspecialchars($image); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div class="wishlist-item-info">
                                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                            <div class="price"><?php echo formatCurrency($item['price']); ?></div>
                                            <p style="margin-bottom: 12px; color: #6c757d;">Category: <?php echo htmlspecialchars(ucwords($item['category'] ?? 'general')); ?></p>
                                            <div class="form-inline">
                                                <form method="post" style="display:flex; gap:8px; width:100%; align-items:center;">
                                                    <input type="hidden" name="action" value="update_cart">
                                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                                    <input type="number" name="quantity" min="1" value="<?php echo $item['quantity']; ?>" style="flex:1;">
                                                    <button class="btn btn-small" type="submit">Update</button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Remove this item from cart?');">
                                                    <input type="hidden" name="action" value="remove_cart_item">
                                                    <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                                    <button type="submit" class="btn btn-small btn-gray">Remove</button>
                                                </form>
                                            </div>
                                            <p style="margin-top:10px; font-weight: 600;">Line Total: <?php echo formatCurrency($item['line_total']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top: 20px; text-align: right;">
                                <a class="btn" href="checkout.php">Proceed to Checkout</a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="content-header" style="margin-top: 40px;">
                            <h2>Recommended For You</h2>
                        </div>
                        <div class="wishlist-grid">
                            <?php if (empty($recommendedItems)): ?>
                                <div class="empty-state">
                                    No recommendations available right now.
                                </div>
                            <?php else: ?>
                                <?php foreach ($recommendedItems as $item): ?>
                                    <?php $image = getImageUrl($item['image_url']); ?>
                                    <div class="wishlist-item">
                                        <img src="../<?php echo htmlspecialchars($image); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <div class="wishlist-item-info">
                                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                            <div class="price"><?php echo formatCurrency($item['price']); ?></div>
                                            <div style="margin-top: 10px;">
                                                <a class="btn btn-small" href="products.php">View Product</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Profile Section -->
                    <div id="profile-section" class="content-section" style="display: none;">
                        <div class="content-header">
                            <h2>My Profile</h2>
                            <a href="#" class="btn" onclick="showSection('dashboard')"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                        </div>
                        
                        <div class="profile-form">
                            <form method="post">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="f_name">First Name *</label>
                                        <input type="text" id="f_name" name="f_name" value="<?php echo htmlspecialchars($userDetails['f_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="l_name">Last Name *</label>
                                        <input type="text" id="l_name" name="l_name" value="<?php echo htmlspecialchars($userDetails['l_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" value="<?php echo htmlspecialchars($userDetails['email'] ?? ''); ?>" readonly style="background: #f5f7fa; cursor: not-allowed;">
                                    <small style="color: #6c757d; font-size: 12px;">Email cannot be changed</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number *</label>
                                    <input type="tel" id="phone" name="phone" max="9999999999" min="9000000000" value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address">Delivery Address *</label>
                                    <textarea id="address" name="address" required><?php echo htmlspecialchars($userDetails['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div style="margin-top: 30px;">
                                    <button type="submit" class="btn">Update Profile</button>
                                    <a href="#" class="btn btn-gray" onclick="showSection('dashboard')" style="margin-left: 10px;">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(sec => {
                sec.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(section + '-section').style.display = 'block';
            
            // Update active menu item
            document.querySelectorAll('.sidebar-menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            // Find and activate the clicked menu item
            const activeLink = document.querySelector(`.sidebar-menu a[onclick*="${section}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Initialize the dashboard section on load
        document.addEventListener('DOMContentLoaded', function() {
            showSection('dashboard');
            
            // Check if URL has a hash to show specific section
            const hash = window.location.hash.substring(1);
            if (hash && ['dashboard', 'orders', 'wishlist', 'profile'].includes(hash)) {
                showSection(hash);
            }
        });
    </script>
</body>
</html>