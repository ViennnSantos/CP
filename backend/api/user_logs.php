<?php
// backend/api/user_logs.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';   // adjust path if your config is elsewhere

// AUTH CHECK: admin only
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}
$role = $_SESSION['admin_role'] ?? '';
if (!in_array($role, ['owner', 'admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

// params
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;
$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $where = "1=1";
    $params = [];

    if ($action !== '') {
        $where .= " AND action = ?";
        $params[] = $action;
    }
    if ($q !== '') {
        $where .= " AND (details LIKE ? OR user_type LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }

    // total
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user_logs WHERE $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // fetch logs
    $stmt = $pdo->prepare("SELECT id, user_type, user_id, action, details, created_at
                            FROM user_logs
                            WHERE $where
                            ORDER BY created_at DESC
                            LIMIT ? OFFSET ?");
    // execute with params + pagination
    $execParams = $params;
    $execParams[] = (int)$perPage;
    $execParams[] = (int)$offset;
    $stmt->execute($execParams);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'logs' => $logs,
        'total' => $total,
        'per_page' => $perPage,
        'page' => $page
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'DB error']);
}
