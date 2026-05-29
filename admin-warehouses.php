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
            $nameStmt = $pdo->prepare("SELECT name FROM warehouses WHERE id = :id");
            $nameStmt->execute([':id' => $id]);
            $warehouseToDelete = $nameStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($warehouseToDelete) {
                $deleteStmt = $pdo->prepare("DELETE FROM warehouses WHERE id = :id");
                $deleteStmt->execute([':id' => $id]);
                $_SESSION['success_message'] = "Warehouse '<strong>" . htmlspecialchars($warehouseToDelete['name']) . "</strong>' was deleted successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Delete failed: " . $e->getMessage();
        }
        header("Location: admin-warehouses.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $form_action = $_POST['form_action'];
    
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $manager = isset($_POST['manager']) ? trim($_POST['manager']) : '';
    
    $errors = [];
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($location)) $errors[] = "Location is required.";
    
    if (empty($errors)) {
        if ($form_action === 'add') {
            try {
                $stmt = $pdo->prepare("INSERT INTO warehouses (name, location, manager) VALUES (:name, :location, :manager)");
                $stmt->execute([':name' => $name, ':location' => $location, ':manager' => $manager]);
                $_SESSION['success_message'] = "Warehouse added successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error adding warehouse: " . $e->getMessage();
            }
        } elseif ($form_action === 'update' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            try {
                $stmt = $pdo->prepare("UPDATE warehouses SET name = :name, location = :location, manager = :manager WHERE id = :id");
                $stmt->execute([':name' => $name, ':location' => $location, ':manager' => $manager, ':id' => $id]);
                $_SESSION['success_message'] = "Warehouse updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error updating warehouse: " . $e->getMessage();
            }
        }
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
    }
    header("Location: admin-warehouses.php");
    exit;
}

$is_edit_mode = false;
$edit_warehouse = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM warehouses WHERE id = :id");
        $stmt->execute([':id' => $edit_id]);
        $edit_warehouse = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($edit_warehouse) $is_edit_mode = true;
    } catch (PDOException $e) {}
}

try {
    $stmt = $pdo->query("SELECT * FROM warehouses ORDER BY id DESC");
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Management</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 1200px;">
        <header>
            <div>
                <h1>Admin Dashboard - Warehouses</h1>
                <p style="font-size: 13px; color: #7f8c8d; margin-top: 4px;">Logged in as: <strong>admin</strong></p>
            </div>
            <nav class="nav-links">
                <a href="inventory.php" target="_blank">View Public Portal ↗</a>
                <a href="admin-dashboard.php">Products</a>
                <a href="admin-warehouses.php" class="active">Warehouses</a>
                <a href="admin-shipments.php">Shipments</a>
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
                        <h2>Edit Warehouse #<?php echo htmlspecialchars($edit_warehouse['id']); ?></h2>
                        <form action="admin-warehouses.php" method="POST">
                            <input type="hidden" name="form_action" value="update">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_warehouse['id']); ?>">
                            
                            <div class="form-group">
                                <label>Warehouse Name *</label>
                                <input type="text" name="name" required value="<?php echo htmlspecialchars($edit_warehouse['name']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Location *</label>
                                <input type="text" name="location" required value="<?php echo htmlspecialchars($edit_warehouse['location']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Manager</label>
                                <input type="text" name="manager" value="<?php echo htmlspecialchars($edit_warehouse['manager']); ?>">
                            </div>
                            <div class="form-group" style="display: flex; gap: 10px; margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="flex: 2;">Save</button>
                                <a href="admin-warehouses.php" class="btn btn-secondary" style="flex: 1; display: inline-flex; align-items: center; justify-content: center;">Cancel</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <h2>Add New Warehouse</h2>
                        <form action="admin-warehouses.php" method="POST">
                            <input type="hidden" name="form_action" value="add">
                            
                            <div class="form-group">
                                <label>Warehouse Name *</label>
                                <input type="text" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Location *</label>
                                <input type="text" name="location" required>
                            </div>
                            <div class="form-group">
                                <label>Manager</label>
                                <input type="text" name="manager">
                            </div>
                            <div class="form-group" style="margin-top: 30px;">
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Add Warehouse</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h2>Warehouse Listing</h2>
                <div class="table-responsive">
                    <table id="dashboard-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Manager</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($warehouses)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #7f8c8d; padding: 30px;">No warehouses found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($warehouses as $wh): ?>
                                    <tr <?php echo ($is_edit_mode && $edit_warehouse['id'] == $wh['id']) ? 'style="background-color: rgba(22, 160, 133, 0.08);"' : ''; ?>>
                                        <td>#<?php echo $wh['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($wh['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($wh['location']); ?></td>
                                        <td><?php echo htmlspecialchars($wh['manager'] ?: '-'); ?></td>
                                        <td style="text-align: center;" class="action-links">
                                            <a href="admin-warehouses.php?action=edit&id=<?php echo $wh['id']; ?>" class="action-edit">Edit</a>
                                            <span style="color: var(--border-color);">|</span>
                                            <a href="admin-warehouses.php?action=delete&id=<?php echo $wh['id']; ?>" class="action-delete" onclick="return confirm('Are you sure you want to delete this warehouse?');">Delete</a>
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
