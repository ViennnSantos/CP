<?php
// ==========================================
// COMPLETE ORDER API
// ==========================================
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once dirname(__DIR__, 2) . '/includes/guard.php';
require_once dirname(__DIR__) . '/config/database.php';

// Authentication check
if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customerId = (int)$_SESSION['user']['id'];

$db = new Database();
$conn = $db->getConnection();

try {
    // Validate inputs
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $completionType = strtolower(trim($_POST['completion_type'] ?? ''));
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$orderId || !in_array($completionType, ['pickup', 'delivery'])) {
        throw new Exception('Invalid request');
    }
    
    // Verify order belongs to customer
    $stmt = $conn->prepare("
        SELECT id, status, payment_status, mode
        FROM orders
        WHERE id = :order_id AND customer_id = :customer_id
    ");
    $stmt->execute([
        ':order_id' => $orderId,
        ':customer_id' => $customerId
    ]);
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Check if order can be completed
    $completableStatuses = ['Delivered', 'Ready for Pickup'];
    if (!in_array($order['status'], $completableStatuses)) {
        throw new Exception('Order is not ready to be completed');
    }
    
    if ($order['payment_status'] !== 'Fully Paid') {
        throw new Exception('Order must be fully paid before completion');
    }
    
    // Handle file upload
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__, 2) . '/uploads/completions/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Invalid file type');
        }
        
        if ($_FILES['photo']['size'] > 10 * 1024 * 1024) { // 10MB
            throw new Exception('File too large. Maximum 10MB');
        }
        
        $fileName = 'completion_' . $orderId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to upload file');
        }
        
        $photoPath = 'uploads/completions/' . $fileName;
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Insert completion record
    $stmt = $conn->prepare("
        INSERT INTO order_completions
        (order_id, completed_by, completion_type, photo_path, notes)
        VALUES
        (:order_id, 'customer', :type, :photo, :notes)
    ");
    
    $stmt->execute([
        ':order_id' => $orderId,
        ':type' => $completionType,
        ':photo' => $photoPath,
        ':notes' => $notes
    ]);
    
    // Update order status to Completed
    $stmt = $conn->prepare("
        UPDATE orders
        SET status = 'Completed'
        WHERE id = :order_id
    ");
    $stmt->execute([':order_id' => $orderId]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order completed successfully!'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Complete order error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>