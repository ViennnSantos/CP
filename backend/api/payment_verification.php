<?php
// backend/api/payment_verification.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// minimal logger
function local_log(string $m): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    @file_put_contents($logDir . '/payment_verification_errors.log', date('[Y-m-d H:i:s] ') . $m . PHP_EOL, FILE_APPEND);
}

// response helpers
function send_json($arr, $code = 200){
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// simple guard: ensure staff/admin session
function guard_require_staff() {
    if (!empty($_SESSION['staff'])) return;
    if (!empty($_SESSION['user']) && (strtolower($_SESSION['user']['role'] ?? '') === 'staff' || strtolower($_SESSION['user']['role'] ?? '') === 'admin')) return;
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Throwable $e) {
    local_log("DB connect error: " . $e->getMessage());
    send_json(['success' => false, 'message' => 'Server DB error'], 500);
}

// All actions require staff
guard_require_staff();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listPaymentVerifications($conn);
        break;
    case 'details':
        getPaymentDetails($conn);
        break;
    case 'approve':
        approvePayment($conn);
        break;
    case 'reject':
        rejectPayment($conn);
        break;
    default:
        send_json(['success' => false, 'message' => 'Invalid action'], 400);
}

// ---------------------------------- functions ----------------------------------

function listPaymentVerifications(PDO $conn): void {
    try {
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $status = $_GET['payment_status'] ?? $_GET['status'] ?? '';
        
        $sql = "
            SELECT 
                pv.id,
                pv.order_id,
                pv.method,
                pv.account_name,
                pv.account_number,
                pv.reference_number,
                pv.amount_reported,
                pv.screenshot_path,
                pv.status,
                pv.created_at,
                o.order_code,
                c.full_name as customer_name,
                o.total_amount,
                COALESCE(p.amount_paid,0) as amount_paid
            FROM payment_verifications pv
            JOIN orders o ON pv.order_id = o.id
            JOIN customers c ON o.customer_id = c.id
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (o.order_code LIKE :search OR c.full_name LIKE :search OR pv.amount_reported LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        // Add status filter
        if (!empty($status)) {
            $sql .= " AND pv.status = :status";
            $params[':status'] = strtoupper($status);
        }
        
        $sql .= " ORDER BY 
            CASE pv.status WHEN 'PENDING' THEN 1 WHEN 'APPROVED' THEN 2 WHEN 'REJECTED' THEN 3 ELSE 4 END,
            pv.created_at DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        send_json(['success' => true, 'data' => $rows]);
    } catch (Throwable $e) {
        local_log("listPaymentVerifications error: " . $e->getMessage());
        send_json(['success' => false, 'message' => 'Failed to load verifications'], 500);
    }
}

function getPaymentDetails(PDO $conn): void {
    // 🔍 Get payment verification ID (supports GET, POST, or JSON body)
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) send_json(['success' => false, 'message' => 'Invalid id'], 400);

    try {
        // helpers to probe schema
        $colExists = function(string $table, string $col) use ($conn): bool {
            $st = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tbl AND column_name = :col LIMIT 1");
            $st->execute([':tbl' => $table, ':col' => $col]);
            return (bool)$st->fetchColumn();
        };
        $tableExists = function(string $table) use ($conn): bool {
            $st = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl LIMIT 1");
            $st->execute([':tbl' => $table]);
            return (bool)$st->fetchColumn();
        };


        // detect available order/customer address columns
        $orderCandidates = [
            'shipping_street','shipping_barangay','shipping_city','shipping_province','shipping_postal_code',
            'order_shipping_street','order_shipping_barangay','order_shipping_city','order_shipping_province','order_shipping_postal_code',
            'street','barangay','city','province','postal_code','zip','address'
        ];
        $customerCandidates = ['address','street','barangay','city','province','postal_code','zip'];

        $orderCols = [];
        foreach ($orderCandidates as $c) if ($colExists('orders', $c)) $orderCols[] = $c;
        $customerCols = [];
        foreach ($customerCandidates as $c) if ($colExists('customers', $c)) $customerCols[] = $c;

        $hasPayments = $tableExists('payments');
        $hasOrderItems = $tableExists('order_items');
        $hasProducts   = $tableExists('products');

        // Build safe select list
        $select = [
            'pv.*',
            'o.id AS order_id',
            'o.order_code',
            'o.total_amount',
            'o.order_date',
            "o.mode AS delivery_mode",
            "o.terms_agreed AS order_terms_agreed"
        ];

        // add detected order columns as order_<col>
        foreach ($orderCols as $c) $select[] = "o.`$c` AS order_{$c}";

        // customer basics and detected customer cols
        $select[] = "c.full_name AS customer_name";
        $select[] = "c.email AS customer_email";
        $select[] = "c.phone AS customer_phone";
        foreach ($customerCols as $c) $select[] = "c.`$c` AS customer_{$c}";

        // payments summary
        if ($hasPayments) {
            $select[] = "COALESCE(p.amount_paid,0) AS amount_paid";
            if ($colExists('payments','deposit_rate')) $select[] = "COALESCE(p.deposit_rate,NULL) AS deposit_rate";
            if ($colExists('payments','amount_due')) $select[] = "COALESCE(p.amount_due,(o.total_amount - COALESCE(p.amount_paid,0))) AS amount_due";
        } else {
            $select[] = "0 AS amount_paid";
            $select[] = "(o.total_amount - 0) AS amount_due";
        }

        // Build SQL
        $sql = "SELECT " . implode(",\n    ", $select) . "
                FROM payment_verifications pv
                JOIN orders o ON pv.order_id = o.id
                JOIN customers c ON o.customer_id = c.id";

        if ($hasPayments) {
            $sql .= " LEFT JOIN (
                SELECT order_id, SUM(COALESCE(amount_paid,0)) AS amount_paid, MAX(deposit_rate) AS deposit_rate, MAX(amount_due) AS amount_due
                FROM payments
                GROUP BY order_id
            ) p ON p.order_id = o.id";
        }

        $sql .= " WHERE pv.id = :id LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            send_json(['success' => false, 'message' => 'Not found'], 404);
        }

        // SAFE items aggregation: only reference columns that exist
        $items = null;
        if ($hasOrderItems) {
            // determine which item-name/qty columns exist
            $itemNameExpr = null;
            if ($colExists('order_items','product_name'))       $itemNameExpr = "oi.product_name";
            elseif ($colExists('order_items','name'))           $itemNameExpr = "oi.name";
            elseif ($colExists('order_items','item_name'))      $itemNameExpr = "oi.item_name";
            elseif ($hasProducts && $colExists('products','name')) $itemNameExpr = "pr.name";
            else $itemNameExpr = "'Item'";

            $qtyExpr = null;
            if ($colExists('order_items','quantity')) $qtyExpr = "COALESCE(oi.quantity,1)";
            elseif ($colExists('order_items','qty'))  $qtyExpr = "COALESCE(oi.qty,1)";
            else $qtyExpr = "1";

            // build query using only safe expressions (note: pr join only if needed)
            $itemsSql = "SELECT {$itemNameExpr} AS item_name, {$qtyExpr} AS qty FROM order_items oi ";
            if (strpos($itemNameExpr, 'pr.') !== false) {
                $itemsSql .= " LEFT JOIN products pr ON COALESCE(oi.product_id,0) = pr.id ";
            }
            $itemsSql .= " WHERE oi.order_id = :oid ORDER BY oi.id";

            $itemsStmt = $conn->prepare($itemsSql);
            $itemsStmt->execute([':oid' => $row['order_id']]);
            $parts = [];
            while ($r = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
                $iname = $r['item_name'] ?? 'Item';
                $qty = (int)($r['qty'] ?? 1);
                $parts[] = $qty . ' x ' . $iname;
            }
            if ($parts) $items = implode(', ', $parts);
        }
        $row['items'] = $items;

        // assemble address using detected columns (order first, then customer)
        $addrParts = [];
        foreach ($orderCols as $c) {
            $alias = "order_{$c}";
            if (!empty($row[$alias])) $addrParts[] = trim($row[$alias]);
        }
        foreach ($customerCols as $c) {
            $alias = "customer_{$c}";
            if (!empty($row[$alias])) $addrParts[] = trim($row[$alias]);
        }
        if (empty($addrParts)) {
            if (!empty($row['customer_address'])) $addrParts[] = trim($row['customer_address']);
            if (!empty($row['address'])) $addrParts[] = trim($row['address']);
        }
        $row['address'] = $addrParts ? implode(' / ', $addrParts) : null;

        // ensure screenshot/proof fields exist (common names)
        if (empty($row['screenshot_path'])) {
            if (isset($row['proof_path'])) $row['screenshot_path'] = $row['proof_path'];
            elseif (isset($row['image'])) $row['screenshot_path'] = $row['image'];
            else $row['screenshot_path'] = null;
        }

        // normalize numeric fields for client
        $row['total_amount'] = isset($row['total_amount']) ? (float)$row['total_amount'] : 0.0;
        $row['amount_paid']  = isset($row['amount_paid']) ? (float)$row['amount_paid'] : 0.0;
        $row['amount_due']   = isset($row['amount_due']) ? (float)$row['amount_due'] : ($row['total_amount'] - $row['amount_paid']);
        $row['deposit_rate'] = array_key_exists('deposit_rate', $row) ? (is_null($row['deposit_rate']) ? null : (float)$row['deposit_rate']) : null;

        send_json(['success' => true, 'data' => $row]);
    } catch (Throwable $e) {
        if (isset($conn) && $conn->inTransaction()) {
            try { $conn->rollBack(); } catch(Throwable $_) {}
        }
        local_log("getPaymentDetails error: " . $e->getMessage());
        // Keep message generic for users, but log actual error
        send_json(['success' => false, 'message' => 'Failed to load details: server error (check logs/payment_verification_errors.log)'], 500);
    }
}


