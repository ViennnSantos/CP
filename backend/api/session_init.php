<?php
/**
 * ============================================
 * SESSION INITIALIZATION FOR PLESK/HOSTED
 * ============================================
 * 
 * File: session_init.php
 * Location: /backend/api/session_init.php
 * 
 * Include this file at the TOP of all your API files:
 * require_once __DIR__ . '/session_init.php';
 * 
 * This handles session configuration for Plesk/shared hosting
 * ============================================
 */

// Prevent any output before headers
if (ob_get_level() == 0) {
    ob_start();
}

// Configure session settings for hosted environment
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);

// CRITICAL: SameSite policy
// 'Lax' allows cookies for navigation from external sites
// Change to 'Strict' for maximum security (but may cause issues)
ini_set('session.cookie_samesite', 'Lax');

// Secure flag - enable if using HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
} else {
    ini_set('session.cookie_secure', 0);
}

// Session lifetime - 24 hours
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);

// Cookie path
ini_set('session.cookie_path', '/');

// Optional: Cookie domain (uncomment and set if needed)
// ini_set('session.cookie_domain', '.yourdomain.com');

// Start the session
session_start();

// CORS Configuration
// Adjust the origin to match your frontend domain
$allowed_origins = [
    'https://yourdomain.com',
    'https://www.yourdomain.com',
    'http://localhost:3000',  // For local development
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // For development, allow all origins (REMOVE IN PRODUCTION!)
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

/**
 * Helper function to check customer authentication
 */
function requireCustomerAuth() {
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required',
            'error' => 'No active session'
        ]);
        exit;
    }

    if (!isset($_SESSION['user']['aud']) || $_SESSION['user']['aud'] !== 'customer') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied',
            'error' => 'Not authorized as customer'
        ]);
        exit;
    }

    return $_SESSION['user']['id'] ?? null;
}

/**
 * Helper function to validate CSRF token
 */
function validateCSRF($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token',
            'error' => 'CSRF validation failed'
        ]);
        exit;
    }
    return true;
}

/**
 * Helper function to send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Return true to indicate successful initialization
return true;