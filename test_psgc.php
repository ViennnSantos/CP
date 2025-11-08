<?php
/**
 * Test PSGC API endpoints
 * Access this file directly to test if PSGC API is working
 */

header('Content-Type: text/html; charset=utf-8');

// Test Batangas province code
$batangas_code = '041000000';

echo "<h2>PSGC API Test</h2>";
echo "<hr>";

// Test 1: Get all provinces
echo "<h3>Test 1: Get All Provinces</h3>";
$url1 = "/backend/api/psgc.php?endpoint=provinces";
echo "<strong>URL:</strong> $url1<br>";
$result1 = file_get_contents("http://" . $_SERVER['HTTP_HOST'] . $url1);
$data1 = json_decode($result1, true);
if ($data1 && is_array($data1)) {
    echo "<strong>✅ SUCCESS:</strong> " . count($data1) . " provinces found<br>";
    // Find Batangas
    foreach ($data1 as $prov) {
        if (stripos($prov['name'], 'Batangas') !== false) {
            echo "<strong>Batangas found:</strong> {$prov['name']} (code: {$prov['code']})<br>";
            $batangas_code = $prov['code'];
            break;
        }
    }
} else {
    echo "<strong>❌ FAILED:</strong> Invalid response<br>";
    echo "<pre>" . htmlspecialchars($result1) . "</pre>";
}

echo "<hr>";

// Test 2: Get cities for Batangas
echo "<h3>Test 2: Get Cities for Batangas ($batangas_code)</h3>";
$url2 = "/backend/api/psgc.php?endpoint=provinces/{$batangas_code}/cities-municipalities";
echo "<strong>URL:</strong> $url2<br>";

$ch = curl_init("http://" . $_SERVER['HTTP_HOST'] . $url2);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$header = substr($response, 0, $header_size);
$body = substr($response, $header_size);

echo "<strong>HTTP Status:</strong> $http_code<br>";

if ($http_code == 200) {
    $data2 = json_decode($body, true);
    if ($data2 && is_array($data2)) {
        echo "<strong>✅ SUCCESS:</strong> " . count($data2) . " cities found<br>";
        echo "<strong>Cities:</strong><br>";
        echo "<ul>";
        foreach (array_slice($data2, 0, 5) as $city) {
            echo "<li>{$city['name']} (code: {$city['code']})</li>";
        }
        if (count($data2) > 5) {
            echo "<li>... and " . (count($data2) - 5) . " more</li>";
        }
        echo "</ul>";
    } else {
        echo "<strong>❌ FAILED:</strong> Invalid JSON response<br>";
        echo "<pre>" . htmlspecialchars($body) . "</pre>";
    }
} else {
    echo "<strong>❌ FAILED:</strong> HTTP $http_code<br>";
    echo "<strong>Response Headers:</strong><br><pre>" . htmlspecialchars($header) . "</pre>";
    echo "<strong>Response Body:</strong><br><pre>" . htmlspecialchars($body) . "</pre>";
}

echo "<hr>";

// Test 3: Direct external API call
echo "<h3>Test 3: Direct Call to External PSGC API</h3>";
$external_url = "https://psgc.cloud/api/provinces/{$batangas_code}/cities-municipalities";
echo "<strong>URL:</strong> $external_url<br>";

$ch = curl_init($external_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$external_result = curl_exec($ch);
$external_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<strong>HTTP Status:</strong> $external_code<br>";

if ($curl_error) {
    echo "<strong>❌ CURL Error:</strong> $curl_error<br>";
} else if ($external_code == 200) {
    $external_data = json_decode($external_result, true);
    if ($external_data && is_array($external_data)) {
        echo "<strong>✅ SUCCESS:</strong> External API working, " . count($external_data) . " cities found<br>";
    } else {
        echo "<strong>❌ FAILED:</strong> Invalid JSON from external API<br>";
    }
} else {
    echo "<strong>❌ FAILED:</strong> HTTP $external_code<br>";
    echo "<pre>" . htmlspecialchars(substr($external_result, 0, 500)) . "</pre>";
}

echo "<hr>";
echo "<p><em>Check the results above to diagnose the issue.</em></p>";
?>
