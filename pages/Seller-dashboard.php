<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Seller User';
$user_initial = strtoupper(substr($user_name, 0, 1));
$flash = $_SESSION['seller_flash'] ?? null;
unset($_SESSION['seller_flash']);

$addFormData = [
    'product_name' => '',
    'product_category' => '',
    'product_price' => '',
    'product_quantity' => '',
    'product_size' => '',
    'product_condition' => 'new',
    'product_description' => '',
];
$formErrors = [];
$editErrors = [];
$shouldOpenAddModal = false;
$editingProduct = null;

function setSellerFlash(string $type, string $message): void
{
    $_SESSION['seller_flash'] = ['type' => $type, 'message' => $message];
}

function validateProductInput(array $source, string $prefix = ''): array
{
    $payload = [
        'name' => trim($source[$prefix . 'product_name'] ?? ''),
        'category' => trim($source[$prefix . 'product_category'] ?? ''),
        'price' => $source[$prefix . 'product_price'] ?? '',
        'quantity' => $source[$prefix . 'product_quantity'] ?? '',
        'size' => trim($source[$prefix . 'product_size'] ?? ''),
        'condition' => trim($source[$prefix . 'product_condition'] ?? ''),
        'description' => trim($source[$prefix . 'product_description'] ?? ''),
    ];

    $errors = [];

    if ($payload['name'] === '') {
        $errors[$prefix . 'product_name'] = 'Product name is required.';
    }

    if ($payload['category'] === '') {
        $errors[$prefix . 'product_category'] = 'Please pick a category.';
    } else {
        $allowedCategories = ['clothing', 'shoes', 'accessories', 'vintage'];
        if (!in_array($payload['category'], $allowedCategories, true)) {
            $errors[$prefix . 'product_category'] = 'Choose a valid category.';
        }
    }

    $priceValue = filter_var($payload['price'], FILTER_VALIDATE_FLOAT);
    if ($priceValue === false || $priceValue < 0) {
        $errors[$prefix . 'product_price'] = 'Enter a valid price.';
    } else {
        $payload['price'] = round($priceValue, 2);
    }

    $quantityValue = filter_var(
        $payload['quantity'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );
    if ($quantityValue === false) {
        $errors[$prefix . 'product_quantity'] = 'Quantity must be zero or more.';
    } else {
        $payload['quantity'] = $quantityValue;
    }

    $allowedConditions = ['new', 'like_new', 'good', 'fair', 'poor'];
    if (!in_array($payload['condition'], $allowedConditions, true)) {
        $errors[$prefix . 'product_condition'] = 'Select a valid condition.';
    }

    return [$payload, $errors];
}

/**
 * Handle product image upload. Returns relative path or null on no file. Appends error to $errors on failure.
 */
function handleImageUpload(string $inputName, array &$errors, string $errorKey, ?string $existingPath = null): ?string
{
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath; // keep previous or null
    }

    $file = $_FILES[$inputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[$errorKey] = 'Image upload failed. Please try again.';
        return $existingPath;
    }

    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMime, true)) {
        $errors[$errorKey] = 'Please upload a JPG, PNG, or WEBP image.';
        return $existingPath;
    }

    if ($file['size'] > 2 * 1024 * 1024) { // 2MB
        $errors[$errorKey] = 'Image must be under 2MB.';
        return $existingPath;
    }

    $uploadDir = dirname(__DIR__) . '/assets/images/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $basename = 'item_' . time() . '_' . bin2hex(random_bytes(4));
    $filename = $basename . '.' . $extension;
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errors[$errorKey] = 'Unable to save image. Please try again.';
        return $existingPath;
    }

    // Return web-accessible relative path
    return 'assets/images/uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        [$payload, $formErrors] = validateProductInput($_POST);
        $imagePath = handleImageUpload('product_image', $formErrors, 'product_image', null);
        if ($imagePath === null) {
            $imagePath = '';
        }
        $shouldOpenAddModal = true;
        $addFormData = array_merge($addFormData, [
            'product_name' => trim($_POST['product_name'] ?? ''),
            'product_category' => trim($_POST['product_category'] ?? ''),
            'product_price' => trim($_POST['product_price'] ?? ''),
            'product_quantity' => trim($_POST['product_quantity'] ?? ''),
            'product_size' => trim($_POST['product_size'] ?? ''),
            'product_condition' => trim($_POST['product_condition'] ?? 'new'),
            'product_description' => trim($_POST['product_description'] ?? ''),
        ]);

        if (empty($formErrors)) {
            $stmt = $conn->prepare(
                "INSERT INTO items (name, category, price, quantity, size, `condition`, description, image_url, added_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            if ($stmt) {
                $stmt->bind_param(
                    "ssdissssi",
                    $payload['name'],
                    $payload['category'],
                    $payload['price'],
                    $payload['quantity'],
                    $payload['size'],
                    $payload['condition'],
                    $payload['description'],
                    $imagePath,
                    $user_id
                );

                if ($stmt->execute()) {
                    setSellerFlash('success', 'Product added successfully.');
                    header("Location: Seller-dashboard.php");
                    exit();
                } else {
                    $formErrors['general'] = 'Unable to save product. Please try again.';
                }
                $stmt->close();
            } else {
                $formErrors['general'] = 'Server error: unable to prepare statement.';
            }
        }
    } elseif ($action === 'update_product') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        [$payload, $editErrors] = validateProductInput($_POST, 'edit_');

        // fetch current image to preserve if no new upload
        $currentImagePath = null;
        if ($itemId > 0) {
            $imgStmt = $conn->prepare("SELECT image_url FROM items WHERE item_id = ? AND added_by = ?");
            if ($imgStmt) {
                $imgStmt->bind_param("ii", $itemId, $user_id);
                $imgStmt->execute();
                $imgResult = $imgStmt->get_result();
                $row = $imgResult ? $imgResult->fetch_assoc() : null;
                $currentImagePath = $row['image_url'] ?? null;
                $imgStmt->close();
            }
        }

        $newImagePath = handleImageUpload('edit_product_image', $editErrors, 'edit_product_image', $currentImagePath);
        if ($newImagePath === null) {
            $newImagePath = $currentImagePath ?? '';
        }

        if ($itemId <= 0) {
            $editErrors['general'] = 'Invalid product selected.';
        }

        if (empty($editErrors)) {
            $stmt = $conn->prepare(
                "UPDATE items
                 SET name = ?, category = ?, price = ?, quantity = ?, size = ?, `condition` = ?, description = ?, image_url = ?
                 WHERE item_id = ? AND added_by = ?"
            );

            if ($stmt) {
                $stmt->bind_param(
                    "ssdissssii",
                    $payload['name'],
                    $payload['category'],
                    $payload['price'],
                    $payload['quantity'],
                    $payload['size'],
                    $payload['condition'],
                    $payload['description'],
                    $newImagePath,
                    $itemId,
                    $user_id
                );

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    setSellerFlash('success', 'Product updated successfully.');
                    header("Location: Seller-dashboard.php");
                    exit();
                } else {
                    $editErrors['general'] = 'No changes saved or product not found.';
                }
                $stmt->close();
            } else {
                $editErrors['general'] = 'Server error: unable to prepare update.';
            }
        }

        $editingProduct = array_merge(['item_id' => $itemId], $payload);
    } elseif ($action === 'delete_product') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            setSellerFlash('error', 'Invalid product selection.');
        } else {
            $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ? AND added_by = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $itemId, $user_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    setSellerFlash('success', 'Product deleted successfully.');
                } else {
                    setSellerFlash('error', 'Unable to delete product.');
                }
                $stmt->close();
            } else {
                setSellerFlash('error', 'Server error: unable to delete product.');
            }
        }
        header("Location: Seller-dashboard.php");
        exit();
    }
}

