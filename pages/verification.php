<?php
// Add at the very top of the file
require_once "db.php";
session_start();

// Check if verification code should be verified
$verification_error = '';
$verification_success = false;

if (isset($_POST['verify_email'])) {
    $email = $_POST['email'] ?? '';
    $verification_code = $_POST['verification_code'] ?? '';
    
    if (!empty($email) && !empty($verification_code)) {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $verification_error = "Invalid email format";
        } else {
            // Check verification code in database
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND code = ? AND is_verified = 0");
            $stmt->bind_param("ss", $email, $verification_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Code is valid - activate account
                $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, code = NULL WHERE email = ?");
                $update_stmt->bind_param("s", $email);
                $update_stmt->execute();
                
                $verification_success = true;
                $_SESSION['verification_success'] = "Email verified successfully! You can now login.";
                header("Location: pages/login.php");
                exit();
            } else {
                $verification_error = "Invalid verification code or email";
            }
        }
    } else {
        $verification_error = "Please enter both email and verification code";
    }
}

global $role;
// Get 3 latest products from database
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
    <style>
        /* Verification Form Styles */
        .verification-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 60px 0;
            margin: 40px 0;
        }
        
        .verification-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        .verification-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .verification-subtitle {
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 16px;
        }
        
        .verification-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-input {
            padding: 12px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        .verification-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .verification-btn:hover {
            background: linear-gradient(135deg, #2980b9, #1f639e);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 128, 185, 0.3);
        }
        
        .verification-note {
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            font-size: 14px;
            color: #555;
        }
        
        .error-message {
            background: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .resend-link {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .resend-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .resend-link a:hover {
            text-decoration: underline;
        }
        
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
        }
        
        .code-input:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
            outline: none;
        }
    </style>
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
   
    <!-- Email Verification Section -->
    <section class="verification-section">
        <div class="container">
            <div class="verification-container">
                <h2 class="verification-title">Verify Your Email</h2>
                <p class="verification-subtitle">Enter the verification code sent to your email address</p>
                
                <?php if (!empty($verification_error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($verification_error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="verification-form">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               placeholder="Enter your email address"
                               value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="verification_code"><i class="fas fa-key"></i> Verification Code</label>
                        <input type="text" 
                               id="verification_code" 
                               name="verification_code" 
                               class="form-input" 
                               placeholder="Enter 6-digit verification code"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               title="Please enter a 6-digit code"
                               required>
                    </div>
                    
                    <button type="submit" name="verify_email" class="verification-btn">
                        <i class="fas fa-check-circle"></i> Verify Email
                    </button>
                </form>
                
                <div class="verification-note">
                    <p><strong>Note:</strong> The verification code was sent to your email after registration. If you didn't receive it:</p>
                    <ul style="margin: 10px 0 10px 20px;">
                        <li>Check your spam folder</li>
                        <li>Make sure you entered the correct email address</li>
                        <li>Wait a few minutes for delivery</li>
                    </ul>
                </div>
                
                <div class="resend-link">
                    <p>Didn't receive the code? <a href="pages/resend_verification.php">Resend verification code</a></p>
                </div>
            </div>
        </div>
    </section>
   
    <!-- Products Section -->
    <section class="container" id="products-section">
        <h2 class="section-title">Latest Items</h2>
       
        <div class="products" id="productsContainer">
            <!-- If database has products, show them dynamically -->
            <?php foreach ($products as $product): ?>
                <?php
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
                    <p><i class="fas fa-phone"></i> 9876543101</p>
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
    
    <!-- Verification Form JavaScript -->
    <script>
        // Auto-advance between verification code inputs
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('verification_code');
            
            if (codeInput) {
                codeInput.addEventListener('input', function(e) {
                    if (this.value.length === 6) {
                        this.form.submit();
                    }
                });
                
                // Auto-focus on code input if email is prefilled
                const emailInput = document.getElementById('email');
                if (emailInput && emailInput.value) {
                    codeInput.focus();
                }
            }
            
            // Show resend confirmation
            const resendLink = document.querySelector('.resend-link a');
            if (resendLink) {
                resendLink.addEventListener('click', function(e) {
                    if (!confirm('Send a new verification code to your email?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>