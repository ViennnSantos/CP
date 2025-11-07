<?php
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/../includes/guard.php';

// If already logged in as staff, go to the dashboard
if (!empty($_SESSION['user']) && ($_SESSION['user']['aud'] ?? '') === 'staff') {
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Rads Tooling</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="/RADS-TOOLING/assets/CSS/login.css">
</head>

<body>
    <div class="login-container">
        <span class="login-logo">R</span><span class="logo-text">ADS </span>
        <span class="login-logo">T</span><span class="logo-text">OOLING </span>
        <p class="subtitle">Login as Admin</p>

        <form id="loginForm">
            <div class="error-message" id="errorMessage"></div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                Sign In
            </button>

            <div class="loading" id="loadingMessage">
                Signing in...
            </div>
        </form>

    </div>

    <script src="/RADS-TOOLING/assets/JS/login.js"> </script>

</body>

</html>