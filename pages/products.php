<?php
session_start();
require_once "db.php";

$isCustomer = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';
$cartFlash = $_SESSION['cart_flash'] ?? null;
unset($_SESSION['cart_flash']);

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$categories = [];
$categoryResult = $conn->query("SELECT DISTINCT category FROM items ORDER BY category ASC");
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        if (!empty($row['category'])) {
            $categories[] = $row['category'];
        }
    }
}

$query = "SELECT item_id, name, category, price, image_url, description FROM items WHERE 1=1";
$types = "";
$params = [];

if ($search !== '') {
    $query .= " AND (name LIKE ? OR category LIKE ?)";
    $like = '%' . $search . '%';
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
}

if ($category !== '') {
    $query .= " AND category = ?";
    $types .= "s";
    $params[] = $category;
}

$query .= " ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
}

$products = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $products = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

$currentPath = 'products.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - ThriftVibe</title>
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
        }
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        header {
            background-color: #fff;
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
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-actions a {
            color: #333;
            text-decoration: none;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .cart-count {
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .login-btn {
            background: var(--secondary);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            transition: background 0.3s;
        }
        .login-btn:hover {
            background: #d65a3c;
        }
        nav {
            background: var(--dark);
            padding: 12px 0;
        }
        .nav-links {
            display: flex;
            justify-content: center;
            list-style: none;
        }
        .nav-links li {
            margin: 0 15px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .nav-links a:hover, .nav-links a.active {
            background: rgba(255,255,255,0.1);
        }
        .page-header {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1520006403909-838d6b92c22e?auto=format&fit=crop&w=1400&q=80');
            background-size: cover;
            background-position: center;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        .page-header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filter-form input,
        .filter-form select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-form button {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(40,167,69,0.15);
            border: 1px solid rgba(40,167,69,0.4);
            color: #1f5130;
        }
        .alert-error {
            background: rgba(220,53,69,0.15);
            border: 1px solid rgba(220,53,69,0.4);
            color: #6e1b23;
        }
        .products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
        }
        .product-card img {
            width: 100%;
            height: 240px;
            object-fit: cover;
        }
        .product-info {
            padding: 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .product-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        .product-category {
            font-size: 14px;
            color: var(--gray);
        }
        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--secondary);
        }
        .product-info form {
            margin-top: auto;
        }
        .buy-btn, .buy-link {
            display: block;
            width: 100%;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }
        .buy-btn:hover, .buy-link:hover {
            background: #21867a;
        }
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <a href="../index.php" style="text-decoration:none;color:inherit;">ThriftVibe</a>
                </div>
                <div class="user-actions">
                    <a href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Cart</span>
                        <span class="cart-count" id="cartCount">0</span>
                    </a>
                    <a href="login.html" class="login-btn"><i class="fas fa-user"></i> <span>Login</span></a>
                </div>
            </div>
        </div>
        <nav>
            <div class="container">
                <ul class="nav-links">
                    <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="products.php" class="active"><i class="fas fa-tshirt"></i> Products</a></li>
                    <li><a href="about.html"><i class="fas fa-info-circle"></i> About Us</a></li>
                    
                </ul>
            </div>
        </nav>
    </header>

    <div class="page-header">
        <div>
            <h1>Shop Sustainable Fashion</h1>
            <p>Discover curated secondhand pieces from trusted sellers</p>
        </div>
    </div>

    <div class="container">
        <?php if ($cartFlash): ?>
            <div class="alert <?php echo $cartFlash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
                <?php echo htmlspecialchars($cartFlash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <form class="filter-form" method="get">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by item or category">
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo ucfirst($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>

        <section class="products-section">
            <h2 class="section-title" style="text-align:center;margin-bottom:25px;">Available Products</h2>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>No products matched your filters.</p>
                </div>
            <?php else: ?>
                <div class="products">
                    <?php foreach ($products as $product): ?>
                <?php
                    $rawImage = trim($product['image_url'] ?? '');
                    if ($rawImage === '') {
                        $image = 'https://via.placeholder.com/400x300?text=Thrift';
                    } elseif (preg_match('~^https?://~i', $rawImage)) {
                        $image = $rawImage;
                    } else {
                        // Stored path is relative to the project root; add ../ because this file is in /pages
                        $image = '../' . ltrim($rawImage, '/');
                    }
                ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars(ucwords($product['category'] ?? '')); ?></div>
                                <div class="product-title"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-price">Rs <?php echo number_format($product['price'], 2); ?></div>
                                <p style="color:var(--gray);font-size:13px;">
                                    <?php echo htmlspecialchars($product['description'] ?: 'A pre-loved item in great condition.'); ?>
                                </p>
                                <?php if ($isCustomer): ?>
                                    <form method="post" action="cart.php">
                                        <input type="hidden" name="action" value="add_item">
                                        <input type="hidden" name="item_id" value="<?php echo (int) $product['item_id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentPath); ?>">
                                        <button type="submit" class="buy-btn"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                                    </form>
                                <?php else: ?>
                                    <a class="buy-link" href="login.html">Login to add</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div>
                    <h3>About ThriftVibe</h3>
                    <p>We connect conscious shoppers with quality secondhand finds.</p>
                </div>
                <div>
                    <h3>Quick Links</h3>
                    <a href="../index.php" style="text-decoration:none;color:inherit;">Home</a><br>
                    <a href="products.php" style="text-decoration:none;color:inherit;">Products</a><br>
                    <a href="cart.php" style="text-decoration:none;color:inherit;">Cart</a>
                </div>
            </div>
            <div class="copyright">
                <p style="text-align:center;margin-top:20px;">&copy; <?php echo date('Y'); ?> ThriftVibe. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../script.js"></script>
</body>
</html>