/**
 * Recalculate order.payment_status using verified payments or installments.
 */
function recalc_order_payment(PDO $conn, int $orderId): void {
    try {
        $stmt = $conn->prepare("SELECT total_amount FROM orders WHERE id = :oid LIMIT 1");
        $stmt->execute([':oid' => $orderId]);
        $total = (float)($stmt->fetchColumn() ?? 0);

        $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) as paid_total FROM payments WHERE order_id = :oid AND UPPER(COALESCE(status,'')) IN ('VERIFIED','APPROVED')");
        $stmt->execute([':oid' => $orderId]);
        $paid = (float)($stmt->fetchColumn() ?? 0);

        $newStatus = 'Pending';
        if ($paid >= $total - 0.01) $newStatus = 'Fully Paid';
        elseif ($paid > 0) $newStatus = 'With Balance';
        else $newStatus = 'Pending';
		
        // Update payment_status and remaining_balance
        $remainingBalance = max(0, $total - $paid);
        $conn->prepare("UPDATE orders SET payment_status = :ps, remaining_balance = :rb WHERE id = :oid")
            ->execute([':ps'=>$newStatus, ':rb'=>$remainingBalance, ':oid'=>$orderId]);
        if ($newStatus === 'Fully Paid') {
            $conn->prepare("UPDATE orders SET status = 'Processing' WHERE id = :oid")->execute([':oid'=>$orderId]);
        }
    } catch (Throwable $e) {
        local_log("recalc_order_payment error: " . $e->getMessage());
    }
}

