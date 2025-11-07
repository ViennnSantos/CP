<?php
// get_part_colors.php - hardened version (read-only endpoint for customer UI)
session_start();

// prevent accidental HTML from PHP warnings being printed
ini_set('display_errors', 0);
error_reporting(E_ALL);

// keep headers early
header('Content-Type: application/json; charset=utf-8');

// small helper to respond and exit
function json_exit($payload)
{
    // ensure no accidental HTML in output buffer is sent
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// start output buffering to catch accidental HTML
ob_start();

require_once __DIR__ . '/../config/database.php'; // adjust if your path differs

$product_id = (int)($_GET['product_id'] ?? 0);
$part = trim($_GET['part'] ?? ''); // expected: 'door'|'body'|'inside' (frontend names)

if (!$product_id) {
    json_exit(['success' => false, 'message' => 'Invalid parameters: product_id required']);
}

// Map frontend part names to database part names (keep consistent with textures)
$partMapping = [
    'door' => 'door',
    'body' => 'body',
    'inside' => 'interior', // frontend "inside" maps to DB "interior"
];

// normalize requested part (db name) or empty if not provided
$dbPart = $partMapping[$part] ?? null;

try {
    // ensure $conn (PDO) exists
    if (!isset($conn) || !($conn instanceof PDO)) {
        error_log('get_part_colors: missing $conn PDO from database.php');
        json_exit(['success' => false, 'message' => 'Internal server error (no DB)']);
    }

    // 1) fetch colors (active only)
    $sql = "SELECT id, color_name, color_code, hex_value, base_price, is_active
            FROM colors
            WHERE is_active = 1
            ORDER BY color_name ASC";
    $stmt = $conn->query($sql);
    $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($colors)) {
        // clear buffer then return empty
        if (ob_get_length()) ob_clean();
        json_exit(['success' => true, 'message' => 'No colors found', 'colors' => [], 'part' => $part]);
    }

    // 2) build id list for batch queries
    $ids = array_column($colors, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // 3) fetch allowed parts for all colors (color_allowed_parts table)
    $partsMap = [];
    $pstmt = $conn->prepare("SELECT color_id, part_name FROM color_allowed_parts WHERE color_id IN ($placeholders)");
    $pstmt->execute($ids);
    foreach ($pstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = (int)$r['color_id'];
        $partsMap[$cid][] = $r['part_name'];
    }

    // 4) fetch assigned colors for this product (product_colors)
    $assigned = [];
    $s = $conn->prepare("SELECT color_id FROM product_colors WHERE product_id = ?");
    $s->execute([$product_id]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $assigned[(int)$r['color_id']] = 1;
    }

    // 5) build final output, filter by part if requested
    $out = [];
    foreach ($colors as $c) {
        $cid = (int)$c['id'];

        // normalize hex value (prefer hex_value then color_code)
        $hex = $c['hex_value'] ?? $c['color_code'] ?? null;
        if (!empty($hex)) {
            $hex = trim($hex);
            if ($hex !== '' && $hex[0] !== '#') $hex = '#' . $hex;
        } else {
            $hex = null;
        }

        $allowed = array_values($partsMap[$cid] ?? []); // maybe empty => applies to all parts

        // If a specific part is requested, skip colors that explicitly disallow that part.
        // Interpretation: empty allowed_parts => applies to all (backwards compatibility).
        if ($dbPart !== null) {
            if (!empty($allowed) && !in_array($dbPart, $allowed, true)) {
                continue; // skip this color for the requested part
            }
        }

        $out[] = [
            'id' => $cid,
            'color_name' => $c['color_name'] ?? '',
            'hex_value' => $hex,
            'base_price' => isset($c['base_price']) ? (float)$c['base_price'] : 0.0,
            'is_active' => isset($c['is_active']) ? (int)$c['is_active'] : 1,
            'assigned' => isset($assigned[$cid]) ? 1 : 0,
            'allowed_parts' => $allowed
        ];
    }

    // clear any accidental buffered output
    if (ob_get_length()) ob_clean();

    json_exit([
        'success' => true,
        'message' => 'Colors retrieved',
        'colors' => $out,
        'part' => $part,
        'product_id' => $product_id
    ]);
} catch (Exception $e) {
    // log full exception server-side
    error_log("Get colors error: " . $e->getMessage() . " -- File: " . $e->getFile() . " Line: " . $e->getLine());
    if (ob_get_length()) ob_clean();
    json_exit(['success' => false, 'message' => 'Database error']);
}
