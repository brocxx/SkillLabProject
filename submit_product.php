<?php
/**
 * Submit Product Handler
 *
 * This PHP script handles incoming POST submissions from the product entry form (index.html).
 * It sanitizes, validates, and stores the product in the database, then redirects to inventory.php.
 */

session_start();
require_once 'db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Retrieve and sanitize input fields
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // 2. Simple server-side validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Product name is required.";
    }
    if (empty($category)) {
        $errors[] = "Category is required.";
    }
    if ($quantity < 0) {
        $errors[] = "Quantity cannot be negative.";
    }
    if ($price < 0.00) {
        $errors[] = "Price cannot be negative.";
    }

    // 3. Database insertion or error redirect
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, category, quantity, price, description) VALUES (:name, :category, :quantity, :price, :description)");
            $stmt->execute([
                ':name' => $name,
                ':category' => $category,
                ':quantity' => $quantity,
                ':price' => $price,
                ':description' => $description
            ]);

            // Save flash success message to session
            $_SESSION['success_message'] = "Product '<strong>" . htmlspecialchars($name) . "</strong>' was successfully added to inventory.";
            
            // Redirect to the read-only inventory view
            header("Location: inventory.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database Error: Could not save the product. " . $e->getMessage();
            header("Location: index.html");
            exit;
        }
    } else {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("Location: index.html");
        exit;
    }
} else {
    // If accessed directly, redirect back to index.html
    header("Location: index.html");
    exit;
}
?>
