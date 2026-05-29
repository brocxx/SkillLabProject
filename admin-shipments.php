<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM shipments WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $_SESSION['success_message'] = "Shipment #$id deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
        }
        header("Location: admin-shipments.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $form_action = $_POST['form_action'];
    
    if ($form_action === 'add') {
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $tracking_number = isset($_POST['tracking_number']) ? trim($_POST['tracking_number']) : '';
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
        $destination = isset($_POST['destination']) ? trim($_POST['destination']) : '';
        $current_location = isset($_POST['current_location']) ? trim($_POST['current_location']) : '';
        
        $errors = [];
        if (empty($product_id)) $errors[] = "Product is required.";
        if (empty($tracking_number)) $errors[] = "Tracking Number is required.";
        if ($quantity <= 0) $errors[] = "Quantity must be greater than zero.";
        if (empty($destination)) $errors[] = "Destination is required.";
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Check stock
                $checkStmt = $pdo->prepare("SELECT name, quantity FROM products WHERE id = :id FOR UPDATE");
                $checkStmt->execute([':id' => $product_id]);
                $product = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    throw new Exception("Product not found.");
                }
                
                if ($product['quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for product '" . $product['name'] . "'. Available: " . $product['quantity']);
                }
                
                // Deduct stock
                $updStmt = $pdo->prepare("UPDATE products SET quantity = quantity - :qty WHERE id = :id");
                $updStmt->execute([':qty' => $quantity, ':id' => $product_id]);
                
                // Insert shipment
                $insStmt = $pdo->prepare("INSERT INTO shipments (product_id, tracking_number, quantity, status, destination, current_location) VALUES (:pid, :tn, :qty, 'Pending', :dest, :loc)");
                $insStmt->execute([
                    ':pid' => $product_id,
                    ':tn' => $tracking_number,
                    ':qty' => $quantity,
                    ':dest' => $destination,
                    ':loc' => $current_location
                ]);
                
                $pdo->commit();
                $_SESSION['success_message'] = "Shipment created successfully. Stock deducted.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = implode(" ", $errors);
        }
    } elseif ($form_action === 'update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $status = isset($_POST['status']) ? $_POST['status'] : 'Pending';
        $current_location = isset($_POST['current_location']) ? trim($_POST['current_location']) : '';
        
        try {
            $stmt = $pdo->prepare("UPDATE shipments SET status = :status, current_location = :loc WHERE id = :id");
            $stmt->execute([':status' => $status, ':loc' => $current_location, ':id' => $id]);
            $_SESSION['success_message'] = "Shipment status updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating shipment: " . $e->getMessage();
        }
    }
    
    header("Location: admin-shipments.php");
    exit;
}

$is_edit_mode = false;
$edit_shipment = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT s.*, p.name as product_name FROM shipments s JOIN products p ON s.product_id = p.id WHERE s.id = :id");
        $stmt->execute([':id' => $edit_id]);
        $edit_shipment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($edit_shipment) $is_edit_mode = true;
    } catch (PDOException $e) {}
}

