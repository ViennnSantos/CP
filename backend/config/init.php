<?php

declare(strict_types=1);

// ---- Session hardening ----
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');                   // 'Strict' if you never do cross-site
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');

session_name('rads_sid');
if (session_status() === PHP_SESSION_NONE) {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}

// CSRF: issue token once per session
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_header_ok(): bool
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;    // only enforce on POST/PUT/DELETE if you want
  $h = $_SERVER['HTTP_X_CSRF'] ?? '';
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $h);
}

// Simple JSON responder
function json_out($data, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ---- DB helper (PDO) ----
function db(): PDO
{
  static $pdo = null;
  if ($pdo) return $pdo;
  $pdo = new PDO(
    'mysql:host=localhost;dbname=your_db;charset=utf8mb4',
    'root',
    '',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
  return $pdo;
}

// ---- Minimal security headers (same‑origin app) ----
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
// Tip: move CSP to Apache/Nginx in production