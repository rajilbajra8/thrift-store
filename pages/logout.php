<?php
session_start();

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - ThriftVibe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {
            background: #f5f7fa;
        }
        .logout-hero {
            position: relative;
            background: linear-gradient(rgba(0,0,0,0.55), rgba(0,0,0,0.55)),
                        url('https://images.unsplash.com/photo-1520006403909-838d6b92c22e?auto=format&fit=crop&w=1600&q=80') center/cover no-repeat;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
            padding: 60px 20px;
        }
        .logout-hero .logout-message {
            max-width: 720px;
        }
        .logout-message h1 {
            font-size: 2.8rem;
            margin-bottom: 1rem;
            color: #fff;
            line-height: 1.2;
        }
        .logout-message p {
            font-size: 1.25rem;
            color: #e9ecef;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .logout-message .cta-btn {
            padding: 14px 28px;
            border-radius: 32px;
            font-weight: 700;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header with Simple Navbar -->
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <a href="../index.php">ThriftVibe</a>
                </div>
            </div>
        </div>
        
        <nav>
            <div class="container">
                <ul class="nav-links">
                    <li><a href="../index.php" class="nav-link"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="products.php" class="nav-link"><i class="fas fa-tshirt"></i> Products</a></li>
                    <li><a href="about.html" class="nav-link"><i class="fas fa-info-circle"></i> About Us</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <!-- Logout Hero with Background -->
    <section class="logout-hero">
        <div class="logout-message">
            <h1>You've been logged out</h1>
            <p>Thank you for visiting ThriftVibe. You have been successfully logged out.</p>
            <a href="login.html" class="cta-btn">Sign In Again</a>
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
                    <a href="../index.php">Home</a>
                    <a href="products.php">Products</a>
                    <a href="about.html">About</a>
                    <a href="contact.html">Contact</a>
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

    <script src="../script.js"></script>
</body>
</html>

