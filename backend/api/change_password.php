<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/includes/guard.php';
require_once dirname(__DIR__)     . '/config/database.php';

if (empty($_SESSION['user']) || (($_SESSION['user']['aud'] ?? '') !== 'customer')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'AUTH']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (($in['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF']);
    exit;
}

$current = (string)($in['current_password'] ?? '');
$new     = (string)($in['new_password'] ?? '');
$confirm = (string)($in['confirm_password'] ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    echo json_encode(['success' => false, 'message' => 'Kumpletohin lahat ng fields']);
    exit;
}
if ($new !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Hindi magkapareho ang new at confirm password']);
    exit;
}
if (strlen($new) < 8) {
    echo json_encode(['success' => false, 'message' => 'Ang password ay dapat ≥ 8 characters']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $cid = (int)$_SESSION['user']['id'];

    $stmt = $pdo->prepare('SELECT password FROM customers WHERE id=?');
    $stmt->execute([$cid]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($current, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Mali ang current password']);
        exit;
    }

    $newHash = password_hash($new, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE customers SET password=?, updated_at=NOW() WHERE id=?')->execute([$newHash, $cid]);

    echo json_encode(['success' => true, 'message' => 'Password updated']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
