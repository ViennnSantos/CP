<?php
// backend/api/feedback/release.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Prevent HTML before JSON
ob_start();

try {
    // Check authentication
    $isAdmin = false;
    
    if (isset($_SESSION['staff']) && !empty($_SESSION['staff'])) {
        $isAdmin = true;
    } elseif (isset($_SESSION['admin']) && !empty($_SESSION['admin'])) {
        $isAdmin = true;
    } elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $isAdmin = true;
    } elseif (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && 
              in_array($_SESSION['user']['role'], ['Owner', 'Admin', 'Secretary'])) {
        $isAdmin = true;
    }

    if (!$isAdmin) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Load config
    $configPaths = [
        __DIR__ . '/../../config/app.php',
        __DIR__ . '/../../../backend/config/app.php',
        __DIR__ . '/../../config/database.php'
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

    // Get database
    $db = null;
    
    if (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    } elseif (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } else {
        $db = new PDO("mysql:host=localhost;dbname=rads_tooling;charset=utf8mb4", "root", "");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Get input - try JSON first, then POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $action = strtolower(trim($input['action'] ?? ''));
    $feedbackId = (int)($input['feedback_id'] ?? 0);

    if (!in_array($action, ['release', 'hide']) || $feedbackId <= 0) {
        throw new Exception('Invalid parameters');
    }

    // Check if feedback exists
    $stmt = $db->prepare("SELECT id, is_released FROM feedback WHERE id = ? LIMIT 1");
    $stmt->execute([$feedbackId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feedback) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Feedback not found']);
        exit;
    }

    // Update release status
    $newValue = ($action === 'release') ? 1 : 0;
    $updateSql = "
        UPDATE feedback 
        SET 
            is_released = ?,
            released_at = " . ($newValue ? "NOW()" : "NULL") . ",
            status = ?
        WHERE id = ?
    ";
    
    $newStatus = ($newValue === 1) ? 'released' : 'pending';
    $stmt = $db->prepare($updateSql);
    $stmt->execute([$newValue, $newStatus, $feedbackId]);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => ($action === 'release' ? 'Feedback released to public' : 'Feedback hidden from public')
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}