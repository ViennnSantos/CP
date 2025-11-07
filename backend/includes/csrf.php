<?php
/**
 * CSRF Protection Helper
 */

function verify_csrf_token() {
    $token = null;

    // Check POST data first
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // Check JSON body
    else {
        $json = json_decode(file_get_contents('php://input'), true);
        if (isset($json['csrf_token'])) {
            $token = $json['csrf_token'];
        }
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!$token || !isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }
}
