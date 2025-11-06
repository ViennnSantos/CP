<?php
// backend/api/mark_received.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Prevent HTML output before JSON
ob_start();

try {
    // Try to include config
    $configPaths = [
        __DIR__ . '/../config/app.php',
        __DIR__ . '/../config/database.php',
        __DIR__ . '/../../backend/config/app.php'
    ];

    $configLoaded = false;
    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $configLoaded = true;
            break;
        }
    }

    if (!$configLoaded) {
        throw new Exception('Configuration file not found');
    }

    // Get database connection
    $db = null;
    
    if (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    } elseif (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } else {
        // Fallback connection
        $db = new PDO("mysql:host=localhost;dbname=rads_tooling;charset=utf8mb4", "root", "");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Read input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['order_id'])) {
        throw new Exception('Missing order_id');
    }

    $orderId = (int)$input['order_id'];
    $rating = isset($input['rating']) ? (int)$input['rating'] : 0;
    $comment = isset($input['comment']) ? trim($input['comment']) : '';

    // Validate rating
    if ($rating < 0) $rating = 0;
    if ($rating > 5) $rating = 5;

    // Find customer ID from session
    $customerId = null;
    $sessionKeys = ['customer_id', 'user_id', 'id', 'cust_id'];
    
    foreach ($sessionKeys as $key) {
        if (isset($_SESSION['user'][$key]) && intval($_SESSION['user'][$key]) > 0) {
            $customerId = intval($_SESSION['user'][$key]);
            break;
        } elseif (isset($_SESSION[$key]) && intval($_SESSION[$key]) > 0) {
            $customerId = intval($_SESSION[$key]);
            break;
        }
    }

    if (!$customerId) {
        throw new Exception('Not authenticated');
    }

    // Start transaction
    $db->beginTransaction();

    // Verify order exists and belongs to customer
    $stmt = $db->prepare("
        SELECT id, customer_id, is_received, received_by_customer 
        FROM orders 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $db->rollBack();
        throw new Exception('Order not found');
    }

    if ((int)$order['customer_id'] !== $customerId) {
        $db->rollBack();
        throw new Exception('Unauthorized - Order does not belong to you');
    }

    // Check if already received
    if ((int)$order['is_received'] === 1 || (int)$order['received_by_customer'] === 1) {
        $db->commit();
        ob_end_clean();
        echo json_encode([
            'status' => 'ok',
            'success' => true,
            'message' => 'Order already marked as received'
        ]);
        exit;
    }

    // Update order
    $updateSql = "
        UPDATE orders 
        SET 
            is_received = 1,
            received_by_customer = 1,
            received_at = NOW(),
            customer_received_at = NOW(),
            status = 'Completed'
        WHERE id = ?
    ";
    $stmt = $db->prepare($updateSql);
    $stmt->execute([$orderId]);

    // ✅ FIXED: Insert feedback using INSERT ... ON DUPLICATE KEY UPDATE
    // This prevents duplicate entries
    if ($rating > 0 || !empty($comment)) {
        try {
            $insertFeedback = $db->prepare("
                INSERT INTO feedback 
                (order_id, customer_id, rating, comment, status, is_released, released_at, created_at)
                VALUES (?, ?, ?, ?, 'released', 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    comment = VALUES(comment),
                    status = 'released',
                    is_released = 1,
                    released_at = NOW(),
                    created_at = NOW()
            ");
            $insertFeedback->execute([$orderId, $customerId, $rating, $comment]);
            
        } catch (PDOException $ex) {
            // Log error but don't break the order flow
            if (function_exists('error_log')) {
                error_log('Feedback insert/update error: ' . $ex->getMessage());
            }
            // Continue - order marked as received is more important than feedback
        }
    }

    // Insert into order_status_history if table exists
    try {
        $historyStmt = $db->prepare("
            INSERT INTO order_status_history (order_id, status, changed_by, notes, changed_at)
            VALUES (?, 'Completed', ?, 'Marked as received by customer', NOW())
        ");
        $historyStmt->execute([$orderId, $customerId]);
    } catch (PDOException $e) {
        // Table might not exist, that's okay
    }

    $db->commit();

    ob_end_clean();
    echo json_encode([
        'status' => 'ok',
        'success' => true,
        'message' => 'Order marked as received successfully'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => $e->getMessage()
    ]);
}