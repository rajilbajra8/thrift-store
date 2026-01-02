<?php
// Connect to database
require_once "db.php";

// Get search term if any
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Toggle featured status
if (isset($_POST['toggle_featured']) && isset($_POST['item_id'])) {
    $item_id = intval($_POST['item_id']); // Force integer
    $new_status = ($_POST['new_status'] == '1') ? '1' : '0'; // Validate
    
    // Use prepared statement
    $stmt = $conn->prepare("UPDATE items SET featured = ? WHERE item_id = ?");
    $stmt->bind_param("si", $new_status, $item_id);
    $stmt->execute();
    $stmt->close();
}

// Get items with optional search - ALWAYS use LIMIT for performance
$limit = 50; // Reasonable default
if ($search) {
    $search_term = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT * FROM items WHERE name LIKE ? ORDER BY item_id DESC LIMIT ?");
    $stmt->bind_param("si", $search_term, $limit);
} else {
    $stmt = $conn->prepare("SELECT * FROM items ORDER BY item_id DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Edit Items</title>
    <style>
        /* Same CSS */
    </style>
</head>
<body>
    <h1>📦 Admin - Edit Items</h1>
    
    <!-- Search Box -->
    <div class="search-box">
        <form method="GET">
            <input type="text" name="search" class="search-input" 
                   placeholder="Search items by name..." 
                   value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
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
                    <td>#<?php echo htmlspecialchars($item['item_id']); ?></td>
                    <td>
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else: ?>
                            <img src="https://via.placeholder.com/80?text=No+Image" alt="No image">
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td>Rs <?php echo htmlspecialchars($item['price']); ?></td>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                    <td class="<?php echo $item['featured'] ? 'featured-yes' : 'featured-no'; ?>">
                        <?php echo $item['featured'] ? '★ Featured' : 'Not Featured'; ?>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Are you sure?');">
                            <input type="hidden" name="item_id" 
                                   value="<?php echo htmlspecialchars($item['item_id']); ?>">
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