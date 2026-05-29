<?php
/**
 * Read-Only Inventory View (User Side)
 *
 * This file displays a clean, readable table of all products in the database.
 * Users cannot edit or delete products here. A lightweight, client-side search 
 * is included to filter items instantly.
 */

session_start();
require_once 'db.php';

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

// Fetch all products, sorted by the newest addition first
try {
    $stmt = $pdo->query("SELECT p.*, w.name AS warehouse_name FROM products p LEFT JOIN warehouses w ON p.warehouse_id = w.id ORDER BY p.id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving inventory data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Inventory List</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Inventory Portal</h1>
            <nav class="nav-links">
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin-dashboard.php">Admin Dashboard</a>
                <?php endif; ?>
                <a href="inventory.php" class="active">View Inventory</a>
                <a href="track.php">Track Shipment</a>
                <a href="index.php?action=logout" class="btn-danger" style="color: white; padding: 6px 12px;">Logout</a>
            </nav>
        </header>

        <h2>Current Stock Inventory</h2>

        <!-- Flash messages for successful submissions -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']); // Clear message after displaying
                ?>
            </div>
        <?php endif; ?>

        <!-- Simple client-side search bar for premium feel and utility -->
        <div class="form-group" style="margin-bottom: 25px;">
            <input type="text" id="search-input" placeholder="🔍 Search by product name, category, or description..." onkeyup="filterInventory()">
        </div>

        <div class="table-responsive">
            <table id="inventory-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Product Name</th>
                        <th style="width: 15%;">Category / Loc.</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 15%;">Unit Price</th>
                        <th style="width: 20%;">Description</th>
                        <th style="width: 10%;">Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #7f8c8d; padding: 30px;">
                                No products found in inventory.
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="admin-dashboard.php">Add the first product!</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td>
                                    <span style="color: #7f8c8d;"><?php echo htmlspecialchars($product['category']); ?></span><br>
                                    <small style="color: #bdc3c7;">&#127981; <?php echo htmlspecialchars($product['warehouse_name'] ?? 'Unassigned'); ?></small>
                                </td>
                                <td>
                                    <?php 
                                        $qty = (int)$product['quantity'];
                                        if ($qty === 0) {
                                            echo '<span class="badge badge-outofstock">Out of Stock</span>';
                                        } elseif ($qty <= 5) {
                                            echo htmlspecialchars($qty) . ' <span class="badge badge-lowstock">Low Stock</span>';
                                        } else {
                                            echo htmlspecialchars($qty) . ' <span class="badge badge-instock">In Stock</span>';
                                        }
                                    ?>
                                </td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td>
                                    <small style="color: #555;">
                                        <?php echo nl2br(htmlspecialchars($product['description'] ?: '—')); ?>
                                    </small>
                                </td>
                                <td>
                                    <small style="color: #7f8c8d;">
                                        <?php echo date('Y-m-d', strtotime($product['created_at'])); ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Chatbot Widget -->
    <?php include 'chatbot-widget.php'; ?>

    <!-- Neat, lightning-fast vanilla Javascript live filter -->
    <script>
        function filterInventory() {
            const query = document.getElementById('search-input').value.toLowerCase();
            const table = document.getElementById('inventory-table');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            // Loop through all table rows, hide those that don't match the search query
            for (let i = 0; i < rows.length; i++) {
                const nameCell = rows[i].getElementsByTagName('td')[0];
                const catCell = rows[i].getElementsByTagName('td')[1];
                const descCell = rows[i].getElementsByTagName('td')[4];
                
                if (nameCell && catCell && descCell) {
                    const nameText = nameCell.textContent || nameCell.innerText;
                    const catText = catCell.textContent || catCell.innerText;
                    const descText = descCell.textContent || descCell.innerText;
                    
                    if (
                        nameText.toLowerCase().indexOf(query) > -1 || 
                        catText.toLowerCase().indexOf(query) > -1 ||
                        descText.toLowerCase().indexOf(query) > -1
                    ) {
                        rows[i].style.display = "";
                    } else {
                        // Keep the "no products found" row visible if it's there
                        if (rows[i].cells.length > 1) {
                            rows[i].style.display = "none";
                        }
                    }
                }
            }
        }
    </script>
</body>
</html>
