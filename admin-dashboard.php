<?php
/**
 * Admin Inventory Management Dashboard
 *
 * This dashboard is session-protected. It allows administrative users to:
 * 1. View the complete inventory list
 * 2. Add new products (via an inline form)
 * 3. Edit existing products (inline, switching the form dynamically)
 * 4. Delete products (with javascript confirmation)
 * 5. Access the printable report generator
 */

session_start();
require_once 'db.php';

// 1. Session Protection Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// 2. Handle GET actions (Logout, Delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // LOGOUT
    if ($action === 'logout') {
        session_destroy();
        header("Location: index.php");
        exit;
    }
    
    // DELETE
    if ($action === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        try {
            // First fetch the name for the delete success notification
            $nameStmt = $pdo->prepare("SELECT name FROM products WHERE id = :id");
            $nameStmt->execute([':id' => $id]);
            $productToDelete = $nameStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($productToDelete) {
                $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
                $deleteStmt->execute([':id' => $id]);
                $_SESSION['success_message'] = "Product '<strong>" . htmlspecialchars($productToDelete['name']) . "</strong>' was deleted successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
        }
        header("Location: admin-dashboard.php");
        exit;
    }
}

// 3. Handle POST Actions (Add Product, Update Product)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $form_action = $_POST['form_action'];
    
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $warehouse_id = !empty($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : null;
    
    // Basic validation
    $errors = [];
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($category)) $errors[] = "Category is required.";
    if ($quantity < 0) $errors[] = "Quantity cannot be negative.";
    if ($price < 0.00) $errors[] = "Price cannot be negative.";
    
    if (empty($errors)) {
        if ($form_action === 'add') {
            // INSERT product
            try {
                $stmt = $pdo->prepare("INSERT INTO products (name, category, quantity, price, description, warehouse_id) VALUES (:name, :category, :quantity, :price, :description, :warehouse_id)");
                $stmt->execute([
                    ':name' => $name,
                    ':category' => $category,
                    ':quantity' => $quantity,
                    ':price' => $price,
                    ':description' => $description,
                    ':warehouse_id' => $warehouse_id
                ]);
                $_SESSION['success_message'] = "Product '<strong>" . htmlspecialchars($name) . "</strong>' added successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error adding product: " . $e->getMessage();
            }
        } elseif ($form_action === 'update' && isset($_POST['id'])) {
            // UPDATE product
            $id = (int)$_POST['id'];
            try {
                $stmt = $pdo->prepare("UPDATE products SET name = :name, category = :category, quantity = :quantity, price = :price, description = :description, warehouse_id = :warehouse_id WHERE id = :id");
                $stmt->execute([
                    ':name' => $name,
                    ':category' => $category,
                    ':quantity' => $quantity,
                    ':price' => $price,
                    ':description' => $description,
                    ':warehouse_id' => $warehouse_id,
                    ':id' => $id
                ]);
                $_SESSION['success_message'] = "Product '<strong>" . htmlspecialchars($name) . "</strong>' updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error updating product: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
    
    header("Location: admin-dashboard.php");
    exit;
}

// 4. Check if we are in "Edit Mode"
$is_edit_mode = false;
$edit_product = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $edit_id]);
        $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($edit_product) {
            $is_edit_mode = true;
        }
    } catch (PDOException $e) {
        $is_edit_mode = false;
    }
}