if ($editingProduct === null && isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare(
            "SELECT item_id, name, category, price, quantity, size, `condition`, description, image_url
             FROM items
             WHERE item_id = ? AND added_by = ?"
        );
        if ($stmt) {
            $stmt->bind_param("ii", $editId, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $editingProduct = $result->fetch_assoc() ?: null;
            $stmt->close();
        }
    }
}

$productList = [];
$stmt = $conn->prepare(
    "SELECT item_id, name, category, price, quantity, size, `condition`, description, image_url, created_at
     FROM items
     WHERE added_by = ?
     ORDER BY created_at DESC"
);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $productList = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

$totalProducts = count($productList);
$totalUnits = 0;
$lowStockCount = 0;
$outOfStockCount = 0;
$inventoryValue = 0;
foreach ($productList as $product) {
    $qty = (int) ($product['quantity'] ?? 0);
    $totalUnits += $qty;
    $inventoryValue += ($product['price'] ?? 0) * $qty;
    if ($qty <= 0) {
        $outOfStockCount++;
    } elseif ($qty <= 5) {
        $lowStockCount++;
    }
}

$categoryOptions = [
    'clothing' => 'Clothing',
    'shoes' => 'Shoes',
    'accessories' => 'Accessories',
    'vintage' => 'Vintage',
];

