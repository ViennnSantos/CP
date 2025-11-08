<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_bootstrap.php';


// ✅ IMPROVED: Better session check with detailed error
if (!isset($_SESSION['customer']) || empty($_SESSION['customer'])) {
    error_log('Order create failed: No customer session');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to place an order',
        'redirect' => '/customer/login.php'
    ]);
    exit;
}

$uid = (int)$_SESSION['customer']['id'];

// Read and parse request body
$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?: [];

// ✅ IMPROVED: Log received payload for debugging
error_log('Order create request from customer ' . $uid . ': ' . $raw);

// Extract basic order data
$pid = (int)($body['pid'] ?? 0);
$qty = max(1, (int)($body['qty'] ?? 1));
$subtotal = (float)($body['subtotal'] ?? 0);
$vat = (float)($body['vat'] ?? 0);
$total = (float)($body['total'] ?? 0);
$mode = ($body['mode'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup';
$terms_agreed = !empty($body['terms_agreed']) ? 1 : 0;

// ✅ FIXED: Extract info from nested structure
$rawInfo = (array)($body['info'] ?? []);

// Handle nested structure (delivery/pickup inside info)
if ($mode === 'delivery' && isset($rawInfo['delivery'])) {
    $info = (array)$rawInfo['delivery'];
} elseif ($mode === 'pickup' && isset($rawInfo['pickup'])) {
    $info = (array)$rawInfo['pickup'];
} else {
    // Fallback: try using info directly
    $info = $rawInfo;
}

// ✅ IMPROVED: Validate required fields
$errors = [];

if ($pid <= 0) {
    $errors[] = 'Invalid product ID';
}

if ($total <= 0) {
    $errors[] = 'Invalid order total';
}

if (empty($info['first_name'])) {
    $errors[] = 'First name is required';
}

if (empty($info['last_name'])) {
    $errors[] = 'Last name is required';
}

if (empty($info['phone'])) {
    $errors[] = 'Phone number is required';
}

if ($mode === 'delivery') {
    if (empty($info['province'])) $errors[] = 'Province is required';
    if (empty($info['city'])) $errors[] = 'City is required';
    if (empty($info['barangay'])) $errors[] = 'Barangay is required';
    if (empty($info['street'])) $errors[] = 'Street address is required';
}

if (!empty($errors)) {
    error_log('Order create validation failed: ' . implode(', ', $errors));
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed: ' . implode(', ', $errors),
        'errors' => $errors
    ]);
    exit;
}

// Start database transaction
$pdo = db();
$pdo->beginTransaction();

try {
    // ==========================================
    // STEP 1: GET ACTUAL PRODUCT NAME FROM DATABASE
    // ==========================================
    $prodName = 'Selected Cabinet'; // Default fallback

    if ($pid > 0) {
        $nameStmt = $pdo->prepare("SELECT name FROM products WHERE id = :pid LIMIT 1");
        $nameStmt->execute([':pid' => $pid]);
        $productRow = $nameStmt->fetch(PDO::FETCH_ASSOC);

        if ($productRow && !empty($productRow['name'])) {
            $prodName = $productRow['name'];
        } else {
            // Product not found
            throw new Exception('Product not found');
        }
    }

    // ==========================================
    // STEP 2: CREATE ORDER
    // ==========================================
    $stmt = $pdo->prepare("INSERT INTO orders
        (order_code, customer_id, mode, status, payment_status, subtotal, vat, total_amount, terms_agreed, order_date)
        VALUES (
            CONCAT('RT', DATE_FORMAT(NOW(),'%y%m%d'), LPAD(FLOOR(RAND()*9999), 4, '0')),
            :cid, :mode, 'Pending', 'Pending', :sub, :vat, :tot, :terms, NOW()
        )");

    $stmt->execute([
    ':cid'  => $uid,
    ':mode' => $mode,
    ':sub'  => $subtotal,
    ':vat'  => $vat,
    ':tot'  => $total,
    ':terms' => $terms_agreed
]);

$err = $stmt->errorInfo();
if ($err[0] !== '00000') {
    throw new Exception('SQL Insert Error on orders: ' . implode(' | ', $err));
}

$order_id = (int)$pdo->lastInsertId();
if (!$order_id) {
    throw new Exception('Failed to create order: No insert ID returned');
}


    // Fetch the generated order_code
    $stmt = $pdo->prepare("SELECT order_code FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $order_code = (string)($stmt->fetchColumn() ?: '');

    // ==========================================
    // STEP 3: INSERT ORDER ITEM WITH ACTUAL NAME
    // ==========================================
    $unitPrice = $subtotal / $qty;

    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, name, unit_price, qty, line_total)
                           VALUES (:oid, :pid, :name, :price, :qty, :lt)");

    $stmt->execute([
        ':oid' => $order_id,
        ':pid' => $pid,
        ':name' => $prodName,
        ':price' => $unitPrice,
        ':qty' => $qty,
        ':lt' => $subtotal
    ]);

    // ==========================================
    // STEP 4: SAVE ADDRESS/CONTACT SNAPSHOT
    // ==========================================
    $stmt = $pdo->prepare("INSERT INTO order_addresses
        (order_id, type, first_name, last_name, phone, email, province, city, barangay, street, postal)
        VALUES (:oid, :type, :fn, :ln, :ph, :em, :pv, :ct, :br, :st, :po)");

    $stmt->execute([
        ':oid' => $order_id,
        ':type' => $mode === 'delivery' ? 'shipping' : 'billing',
        ':fn' => $info['first_name'] ?? '',
        ':ln' => $info['last_name'] ?? '',
        ':ph' => $info['phone'] ?? '',
        ':em' => $info['email'] ?? '',
        ':pv' => $info['province'] ?? '',
        ':ct' => $info['city'] ?? '',
        ':br' => $info['barangay'] ?? '',
        ':st' => $info['street'] ?? '',
        ':po' => $info['postal'] ?? ''
    ]);

    $pdo->commit();

    error_log('Order created successfully: #' . $order_id . ' (' . $order_code . ')');

    echo json_encode([
        'success'    => true,
        'message'    => 'Order created successfully!',
        'order_id'   => $order_id,
        'order_code' => $order_code
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Order creation error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create order: ' . $e->getMessage()
    ]);
}