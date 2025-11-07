<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/guard.php';
require_once dirname(__DIR__) . '/config/database.php';

if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customerId = (int)($_SESSION['user']['id'] ?? 0);

$raw = file_get_contents('php://input');
$js  = $raw ? json_decode($raw, true) : null;
$IN  = is_array($js) ? $js : $_POST;

$orderId       = (int)($IN['order_id'] ?? 0);
$installmentId = (int)($IN['installment_id'] ?? 0);

if ($orderId <= 0 || $installmentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing order/installment id']);
    exit;
}

try {
    $conn = $database->getConnection();

    // ownership + current status
    $q = $conn->prepare("
    SELECT o.customer_id, pi.status
    FROM orders o
    JOIN payment_installments pi ON pi.order_id=o.id
    WHERE o.id=:oid AND pi.id=:iid
  ");
    $q->execute([':oid' => $orderId, ':iid' => $installmentId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['customer_id'] !== $customerId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    if (strtoupper($row['status']) === 'PAID') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Installment already paid']);
        exit;
    }

    // promote UNPAID → PENDING
    $upd = $conn->prepare("UPDATE payment_installments SET status='PENDING' WHERE id=:iid");
    $upd->execute([':iid' => $installmentId]);

    echo json_encode(['success' => true, 'message' => 'Installment ready']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