$conditionOptions = [
    'new' => 'Brand New',
    'like_new' => 'Like New',
    'good' => 'Good',
    'fair' => 'Fair',
    'poor' => 'Well Loved',
];

// Orders that include this seller's items
$ordersList = [];
$ordersStmt = $conn->prepare(
    "SELECT 
        so.order_id,
        so.status,
        so.payment_status,
        so.created_at,
        COALESCE(so.shipping_name, u.f_name) AS customer_name,
        COALESCE(so.shipping_email, u.email) AS customer_email,
        SUM(oi.price * oi.quantity) AS seller_total,
        SUM(oi.quantity) AS items_count
    FROM shop_order so
    JOIN order_item oi ON so.order_id = oi.order_id
    JOIN items i ON i.item_id = oi.item_id
    JOIN user u ON u.user_id = so.customer_id
    WHERE i.added_by = ?
    GROUP BY so.order_id, so.status, so.payment_status, so.created_at, customer_name, customer_email
    ORDER BY so.created_at DESC
    LIMIT 50"
);
if ($ordersStmt) {
    $ordersStmt->bind_param("i", $user_id);
    $ordersStmt->execute();
    $ordersResult = $ordersStmt->get_result();
    if ($ordersResult) {
        $ordersList = $ordersResult->fetch_all(MYSQLI_ASSOC);
    }
    $ordersStmt->close();
}

