<?php
require_once __DIR__ . '/_bootstrap.php';
$uid = require_customer_id();

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$order_id = (int)($body['order_id'] ?? 0);
$method   = strtolower(trim((string)($body['method'] ?? '')));
$deposit  = (int)($body['deposit_rate'] ?? 0);

if (!$order_id || !in_array($method, ['gcash', 'bpi'], true) || !in_array($deposit, [30, 50, 100], true)) {
  fail('Invalid payment decision payload.');
}

$pdo = db();
$stmt = $pdo->prepare("SELECT customer_id, total_amount FROM orders WHERE id=:id");
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch();
if (!$order || (int)$order['customer_id'] !== $uid) fail('Order not found.', 404);

$amount_due = round(((float)$order['total_amount']) * ($deposit / 100), 2);

try {
  $pdo->beginTransaction();

  // 1) Upsert payments row (set to PENDING – handang bayaran)
  $stmt = $pdo->prepare("
    INSERT INTO payments (order_id, method, deposit_rate, amount_due, amount_paid, status)
    VALUES (:oid, :m, :d, :due, 0, 'PENDING')
    ON DUPLICATE KEY UPDATE
      method=VALUES(method),
      deposit_rate=VALUES(deposit_rate),
      amount_due=VALUES(amount_due),
      status='PENDING'
  ");
  $stmt->execute([':oid' => $order_id, ':m' => $method, ':d' => $deposit, ':due' => $amount_due]);

  // 2) Flag order as installment if <100% deposit
  if ($deposit < 100) {
    $pdo->prepare("UPDATE orders SET is_installment=1 WHERE id=:oid")
      ->execute([':oid' => $order_id]);
  } else {
    $pdo->prepare("UPDATE orders SET is_installment=0 WHERE id=:oid")
      ->execute([':oid' => $order_id]);
  }

  // 3) Ensure 2 rows in payment_installments (if none)
  $chk = $pdo->prepare("SELECT COUNT(*) FROM payment_installments WHERE order_id=:oid");
  $chk->execute([':oid' => $order_id]);
  $count = (int)$chk->fetchColumn();

  $total = (float)$order['total_amount'];              // NOTE: kung 'total' ang tunay mong column, palitan mo dito at sa SELECT sa taas
  $depAmt = round($total * ($deposit / 100), 2);
  $remain = max(0.0, round($total - $depAmt, 2));

  if ($count === 0) {
    // #1 deposit -> PENDING (para agad mabayaran)
    $pdo->prepare("
      INSERT INTO payment_installments (order_id, installment_number, amount_due, amount_paid, status, due_date)
      VALUES (:oid, 1, :due, 0, 'PENDING', NULL)
    ")->execute([':oid' => $order_id, ':due' => $depAmt]);

    // #2 remaining -> UNPAID (naka-line up)
    if ($remain > 0) {
      $pdo->prepare("
        INSERT INTO payment_installments (order_id, installment_number, amount_due, amount_paid, status, due_date)
        VALUES (:oid, 2, :due, 0, 'UNPAID', NULL)
      ")->execute([':oid' => $order_id, ':due' => $remain]);
    }
  } else {
    // Promote earliest UNPAID/PENDING installment → PENDING (safe 2-step)
    $sel = $pdo->prepare("
    SELECT id
    FROM payment_installments
    WHERE order_id=:oid AND UPPER(status) IN ('UNPAID','PENDING')
    ORDER BY installment_number ASC
    LIMIT 1
  ");
    $sel->execute([':oid' => $order_id]);
    $promoteId = $sel->fetchColumn();

    if ($promoteId) {
      $pdo->prepare("
      UPDATE payment_installments
      SET status='PENDING'
      WHERE id=:id
    ")->execute([':id' => $promoteId]);
    }
  }

  $pdo->commit();
  ok(['amount_due' => $depAmt]);
} catch (Throwable $e) {
  $pdo->rollBack();
  fail('DB error: ' . $e->getMessage(), 500);
}