function approvePayment(PDO $conn): void {
    // read JSON body or POST
    $raw = file_get_contents('php://input');
    $input = [];
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $input = $decoded;
    }
    // also merge $_POST
    foreach ($_POST as $k => $v) $input[$k] = $v;

    $pv_id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$pv_id) send_json(['success' => false, 'message' => 'Invalid verification id'], 400);

    try {
        $conn->beginTransaction();

        // load verification
        $s = $conn->prepare("SELECT id, order_id, amount_reported, status, method FROM payment_verifications WHERE id = :id LIMIT 1");
        $s->execute([':id' => $pv_id]);
        $pv = $s->fetch(PDO::FETCH_ASSOC);
        if (!$pv) {
            $conn->rollBack();
            send_json(['success' => false, 'message' => 'Verification not found'], 404);
        }

        $orderId = (int)$pv['order_id'];
        $amt = (float)$pv['amount_reported'];
        $cur = strtoupper(trim((string)$pv['status'] ?? ''));

        if (in_array($cur, ['APPROVED','VERIFIED'], true)) {
            $conn->rollBack();
            send_json(['success' => true, 'message' => 'Already approved', 'order_id' => $orderId]);
        }

        // approver id from session
        $approverId = $_SESSION['staff']['id'] ?? $_SESSION['user']['id'] ?? 0;

        // mark pv approved
        $u = $conn->prepare("UPDATE payment_verifications SET status = 'APPROVED', approved_by = :uid, approved_at = NOW() WHERE id = :id");
        $u->execute([':uid' => $approverId, ':id' => $pv_id]);

        // ensure payments row exists: select first
        $sel = $conn->prepare("SELECT id, COALESCE(amount_paid,0) as amount_paid FROM payments WHERE order_id = :oid LIMIT 1");
        $sel->execute([':oid' => $orderId]);
        $payRow = $sel->fetch(PDO::FETCH_ASSOC);

        if ($payRow) {
            $upd = $conn->prepare("UPDATE payments SET amount_paid = COALESCE(amount_paid,0) + :amt, status = 'VERIFIED', verified_by = :vby, verified_at = NOW(), updated_at = NOW() WHERE id = :pid");
            $upd->execute([':amt' => $amt, ':vby' => $approverId, ':pid' => $payRow['id']]);
        } else {
            $ins = $conn->prepare("INSERT INTO payments (order_id, method, deposit_rate, amount_due, amount_paid, status, created_at, verified_by, verified_at) VALUES (:oid, :method, 0, 0, :amt, 'VERIFIED', NOW(), :vby, NOW())");
            $ins->execute([':oid' => $orderId, ':method' => $pv['method'] ?? 'gcash', ':amt' => $amt, ':vby' => $approverId]);
        }

        // optionally mark next installment as PAID (if table exists)
        try {
            $st = $conn->prepare("SELECT id FROM payment_installments WHERE order_id = :oid AND UPPER(COALESCE(status,'')) IN ('PENDING','UNPAID') ORDER BY installment_number ASC LIMIT 1");
            $st->execute([':oid' => $orderId]);
            $nextInst = $st->fetchColumn();
            if ($nextInst) {
                $conn->prepare("UPDATE payment_installments SET status = 'PAID', amount_paid = amount_due, verified_at = NOW() WHERE id = :id")->execute([':id' => $nextInst]);
            }
        } catch (Throwable $e) {
            // ignore if table missing
        }

        // recalc order's payment_status
        recalc_order_payment($conn, $orderId);

        // Fetch updated order details to return fresh data
        $orderStmt = $conn->prepare("
            SELECT
                id,
                total_amount,
                remaining_balance,
                payment_status,
                status,
                COALESCE((
                    SELECT SUM(p2.amount_paid)
                    FROM payments p2
                    WHERE p2.order_id = o.id
                    AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')
                ), 0) AS amount_paid
            FROM orders o
            WHERE id = :oid
            LIMIT 1
        ");
        $orderStmt->execute([':oid' => $orderId]);
        $updatedOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);
        $conn->commit();
        send_json([
            'success' => true,
            'message' => 'Payment approved successfully',
            'data' => [
                'order_id' => $orderId,
                'pv_id' => $pv_id,
                'order' => $updatedOrder
            ]
        ]);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        local_log("approvePayment error: " . $e->getMessage());
        send_json(['success' => false, 'message' => 'Failed to approve: ' . $e->getMessage()], 500);
    }
}