// Customers who purchased this seller's items
$customersList = [];
$customersStmt = $conn->prepare(
    "SELECT 
        u.user_id,
        u.f_name,
        u.email,
        COUNT(DISTINCT so.order_id) AS orders_count,
        SUM(oi.price * oi.quantity) AS total_spent,
        MAX(so.created_at) AS last_order
    FROM user u
    JOIN shop_order so ON so.customer_id = u.user_id
    JOIN order_item oi ON oi.order_id = so.order_id
    JOIN items i ON i.item_id = oi.item_id
    WHERE i.added_by = ?
    GROUP BY u.user_id, u.f_name, u.email
    ORDER BY total_spent DESC
    LIMIT 50"
);
if ($customersStmt) {
    $customersStmt->bind_param("i", $user_id);
    $customersStmt->execute();
    $customersResult = $customersStmt->get_result();
    if ($customersResult) {
        $customersList = $customersResult->fetch_all(MYSQLI_ASSOC);
    }
    $customersStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - ThriftVibe</title>
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

        .muted-text {
            font-size: 13px;
            color: var(--gray);
            margin-top: 4px;
        }

        .edit-product-panel {
            background: #fff7e6;
            border: 1px solid #ffe0a3;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .edit-product-panel h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }

        .btn-group form {
            display: inline;
        }

        .empty-table-row {
            text-align: center;
            padding: 30px;
            color: var(--gray);
        }
        
        /* Header */
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
        }
        
        .logo i {
            margin-right: 10px;
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
        
        /* Dashboard Layout */
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            margin: 20px 0;
            min-height: calc(100vh - 100px);
        }
        
        /* Sidebar */
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
        
        /* Main Content */
        .main-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .content-section {
            display: none;
        }
        
        .content-section.active {
            display: block;
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
        }
        
        .content-header .btn:hover {
            background: #21867a;
        }
        
        /* Stats Cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            align-items: stretch;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
            min-height: 150px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.sales {
            border-left-color: var(--success);
        }
        
        .stat-card.products {
            border-left-color: var(--warning);
        }
        
        .stat-card.customers {
            border-left-color: var(--primary);
        }
        
        .stat-card.revenue {
            border-left-color: var(--secondary);
        }
        
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
        
        .stat-change {
            font-size: 12px;
            font-weight: 600;
        }
        
        .stat-change.positive {
            color: var(--success);
        }
        
        .stat-change.negative {
            color: var(--danger);
        }
        
        /* Inventory Table */
        .inventory-section {
            margin-top: 30px;
        }
        
        .inventory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .inventory-header h3 {
            color: var(--dark);
            font-size: 22px;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .inventory-table th, .inventory-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .inventory-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        .inventory-table tr:hover {
            background: #f9f9f9;
        }
        
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status.in-stock {
            background: #e7f5f3;
            color: var(--success);
        }
        
        .status.low-stock {
            background: #fff3e0;
            color: var(--warning);
        }
        
        .status.out-of-stock {
            background: #ffebee;
            color: var(--danger);
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #21867a;
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Add Product Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            color: var(--dark);
            font-size: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .inventory-table {
                display: block;
                overflow-x: auto;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats {
                grid-template-columns: 1fr;
            }
            
            .content-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-recycle"></i>
                    <a href="../index.php" style="color: inherit; text-decoration: none;">ThriftVibe Seller</a>
                </div>
                
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar" id="userAvatar"><?php echo htmlspecialchars($user_initial); ?></div>
                        <div>
                            <div id="userName"><?php echo htmlspecialchars($user_name); ?></div>
                            <div style="font-size: 12px; color: var(--gray);">Seller Account</div>
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
    
    <!-- Dashboard -->
    <div class="container">
        <div class="dashboard">
            <!-- Sidebar -->
            <div class="sidebar">
                <h3>Management</h3>
                <ul class="sidebar-menu">
                    <li><a href="#" class="active" onclick="showSection(event, 'dashboard')"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#inventory-management" onclick="showSection(event, 'inventory')"><i class="fas fa-box"></i> Inventory</a></li>
                    <li><a href="#Order-management" onclick="showSection(event, 'orders')"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                    <li><a href="#Customer-management" onclick="showSection(event, 'customers')"><i class="fas fa-users"></i> Customers</a></li>
                </ul>
                
                <h3>Quick Actions</h3>
                <ul class="sidebar-menu">
                    <li><a href="#" onclick="openAddProductModal(); return false;"><i class="fas fa-plus"></i> Add Product</a></li>
                </ul>
            </div>
            
            <!-- Main Content -->
            <div class="main-content">
                <!-- Dashboard Section -->
                <div id="dashboard-section" class="content-section active">
                    <div class="content-header">
                        <h2>Dashboard Overview</h2>
                        <button class="btn" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    
                    <div class="stats">
                        <div class="stat-card sales">
                            <div class="stat-header">
                                <i class="fas fa-boxes stat-icon"></i>
                                <span class="stat-change positive">Live</span>
                            </div>
                            <div class="stat-value"><?php echo $totalProducts; ?></div>
                            <div class="stat-label">Active Products</div>
                        </div>
                        
                        <div class="stat-card products">
                            <div class="stat-header">
                                <i class="fas fa-layer-group stat-icon"></i>
                                <span class="stat-change positive">Units</span>
                            </div>
                            <div class="stat-value"><?php echo $totalUnits; ?></div>
                            <div class="stat-label">Total Inventory</div>
                        </div>
                        
                        <div class="stat-card revenue">
                            <div class="stat-header">
                                <i class="fas fa-rupee-sign stat-icon"></i>
                                <span class="stat-change positive">Value</span>
                            </div>
                            <div class="stat-value">Rs <?php echo number_format((float) $inventoryValue, 2); ?></div>
                            <div class="stat-label">Inventory Value</div>
                        </div>
                        
                        <div class="stat-card customers">
                            <div class="stat-header">
                                <i class="fas fa-exclamation-triangle stat-icon"></i>
                                <span class="stat-change warning">Check</span>
                            </div>
                            <div class="stat-value"><?php echo $lowStockCount; ?></div>
                            <div class="stat-label">Low Stock Items</div>
                        </div>
                        
                        <div class="stat-card revenue">
                            <div class="stat-header">
                                <i class="fas fa-ban stat-icon"></i>
                                <span class="stat-change danger">Action</span>
                            </div>
                            <div class="stat-value"><?php echo $outOfStockCount; ?></div>
                            <div class="stat-label">Out of Stock</div>
                        </div>
                    </div>
                    
                    <?php if ($editingProduct): ?>
                    <div class="edit-product-panel" id="editProductPanel">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <h3>Edit Product</h3>
                            <a href="Seller-dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>

                        <?php if (!empty($editErrors)): ?>
                            <div class="alert alert-error">
                                <strong>Unable to save product:</strong>
                                <ul>
                                    <?php foreach ($editErrors as $message): ?>
                                        <li><?php echo htmlspecialchars($message); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_product">
                            <input type="hidden" name="item_id" value="<?php echo (int) ($editingProduct['item_id'] ?? 0); ?>">

                            <div class="form-group">
                                <label for="editProductName">Product Name</label>
                                <input type="text" id="editProductName" name="edit_product_name" class="form-control" required value="<?php echo htmlspecialchars($editingProduct['name'] ?? ''); ?>">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editProductCategory">Category</label>
                                    <select id="editProductCategory" name="edit_product_category" class="form-control" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categoryOptions as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo (($editingProduct['category'] ?? '') === $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="editProductPrice">Price (Rs)</label>
                                    <input type="number" step="0.01" id="editProductPrice" name="edit_product_price" class="form-control" required value="<?php echo htmlspecialchars($editingProduct['price'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editProductQuantity">Stock Quantity</label>
                                    <input type="number" id="editProductQuantity" name="edit_product_quantity" class="form-control" min="0" required value="<?php echo htmlspecialchars($editingProduct['quantity'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="editProductSize">Size / Dimensions</label>
                                    <input type="text" id="editProductSize" name="edit_product_size" class="form-control" value="<?php echo htmlspecialchars($editingProduct['size'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="editProductCondition">Condition</label>
                                    <select id="editProductCondition" name="edit_product_condition" class="form-control" required>
                                        <option value="">Select Condition</option>
                                        <?php foreach ($conditionOptions as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo (($editingProduct['condition'] ?? '') === $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="editProductImage">Product Image (upload to replace)</label>
                                    <input type="file" id="editProductImage" name="edit_product_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                    <?php if (!empty($editingProduct['image_url'])): ?>
                                        <div class="muted-text" style="margin-top:6px;">
                                            Current: <a href="../<?php echo htmlspecialchars($editingProduct['image_url']); ?>" target="_blank">View image</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="editProductDescription">Description</label>
                                <textarea id="editProductDescription" name="edit_product_description" class="form-control" rows="3"><?php echo htmlspecialchars($editingProduct['description'] ?? ''); ?></textarea>
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="inventory-section" id="inventory">
                        <div class="inventory-header">
                            <h3>Recent Inventory Updates</h3>
                            <button class="btn btn-primary" onclick="showSection(event, 'inventory')">View All</button>
                        </div>
                        
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $recentProducts = array_slice($productList, 0, 4);
                                ?>
                                <?php if (empty($recentProducts)): ?>
                                    <tr>
                                        <td colspan="5" class="empty-table-row">No inventory yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentProducts as $product): ?>
                                        <?php
                                            $qty = (int) ($product['quantity'] ?? 0);
                                            $statusClass = 'in-stock';
                                            $statusLabel = 'In Stock';
                                            if ($qty <= 0) {
                                                $statusClass = 'out-of-stock';
                                                $statusLabel = 'Out of Stock';
                                            } elseif ($qty <= 5) {
                                                $statusClass = 'low-stock';
                                                $statusLabel = 'Low Stock';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars(ucwords($product['category'])); ?></td>
                                            <td><?php echo $qty; ?></td>
                                            <td><span class="status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                            <td>
                                                <a class="btn btn-primary" href="?edit=<?php echo $product['item_id']; ?>">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Inventory Section -->
                <div id="inventory-section" class="content-section" style="display: none;">
                    <div class="content-header">
                        <h2 id="inventory-management">Inventory Management </h2>
                        <button class="btn" onclick="openAddProductModal()">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                    
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <?php if (empty($productList)): ?>
                                <tr>
                                    <td colspan="7" class="empty-table-row">
                                        You have not added any products yet. Click "Add Product" to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productList as $product): ?>
                                    <?php
                                        $qty = (int) ($product['quantity'] ?? 0);
                                        $statusClass = 'in-stock';
                                        $statusLabel = 'In Stock';
                                        if ($qty <= 0) {
                                            $statusClass = 'out-of-stock';
                                            $statusLabel = 'Out of Stock';
                                        } elseif ($qty <= 5) {
                                            $statusClass = 'low-stock';
                                            $statusLabel = 'Low Stock';
                                        }
                                        $priceLabel = 'Rs ' . number_format((float) ($product['price'] ?? 0), 2);
                                    ?>
                                    <tr>
                                        <td>#TV<?php echo str_pad((string) $product['item_id'], 3, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                            <?php if (!empty($product['description'])): ?>
                                                <div class="muted-text"><?php echo htmlspecialchars($product['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(ucwords($product['category'])); ?></td>
                                        <td><?php echo $priceLabel; ?></td>
                                        <td><?php echo $qty; ?></td>
                                        <td><span class="status <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                        <td>
                                            <div class="btn-group" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                                <a class="btn btn-primary" href="?edit=<?php echo $product['item_id']; ?>">Edit</a>
                                                <form method="post" onsubmit="return confirm('Delete this product?');">
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="item_id" value="<?php echo $product['item_id']; ?>">
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Orders Section -->
                <div id="orders-section" class="content-section" style="display: none;">
                    <div class="content-header">
                        <h2 id="Order-management">Orders Management</h2>
                        <button class="btn" onclick="refreshData()"><i class="fas fa-sync-alt"></i> Refresh</button>
                    </div>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Seller Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Placed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ordersList)): ?>
                                <tr><td colspan="7" class="empty-table-row">No orders yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ordersList as $order): ?>
                                    <tr>
                                        <td>#ORD<?php echo str_pad((string)$order['order_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['customer_name'] ?? 'Customer'); ?></strong>
                                            <div class="muted-text"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo (int) $order['items_count']; ?></td>
                                        <td>Rs <?php echo number_format((float) $order['seller_total'], 2); ?></td>
                                        <td><span class="status <?php echo $order['payment_status'] === 'completed' ? 'in-stock' : ($order['payment_status'] === 'pending' ? 'low-stock' : 'out-of-stock'); ?>">
                                            <?php echo htmlspecialchars(ucwords($order['payment_status'])); ?></span>
                                        </td>
                                        <td><span class="status <?php echo $order['status'] === 'delivered' ? 'in-stock' : ($order['status'] === 'processing' ? 'low-stock' : 'out-of-stock'); ?>">
                                            <?php echo htmlspecialchars(ucwords($order['status'])); ?></span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Customers Section -->
                <div id="customers-section" class="content-section" style="display: none;">
                    <div class="content-header">
                        <h2 id="Customer-management">Customer Management</h2>
                        <button class="btn" onclick="refreshData()"><i class="fas fa-sync-alt"></i> Refresh</button>
                    </div>
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Last Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customersList)): ?>
                                <tr><td colspan="5" class="empty-table-row">No customers yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($customersList as $customer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($customer['f_name'] ?? 'Customer'); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email'] ?? ''); ?></td>
                                        <td><?php echo (int) $customer['orders_count']; ?></td>
                                        <td>Rs <?php echo number_format((float) $customer['total_spent'], 2); ?></td>
                                        <td><?php echo $customer['last_order'] ? date('M j, Y', strtotime($customer['last_order'])) : '—'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                

            </div>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div class="modal" id="addProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Product</h3>
                <button class="close-modal" onclick="closeAddProductModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="addProductForm" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">

                <?php if (!empty($formErrors)): ?>
                    <div class="alert alert-error">
                        <strong>Please fix the following:</strong>
                        <ul>
                            <?php foreach ($formErrors as $message): ?>
                                <li><?php echo htmlspecialchars($message); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="productName">Product Name</label>
                    <input type="text" id="productName" name="product_name" class="form-control" required value="<?php echo htmlspecialchars($addFormData['product_name']); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="productCategory">Category</label>
                        <select id="productCategory" name="product_category" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categoryOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($addFormData['product_category'] === $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="productPrice">Price (Rs)</label>
                        <input type="number" id="productPrice" name="product_price" class="form-control" step="0.01" min="0" required value="<?php echo htmlspecialchars($addFormData['product_price']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="productStock">Stock Quantity</label>
                        <input type="number" id="productStock" name="product_quantity" class="form-control" min="0" required value="<?php echo htmlspecialchars($addFormData['product_quantity']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="productSize">Size / Dimensions</label>
                        <input type="text" id="productSize" name="product_size" class="form-control" value="<?php echo htmlspecialchars($addFormData['product_size']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="productCondition">Condition</label>
                        <select id="productCondition" name="product_condition" class="form-control" required>
                            <option value="">Select Condition</option>
                            <?php foreach ($conditionOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($addFormData['product_condition'] === $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="productImage">Product Image (JPG, PNG, WEBP, max 2MB)</label>
                        <input type="file" id="productImage" name="product_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="productDescription">Description</label>
                    <textarea id="productDescription" name="product_description" class="form-control" rows="3"><?php echo htmlspecialchars($addFormData['product_description']); ?></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddProductModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSection = 'dashboard';

        function showSection(e, section) {
            if (e) {
                e.preventDefault();
            }
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(sec => {
                sec.style.display = 'none';
                sec.classList.remove('active');
            });
            
            // Show selected section
            const targetSection = document.getElementById(section + '-section');
            if (targetSection) {
                targetSection.style.display = 'block';
                targetSection.classList.add('active');
            }

            // Update active menu item
            document.querySelectorAll('.sidebar-menu a').forEach(link => link.classList.remove('active'));
            if (e && e.currentTarget) {
                e.currentTarget.classList.add('active');
            } else {
                // Find the link that matches this section
                const matchingLink = document.querySelector(`.sidebar-menu a[onclick*="'${section}'"]`);
                if (matchingLink) {
                    matchingLink.classList.add('active');
                }
            }

            currentSection = section;
        }

        function openAddProductModal() {
            document.getElementById('addProductModal').classList.add('active');
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').classList.remove('active');
            const form = document.getElementById('addProductForm');
            if (form) {
                form.reset();
            }
        }

        function refreshData() {
            window.location.reload();
        }

        function generateReport() {
            alert('Report generation is coming soon.');
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        document.addEventListener('click', function(e) {
            const modal = document.getElementById('addProductModal');
            if (e.target === modal) {
                closeAddProductModal();
            }
        });
    </script>
    <?php if ($shouldOpenAddModal): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            openAddProductModal();
        });
    </script>
    <?php endif; ?>
        <script src="../script.js"></script>

</body>
</html>
