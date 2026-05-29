<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

$shipment = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['tracking_number'])) {
    $tracking_number = trim($_GET['tracking_number']);
    
    try {
        $stmt = $pdo->prepare("SELECT s.*, p.name as product_name FROM shipments s LEFT JOIN products p ON s.product_id = p.id WHERE s.tracking_number = :tn");
        $stmt->execute([':tn' => $tracking_number]);
        $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shipment) {
            $error = "No shipment found with tracking number: " . htmlspecialchars($tracking_number);
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Shipment</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container container-small" style="margin-top: 5vh; max-width: 600px;">
        <header>
            <h1 style="text-align: center; width: 100%;">Shipment Tracking</h1>
        </header>

        <nav class="nav-links" style="justify-content: center; margin-bottom: 30px;">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin-dashboard.php">Admin Dashboard</a>
            <?php endif; ?>
            <a href="inventory.php">View Inventory</a>
            <a href="track.php" class="active">Track Shipment</a>
            <a href="index.php?action=logout" class="btn-danger" style="color: white; padding: 6px 12px;">Logout</a>
        </nav>

        <div style="background-color: #f8f9fa; padding: 30px; border-radius: 6px; border: 1px solid var(--border-color);">
            <form action="track.php" method="GET" style="display: flex; gap: 10px;">
                <input type="text" name="tracking_number" placeholder="Enter Tracking Number (e.g. TRK-123)" required style="flex: 1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;" value="<?php echo isset($_GET['tracking_number']) ? htmlspecialchars($_GET['tracking_number']) : ''; ?>">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Track</button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-top: 20px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($shipment): ?>
            <div style="margin-top: 30px; padding: 20px; border: 2px solid #3498db; border-radius: 8px; background: #fff;">
                <h3 style="margin-top: 0; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                    Tracking ID: <?php echo htmlspecialchars($shipment['tracking_number']); ?>
                </h3>
                
                <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <strong style="color: #7f8c8d; font-size: 13px;">Status</strong><br>
                        <span style="font-size: 18px; font-weight: bold; color: <?php echo $shipment['status'] === 'Delivered' ? '#2ecc71' : ($shipment['status'] === 'In Transit' ? '#3498db' : '#f39c12'); ?>">
                            <?php echo htmlspecialchars($shipment['status']); ?>
                        </span>
                    </div>
                    <div>
                        <strong style="color: #7f8c8d; font-size: 13px;">Product</strong><br>
                        <?php echo htmlspecialchars($shipment['product_name'] ?? 'Unknown'); ?> (Qty: <?php echo htmlspecialchars($shipment['quantity']); ?>)
                    </div>
                    <div>
                        <strong style="color: #7f8c8d; font-size: 13px;">Destination</strong><br>
                        <?php echo htmlspecialchars($shipment['destination']); ?>
                    </div>
                    <div>
                        <strong style="color: #7f8c8d; font-size: 13px;">Last Update</strong><br>
                        <?php echo date('M d, Y h:i A', strtotime($shipment['created_at'])); ?>
                    </div>
                </div>

                <div style="margin-top: 20px; background: #f9f9f9; padding: 15px; border-radius: 6px; border-left: 4px solid #3498db;">
                    <strong style="color: #7f8c8d; font-size: 13px;">Current Location / Note:</strong>
                    <p style="margin: 5px 0 0 0; font-size: 16px;">
                        <?php echo htmlspecialchars($shipment['current_location'] ?: 'Awaiting updates...'); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chatbot Widget -->
    <?php include 'chatbot-widget.php'; ?>

</body>
</html>
