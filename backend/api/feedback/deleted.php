<?php
// backend/api/feedback/delete.php
// ✅ UPDATED: Better delete functionality with soft delete
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    // include config (adjust path if needed)
    $configPaths = [
        __DIR__ . '/../../config/app.php',
        __DIR__ . '/../config/app.php',
        __DIR__ . '/../../backend/config/app.php'
    ];
    foreach ($configPaths as $p) if (file_exists($p)) { require_once $p; break; }

    // get PDO (adjust to your project)
    if (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    } elseif (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } else {
        $db = new PDO("mysql:host=localhost;dbname=rads_tooling;charset=utf8mb4", "root", "");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['feedback_id'])) {
        throw new Exception('Missing feedback_id');
    }
    $fid = (int)$input['feedback_id'];
    if ($fid <= 0) throw new Exception('Invalid feedback_id');

    // identify current user id & role from session
    $currentUserId = null;
    $currentUserRole = 'Customer';
    if (isset($_SESSION['user'])) {
        $u = $_SESSION['user'];
        if (isset($u['id'])) $currentUserId = (int)$u['id'];
        if (isset($u['role'])) $currentUserRole = $u['role'];
        if (isset($u['customer_id'])) $currentUserId = (int)$u['customer_id'];
    } elseif (isset($_SESSION['customer_id'])) {
        $currentUserId = (int)$_SESSION['customer_id'];
    } elseif (isset($_SESSION['staff'])) {
        // Admin user
        $currentUserId = (int)$_SESSION['staff']['id'];
        $currentUserRole = $_SESSION['staff']['role'] ?? 'Admin';
    }

    if (!$currentUserId) {
        throw new Exception('Not authenticated');
    }

    // fetch feedback owner
    $stmt = $db->prepare("SELECT id, customer_id, is_released, deleted FROM feedback WHERE id = ? LIMIT 1");
    $stmt->execute([$fid]);
    $fb = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fb) throw new Exception('Feedback not found');

    $ownerId = (int)$fb['customer_id'];

    // Only admin/owner or the feedback owner may delete
    $isAdmin = in_array(strtolower($currentUserRole), ['admin', 'owner', 'administrator', 'manager', 'secretary']);
    if (!$isAdmin && $ownerId !== $currentUserId) {
        throw new Exception('Unauthorized - You can only delete your own feedback');
    }

    // ✅ Check if 'deleted' column exists
    $colCheck = $db->query("SHOW COLUMNS FROM feedback LIKE 'deleted'")->fetch();
    
    if ($colCheck) {
        // ✅ Soft delete using 'deleted' column
        $q = $db->prepare("UPDATE feedback SET deleted = 1 WHERE id = ?");
        $q->execute([$fid]);
    } else {
        // ✅ Fallback: check for 'is_deleted' column
        $colCheck2 = $db->query("SHOW COLUMNS FROM feedback LIKE 'is_deleted'")->fetch();
        if ($colCheck2) {
            $q = $db->prepare("UPDATE feedback SET is_deleted = 1, deleted_by = ?, deleted_at = NOW() WHERE id = ?");
            $q->execute([$currentUserId, $fid]);
        } else {
            // ✅ Last resort: hard delete (not recommended)
            $q = $db->prepare("DELETE FROM feedback WHERE id = ? LIMIT 1");
            $q->execute([$fid]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}