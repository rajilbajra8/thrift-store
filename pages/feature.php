<?php
// Connect to database
require_once "db.php";

// Get search term if any
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Toggle featured status
if (isset($_POST['toggle_featured'])) {
    $item_id = $_POST['item_id'];
    $new_status = $_POST['new_status'];
    
    $sql = "UPDATE items SET featured = '$new_status' WHERE item_id = '$item_id'";
    $conn->query($sql);
}

// Get items with optional search
if ($search) {
    $sql = "SELECT * FROM items WHERE name LIKE '%$search%' ORDER BY item_id DESC LIMIT 4";
} else {
    $sql = "SELECT * FROM items ORDER BY item_id DESC";
}

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Edit Items</title>
    <style>
        body {
            font-family: Arial;
            padding: 20px;
        }
        
        h1 {
            color: #333;
        }
        
        .search-box {
            margin: 20px 0;
        }
        
        .search-input {
            padding: 10px;
            width: 300px;
        }
        
        .search-btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        
        th {
            background: #007bff;
            color: white;
        }
        
        tr:hover {
            background: #f5f5f5;
        }
        
        .featured-yes {
            color: green;
            font-weight: bold;
        }
        
        .featured-no {
            color: red;
        }
        
        .toggle-btn {
            padding: 5px 10px;
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        
        .toggle-btn.remove {
            background: #dc3545;
        }
        
        img {
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <h1>📦 Admin - Edit Items</h1>
    
    <!-- Search Box -->
    <div class="search-box">
        <form method="GET">
            <input type="text" name="search" class="search-input" 
                   placeholder="Search items by name..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>
    
    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Featured</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($item = $result->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $item['item_id']; ?></td>
                    <td>
                        <?php if ($item['image_url']): ?>
                            <img src="<?php echo $item['image_url']; ?>" 
                                 alt="<?php echo $item['name']; ?>">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/80?text=No+Image" alt="No image">
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['name']; ?></td>
                    <td>Rs <?php echo $item['price']; ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td class="<?php echo $item['featured'] ? 'featured-yes' : 'featured-no'; ?>">
                        <?php echo $item['featured'] ? '★ Featured' : 'Not Featured'; ?>
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <input type="hidden" name="new_status" 
                                   value="<?php echo $item['featured'] ? '0' : '1'; ?>">
                            
                            <?php if ($item['featured']): ?>
                                <button type="submit" name="toggle_featured" 
                                        class="toggle-btn remove">
                                    Remove Featured
                                </button>
                            <?php else: ?>
                                <button type="submit" name="toggle_featured" 
                                        class="toggle-btn">
                                    Make Featured
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>