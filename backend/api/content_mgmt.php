<?php

while (ob_get_level()) {
  ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../../includes/guard.php';

// Get action early
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Only require staff authentication for non-public actions
$publicActions = ['get_payment_qr'];
if (!in_array($action, $publicActions)) {
    guard_require_staff();
}

/** @var \PDO|null $pdo */
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
  error_log("FATAL: missing/invalid \$pdo in content_mgmt.php");
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Database connection not available. Check config/app.php'
  ]);
  exit;
}


// CRITICAL: Stop ALL output before JSON
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any output buffer
if (ob_get_level()) ob_end_clean();

try {
  if ($action === 'get') {
    getContent();
  } elseif ($action === 'save') {
    saveContent();
  } elseif ($action === 'publish') {
    publishContent();
  } elseif ($action === 'discard') {
    discardDraft();
  } elseif ($action === 'upload_image') {
    uploadImage();
  } elseif ($action === 'get_payment_qr') {
    getPaymentQR();
  } elseif ($action === 'update_payment_qr') {
    updatePaymentQR();
  } else {
    throw new Exception('Invalid action');
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
  exit;
}

function getContent()
{
  global $pdo;

  $page = $_GET['page'] ?? 'home_public';
  $status = $_GET['status'] ?? 'draft';

  $validPages = ['home_public', 'home_customer', 'about', 'privacy', 'terms'];
  if (!in_array($page, $validPages)) {
    throw new Exception('Invalid page');
  }

  // Try to get requested status (draft or published)
  $stmt = $pdo->prepare("
        SELECT content_data, updated_at, updated_by, status
        FROM rt_cms_pages 
        WHERE page_key = ? AND status = ?
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
  $stmt->execute([$page, $status]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);

  // If no draft, fall back to published
  if (!$result && $status === 'draft') {
    $stmt->execute([$page, 'published']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
  }

  if ($result) {
    $content = json_decode($result['content_data'], true);
    echo json_encode([
      'success' => true,
      'content' => $content,
      'status' => $result['status'],
      'updated_at' => $result['updated_at'],
      'updated_by' => $result['updated_by']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  } else {
    // Return default content
    echo json_encode([
      'success' => true,
      'content' => getDefaultContent($page),
      'status' => 'published',
      'updated_at' => null,
      'updated_by' => 'System'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
}

function saveContent()
{
  global $pdo;

  $page = $_POST['page'] ?? '';
  $content = $_POST['content'] ?? '';

  $validPages = ['home_public', 'home_customer', 'about', 'privacy', 'terms'];
  if (!in_array($page, $validPages)) {
    throw new Exception('Invalid page');
  }

  $contentData = json_decode($content, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Invalid JSON content: ' . json_last_error_msg());
  }

  $staffName = $_SESSION['staff']['full_name'] ?? 'Admin';

  $pageNames = [
    'home_public' => 'Public Homepage',
    'home_customer' => 'Customer Homepage',
    'about' => 'About Us',
    'privacy' => 'Privacy Policy',
    'terms' => 'Terms & Conditions'
  ];

  $newContentJson = json_encode($contentData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  // Check if content actually changed
  $stmt = $pdo->prepare("
        SELECT content_data 
        FROM rt_cms_pages 
        WHERE page_key = ? AND status = 'draft' 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
  $stmt->execute([$page]);
  $existingDraft = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($existingDraft && $existingDraft['content_data'] === $newContentJson) {
    echo json_encode([
      'success' => true,
      'message' => 'No changes detected',
      'unchanged' => true
    ]);
    return;
  }

  // Start transaction
  $pdo->beginTransaction();

  try {
    // Delete ALL old drafts for this page
    $stmt = $pdo->prepare("DELETE FROM rt_cms_pages WHERE page_key = ? AND status = 'draft'");
    $stmt->execute([$page]);

    // Get next version
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(version), 0) + 1 as next_version FROM rt_cms_pages WHERE page_key = ?");
    $stmt->execute([$page]);
    $nextVersion = $stmt->fetch(PDO::FETCH_ASSOC)['next_version'];

    // Insert new draft with current timestamp
    $stmt = $pdo->prepare("
            INSERT INTO rt_cms_pages (page_key, page_name, content_data, status, version, updated_by, updated_at)
            VALUES (?, ?, ?, 'draft', ?, ?, NOW())
        ");
    $stmt->execute([$page, $pageNames[$page], $newContentJson, $nextVersion, $staffName]);

    // CRITICAL: Commit immediately
    $pdo->commit();

    // Wait for database to finish writing
    usleep(150000); // 150ms

    echo json_encode([
      'success' => true,
      'message' => 'Draft saved successfully',
      'timestamp' => time()
    ]);
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function publishContent()
{
  global $pdo;

  $page = $_POST['page'] ?? '';

  $validPages = ['home_public', 'home_customer', 'about', 'privacy', 'terms'];
  if (!in_array($page, $validPages)) {
    throw new Exception('Invalid page');
  }

  $pdo->beginTransaction();

  // Get latest draft
  $stmt = $pdo->prepare("SELECT * FROM rt_cms_pages WHERE page_key = ? AND status = 'draft' ORDER BY updated_at DESC LIMIT 1");
  $stmt->execute([$page]);
  $draft = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$draft) {
    throw new Exception('No draft to publish');
  }

  // Delete old published version
  $stmt = $pdo->prepare("DELETE FROM rt_cms_pages WHERE page_key = ? AND status = 'published'");
  $stmt->execute([$page]);

  // Insert draft as published
  $stmt = $pdo->prepare("
  INSERT INTO rt_cms_pages (page_key, page_name, content_data, status, version, updated_by, updated_at)
  VALUES (?, ?, ?, 'published', ?, ?, NOW())
");
  $stmt->execute([$draft['page_key'], $draft['page_name'], $draft['content_data'], $draft['version'], $draft['updated_by']]);


  $pdo->commit();

  echo json_encode([
    'success' => true,
    'message' => 'Content published successfully'
  ]);
}

function discardDraft()
{
  global $pdo;

  $page = $_POST['page'] ?? '';

  $validPages = ['home_public', 'home_customer', 'about', 'privacy', 'terms'];
  if (!in_array($page, $validPages)) {
    throw new Exception('Invalid page');
  }

  $stmt = $pdo->prepare("DELETE FROM rt_cms_pages WHERE page_key = ? AND status = 'draft'");
  $stmt->execute([$page]);

  echo json_encode([
    'success' => true,
    'message' => 'Draft discarded successfully'
  ]);
}

function getDefaultContent($page)
{
  $defaults = [
    'home_public' => [
      'hero_headline' => '<h1>Welcome to RADS Tooling</h1>',
      'hero_subtitle' => '<p>Your trusted partner in custom cabinets</p>',
      'carousel_images' => [],
      'footer_email' => 'RadsTooling@gmail.com',
      'footer_phone' => '+63 976 228 4270'
    ],
    'home_customer' => [
      'welcome_message' => '<h1>Welcome back, {{customer_name}}!</h1>',
      'intro_text' => '<p>Explore our latest designs</p>'
    ],
    'about' => [
      'content' => '<h1>About Us</h1><p>Content goes here...</p>'
    ],
    'privacy' => [
      'content' => '<h1>Privacy Policy</h1><p>Content goes here...</p>'
    ],
    'terms' => [
      'content' => '<h1>Terms & Conditions</h1><p>Content goes here...</p>'
    ]
  ];

  return $defaults[$page] ?? [];
}

function getPaymentQR()
{
  global $pdo;

  try {
    $stmt = $pdo->prepare("SELECT id, method, image_path, is_active FROM payment_qr WHERE is_active = 1 ORDER BY method");
    $stmt->execute();
    $qrCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [
      'gcash' => null,
      'bpi' => null
    ];

    foreach ($qrCodes as $qr) {
      $data[$qr['method']] = $qr;
    }

    echo json_encode([
      'success' => true,
      'data' => $data
    ]);
  } catch (Exception $e) {
    error_log('getPaymentQR Error: ' . $e->getMessage());
    throw $e;
  }
}

function updatePaymentQR()
{
  global $pdo;

  try {
    $method = $_POST['method'] ?? '';
    
    if (!in_array($method, ['gcash', 'bpi'])) {
      throw new Exception('Invalid payment method');
    }

    if (!isset($_FILES['qr_image'])) {
      throw new Exception('No QR image uploaded');
    }

    $file = $_FILES['qr_image'];

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
      throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
      throw new Exception('File too large. Maximum size is 5MB.');
    }

    // Create uploads/qrs directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/qrs';
    if (!file_exists($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $method . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
      throw new Exception('Failed to save uploaded file');
    }

    // Update or insert into database
    $relativePath = 'uploads/qrs/' . $filename;
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id, image_path FROM payment_qr WHERE method = ?");
    $stmt->execute([$method]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
      // Delete old image file
      $oldImagePath = __DIR__ . '/../../' . $existing['image_path'];
      if (file_exists($oldImagePath)) {
        unlink($oldImagePath);
      }

      // Update record
      $stmt = $pdo->prepare("UPDATE payment_qr SET image_path = ?, is_active = 1 WHERE method = ?");
      $stmt->execute([$relativePath, $method]);
    } else {
      // Insert new record
      $stmt = $pdo->prepare("INSERT INTO payment_qr (method, image_path, is_active) VALUES (?, ?, 1)");
      $stmt->execute([$method, $relativePath]);
    }

    echo json_encode([
      'success' => true,
      'message' => ucfirst($method) . ' QR code updated successfully',
      'data' => [
        'method' => $method,
        'image_path' => $relativePath,
        'filename' => $filename
      ]
    ]);
  } catch (Exception $e) {
    error_log('updatePaymentQR Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => $e->getMessage()
    ]);
  }
}

function uploadImage()
{
  try {
    if (!isset($_FILES['image'])) {
      throw new Exception('No file uploaded - $_FILES is empty');
    }

    $file = $_FILES['image'];
    $group = $_POST['group'] ?? 'general';

    // Debug logging
    error_log('Upload attempt - File: ' . ($file['name'] ?? 'unknown'));
    error_log('Upload attempt - Size: ' . ($file['size'] ?? 0));
    error_log('Upload attempt - Error: ' . ($file['error'] ?? 'none'));

    // Load upload helper
    $uploadHelperPath = __DIR__ . '/../helpers/upload.php';

    if (!file_exists($uploadHelperPath)) {
      throw new Exception('Upload helper not found');
    }

    require_once $uploadHelperPath;

    // CHANGED: Call the renamed function
    $result = processFileUpload($file, $group);

    // Log result
    error_log('Upload result: ' . json_encode($result));

    echo json_encode($result);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => $e->getMessage(),
      'file' => __FILE__,
      'line' => __LINE__
    ]);  
  }
}