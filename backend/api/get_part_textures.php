<?php
// get_part_textures.php - hardened version
session_start();

// prevent accidental HTML from PHP warnings being printed
ini_set('display_errors', 0);
error_reporting(E_ALL);

// keep headers early
header('Content-Type: application/json; charset=utf-8');

// small helper to respond and exit
function json_exit($payload) {
    // ensure no accidental HTML in output buffer is sent
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload);
    exit;
}

// start output buffering to catch accidental HTML
ob_start();

require_once __DIR__ . '/../config/database.php'; // keep path but use __DIR__ for reliability

$product_id = (int)($_GET['product_id'] ?? 0);
$part = trim($_GET['part'] ?? '');

if (!$product_id || !$part) {
    json_exit(['success' => false, 'message' => 'Invalid parameters']);
}

try {
    // ensure $conn (PDO) exists
    if (!isset($conn) || !($conn instanceof PDO)) {
        error_log('get_part_textures: missing $conn PDO from database.php');
        json_exit(['success' => false, 'message' => 'Internal server error (no DB)']);
    }

    // Map frontend part names to database part names
    $partMapping = [
        'door' => 'door',
        'body' => 'body',
        'inside' => 'interior' // adjust if your DB uses another name
    ];
    $dbPart = $partMapping[$part] ?? $part;

    // Query textures for this product and allowed part
    $sql = "
        SELECT t.*, pt.id as pt_id
        FROM textures t
        INNER JOIN product_textures pt ON t.id = pt.texture_id
        INNER JOIN texture_allowed_parts tap ON t.id = tap.texture_id
        WHERE pt.product_id = :product_id
          AND tap.part_name = :part_name
          AND t.is_active = 1
        GROUP BY t.id
        ORDER BY t.texture_name
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindValue(':part_name', $dbPart, PDO::PARAM_STR);
    $stmt->execute();
    $textures = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build image URLs and normalize structure
    foreach ($textures as &$tex) {
        $tex['image_url'] = '';
        if (!empty($tex['texture_image'])) {
            // make absolute-ish URL path (adjust if your uploads folder differs)
            $tex['image_url'] = '/uploads/textures/' . ltrim($tex['texture_image'], '/');
        }
    }
    unset($tex);

    // clear any accidental buffered output
    if (ob_get_length()) {
        ob_clean();
    }

    json_exit([
        'success' => true,
        'textures' => $textures,
        'part' => $part
    ]);

} catch (Exception $e) {
    // log full exception server-side
    error_log("Get textures error: " . $e->getMessage() . " -- File: " . $e->getFile() . " Line: " . $e->getLine());
    // clean buffer then return JSON error
    if (ob_get_length()) {
        ob_clean();
    }
    json_exit(['success' => false, 'message' => 'Database error']);
}
