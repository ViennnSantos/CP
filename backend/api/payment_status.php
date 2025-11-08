<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';

try {
    $uid = require_customer_id();
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orderId = (int)($_GET['order'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing order id']);
    exit;
}

$pdo = db();

// Make sure order belongs to this user + get payment state
$stmt = $pdo->prepare("
  SELECT 
    o.id, o.customer_id, 
    COALESCE(o.payment_status,'') AS payment_status,
    COALESCE(p.status,'')        AS pay_row_status
  FROM orders o
  LEFT JOIN payments p ON p.order_id = o.id
  WHERE o.id = :oid
  LIMIT 1
");
$stmt->execute([':oid' => $orderId]);
$row = $stmt->fetch();

if (!$row || (int)$row['customer_id'] !== $uid) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

/**
 * Normalize status:
 * - kapag admin na-approve sa payment_verification.php:
 *     payments.status = 'VERIFIED'
 *     orders.payment_status = 'Paid' (or 'Partially Paid' depende sa logic mo)
 * - kapag reject: payment_verifications.status = 'REJECTED' (pwede mong i-extend ito)
 */
$status      = 'pending';
$status_text = 'Pending verification…';

$payRow = strtoupper((string)$row['pay_row_status']);
$orderPay = strtoupper((string)$row['payment_status']);

if ($payRow === 'VERIFIED' || $orderPay === 'PAID' || $orderPay === 'PARTIALLY PAID') {
    $status = 'approved';
    $status_text = ($orderPay === 'PAID') ? 'Payment approved (Fully Paid)' : 'Payment approved (Partially Paid)';
}

// (Optional) kung gusto mong magmarka ng rejected pag may latest verification = REJECTED
// You can add a join/check here if you store the latest verification status per order.

echo json_encode([
    'success'     => true,
    'status'      => $status,       // 'pending' | 'approved' | 'rejected'
    'status_text' => $status_text,
    // 'receipt_url' => '/customer/receipt.php?order='.$orderId // kung meron ka
]);