// 4.5 Fetch available warehouses
$warehouses = [];
try {
    $stmt = $pdo->query("SELECT id, name, location FROM warehouses ORDER BY name ASC");
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 5. Fetch all products for display
try {
    $stmt = $pdo->query("SELECT p.*, w.name AS warehouse_name FROM products p LEFT JOIN warehouses w ON p.warehouse_id = w.id ORDER BY p.id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving inventory data: " . $e->getMessage());
}

// 6. Prepare Analytics Data
$chartDataProducts = [];
$chartDataStock = [];
$categoryCounts = [];

foreach ($products as $p) {
    // Top 10 products for bar chart (otherwise it gets too crowded)
    if (count($chartDataProducts) < 10) {
        $chartDataProducts[] = $p['name'];
        $chartDataStock[] = $p['quantity'];
    }
    
    // Aggregate by category
    if (!isset($categoryCounts[$p['category']])) {
        $categoryCounts[$p['category']] = 0;
    }
    $categoryCounts[$p['category']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Inventory System</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <header>
            <div>
                <h1>Admin Dashboard</h1>
                <p style="font-size: 13px; color: #7f8c8d; margin-top: 4px;">Logged in as: <strong>admin</strong></p>
            </div>
            <nav class="nav-links">
                <a href="inventory.php" target="_blank">View Public Portal ↗</a>
                <a href="admin-dashboard.php" class="active">Products</a>
                <a href="admin-warehouses.php">Warehouses</a>
                <a href="admin-shipments.php">Shipments</a>
                <a href="admin-report.php">Generate Report</a>
                <a href="admin-dashboard.php?action=logout" class="btn-danger" style="color: white; padding: 6px 12px;">Logout</a>
            </nav>
        </header>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']); 
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']); 
                ?>
            </div>
        <?php endif; ?>

        <!-- Analytics Section -->
        <div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">
            <div style="background: white; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); flex: 1; min-width: 400px; display: flex; flex-direction: column; align-items: center;">
                <h3 style="margin-top: 0; color: #2c3e50; font-size: 16px; align-self: flex-start; width: 100%;">Stock Levels (Recent Products)</h3>
                <div style="width: 450px; height: 250px; display: flex; justify-content: center; align-items: center; margin-top: 10px;">
                    <canvas id="stockChart" width="450" height="250"></canvas>
                </div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color); flex: 1; min-width: 400px; display: flex; flex-direction: column; align-items: center;">
                <h3 style="margin-top: 0; color: #2c3e50; font-size: 16px; align-self: flex-start; width: 100%;">Product Distribution by Category</h3>
                <div style="width: 400px; height: 250px; display: flex; justify-content: center; align-items: center; margin-top: 10px;">
                    <canvas id="categoryChart" width="400" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            
            <!-- Left Column: Context-Aware Action Form -->
            <div>
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color);">
                    <?php if ($is_edit_mode): ?>
                        <h2>Edit Product #<?php echo htmlspecialchars($edit_product['id']); ?></h2>
                        <form action="admin-dashboard.php" method="POST">
                            <input type="hidden" name="form_action" value="update">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_product['id']); ?>">
                            
                            <div class="form-group">
                                <label for="name">Product Name *</label>
                                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($edit_product['name']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="category">Category *</label>
                                <input type="text" id="category" name="category" required value="<?php echo htmlspecialchars($edit_product['category']); ?>" list="category-suggestions">
                            </div>

                            <div class="form-group">
                                <label for="quantity">Quantity *</label>
                                <input type="number" id="quantity" name="quantity" min="0" required value="<?php echo htmlspecialchars($edit_product['quantity']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="price">Unit Price ($) *</label>
                                <input type="number" id="price" name="price" min="0.00" step="0.01" required value="<?php echo htmlspecialchars($edit_product['price']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="description">Product Description</label>
                                <textarea id="description" name="description"><?php echo htmlspecialchars($edit_product['description']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="warehouse_id">Warehouse Location</label>
                                <select id="warehouse_id" name="warehouse_id">
                                    <option value="">-- Select a Warehouse (Optional) --</option>
                                    <?php foreach ($warehouses as $w): ?>
                                        <option value="<?php echo htmlspecialchars($w['id']); ?>" <?php echo ($edit_product['warehouse_id'] == $w['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($w['name'] . ' - ' . $w['location']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="display: flex; gap: 10px; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="flex: 2;">Save Changes</button>
                                <a href="admin-dashboard.php" class="btn btn-secondary" style="flex: 1; display: inline-flex; align-items: center; justify-content: center;">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <h2>Add New Product</h2>
                        <form action="admin-dashboard.php" method="POST">
                            <input type="hidden" name="form_action" value="add">
                            
                            <div class="form-group">
                                <label for="name">Product Name *</label>
                                <input type="text" id="name" name="name" required placeholder="e.g., HDMI Cable">
                            </div>

                            <div class="form-group">
                                <label for="category">Category *</label>
                                <input type="text" id="category" name="category" required placeholder="e.g., Electronics" list="category-suggestions">
                            </div>

                            <div class="form-group">
                                <label for="quantity">Quantity *</label>
                                <input type="number" id="quantity" name="quantity" min="0" required placeholder="e.g., 100">
                            </div>

                            <div class="form-group">
                                <label for="price">Unit Price ($) *</label>
                                <input type="number" id="price" name="price" min="0.00" step="0.01" required placeholder="e.g., 9.99">
                            </div>

                            <div class="form-group">
                                <label for="description">Product Description</label>
                                <textarea id="description" name="description" placeholder="Product specifications, details..."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="warehouse_id">Warehouse Location</label>
                                <select id="warehouse_id" name="warehouse_id">
                                    <option value="">-- Select a Warehouse (Optional) --</option>
                                    <?php foreach ($warehouses as $w): ?>
                                        <option value="<?php echo htmlspecialchars($w['id']); ?>">
                                            <?php echo htmlspecialchars($w['name'] . ' - ' . $w['location']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Add to Inventory</button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <datalist id="category-suggestions">
                        <option value="Electronics">
                        <option value="Office Supplies">
                        <option value="Furniture">
                        <option value="Apparel">
                        <option value="Groceries">
                    </datalist>
                </div>
            </div>

            <!-- Right Column: Interactive Inventory Control Table -->
            <div>
                <h2>Inventory Listing</h2>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <input type="text" id="search-dashboard" placeholder="🔍 Search dashboard listing..." onkeyup="filterDashboard()">
                </div>

                <div class="table-responsive">
                    <table id="dashboard-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category / Loc.</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #7f8c8d; padding: 30px;">
                                        No inventory products recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr <?php echo ($is_edit_mode && $edit_product['id'] == $product['id']) ? 'style="background-color: rgba(22, 160, 133, 0.08);"' : ''; ?>>
                                        <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                        <td>
                                            <span style="color: #7f8c8d; font-size: 13px;"><?php echo htmlspecialchars($product['category']); ?></span><br>
                                            <small style="color: #bdc3c7;">&#127981; <?php echo htmlspecialchars($product['warehouse_name'] ?? 'Unassigned'); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                                $qty = (int)$product['quantity'];
                                                if ($qty === 0) {
                                                    echo '<span class="badge badge-outofstock">Out of Stock</span>';
                                                } elseif ($qty <= 5) {
                                                    echo htmlspecialchars($qty) . ' <span class="badge badge-lowstock">Low</span>';
                                                } else {
                                                    echo htmlspecialchars($qty) . ' <span class="badge badge-instock">OK</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        <td style="text-align: center;" class="action-links">
                                            <a href="admin-dashboard.php?action=edit&id=<?php echo $product['id']; ?>" class="action-edit">Edit</a>
                                            <span style="color: var(--border-color);">|</span>
                                            <a href="admin-dashboard.php?action=delete&id=<?php echo $product['id']; ?>" class="action-delete" onclick="return confirm('Are you sure you want to permanently delete \'<?php echo addslashes(htmlspecialchars($product['name'])); ?>\'?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Chatbot Widget -->
    <?php include 'chatbot-widget.php'; ?>

    <!-- Client-side filter specifically for dashboard column layouts -->
    <script>
        function filterDashboard() {
            const query = document.getElementById('search-dashboard').value.toLowerCase();
            const table = document.getElementById('dashboard-table');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const nameCell = rows[i].getElementsByTagName('td')[0];
                const catCell = rows[i].getElementsByTagName('td')[1];
                
                if (nameCell && catCell) {
                    const nameText = nameCell.textContent || nameCell.innerText;
                    const catText = catCell.textContent || catCell.innerText;
                    
                    if (nameText.toLowerCase().indexOf(query) > -1 || catText.toLowerCase().indexOf(query) > -1) {
                        rows[i].style.display = "";
                    } else {
                        if (rows[i].cells.length > 1) {
                            rows[i].style.display = "none";
                        }
                    }
                }
            }
        }
    </script>
    <script>
        // Chart Initialization
        const ctxStock = document.getElementById('stockChart').getContext('2d');
        const ctxCategory = document.getElementById('categoryChart').getContext('2d');

        new Chart(ctxStock, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartDataProducts); ?>,
                datasets: [{
                    label: 'Stock Quantity',
                    data: <?php echo json_encode($chartDataStock); ?>,
                    backgroundColor: '#3498db',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: false,
                scales: { y: { beginAtZero: true } }
            }
        });

        new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($categoryCounts)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($categoryCounts)); ?>,
                    backgroundColor: ['#e74c3c', '#2ecc71', '#f1c40f', '#9b59b6', '#34495e', '#e67e22', '#1abc9c']
                }]
            },
            options: {
                responsive: false
            }
        });
    </script>
</body>
</html>
