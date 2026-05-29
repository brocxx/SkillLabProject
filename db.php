<?php
/**
 * Database Connection & Auto-Setup Configuration
 *
 * This file establishes a PDO database connection.
 * To make deployment plug-and-play, it will automatically attempt to 
 * create the 'inventory_db' database and the 'products' table if they do not exist.
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'inventory_db';

try {
    // 1. Connect to MySQL server first (without database selected)
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // 3. Reconnect to the newly created/existing database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 4. Create 'warehouses' table
    $sqlWarehouses = "CREATE TABLE IF NOT EXISTS warehouses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location VARCHAR(255) NOT NULL,
        manager VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlWarehouses);

    // 5. Create 'products' table if it doesn't exist
    $sqlProducts = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        description TEXT,
        warehouse_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlProducts);

    // Alter products to add warehouse_id if missing (for existing installations)
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN warehouse_id INT NULL");
        $pdo->exec("ALTER TABLE products ADD FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        // Column likely already exists, ignore
    }

    // 6. Create 'shipments' table
    $sqlShipments = "CREATE TABLE IF NOT EXISTS shipments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        tracking_number VARCHAR(100) NOT NULL,
        quantity INT NOT NULL,
        status ENUM('Pending', 'In Transit', 'Delivered') DEFAULT 'Pending',
        destination VARCHAR(255) NOT NULL,
        current_location TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlShipments);

    // Alter shipments to add current_location if missing (for existing installations)
    try {
        $pdo->exec("ALTER TABLE shipments ADD COLUMN current_location TEXT NULL");
    } catch (PDOException $e) {}

    // 7. Create 'users' table
    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    $pdo->exec($sqlUsers);

    // 8. Insert default users if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'admin')");
        $pdo->exec("INSERT INTO users (username, password, role) VALUES ('user', 'user123', 'user')");
    }

} catch (PDOException $e) {
    // Handle database errors gracefully and provide helpful guidance
    die("<h3>Database Connection/Setup Error</h3>" .
        "<p>Could not connect to MySQL database.</p>" .
        "<p><strong>Error details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>" .
        "<hr>" .
        "<p><em>Note: If you are running XAMPP, WampServer, or MAMP, make sure MySQL server is started. " .
        "If you use a custom username or password, please edit the connection details in <code>db.php</code>.</em></p>");
}
?>
