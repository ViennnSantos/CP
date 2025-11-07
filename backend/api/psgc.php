<?php
// /RADS-TOOLING/api/psgc.php
// Simple PSGC proxy to avoid CORS and keep URLs consistent.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$path = $_GET['path'] ?? '';
$path = ltrim($path, '/'); // sanitize
$qs   = $_SERVER['QUERY_STRING'] ?? '';

$base = 'https://psgc.cloud/api/';
$url  = $base . $path;

// keep other query params (except "path")
if ($qs) {
  parse_str($qs, $q);
  unset($q['path']);
  if (!empty($q)) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($q);
  }
}

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_TIMEOUT        => 20,
  CURLOPT_SSL_VERIFYPEER => true,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($code ?: 200);
echo $body ?: '[]';
