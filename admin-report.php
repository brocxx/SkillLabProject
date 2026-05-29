<?php
/**
 * Simple Printable Inventory Summary Report
 *
 * This file is session-protected. It aggregates inventory statistics:
 * 1. Total unique products
 * 2. Total items in stock
 * 3. Total valuation of active inventory
 * Includes print-friendly styles and triggers the system print dialog.
 */

session_start();
require_once 'db.php';

// 1. Session Protection Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// 2. Fetch all products to aggregate report statistics
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY category ASC, name ASC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving report data: " . $e->getMessage());
}

// 3. Compute Aggregates
$total_products = count($products);
$total_stock = 0;
$total_valuation = 0.00;

foreach ($products as $product) {
    $qty = (int)$product['quantity'];
    $price = (float)$product['price'];
    
    $total_stock += $qty;
    $total_valuation += ($qty * $price);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Summary Report</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        
        <!-- Navbar (Hidden on Print) -->
        <header class="no-print">
            <h1>Inventory Report Portal</h1>
            <nav class="nav-links">
                <a href="admin-dashboard.php">← Back to Dashboard</a>
                <button onclick="window.print();" class="btn btn-primary" style="padding: 6px 16px;">🖨️ Print Report</button>
            </nav>
        </header>

        <!-- Printable Document Header -->
        <div class="report-header">
            <h2>PRODUCT INVENTORY SUMMARY REPORT</h2>
            <p>Generated on: <strong><?php echo date('Y-m-d H:i:s'); ?></strong></p>
            <p>Operator: <em>System Administrator (<?php echo htmlspecialchars($_SESSION['admin_user']); ?>)</em></p>
        </div>

        <!-- Aggregate Summary Stat Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Unique Products</div>
                <div class="stat-value"><?php echo number_format($total_products); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Total Stock Quantity</div>
                <div class="stat-value"><?php echo number_format($total_stock); ?></div>
            </div>
            
            <div class="stat-card" style="border-left: 4px solid var(--accent);">
                <div class="stat-label">Total Inventory Valuation</div>
                <div class="stat-value" style="color: var(--accent);">$<?php echo number_format($total_valuation, 2); ?></div>
            </div>
        </div>

        <!-- Detailed Inventory Grid -->
        <h2>Inventory Asset Breakdown</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">ID</th>
                        <th style="width: 30%;">Product Name</th>
                        <th style="width: 20%;">Category</th>
                        <th style="width: 12%;">Qty in Stock</th>
                        <th style="width: 13%;">Unit Price</th>
                        <th style="width: 15%;">Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #7f8c8d; padding: 30px;">
                                No inventory records found to generate statistics.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <?php 
                                $qty = (int)$product['quantity'];
                                $price = (float)$product['price'];
                                $row_valuation = $qty * $price;
                            ?>
                            <tr>
                                <td><small style="color: #7f8c8d;">#<?php echo htmlspecialchars($product['id']); ?></small></td>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td>
                                    <?php 
                                        echo number_format($qty); 
                                        if ($qty === 0) {
                                            echo ' <span class="badge badge-outofstock">Out</span>';
                                        } elseif ($qty <= 5) {
                                            echo ' <span class="badge badge-lowstock">Low</span>';
                                        }
                                    ?>
                                </td>
                                <td>$<?php echo number_format($price, 2); ?></td>
                                <td><strong>$<?php echo number_format($row_valuation, 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- Totals Footer Row -->
                        <tr style="background-color: #f8f9fa; border-top: 2px solid var(--primary);">
                            <td colspan="3" style="text-align: right; font-weight: 700; font-size: 15px;">TOTALS:</td>
                            <td style="font-weight: 700; font-size: 15px;"><?php echo number_format($total_stock); ?></td>
                            <td>—</td>
                            <td style="font-weight: 700; font-size: 15px; color: var(--accent);">
                                $<?php echo number_format($total_valuation, 2); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Print Footer Note (Only appears when printed or bottom of page) -->
        <div style="margin-top: 40px; text-align: center; font-size: 11px; color: #95a5a6; border-top: 1px dashed var(--border-color); padding-top: 15px;">
            <p>This document is an official inventory breakdown for current active stock.</p>
            <p>© <?php echo date('Y'); ?> Product Inventory Management System. Confirmed secure.</p>
        </div>

    </div>
</body>
</html>