function rejectPayment(PDO $conn): void {
    $raw = file_get_contents('php://input');
    $input = [];
    if (!empty($raw)) {
        $dec = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) $input = $dec;
    }
    foreach ($_POST as $k => $v) $input[$k] = $v;

    $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
    $reason = trim($input['reason'] ?? '');

    if (!$id) send_json(['success' => false, 'message' => 'Invalid id'], 400);

    try {
        // ---------- Ensure reject column exists BEFORE starting transaction ----------
        $colCheck = $conn->prepare("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE() AND table_name = 'payment_verifications' 
              AND column_name IN ('rejected_reason','reject_reason')
            LIMIT 1
        ");
        $colCheck->execute();
        $found = $colCheck->fetchColumn();
        if ($found) {
            $colName = $found;
        } else {
            // create column outside transaction (DDL causes implicit commits)
            $colName = 'reject_reason';
            $conn->exec("ALTER TABLE payment_verifications ADD COLUMN {$colName} TEXT NULL");
        }

        // ---------- Now start transaction for the update logic ----------
        $conn->beginTransaction();

        // load verification
        $stmt = $conn->prepare("SELECT id, order_id, status FROM payment_verifications WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $pv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pv) {
            if ($conn->inTransaction()) $conn->rollBack();
            send_json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Prevent double-reject (business rule). If you want to overwrite, remove this check.
        if (strtoupper($pv['status']) === 'REJECTED') {
            if ($conn->inTransaction()) $conn->rollBack();
            send_json(['success' => false, 'message' => 'Already rejected']);
        }

        // Update verification status + reason using detected column
        $updSql = "UPDATE payment_verifications SET status = 'REJECTED', {$colName} = :reason, rejected_at = NOW() WHERE id = :id";
        $upd = $conn->prepare($updSql);
        $upd->execute([':reason' => $reason, ':id' => $id]);

        // Mark payments for that order as REJECTED (if payments table exists)
        $u2 = $conn->prepare("UPDATE payments SET status = 'REJECTED' WHERE order_id = :oid");
        $u2->execute([':oid' => $pv['order_id']]);

        // Optional: insert audit record if audit table exists (best practice)
        try {
            $auditCheck = $conn->prepare("SHOW TABLES LIKE 'payment_verification_audit'");
            $auditCheck->execute();
            $hasAudit = (bool)$auditCheck->fetchColumn();
            if ($hasAudit) {
                $oldStatus = $pv['status'];
                $userId = $_SESSION['staff']['id'] ?? $_SESSION['user']['id'] ?? 0;
                $insAudit = $conn->prepare("INSERT INTO payment_verification_audit (payment_verification_id, old_status, new_status, reason, changed_by, changed_at) VALUES (:pv, :old, 'REJECTED', :r, :u, NOW())");
                $insAudit->execute([':pv' => $id, ':old' => $oldStatus, ':r' => $reason, ':u' => $userId]);
            }
        } catch (Throwable $e) {
            // ignore audit errors
        }

        // recalc order payment
        recalc_order_payment($conn, (int)$pv['order_id']);

        $conn->commit();
        send_json(['success' => true, 'message' => 'Rejected']);
    } catch (Throwable $e) {
        // Only roll back if transaction active
        try {
            if ($conn->inTransaction()) $conn->rollBack();
        } catch (Throwable $_) {
            // ignore rollback errors
        }
        local_log("rejectPayment error: " . $e->getMessage());
        send_json(['success' => false, 'message' => 'Failed to reject: ' . $e->getMessage()], 500);
    }
}

