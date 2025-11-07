<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function db(): PDO {
  static $cached = null;
  if ($cached instanceof PDO) return $cached;

  // reuse global $pdo if your config sets it
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    return $cached = $GLOBALS['pdo'];
  }

  // use your Database class (no edits to class)
  if (class_exists('Database')) {
    $db = new Database();
    $pdo = $db->getConnection();
    $GLOBALS['pdo'] = $pdo;
    return $cached = $pdo;
  }

  throw new RuntimeException('Database connection (PDO) not available.');
}

function ok($data = [], int $status = 200) {
  http_response_code($status);
  echo json_encode(['success'=>true, 'data'=>$data], JSON_UNESCAPED_SLASHES);
  exit;
}

function fail(string $msg, int $status = 400) {
  http_response_code($status);
  echo json_encode(['success'=>false, 'message'=>$msg], JSON_UNESCAPED_SLASHES);
  exit;
}

function require_customer_id(): int {
  $u = $_SESSION['user'] ?? null;
  if (!$u || (($u['aud'] ?? '') !== 'customer')) fail('Unauthorized', 401);
  return (int)($u['id'] ?? 0);
}
