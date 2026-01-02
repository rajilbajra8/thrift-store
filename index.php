<?php
// Add at the very top of the file
require_once "pages/db.php";

// Get 8 latest products from database
$query = "SELECT item_id, name, category, price, image_url, description 
          FROM items 
          ORDER BY created_at DESC 
          LIMIT 3";
$result = $conn->query($query);
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThriftVibe - Sustainable Fashion</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- Header with Search and Login -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <a href="index.php">ThriftVibe</a>
                </div>
                
                <div class="search-bar">
    <form method="GET" action="pages/products.php" id="searchForm" style="all: unset; display: contents;">
        <input type="text" id="searchInput" name="search" placeholder="Search for clothes, shoes, accessories...">
        <button type="submit" id="searchButton"><i class="fas fa-search"></i> Search</button>
    </form>
</div>
                
                <div class="user-actions">
                    <a href="pages/cart.php" id="cartButton">
                        <i class="fas fa-shopping-cart"></i> 
                        <span>Cart</span>
                        <span class="cart-count" id="cartCount">0</span>
                    </a>
                    <a href="pages/login.php" class="login-btn"><i class="fas fa-user"></i> <span>Login</span></a>
                </div>
            </div>
        </div>
        
        <nav>
            <div class="container">
                <ul class="nav-links">
                    <li><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="pages/products.php" class="nav-link"><i class="fas fa-tshirt"></i> Products</a></li>
                    <li><a href="pages/about.html" class="nav-link"><i class="fas fa-info-circle"></i> About Us</a></li>
                </ul>
            </div>
        </nav>
    </header>
    
    <!-- Hero Section -->
    <section class="hero" id="home-section">
        <div class="hero-content">
            <h1>Style That Doesn't Cost the Earth</h1>
            <p>Discover unique secondhand clothing and shoes at amazing prices</p>
            <a href="pages/products.php" class="cta-btn">Shop Now</a>
        </div>
    </section>
    
    <!-- Products Section -->
<section class="container" id="products-section">
    <h2 class="section-title">Featured Items</h2>
    
    <div class="products" id="productsContainer">
            <!-- If database has products, show them dynamically -->
            <?php foreach ($products as $product): ?>
                <?php 
                // $product[];
                $rawImage = trim($product['image_url'] ?? '');
                if ($rawImage === '') {
                    $image = 'https://images.unsplash.com/photo-1552374196-1ab2a1c593e8?ixlib=rb-4.0.3&auto=format&fit=crop&w=687&q=80';
                } elseif (preg_match('~^https?://~i', $rawImage)) {
                    $image = $rawImage;
                } else {
                    $image = $rawImage;
                }
                $price = 'Rs ' . number_format($product['price'], 2);
                ?>
                <div class="product-card">
                    <img src="<?php echo $image; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-img">
                    <div class="product-info">
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-price"><?php echo $price; ?></p>
                        <a class="buy-btn" href="pages/products.php">View Product</a>
                    </div>
                </div>
            <?php endforeach; ?>
    </div>
</section>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About Us</h3>
                    <p>ThriftVibe is dedicated to providing quality secondhand fashion while promoting sustainable shopping practices.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <a href="index.php">Home</a>
                    <a href="pages/products.php">Products</a>
                    <a href="pages/about.html">About</a>
                    <a href="pages/contact.html">Contact</a>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Patan,Lalitpur</p>
                    <p><i class="fas fa-phone"></i> 987654310</p>
                    <p><i class="fas fa-envelope"></i> info@thriftvibe.com</p>
                </div>
                
                <div class="footer-section">
                    <h3>Follow Us</h3>
                    <div class="socials">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; 2023 ThriftVibe. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Main JavaScript -->
    <script src="script.js"></script>
</body>
</html>