<?php
/**
 * Application Configuration & Database Initialization
 * This file must be included at the top of all backend files
 */

// Prevent direct access
if (!defined('RADS_TOOLING_APP')) {
    define('RADS_TOOLING_APP', true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
}

// Define base URL
define('BASE_URL', '');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Include database class
require_once __DIR__ . '/database.php';

// Create global PDO connection
try {
    $dbInstance = Database::getInstance();
    $pdo = $dbInstance->getConnection();
    if (!$pdo) throw new Exception("Failed to establish database connection");
} catch (Exception $e) {
    error_log("FATAL: Database connection failed - " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed. Contact admin.',
        'error' => (defined('DEBUG') && DEBUG) ? $e->getMessage() : 'Internal error'
    ]));
}

// Timezone (adjust to your location)
date_default_timezone_set('Asia/Manila');