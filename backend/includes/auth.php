<?php
/**
 * Authentication Helper Functions
 */

function check_customer_auth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required',
            'redirect' => '/RADS-TOOLING/customer/login.php'
        ]);
        exit;
    }

    // Set customer_id for convenience
    if (!isset($_SESSION['customer_id']) && isset($_SESSION['user']['id'])) {
        $_SESSION['customer_id'] = $_SESSION['user']['id'];
    }
}
