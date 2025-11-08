<?php
// Prevent ALL caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once __DIR__ . '/../config/app.php';

// Manual session start
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Verify staff access
if (!isset($_SESSION['staff']) && !isset($_SESSION['admin_logged_in'])) {
  http_response_code(403);
  die('<h1>Access Denied</h1><p>Staff login required to preview content</p>');
}

$page = $_GET['page'] ?? 'home_public';

/* Fetch CMS content - ALWAYS prefer draft over published */
$fetch = function (string $key) use ($pdo) {
  // Force fresh connection
  $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

  // Get the LATEST draft (most recent updated_at)
  $stmt = $pdo->prepare("
        SELECT content_data, status, updated_at
        FROM rt_cms_pages 
        WHERE page_key = ? AND status = 'draft'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
  $stmt->execute([$key]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $data = json_decode($row['content_data'] ?? '{}', true);
    return is_array($data) ? $data : [];
  }

  // Fallback to published only if no draft exists
  $stmt = $pdo->prepare("
        SELECT content_data, status, updated_at
        FROM rt_cms_pages 
        WHERE page_key = ? AND status = 'published'
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
  $stmt->execute([$key]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  $data = $row ? json_decode($row['content_data'] ?? '{}', true) : [];
  return is_array($data) ? $data : [];
};

$content = $fetch($page);

/* Special handling for customer homepage - Include the actual file */
if ($page === 'home_customer') {
  // Set the draft content globally so homepage.php can use it
  $GLOBALS['cms_preview_content'] = $content;

  // Include the actual customer homepage file
  $filePath = __DIR__ . '/../../customer/homepage.php';

  if (!file_exists($filePath)) {
    http_response_code(404);
    die('<h1>Preview Error</h1><p>Customer homepage file not found</p>');
  }

  // Capture the output
  ob_start();
  include $filePath;
  $output = ob_get_clean();

  /* Disable interactive elements */
  $output = preg_replace('/<a\s+([^>]*)href="([^"]*)"/', '<a $1href="javascript:void(0)" style="pointer-events:none !important; cursor:not-allowed !important; opacity:0.7 !important;"', $output);
  $output = preg_replace('/<button([^>]*)>/', '<button$1 disabled style="pointer-events:none !important; cursor:not-allowed !important; opacity:0.7 !important;">', $output);
  $output = preg_replace('/<form([^>]*)>/', '<form$1 onsubmit="return false;">', $output);

  // Hide navigation for preview
  $hideChrome = '<style>
      header.navbar, .navbar, .navbar-menu, nav { display:none !important; }
      .page-wrapper { padding-top:0 !important; }
    </style>';

  if (strpos($output, '</head>') !== false) {
    $output = str_replace('</head>', $hideChrome . '</head>', $output);
  }

  // Add preview watermark
  $watermark = '<div id="cms-preview-watermark" style="position:fixed;top:10px;right:10px;background:rgba(255,193,7,0.95);color:#000;padding:10px 20px;border-radius:6px;font-weight:bold;z-index:999999;font-size:14px;pointer-events:none;">PREVIEW MODE</div>';

  $output = str_replace('</body>', $watermark . '</body>', $output);

  echo $output;
  exit;
}

/* All other pages */
$pageFiles = [
  'home_public' => '/index.php',
  'about'       => '/public/about.php',
  'privacy'     => '/public/privacy.php',
  'terms'       => '/public/terms.php',
];

$filePath = __DIR__ . '/../..' . ($pageFiles[$page] ?? '/index.php');

if (!file_exists($filePath)) {
  http_response_code(404);
  die('<h1>Preview Error</h1><p>Page file not found</p>');
}

// Make CMS content available
$GLOBALS['cms_preview_content'] = $content;

// Capture output
ob_start();
include $filePath;
$output = ob_get_clean();

// Add cache prevention headers to HTML
$cacheMeta = '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">';

if (preg_match('/<head[^>]*>/i', $output)) {
  $output = preg_replace('/(<head[^>]*>)/i', '$1' . $cacheMeta, $output, 1);
}

/* Disable interactive elements */
$output = preg_replace('/<a\s+([^>]*)href="([^"]*)"/', '<a $1href="javascript:void(0)" style="pointer-events:none !important; cursor:not-allowed !important; opacity:0.7 !important;"', $output);
$output = preg_replace('/<button([^>]*)>/', '<button$1 disabled style="pointer-events:none !important; cursor:not-allowed !important; opacity:0.7 !important;">', $output);
$output = preg_replace('/<form([^>]*)>/', '<form$1 onsubmit="return false;">', $output);

// Hide navigation
$hideChrome = '<style>
  header.navbar, .navbar, .navbar-menu { display:none !important; }
  .page-wrapper { padding-top:0 !important; }
</style>';

if (strpos($output, '</head>') !== false) {
  $output = str_replace('</head>', $hideChrome . '</head>', $output);
}

// Add watermark
$watermark = '<div id="cms-preview-watermark" style="position:fixed;top:10px;right:10px;background:rgba(255,193,7,0.95);color:#000;padding:10px 20px;border-radius:6px;font-weight:bold;z-index:999999;font-size:14px;pointer-events:none;">PREVIEW MODE</div>';

$output = str_replace('</body>', $watermark . '</body>', $output);

echo $output;
