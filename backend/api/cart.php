<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/database.php';

// Check if user is logged in
if (empty($_SESSION['user']) || (($_SESSION['user']['aud'] ?? '') !== 'customer')) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Login required'
    ]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$customer_id = (int) $_SESSION['user']['id'];

// GET - Fetch saved cart
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $conn->prepare("
            SELECT cart_data
            FROM customer_carts
            WHERE customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        $result = $stmt->fetch();

        if ($result && $result['cart_data']) {
            $cart_data = json_decode($result['cart_data'], true);
            echo json_encode([
                'success' => true,
                'cart' => $cart_data ?? []
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'cart' => []
            ]);
        }
    } catch (PDOException $e) {
        error_log("Cart fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load cart']);
    }
    exit;
}

// POST - Save cart
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cart = $input['cart'] ?? [];

    try {
        // Upsert cart data
        $stmt = $conn->prepare("
            INSERT INTO customer_carts (customer_id, cart_data, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            cart_data = VALUES(cart_data),
            updated_at = NOW()
        ");
        $stmt->execute([$customer_id, json_encode($cart)]);

        echo json_encode([
            'success' => true,
            'message' => 'Cart saved successfully'
        ]);
    } catch (PDOException $e) {
        error_log("Cart save error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save cart']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
