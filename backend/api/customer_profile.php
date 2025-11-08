<?php
// backend/api/customer_profile.php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

// Para hindi sumingit ang notices/warnings sa JSON:
ini_set('display_errors', '0');

// ✅ CORRECT PATHS (important!)
require_once dirname(__DIR__, 2) . '/includes/phone_util.php';
require_once dirname(__DIR__, 2) . '/includes/guard.php';   // from backend/api -> up 2 = project/, then /includes/guard.php
require_once dirname(__DIR__)     . '/config/database.php'; // from backend/api -> up 1 = backend/, then /config/database.php

// JSON auth guard (wag mag-HTML redirect)
if (empty($_SESSION['user']) || (($_SESSION['user']['aud'] ?? '') !== 'customer')) {
    http_response_code(401);
    echo json_encode([
        'success'  => false,
        'code'     => 'AUTH',
        'message'  => 'Login required',
        'redirect' => '/customer/login.php'
    ]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$customer_id = (int) $_SESSION['user']['id'];



// GET - Fetch profile data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare("
            SELECT id, username, full_name, email, phone, address, profile_image, created_at
            FROM customers 
            WHERE id = ?
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
            exit;
        }

        // Get order statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_orders
            FROM orders 
            WHERE customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        $order_stats = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'stats' => $order_stats
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Fetch customer error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load profile']);
    }
    exit;
}

// POST - Update profile information
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate CSRF token
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }

    $full_name = trim($input['full_name'] ?? '');
    $phone     = trim($input['phone'] ?? '');   // can be "+63xxxxxxxxxx" or "09xxxxxxxxx" or "9123456789"
    $address   = trim($input['address'] ?? '');

    // --- Basic name checks ---
    if ($full_name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Full name is required']);
        exit;
    }
    if (mb_strlen($full_name) < 3) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Full name must be at least 3 characters']);
        exit;
    }

    // --- Phone normalize & uniqueness ---
    // Allow empty to keep/clear, else enforce PH mobile and unique
    $phoneToSave = null;
    if ($phone !== '') {
        try {
            // from includes/phone_util.php → ensures "+63" + 10 digits starting with 9
            $normalizedPhone = normalize_ph_phone($phone);  // returns "+63xxxxxxxxxx"
        } catch (RuntimeException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Enter a valid PH mobile (+63 + 10 digits).']);
            exit;
        }

        // unique except self
        $stmt = $conn->prepare('SELECT 1 FROM customers WHERE phone = ? AND id <> ? LIMIT 1');
        $stmt->execute([$normalizedPhone, $customer_id]);
        if ($stmt->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Mobile number is already taken']);
            exit;
        }

        $phoneToSave = $normalizedPhone;
    }

    try {
        $stmt = $conn->prepare("
            UPDATE customers 
            SET full_name = ?, phone = ?, address = ?, updated_at = NOW()
            WHERE id = ?
        ");
        // Note: if phone is empty string, we save NULL (allowed only if column permits NULL)
        $stmt->execute([$full_name, $phoneToSave, $address, $customer_id]);

        // Update session display name
        $_SESSION['user']['full_name'] = $full_name;

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'full_name' => $full_name,
                'phone'     => $phoneToSave,  // returns normalized "+63xxxxxxxxxx" or null
                'address'   => $address
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
