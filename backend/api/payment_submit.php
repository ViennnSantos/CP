<?php
// backend/api/payment_submit.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Basic logging helper
function local_log(string $msg): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    @file_put_contents($logDir . '/payment_submit_errors.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// minimal response helpers
function ok($data = []) {
    echo json_encode(array_merge(['success' => true], is_array($data) ? $data : ['data' => $data]));
    exit;
}
function fail($msg = 'Error', $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// get current customer id from session (adjust keys if your app uses different)
function require_customer_id(): int {
    if (!empty($_SESSION['customer']['id'])) return (int)$_SESSION['customer']['id'];
    if (!empty($_SESSION['user']['id']) && (strtolower($_SESSION['user']['role'] ?? '') === 'customer')) return (int)$_SESSION['user']['id'];
    fail('Unauthorized', 401);
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (Throwable $e) {
    local_log("DB connect error: " . $e->getMessage());
    fail('Server error (db)', 500);
}

// --- begin input collection ---
$uid = require_customer_id();

$order_id        = (int)($_POST['order_id'] ?? ($_GET['order_id'] ?? 0));
$account_name    = trim((string)($_POST['account_name'] ?? ''));
$account_number  = trim((string)($_POST['account_number'] ?? ''));
$reference       = trim((string)($_POST['reference_number'] ?? $_POST['reference'] ?? ''));
$amount_paid     = (float)($_POST['amount_paid'] ?? 0.0);

// quick debug (uncomment for troubleshooting)
// @file_put_contents(__DIR__ . '/../logs/payment_submit_debug.log', date('[Y-m-d H:i:s] ') . "POST: " . json_encode($_POST) . PHP_EOL, FILE_APPEND);
// @file_put_contents(__DIR__ . '/../logs/payment_submit_debug.log', date('[Y-m-d H:i:s] ') . "FILES: " . json_encode($_FILES) . PHP_EOL, FILE_APPEND);

// ✅ IMPROVED: Better validation
if (!$order_id) fail('Missing order_id.');
if (!$account_name) fail('Account name is required.');
if ($amount_paid <= 0) fail('Amount paid must be greater than zero.');
if (!$reference) fail('Reference number is required.');

// ✅ NEW: Validate account number format
if (!empty($account_number)) {
    // Remove common formatting
    $cleanAccNum = preg_replace('/[\s\-]+/', '', $account_number);

    // Check if it's a valid format (digits only, reasonable length)
    if (!preg_match('/^\d{9,12}$/', $cleanAccNum) && !preg_match('/^(09|\+639)\d{9}$/', $cleanAccNum)) {
        fail('Invalid account number format. Must be 9-12 digits for bank accounts or 11 digits for mobile (09XXXXXXXXX).');
    }
}

// ✅ NEW: Validate reference number (alphanumeric, 6-20 chars)
if (!preg_match('/^[A-Za-z0-9]{6,20}$/', $reference)) {
    fail('Invalid reference number. Must be 6-20 alphanumeric characters.');
}

// verify order ownership
try {
    $st = $pdo->prepare("SELECT id, customer_id FROM orders WHERE id = :id LIMIT 1");
    $st->execute([':id' => $order_id]);
    $ord = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ord) fail('Order not found.', 404);
    if ((int)$ord['customer_id'] !== $uid) fail('Order does not belong to you.', 403);
} catch (Throwable $e) {
    local_log("Order lookup error: " . $e->getMessage());
    fail('Server error', 500);
}

// handle screenshot upload
$upload_dir = __DIR__ . '/../../uploads/payments';
if (!is_dir($upload_dir)) {
    if (!@mkdir($upload_dir, 0775, true) && !is_dir($upload_dir)) {
        local_log("Cannot create payments dir: {$upload_dir}");
        fail('Server error (upload folder)', 500);
    }
}

$screenshot_path = null;
if (!empty($_FILES['screenshot']['tmp_name'])) {
    $tmp = $_FILES['screenshot']['tmp_name'];
    $orig = $_FILES['screenshot']['name'] ?? 'proof.jpg';
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?: 'jpg');
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
    $fname = 'proof_' . $order_id . '_' . time() . '.' . $ext;
    $dest = rtrim($upload_dir, '/') . '/' . $fname;

    if (!@move_uploaded_file($tmp, $dest)) {
        // fallback copy
        if (!@copy($tmp, $dest)) {
            local_log("Failed to save screenshot for order {$order_id} tmp={$tmp} dest={$dest}");
            fail('Failed to save screenshot.', 500);
        } else {
            @unlink($tmp);
        }
    }
    // web path used elsewhere in project; keep consistent
    $screenshot_path = 'uploads/payments/' . $fname;
}

// Validation: enforce minimum payment rules (same logic as earlier)
try {
    $st = $pdo->prepare("SELECT total_amount FROM orders WHERE id = :id LIMIT 1");
    $st->execute([':id' => $order_id]);
    $ordRow = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ordRow) fail('Order not found.', 404);
    $total = (float)($ordRow['total_amount'] ?? 0);

    $st = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) AS paid FROM payments WHERE order_id = :id AND UPPER(COALESCE(status,'')) IN ('VERIFIED','APPROVED')");
    $st->execute([':id' => $order_id]);
    $paid = (float)($st->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0);

    $remaining = max(0.0, round($total - $paid, 2));
    if ($remaining <= 0) fail('Order already fully paid.', 400);

    // chosen deposit_rate if any (from payments table)
    $st = $pdo->prepare("SELECT COALESCE(deposit_rate,0) AS deposit_rate FROM payments WHERE order_id = :id LIMIT 1");
    $st->execute([':id' => $order_id]);
    $depRow = $st->fetch(PDO::FETCH_ASSOC);
    $chosenRate = (int)($depRow['deposit_rate'] ?? 0);

    $minThisPayment = 0.0;
    if ($paid <= 0 && in_array($chosenRate, [30,50,100], true)) {
        $minThisPayment = round($total * ($chosenRate / 100.0), 2);
    } else {
        $minThisPayment = round($remaining * 0.30, 2);
    }

    // ✅ IMPROVED: Enforce exact amount match
    if (abs($amount_paid - $remaining) >= 0.01) {
        fail('Payment amount must exactly match the order total: ₱' . number_format($remaining,2), 400);
    }
} catch (Throwable $e) {
    local_log("Payment validation error: " . $e->getMessage());
    fail('Server validation error', 500);
}