// Fetch lists
try {
    $productsStmt = $pdo->query("SELECT id, name, quantity FROM products ORDER BY name ASC");
    $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $shipmentsStmt = $pdo->query("SELECT s.*, p.name as product_name FROM shipments s LEFT JOIN products p ON s.product_id = p.id ORDER BY s.id DESC");
    $shipments = $shipmentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipment Monitoring</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <header>
            <div>
                <h1>Admin Dashboard - Shipments</h1>
                <p style="font-size: 13px; color: #7f8c8d; margin-top: 4px;">Logged in as: <strong>admin</strong></p>
            </div>
            <nav class="nav-links">
                <a href="inventory.php" target="_blank">View Public Portal ↗</a>
                <a href="admin-dashboard.php">Products</a>
                <a href="admin-warehouses.php">Warehouses</a>
                <a href="admin-shipments.php" class="active">Shipments</a>
                <a href="admin-report.php">Generate Report</a>
                <a href="admin-dashboard.php?action=logout" class="btn-danger" style="color: white; padding: 6px 12px;">Logout</a>
            </nav>
        </header>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div>
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid var(--border-color);">
                    <?php if ($is_edit_mode): ?>
                        <h2>Update Shipment #<?php echo htmlspecialchars($edit_shipment['id']); ?></h2>
                        <form action="admin-shipments.php" method="POST">
                            <input type="hidden" name="form_action" value="update">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_shipment['id']); ?>">
                            
                            <div class="form-group">
                                <label>Product</label>
                                <input type="text" disabled value="<?php echo htmlspecialchars($edit_shipment['product_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Tracking Number</label>
                                <input type="text" disabled value="<?php echo htmlspecialchars($edit_shipment['tracking_number']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Status *</label>
                                <select name="status" required>
                                    <option value="Pending" <?php echo ($edit_shipment['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Transit" <?php echo ($edit_shipment['status'] == 'In Transit') ? 'selected' : ''; ?>>In Transit</option>
                                    <option value="Delivered" <?php echo ($edit_shipment['status'] == 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Current Location / Note</label>
                                <input type="text" name="current_location" value="<?php echo htmlspecialchars($edit_shipment['current_location'] ?? ''); ?>">
                            </div>
                            <div class="form-group" style="display: flex; gap: 10px; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="flex: 2;">Update Status</button>
                                <a href="admin-shipments.php" class="btn btn-secondary" style="flex: 1; display: inline-flex; align-items: center; justify-content: center;">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <h2>Create Shipment</h2>
                        <form action="admin-shipments.php" method="POST">
                            <input type="hidden" name="form_action" value="add">
                            
                            <div class="form-group">
                                <label>Product *</label>
                                <select name="product_id" required>
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>">
                                            <?php echo htmlspecialchars($p['name'] . ' (Stock: ' . $p['quantity'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantity to Ship *</label>
                                <input type="number" name="quantity" min="1" required>
                            </div>
                            <div class="form-group">
                                <label>Tracking Number *</label>
                                <input type="text" name="tracking_number" required placeholder="e.g., TRK-12345">
                            </div>
                            <div class="form-group">
                                <label>Destination *</label>
                                <input type="text" name="destination" required placeholder="e.g., Warehouse B / Customer Address">
                            </div>
                            <div class="form-group">
                                <label>Current Location / Note</label>
                                <input type="text" name="current_location" placeholder="e.g., Awaiting Pickup">
                            </div>
                            <div class="form-group" style="margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Create Shipment</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h2>Shipment Tracking</h2>
                <div class="table-responsive">
                    <table id="dashboard-table">
                        <thead>
                            <tr>
                                <th>Tracking #</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Destination</th>
                                <th>Location/Notes</th>
                                <th>Status</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($shipments)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #7f8c8d; padding: 30px;">No shipments found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($shipments as $sh): ?>
                                    <tr <?php echo ($is_edit_mode && $edit_shipment['id'] == $sh['id']) ? 'style="background-color: rgba(22, 160, 133, 0.08);"' : ''; ?>>
                                        <td><strong><?php echo htmlspecialchars($sh['tracking_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($sh['product_name'] ?? 'Unknown Product'); ?></td>
                                        <td><?php echo htmlspecialchars($sh['quantity']); ?></td>
                                        <td><?php echo htmlspecialchars($sh['destination']); ?></td>
                                        <td><small style="color: #7f8c8d;"><?php echo htmlspecialchars($sh['current_location'] ?? '—'); ?></small></td>
                                        <td>
                                            <?php 
                                                $status = $sh['status'];
                                                if ($status == 'Delivered') echo '<span class="badge badge-instock">Delivered</span>';
                                                elseif ($status == 'In Transit') echo '<span style="background: #3498db; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px;">In Transit</span>';
                                                else echo '<span style="background: #f39c12; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Pending</span>';
                                            ?>
                                        </td>
                                        <td style="text-align: center;" class="action-links">
                                            <a href="admin-shipments.php?action=edit&id=<?php echo $sh['id']; ?>" class="action-edit">Edit</a>
                                            <span style="color: var(--border-color);">|</span>
                                            <a href="admin-shipments.php?action=delete&id=<?php echo $sh['id']; ?>" class="action-delete" onclick="return confirm('Delete this shipment record?');">Delete</a>
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
</body>
</html>
