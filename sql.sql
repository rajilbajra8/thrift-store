
USE thrift;

-- User table
CREATE TABLE user (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    f_name VARCHAR(50) NOT NULL,
    l_name VARCHAR(50) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    role ENUM('customer', 'seller', 'staff') DEFAULT 'customer',
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Items table
CREATE TABLE items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    added_by INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    size VARCHAR(20),
    `condition` ENUM('new', 'like_new', 'good', 'fair', 'poor') NOT NULL,
    category VARCHAR(50) NOT NULL,
    quantity INT DEFAULT 1,
    description TEXT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES user(user_id) ON DELETE CASCADE
);

-- Item images table
CREATE TABLE item_image (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    imagepath VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE
);

-- Cart table
CREATE TABLE cart (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE
);

-- Cart items table
CREATE TABLE cart_item (
    cart_item_id INT PRIMARY KEY AUTO_INCREMENT,
    cart_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES cart(cart_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (cart_id, item_id)
);

-- Shop order table
CREATE TABLE shop_order (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    status ENUM('processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'processing',
    shipping_name VARCHAR(100),
    shipping_email VARCHAR(120),
    shipping_phone VARCHAR(30),
    shipping_address TEXT,
    shipping_city VARCHAR(120),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES user(user_id) ON DELETE RESTRICT
);

-- Order items table
CREATE TABLE order_item (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES shop_order(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT
);

-- Create indexes for better query performance
CREATE INDEX idx_user_role ON user(role);
CREATE INDEX idx_items_category ON items(category);
CREATE INDEX idx_items_condition ON items(`condition`);
CREATE INDEX idx_shop_order_customer ON shop_order(customer_id);
CREATE INDEX idx_shop_order_status ON shop_order(status);
CREATE INDEX idx_cart_user ON cart(user_id);
CREATE INDEX idx_item_image_item ON item_image(item_id);

ALTER TABLE items ADD COLUMN featured tinyint(1) default 0;