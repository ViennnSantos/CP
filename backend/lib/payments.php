<?php
function recalc_order_payment(PDO $pdo, int $orderId): void
{
    // 1) Kunin total ng order
    // NOTE: Palitan mo 'total_amount' -> 'total' kung ganoon ang column mo.
    $st = $pdo->prepare("SELECT total_amount AS total FROM orders WHERE id=:id");
    $st->execute([':id' => $orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    $total = (float)($row['total'] ?? 0);

    // 2) Sum ng lahat ng VERIFIED payments (single source of truth)
    $st = $pdo->prepare("
            SELECT COALESCE(SUM(amount_paid),0) AS paid 
            FROM payments 
            WHERE order_id=:id AND UPPER(status)='VERIFIED'
        ");

    $st->execute([':id' => $orderId]);
    $paid = (float)($st->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0);

    // (Optional fallback) – kung sakaling wala sa payments pero may naka-PAID na installments:
    if ($paid <= 0.0) {
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(amount_paid),0) AS paid
            FROM payment_installments
            WHERE order_id = :id AND UPPER(status) = 'PAID'
        ");
        $st->execute([':id' => $orderId]);
        $paid = max($paid, (float)($st->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0));
    }

    // 3) Compute remaining (with a tiny epsilon for rounding)
    $remain = max(0.0, round($total - $paid, 2));
    if ($remain > 0 && $remain < 0.01) $remain = 0.0; // guard sa floating errors

    // 4) Decide label
    $label = ($paid <= 0.0) ? 'Unpaid' : (($remain > 0.0) ? 'Partially Paid' : 'Paid');

    // 5) Update sa orders
    $u = $pdo->prepare("UPDATE orders SET payment_status = :ps WHERE id = :id");
    $u->execute([':ps' => $label, ':id' => $orderId]);
}
