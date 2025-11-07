<?php
// /RADS-TOOLING/backend/api/payment_qr.php
// ✅ FIXED VERSION - Returns QR code URL from database with proper data wrapper

declare(strict_types=1);

// Include bootstrap file for helper functions and database connection
require_once __DIR__ . '/_bootstrap.php';

// Verify customer is logged in
require_customer_id();

// Get payment method from URL parameter
$method = strtolower($_GET['method'] ?? '');

// Validate method - only gcash or bpi allowed
if (!in_array($method, ['gcash', 'bpi'], true)) {
    fail('Invalid method.');
}

// Get database connection
$pdo = db();

// Query database for active QR code for this method
$stmt = $pdo->prepare("SELECT image_path FROM payment_qr WHERE method=:m AND is_active=1 LIMIT 1");
$stmt->execute([':m' => $method]);
$row = $stmt->fetch();

// Build QR code URL
$qr = null;
if ($row && !empty($row['image_path'])) {
    // Add /RADS-TOOLING/ prefix and ensure no double slashes
    $qr = '/RADS-TOOLING/' . ltrim($row['image_path'], '/');
}

// ✅ FIXED: Return with 'data' wrapper to match checkout.js expectation
// checkout.js expects: result.data.qr_url
// OLD format was: result.qr_url (WRONG!)
// NEW format is: result.data.qr_url (CORRECT!)
ok(['data' => ['qr_url' => $qr]]);