// --- ADDRESS ASSEMBLY & OPTIONAL ORDER UPDATE (safe, non-fatal) ---
try {
    // Collect possible POST address fields (common names)
    $rawVals = [
        'blk'      => trim((string)($_POST['blk'] ?? $_POST['block'] ?? $_POST['lot'] ?? '')),
        'street'   => trim((string)($_POST['street'] ?? $_POST['st'] ?? '')),
        'barangay' => trim((string)($_POST['barangay'] ?? $_POST['brgy'] ?? '')),
        'city'     => trim((string)($_POST['city'] ?? $_POST['municipality'] ?? $_POST['town'] ?? '')),
        'province' => trim((string)($_POST['province'] ?? '')),
        'postal'   => trim((string)($_POST['postal_code'] ?? $_POST['zip'] ?? '')),
        'combined' => trim((string)($_POST['address'] ?? '')),
        'psgc'     => trim((string)($_POST['psgc'] ?? ''))
    ];

    // Normalize empties to null
    foreach ($rawVals as $k => $v) if ($v === '') $rawVals[$k] = null;

    // Optional PSGC resolver (if you have it)
    if (!empty($rawVals['psgc']) && function_exists('getPsgcParts')) {
        $ps = getPsgcParts($rawVals['psgc']);
        if (!empty($ps['barangay']) && empty($rawVals['barangay'])) $rawVals['barangay'] = $ps['barangay'];
        if (!empty($ps['city']) && empty($rawVals['city'])) $rawVals['city'] = $ps['city'];
        if (!empty($ps['province']) && empty($rawVals['province'])) $rawVals['province'] = $ps['province'];
        if (!empty($ps['postal']) && empty($rawVals['postal'])) $rawVals['postal'] = $ps['postal'] ?? $rawVals['postal'];
    }

    // Build readable address array
    $addrParts = [];
    if (!empty($rawVals['blk'])) $addrParts[] = 'Blk ' . $rawVals['blk'];
    if (!empty($rawVals['street'])) $addrParts[] = $rawVals['street'];
    if (!empty($rawVals['barangay'])) $addrParts[] = 'Brgy ' . $rawVals['barangay'];
    if (!empty($rawVals['city'])) $addrParts[] = $rawVals['city'];
    if (!empty($rawVals['province'])) $addrParts[] = $rawVals['province'];
    if (!empty($rawVals['postal'])) $addrParts[] = $rawVals['postal'];

    if (empty($addrParts) && !empty($rawVals['combined'])) {
        $addrParts[] = $rawVals['combined'];
    }

    $fullAddress = $addrParts ? implode(', ', $addrParts) : null;

    if ($fullAddress) {
        // Determine which columns exist and update what we can
        $check = $pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'orders'
              AND column_name IN (
                'customer_address',
                'address','shipping_street','shipping_barangay','shipping_city','shipping_province','shipping_postal_code',
                'street','barangay','city','province','postal_code'
              )
        ");
        $check->execute();
        $existing = $check->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $cols = [];

        // prefer structured shipping columns
        if (in_array('shipping_street', $existing) && !empty($rawVals['street'])) $cols['shipping_street'] = $rawVals['street'];
        if (in_array('shipping_barangay', $existing) && !empty($rawVals['barangay'])) $cols['shipping_barangay'] = $rawVals['barangay'];
        if (in_array('shipping_city', $existing) && !empty($rawVals['city'])) $cols['shipping_city'] = $rawVals['city'];
        if (in_array('shipping_province', $existing) && !empty($rawVals['province'])) $cols['shipping_province'] = $rawVals['province'];
        if (in_array('shipping_postal_code', $existing) && !empty($rawVals['postal'])) $cols['shipping_postal_code'] = $rawVals['postal'];

        // generic fields if present
        if (in_array('street', $existing) && !empty($rawVals['street'])) $cols['street'] = $rawVals['street'];
        if (in_array('barangay', $existing) && !empty($rawVals['barangay'])) $cols['barangay'] = $rawVals['barangay'];
        if (in_array('city', $existing) && !empty($rawVals['city'])) $cols['city'] = $rawVals['city'];
        if (in_array('province', $existing) && !empty($rawVals['province'])) $cols['province'] = $rawVals['province'];
        if (in_array('postal_code', $existing) && !empty($rawVals['postal'])) $cols['postal_code'] = $rawVals['postal'];

        // prefer dedicated customer_address if available and nothing else
        if (empty($cols) && in_array('customer_address', $existing)) {
            $cols['customer_address'] = $fullAddress;
        }

        if (!empty($cols)) {
            $sets = [];
            $params = [':oid' => $order_id];
            foreach ($cols as $k => $v) {
                $sets[] = "`$k` = :$k";
                $params[":$k"] = $v;
            }
            $sqlUpd = "UPDATE orders SET " . implode(', ', $sets) . " WHERE id = :oid";
            $updStmt = $pdo->prepare($sqlUpd);
            $updStmt->execute($params);
        } else {
            // last fallback: update customer_address if exists
            $fallbackChk = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'orders'
                  AND column_name = 'customer_address'
                LIMIT 1
            ");
            $fallbackChk->execute();
            if ($fallbackChk->fetchColumn()) {
                $pdo->prepare("UPDATE orders SET customer_address = :addr WHERE id = :oid")->execute([':addr' => $fullAddress, ':oid' => $order_id]);
            } else {
                // no columns available; log for debug
                local_log("payment_submit: built fullAddress but no orders address column found. order_id={$order_id} address=" . $fullAddress);
            }
        }
    } else {
        // nothing posted — log for debug
        local_log("payment_submit: no address fields posted for order {$order_id} by user {$uid}");
    }
} catch (Throwable $e) {
    local_log("payment_submit address update error: " . $e->getMessage());
    // non-fatal: we don't want to block payment submission if address update fails
}

