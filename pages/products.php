<?php
session_start();
require_once "db.php";

// ✅ ADD CHECKOUT SUCCESS CHECK
$checkoutSuccess = false;
$orderId = '';
if (isset($_GET['checkout']) && $_GET['checkout'] === 'success') {
    $checkoutSuccess = true;
    $orderId = $_GET['order_id'] ?? '';
}

$isCustomer = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';
$isSeller = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'seller';

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
    <link rel="stylesheet" href="products.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <a href="../index.php" style="text-decoration:none;color:inherit;">ThriftVibe</a>
                </div>
               
                <!-- Search Bar in Header -->
                <div class="search-bar">
                    <form method="GET" action="products.php" id="searchForm">
                        <input type="text" id="searchInput" name="search"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search for clothes, shoes, accessories...">
                        <button type="submit" id="searchButton">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                </div>
               
                <div class="user-actions">
                    <a href="cart.php" id="cartButton">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Cart</span>
                        <span class="cart-count" id="cartCount">0</span>
                    </a>
                    <a href="login.html" class="login-btn">
                        <i class="fas fa-user"></i>
                        <span>Login</span>
                    </a>
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

        <!-- ✅ CHECKOUT SUCCESS MESSAGE -->
        <?php if ($checkoutSuccess): ?>
            <div class="checkout-success">
                <h3><i class="fas fa-check-circle"></i> Checkout Successful!</h3>
                <p>Thank you for your order! Your payment is confirmed and your items are being prepared.</p>
                <?php if ($orderId): ?>
                    <div class="order-id">Order ID: #<?php echo htmlspecialchars($orderId); ?></div>
                <?php endif; ?>
                <p><i class="fas fa-info-circle"></i> You will receive an email confirmation shortly. Keep shopping for more great finds!</p>
            </div>
        <?php endif; ?>

        <!-- Category Filter Section (now only category filter) -->
        <div class="filter-section">
            <form class="filter-form" method="get">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo ucfirst($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><i class="fas fa-filter"></i> Filter by Category</button>
                <?php if ($search !== '' || $category !== ''): ?>
                    <a href="products.php" class="login-btn" style="background: var(--gray);">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <section class="products-section">
            <h2 class="section-title" style="text-align:center;25px;">
                Available Products
            </h2>
           
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>No products matched your filters.</p>
                    <a href="products.php" class="login-btn" style=" 15px;">
                        <i class="fas fa-redo"></i> View All Products
                    </a>
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
                            $image = '../' . ltrim($rawImage, '/');
                        }
                        ?>
                        <div class="product-card">
                            <img src="<?php echo htmlspecialchars($image); ?>"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="product-info">
                                <div class="product-category">
                                    <?php echo htmlspecialchars(ucwords($product['category'] ?? '')); ?>
                                </div>
                                <div class="product-title">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </div>
                                <div class="product-price">
                                    Rs <?php echo number_format($product['price'], 2); ?>
                                </div>
                                <p style="color:var(--gray);font-size:13px;">
                                    <?php echo htmlspecialchars($product['description'] ?: 'A pre-loved item in great condition.'); ?>
                                </p>
                               
                                <?php if ($isCustomer): ?>
                                    <form method="post" action="cart.php">
                                        <input type="hidden" name="action" value="add_item">
                                        <input type="hidden" name="item_id" value="<?php echo (int) $product['item_id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($currentPath); ?>">
                                        <button type="submit" class="buy-btn">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </form>
                                <?php elseif (!$isSeller): ?>
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
                <p style="text-align:center;20px;">&copy; <?php echo date('Y'); ?> ThriftVibe. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="../script.js"></script>
    <script>
        // JavaScript to enhance search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchForm = document.getElementById('searchForm');
           
            // Focus on search input when page loads
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }
           
            // Auto-submit category filter when changed
            const categorySelect = document.querySelector('select[name="category"]');
            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    this.form.submit();
                });
            }
        });
    </script>
</body>
</html>