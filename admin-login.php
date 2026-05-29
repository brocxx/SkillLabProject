<?php
/**
 * Admin Login Page
 *
 * Provides a clean form for administrative access using a secure hardcoded login.
 * Once successfully authenticated, PHP Session variables protect successive pages.
 */

session_start();

// If already logged in, redirect directly to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin-dashboard.php");
    exit;
}

$error = '';

// Handle credentials check on POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Hardcoded credentials for simple, framework-free security
    $correct_username = 'admin';
    $correct_password = 'admin123';

    if ($username === $correct_username && $password === $correct_password) {
        // Authenticate session and redirect
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = $username;
        header("Location: admin-dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Inventory System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container container-small" style="margin-top: 10vh;">
        <header>
            <h1>Admin Portal</h1>
            <nav class="nav-links">
                <a href="inventory.php">← Back to Portal</a>
            </nav>
        </header>

        <h2>Secure Login Required</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="admin-login.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="e.g., admin" autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••" autocomplete="current-password">
            </div>

            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Log In</button>
            </div>
            
            <p style="font-size: 12px; color: #7f8c8d; text-align: center; margin-top: 15px;">
                <em>Default credentials: admin / admin123</em>
            </p>
        </form>
    </div>
</body>
</html>
