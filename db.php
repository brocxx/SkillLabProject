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
    
    // 4. Create 'products' table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        quantity INT NOT NULL DEFAULT 0,
        price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;";
    
    $pdo->exec($sql);

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
