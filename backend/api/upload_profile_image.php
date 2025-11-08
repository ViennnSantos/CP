<?php
// /backend/api/upload_profile_image.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

// ✅ CORRECT PATHS (nasa backend/api ka ngayon)
require_once dirname(__DIR__, 2) . '/includes/guard.php';   // project_root/includes/guard.php
require_once dirname(__DIR__)     . '/config/database.php'; // backend/config/database.php  <-- ito ang tama

// Auth
if (empty($_SESSION['user']) || (($_SESSION['user']['aud'] ?? '') !== 'customer')) {
  http_response_code(401);
  echo json_encode(['success' => false, 'code' => 'AUTH', 'message' => 'Login required', 'redirect' => '/customer/login.php']);
  exit;
}

$db = new Database();
$conn = $db->getConnection();
$customer_id = (int)$_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

// CSRF
if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Invalid security token']);
  exit;
}

// File
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'No file uploaded']);
  exit;
}

$max_size = 5 * 1024 * 1024; // 5MB
$tmp = $_FILES['profile_image']['tmp_name'];
$mime = @mime_content_type($tmp) ?: ($_FILES['profile_image']['type'] ?? '');
$allowed = ['image/jpeg', 'image/png', 'image/jpg'];

if (!in_array($mime, $allowed, true)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, and PNG files are allowed']);
  exit;
}
if (filesize($tmp) > $max_size) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
  exit;
}

// Paths (gumagamit ka ng assets/uploads/avatars)
$upload_dir_abs = dirname(__DIR__, 2) . '/assets/uploads/avatars/';
@mkdir($upload_dir_abs, 0755, true);

$ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
$filename = 'customer_' . $customer_id . '_' . time() . '.' . $ext;
$abs_path = $upload_dir_abs . $filename;
$rel_path = 'assets/uploads/avatars/' . $filename;

if (!move_uploaded_file($tmp, $abs_path)) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
  exit;
}

try {
  // delete old
  $stmt = $conn->prepare("SELECT profile_image FROM customers WHERE id=?");
  $stmt->execute([$customer_id]);
  $old = $stmt->fetchColumn();
  if ($old && is_file(dirname(__DIR__, 2) . '/' . $old)) {
    @unlink(dirname(__DIR__, 2) . '/' . $old);
  }

  // update DB
  $stmt = $conn->prepare("UPDATE customers SET profile_image=? WHERE id=?");
  $stmt->execute([$rel_path, $customer_id]);

  // update session
  $_SESSION['user']['profile_image'] = $rel_path;

  echo json_encode([
    'success' => true,
    'message' => 'Profile picture updated successfully',
    'data' => ['profile_image' => $rel_path]
  ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  error_log('Profile image update error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to update profile picture']);
}
