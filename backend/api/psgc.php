<?php
// /RADS-TOOLING/api/psgc.php
// PSGC proxy with UTF-8 encoding support, caching, and data supplement

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Load supplement data
require_once __DIR__ . '/psgc_supplement.php';

$path = $_GET['endpoint'] ?? '';
$path = ltrim($path, '/'); // sanitize
$qs   = $_SERVER['QUERY_STRING'] ?? '';

$base = 'https://psgc.cloud/api/';
$url  = $base . $path;

// keep other query params (except "endpoint")
if ($qs) {
  parse_str($qs, $q);
  unset($q['endpoint']);
  if (!empty($q)) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($q);
  }
}

// Simple file-based cache (30 days)
$cache_dir = __DIR__ . '/../cache/psgc';
$cache_key = md5($url);
$cache_file = $cache_dir . '/' . $cache_key . '.json';
$cache_duration = 30 * 24 * 60 * 60; // 30 days

// Check cache
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_duration)) {
    $body = file_get_contents($cache_file);
    http_response_code(200);
    echo $body;
    exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_ENCODING       => 'UTF-8',           // Request UTF-8 encoding
  CURLOPT_HTTPHEADER     => [
      'Accept: application/json',
      'Accept-Charset: utf-8'
  ],
]);

$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Log errors for debugging
if ($error || !$body) {
    error_log("PSGC API Error: $error - URL: $url");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch PSGC data', 'details' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ensure proper UTF-8 encoding
$body = mb_convert_encoding($body, 'UTF-8', 'auto');

// Validate JSON
$test_decode = json_decode($body);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("PSGC JSON Error: " . json_last_error_msg() . " - URL: $url");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid JSON from PSGC API'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Decode JSON for supplemental data merging
$data = json_decode($body, true);

// Check if we have supplement data for this endpoint
$supplement = get_supplement($path);

// Merge with supplement if available
if ($supplement && is_array($data)) {
    $data = merge_psgc_data($data, $supplement);
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Cache the merged response
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}
file_put_contents($cache_file, $body);

http_response_code($code ?: 200);
echo $body;