// insert verification and ensure payments row exists (but do NOT add to amount_paid here)
try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("INSERT INTO payment_verifications
        (order_id, method, account_name, account_number, reference_number, screenshot_path, amount_reported, status, created_at)
        VALUES (:oid, :method, :accname, :accnum, :ref, :img, :amt, 'PENDING', NOW())");

    // method: try to infer from existing payments row else default 'gcash'
    $st = $pdo->prepare("SELECT method FROM payments WHERE order_id = :oid LIMIT 1");
    $st->execute([':oid' => $order_id]);
    $pRow = $st->fetch(PDO::FETCH_ASSOC);
    $method = $pRow['method'] ?? 'gcash';

    $ins->execute([
        ':oid' => $order_id,
        ':method' => $method,
        ':accname' => $account_name,
        ':accnum' => $account_number ?: null,
        ':ref' => $reference,
        ':img' => $screenshot_path,
        ':amt' => $amount_paid
    ]);
    $pv_id = (int)$pdo->lastInsertId();

    // ensure a payments row exists (do not change amount_paid)
    $sel = $pdo->prepare("SELECT id FROM payments WHERE order_id = :oid LIMIT 1");
    $sel->execute([':oid' => $order_id]);
    $exists = $sel->fetchColumn();
    if (!$exists) {
        $insPay = $pdo->prepare("INSERT INTO payments (order_id, method, deposit_rate, amount_due, amount_paid, status, created_at) VALUES (:oid, :m, 0, 0, 0, 'PENDING', NOW())");
        $insPay->execute([':oid' => $order_id, ':m' => $method]);
    } else {
        // ensure status is PENDING
        $pdo->prepare("UPDATE payments SET status = 'PENDING' WHERE order_id = :oid")->execute([':oid' => $order_id]);
    }

    // ✅ Update T&C acceptance timestamp and IP (if columns exist)
    try {
        $colCheck = $pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'orders'
              AND column_name IN ('terms_agreed_at', 'terms_agreed_ip')
        ");
        $colCheck->execute();
        $existingCols = $colCheck->fetchAll(PDO::FETCH_COLUMN);

        $updates = [];
        $updateParams = [':oid' => $order_id];

        if (in_array('terms_agreed_at', $existingCols)) {
            $updates[] = 'terms_agreed_at = NOW()';
        }

        if (in_array('terms_agreed_ip', $existingCols)) {
            $updates[] = 'terms_agreed_ip = :ip';
            $updateParams[':ip'] = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        if (!empty($updates)) {
            $updates[] = 'terms_agreed = 1';  // Ensure terms_agreed is set to 1
            $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = :oid";
            $pdo->prepare($sql)->execute($updateParams);
            error_log("T&C acceptance recorded for order {$order_id} at " . date('Y-m-d H:i:s'));
        }
    } catch (Throwable $e) {
        local_log("T&C timestamp update error: " . $e->getMessage());
        // Non-fatal: continue even if T&C tracking fails
    }

    // Notify admin -> optional: insert into notifications table if you have it
    try {
        $notStmt = $pdo->prepare("INSERT INTO admin_notifications (type, payload, created_at) VALUES (:type, :payload, NOW())");
        $notStmt->execute([':type' => 'payment_verification', ':payload' => json_encode(['order_id' => $order_id, 'pv_id' => $pv_id])]);
    } catch (Throwable $e) {
        // don't fail user if notifications table missing - just log
        local_log("Notification insert skipped or failed: " . $e->getMessage());
    }

    $pdo->commit();

    ok(['message' => 'Payment submitted for verification.', 'order_id' => $order_id, 'pv_id' => $pv_id]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    local_log("DB error on payment_submit: " . $e->getMessage());
    fail('Database error while submitting payment.', 500);
}
