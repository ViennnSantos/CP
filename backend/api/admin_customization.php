<?php
// admin/backend/api/admin_customization.php - FIXED VERSION
declare(strict_types=1);

// CRITICAL: Catch all errors before any output
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/customization_errors.log');

// Start output buffering
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Helper function for JSON responses
function sendJSON(bool $success, string $message, $data = null, int $code = 200): void
{
    if (ob_get_level()) ob_clean();

    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Error handlers
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
    sendJSON(false, 'Server error occurred', null, 500);
});

set_exception_handler(function ($e) {
    error_log("Exception: " . $e->getMessage());
    sendJSON(false, 'An error occurred', null, 500);
});

try {
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    error_log("Database config error: " . $e->getMessage());
    sendJSON(false, 'Database configuration error', null, 500);
}

// Check authentication
function requireStaffAuth(): void
{
    if (!empty($_SESSION['user']) && ($_SESSION['user']['aud'] ?? null) === 'staff') {
        return;
    }
    if (!empty($_SESSION['staff'])) {
        return;
    }
    if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return;
    }

    sendJSON(false, 'Unauthorized access', null, 401);
}

requireStaffAuth();

// Get database connection
try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
    sendJSON(false, 'Database connection failed', null, 500);
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // Textures
        case 'list_textures':
            listTextures($conn);
            break;
        case 'add_texture':
            addTexture($conn);
            break;
        case 'update_texture':
            updateTexture($conn);
            break;
        case 'assign_textures_parts':
            assignTexturesParts($conn);
            break;
        case 'list_product_textures_parts':
            listProductTexturesParts($conn);
            break;
        case 'delete_texture':
            deleteTexture($conn);
            break;

        // Colors
        case 'list_colors':
            listColors($conn);
            break;
        case 'toggle_assign_color':
            toggleAssignColor($conn);
            break;
        case 'add_color':
            addColor($conn);
            break;
        case 'update_color':
            updateColor($conn);
            break;
        case 'delete_color':
            deleteColor($conn);
            break;

        // Handles
        case 'list_handles':
            listHandles($conn);
            break;
        case 'add_handle':
            addHandle($conn);
            break;
        case 'update_handle':
            updateHandle($conn);
            break;
        case 'delete_handle':
            deleteHandle($conn);
            break;

        // Product Assignments
        case 'update_size_config':
            updateSizeConfig($conn);
            break;
        case 'assign_textures':
            assignTextures($conn);
            break;
        case 'assign_colors':
            assignColors($conn);
            break;
        case 'assign_handles':
            assignHandles($conn);
            break;

        case 'upload_texture_image':
            uploadTextureImage();
            break;
        case 'upload_handle_image':
            uploadHandleImage();
            break;

        default:
            sendJSON(false, 'Invalid action', null, 400);
    }
} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage());
    sendJSON(false, 'An error occurred while processing your request', null, 500);
}

// ========== TEXTURE FUNCTIONS ==========
function listTextures(PDO $conn): void
{
    try {
        // optional filter by product_id (so customer page can request textures for a product)
        $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

        if ($product_id) {
            $sql = "SELECT t.* FROM textures t
                    JOIN product_textures pt ON pt.texture_id = t.id
                    WHERE pt.product_id = :pid AND t.is_active = 1
                    ORDER BY t.texture_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':pid' => $product_id]);
            $textures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT * FROM textures WHERE is_active = 1 ORDER BY texture_name ASC";
            $stmt = $conn->query($sql);
            $textures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // fetch allowed parts for all textures in one query (avoid N+1)
        if (!empty($textures)) {
            $ids = array_column($textures, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pstmt = $conn->prepare("SELECT texture_id, part_name FROM texture_allowed_parts WHERE texture_id IN ($placeholders)");
            $pstmt->execute($ids);
            $rows = $pstmt->fetchAll(PDO::FETCH_ASSOC);

            $allowedMap = [];
            foreach ($rows as $r) {
                $tid = (int)$r['texture_id'];
                $allowedMap[$tid][] = $r['part_name'];
            }

            // attach allowed_parts array to each texture
            foreach ($textures as &$t) {
                $tid = (int)$t['id'];
                $t['allowed_parts'] = $allowedMap[$tid] ?? [];
            }
            unset($t);
        }

        sendJSON(true, 'Textures retrieved successfully', $textures);
    } catch (Throwable $e) {
        error_log("List textures error: " . $e->getMessage());
        sendJSON(false, 'Failed to retrieve textures', null, 500);
    }
}

function addTexture(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['texture_name']) || empty($input['texture_code'])) {
        sendJSON(false, 'Texture name and code are required', null, 400);
    }

    try {
        $conn->beginTransaction();

        $sql = "INSERT INTO textures (texture_name, texture_code, texture_image, base_price, description, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['texture_name'],
            $input['texture_code'],
            $input['texture_image'] ?? '',
            $input['base_price'] ?? 0.00,
            $input['description'] ?? null,
            $input['is_active'] ?? 1
        ]);

        $textureId = (int)$conn->lastInsertId();

        // save allowed_parts (if any)
        if (!empty($input['allowed_parts']) && is_array($input['allowed_parts'])) {
            $ins = $conn->prepare("INSERT IGNORE INTO texture_allowed_parts (texture_id, part_name) VALUES (?, ?)");
            foreach ($input['allowed_parts'] as $part) {
                $ins->execute([$textureId, trim($part)]);
            }
        }

        $conn->commit();
        sendJSON(true, 'Texture added successfully', ['id' => $textureId]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Add texture error: " . $e->getMessage());
        if (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062) {
            sendJSON(false, 'Texture code already exists', null, 400);
        }
        sendJSON(false, 'Failed to add texture', null, 500);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Add texture error: " . $e->getMessage());
        sendJSON(false, 'Failed to add texture', null, 500);
    }
}

function updateTexture(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        sendJSON(false, 'Texture ID is required', null, 400);
    }

    try {
        $conn->beginTransaction();

        $updateFields = [];
        $params = [];

        if (isset($input['texture_name'])) {
            $updateFields[] = 'texture_name = ?';
            $params[] = $input['texture_name'];
        }
        if (isset($input['texture_code'])) {
            $updateFields[] = 'texture_code = ?';
            $params[] = $input['texture_code'];
        }
        if (isset($input['texture_image'])) {
            $updateFields[] = 'texture_image = ?';
            $params[] = $input['texture_image'];
        }
        if (isset($input['base_price'])) {
            $updateFields[] = 'base_price = ?';
            $params[] = $input['base_price'];
        }
        if (isset($input['description'])) {
            $updateFields[] = 'description = ?';
            $params[] = $input['description'];
        }
        if (isset($input['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $params[] = $input['is_active'];
        }

        if (!empty($updateFields)) {
            $params[] = $input['id'];
            $sql = 'UPDATE textures SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }

        // sync allowed_parts (delete existing then insert new, if provided)
        if (isset($input['allowed_parts'])) {
            // clear existing
            $del = $conn->prepare("DELETE FROM texture_allowed_parts WHERE texture_id = ?");
            $del->execute([$input['id']]);

            if (!empty($input['allowed_parts']) && is_array($input['allowed_parts'])) {
                $ins = $conn->prepare("INSERT IGNORE INTO texture_allowed_parts (texture_id, part_name) VALUES (?, ?)");
                foreach ($input['allowed_parts'] as $part) {
                    $ins->execute([$input['id'], trim($part)]);
                }
            }
        }

        $conn->commit();
        sendJSON(true, 'Texture updated successfully');
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Update texture error: " . $e->getMessage());
        sendJSON(false, 'Failed to update texture', null, 500);
    }
}


function deleteTexture(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['texture_id'] ?? $input['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {
        sendJSON(false, 'Texture ID required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Check if texture is assigned to any products
        $checkStmt = $conn->prepare('SELECT COUNT(*) as count FROM product_textures WHERE texture_id = ?');
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['count'] > 0) {
            // Unassign from products first
            $unassignStmt = $conn->prepare('DELETE FROM product_textures WHERE texture_id = ?');
            $unassignStmt->execute([$id]);
        }

        // Delete from texture_allowed_parts if exists
        $partsStmt = $conn->prepare('DELETE FROM texture_allowed_parts WHERE texture_id = ?');
        $partsStmt->execute([$id]);

        // Delete the texture
        $stmt = $conn->prepare('DELETE FROM textures WHERE id = ?');
        $stmt->execute([$id]);

        $conn->commit();

        sendJSON(true, 'Texture deleted successfully');
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Delete texture error: " . $e->getMessage());
        sendJSON(false, 'Failed to delete texture', null, 500);
    }
}

// ========== COLOR FUNCTIONS ==========
function listColors(PDO $conn): void
{
    try {
        // optional product filter
        $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

        $sql = "SELECT id, color_name, color_code, hex_value, base_price, is_active FROM colors ORDER BY color_name ASC";
        $stmt = $conn->query($sql);
        $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // build assigned map if product_id present
        $assigned = [];
        if ($product_id) {
            $s = $conn->prepare("SELECT color_id FROM product_colors WHERE product_id = ?");
            $s->execute([$product_id]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) $assigned[(int)$r['color_id']] = 1;
        }

        $out = [];
        foreach ($colors as $r) {
            $hex = null;
            if (!empty($r['hex_value'])) $hex = $r['hex_value'];
            elseif (!empty($r['color_code'])) $hex = $r['color_code'];

            if (!empty($hex)) {
                $hex = trim($hex);
                if ($hex !== '' && $hex[0] !== '#') $hex = '#' . $hex;
            } else {
                $hex = null;
            }

            $out[] = [
                'id' => (int)$r['id'],
                'color_name' => $r['color_name'] ?? '',
                'name' => $r['color_name'] ?? '',
                'hex_value' => $hex,
                'hex_code' => $hex,
                'hex' => $hex,
                'base_price' => isset($r['base_price']) ? (float)$r['base_price'] : 0,
                'is_active' => isset($r['is_active']) ? (int)$r['is_active'] : 1,
                'assigned' => isset($assigned[(int)$r['id']]) ? 1 : 0
            ];
        }

        sendJSON(true, 'Colors retrieved successfully', $out);
    } catch (Throwable $e) {
        error_log("List colors error: " . $e->getMessage());
        sendJSON(false, 'Failed to retrieve colors', null, 500);
    }
}


function addColor(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['color_name']) || empty($input['color_code']) || empty($input['hex_value'])) {
        sendJSON(false, 'Color name, code, and hex value are required', null, 400);
    }

    try {
        $sql = "INSERT INTO colors (color_name, color_code, hex_value, base_price, is_active) 
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['color_name'],
            $input['color_code'],
            $input['hex_value'],
            $input['base_price'] ?? 0.00,
            $input['is_active'] ?? 1
        ]);

        $colorId = $conn->lastInsertId();

        sendJSON(true, 'Color added successfully', ['id' => $colorId]);
    } catch (PDOException $e) {
        error_log("Add color error: " . $e->getMessage());
        if ($e->errorInfo[1] === 1062) {
            sendJSON(false, 'Color code already exists', null, 400);
        }
        sendJSON(false, 'Failed to add color', null, 500);
    }
}

function toggleAssignColor(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $color_id = isset($input['color_id']) ? (int)$input['color_id'] : 0;
    $assign = isset($input['assign']) ? (int)$input['assign'] : 0;

    if (!$product_id || !$color_id) {
        sendJSON(false, 'Product ID and Color ID are required', null, 400);
    }

    try {
        if ($assign) {
            $ins = $conn->prepare('INSERT IGNORE INTO product_colors (product_id, color_id) VALUES (?, ?)');
            $ins->execute([$product_id, $color_id]);
        } else {
            $del = $conn->prepare('DELETE FROM product_colors WHERE product_id = ? AND color_id = ?');
            $del->execute([$product_id, $color_id]);
        }
        sendJSON(true, 'Assignment updated successfully');
    } catch (Throwable $e) {
        error_log('toggleAssignColor error: ' . $e->getMessage());
        sendJSON(false, 'Failed to update assignment', null, 500);
    }
}


function updateColor(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        sendJSON(false, 'Color ID is required', null, 400);
    }

    try {
        $updateFields = [];
        $params = [];

        if (isset($input['color_name'])) {
            $updateFields[] = 'color_name = ?';
            $params[] = $input['color_name'];
        }
        if (isset($input['color_code'])) {
            $updateFields[] = 'color_code = ?';
            $params[] = $input['color_code'];
        }
        if (isset($input['hex_value'])) {
            $updateFields[] = 'hex_value = ?';
            $params[] = $input['hex_value'];
        }
        if (isset($input['base_price'])) {
            $updateFields[] = 'base_price = ?';
            $params[] = $input['base_price'];
        }
        if (isset($input['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $params[] = $input['is_active'];
        }

        if (empty($updateFields)) {
            sendJSON(false, 'No fields to update', null, 400);
        }

        $params[] = $input['id'];
        $sql = 'UPDATE colors SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        sendJSON(true, 'Color updated successfully');
    } catch (Throwable $e) {
        error_log("Update color error: " . $e->getMessage());
        sendJSON(false, 'Failed to update color', null, 500);
    }
}

function deleteColor(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['color_id'] ?? $input['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {
        sendJSON(false, 'Color ID required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Check if color is assigned to any products
        $checkStmt = $conn->prepare('SELECT COUNT(*) as count FROM product_colors WHERE color_id = ?');
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['count'] > 0) {
            // Unassign from products first
            $unassignStmt = $conn->prepare('DELETE FROM product_colors WHERE color_id = ?');
            $unassignStmt->execute([$id]);
        }

        // Delete from color_allowed_parts if exists
        $partsStmt = $conn->prepare('DELETE FROM color_allowed_parts WHERE color_id = ?');
        $partsStmt->execute([$id]);

        // Delete the color
        $stmt = $conn->prepare('DELETE FROM colors WHERE id = ?');
        $stmt->execute([$id]);

        $conn->commit();
        sendJSON(true, 'Color deleted successfully');
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Delete color error: " . $e->getMessage());
        sendJSON(false, 'Failed to delete color', null, 500);
    }
}

// ========== HANDLE FUNCTIONS ==========
function listHandles(PDO $conn): void
{
    try {
        $sql = "SELECT * FROM handle_types ORDER BY handle_name ASC";
        $stmt = $conn->query($sql);
        $handles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJSON(true, 'Handles retrieved successfully', $handles);
    } catch (Throwable $e) {
        error_log("List handles error: " . $e->getMessage());
        sendJSON(false, 'Failed to retrieve handles', null, 500);
    }
}

function addHandle(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['handle_name']) || empty($input['handle_code'])) {
        sendJSON(false, 'Handle name and code are required', null, 400);
    }

    try {
        $sql = "INSERT INTO handle_types (handle_name, handle_code, handle_image, base_price, description, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['handle_name'],
            $input['handle_code'],
            $input['handle_image'] ?? '',
            $input['base_price'] ?? 0.00,
            $input['description'] ?? null,
            $input['is_active'] ?? 1
        ]);

        $handleId = $conn->lastInsertId();

        sendJSON(true, 'Handle added successfully', ['id' => $handleId]);
    } catch (PDOException $e) {
        error_log("Add handle error: " . $e->getMessage());
        if ($e->errorInfo[1] === 1062) {
            sendJSON(false, 'Handle code already exists', null, 400);
        }
        sendJSON(false, 'Failed to add handle', null, 500);
    }
}

function updateHandle(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        sendJSON(false, 'Handle ID is required', null, 400);
    }

    try {
        $updateFields = [];
        $params = [];

        if (isset($input['handle_name'])) {
            $updateFields[] = 'handle_name = ?';
            $params[] = $input['handle_name'];
        }
        if (isset($input['handle_code'])) {
            $updateFields[] = 'handle_code = ?';
            $params[] = $input['handle_code'];
        }
        if (isset($input['handle_image'])) {
            $updateFields[] = 'handle_image = ?';
            $params[] = $input['handle_image'];
        }
        if (isset($input['base_price'])) {
            $updateFields[] = 'base_price = ?';
            $params[] = $input['base_price'];
        }
        if (isset($input['description'])) {
            $updateFields[] = 'description = ?';
            $params[] = $input['description'];
        }
        if (isset($input['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $params[] = $input['is_active'];
        }

        if (empty($updateFields)) {
            sendJSON(false, 'No fields to update', null, 400);
        }

        $params[] = $input['id'];
        $sql = 'UPDATE handle_types SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        sendJSON(true, 'Handle updated successfully');
    } catch (Throwable $e) {
        error_log("Update handle error: " . $e->getMessage());
        sendJSON(false, 'Failed to update handle', null, 500);
    }
}

function deleteHandle(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['handle_id'] ?? $input['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {
        sendJSON(false, 'Handle ID required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Check if handle is assigned to any products
        $checkStmt = $conn->prepare('SELECT COUNT(*) as count FROM product_handles WHERE handle_id = ?');
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['count'] > 0) {
            // Unassign from products first
            $unassignStmt = $conn->prepare('DELETE FROM product_handles WHERE handle_id = ?');
            $unassignStmt->execute([$id]);
        }

        // Delete the handle
        $stmt = $conn->prepare('DELETE FROM handle_types WHERE id = ?');
        $stmt->execute([$id]);

        $conn->commit();
        sendJSON(true, 'Handle deleted successfully');
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Delete handle error: " . $e->getMessage());
        sendJSON(false, 'Failed to delete handle', null, 500);
    }
}

// ========== PRODUCT ASSIGNMENT FUNCTIONS ==========
function updateSizeConfig(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    // read JSON body
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    // log payload for debugging (will be helpful if an error occurs)
    error_log("updateSizeConfig payload: " . ($raw ?: '[empty]'));

    if (empty($input['product_id'])) {
        sendJSON(false, 'Product ID is required', null, 400);
    }

    // Normalize: accept either 'step_value' or 'increment' from frontend
    if (!empty($input['size_config']) && is_array($input['size_config'])) {
        foreach ($input['size_config'] as $k => &$cfg) {
            if (isset($cfg['increment']) && !isset($cfg['step_value'])) {
                $cfg['step_value'] = $cfg['increment'];
            }
            // ensure numeric defaults exist
            $cfg['min_value'] = isset($cfg['min_value']) ? (float)$cfg['min_value'] : 0.0;
            $cfg['max_value'] = isset($cfg['max_value']) ? (float)$cfg['max_value'] : 300.0;
            if (isset($cfg['default_value'])) $cfg['default_value'] = (float)$cfg['default_value'];
            $cfg['step_value'] = isset($cfg['step_value']) ? (float)$cfg['step_value'] : 1.0;
            $cfg['price_per_unit'] = isset($cfg['price_per_unit']) ? (float)$cfg['price_per_unit'] : 0.0;
            $cfg['measurement_unit'] = $cfg['measurement_unit'] ?? 'cm';
            $cfg['price_block_cm'] = isset($cfg['price_block_cm']) ? (float)$cfg['price_block_cm'] : 0.0;
            $cfg['price_per_block'] = isset($cfg['price_per_block']) ? (float)$cfg['price_per_block'] : 0.0;
        }
        unset($cfg);
    }

    try {
        $conn->beginTransaction();

        // Delete existing config rows for this product
        $del = $conn->prepare('DELETE FROM product_size_config WHERE product_id = ?');
        $del->execute([$input['product_id']]);

        // If there's no size_config to insert, commit and return success
        if (empty($input['size_config'])) {
            $conn->commit();
            sendJSON(true, 'Size configuration updated successfully');
        }

        // Insert new config rows.
        // NOTE: column list intentionally matches your DB: (product_id, dimension_type, min_value, max_value, default_value, step_value, price_per_unit, measurement_unit, price_block_cm, price_per_block)
        $sql = "INSERT INTO product_size_config (
                    product_id, dimension_type, min_value, max_value, default_value,
                    step_value, price_per_unit, measurement_unit, price_block_cm, price_per_block
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        foreach ($input['size_config'] as $dimension => $config) {
            // if size_config was sent as an indexed array (0,1,2) and not keyed by dimension,
            // try to extract dimension from 'dimension_type' field inside config:
            if (is_int($dimension) && !isset($config['dimension_type'])) {
                sendJSON(false, 'Invalid size_config format: expected associative object keyed by dimension or include dimension_type inside each config', null, 400);
            }

            // dimension type: either key or inner property
            $dimType = is_string($dimension) ? $dimension : ($config['dimension_type'] ?? null);
            if (!$dimType) {
                sendJSON(false, 'Missing dimension type for size config', null, 400);
            }

            // ensure config numeric conversions already applied above, but re-check safety
            $min = (float)($config['min_value'] ?? 0.0);
            $max = (float)($config['max_value'] ?? 300.0);
            $def = isset($config['default_value']) ? (float)$config['default_value'] : null;
            // fallback default value if null
            if ($def === null) $def = $min;

            $stepVal = (float)($config['step_value'] ?? 1.0);
            $ppu = (float)($config['price_per_unit'] ?? 0.0);
            $unit = $config['measurement_unit'] ?? 'cm';
            $blockCm = (float)($config['price_block_cm'] ?? 0.0);
            $perBlock = (float)($config['price_per_block'] ?? 0.0);

            // execute insert (10 placeholders)
            $stmt->execute([
                $input['product_id'],
                $dimType,
                $min,
                $max,
                $def,
                $stepVal,
                $ppu,
                $unit,
                $blockCm,
                $perBlock
            ]);
        }

        $conn->commit();
        sendJSON(true, 'Size configuration updated successfully');
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();

        // Log detailed error info for debugging
        $err = "updateSizeConfig error: " . $e->getMessage();
        if ($e instanceof PDOException && isset($e->errorInfo)) {
            $err .= ' | SQLSTATE: ' . ($e->errorInfo[0] ?? '') . ' | Code: ' . ($e->errorInfo[1] ?? '') . ' | Msg: ' . ($e->errorInfo[2] ?? '');
        }
        error_log($err);
        // also include last payload in log (already logged at top)
        sendJSON(false, 'Failed to update size configuration', null, 500);
    }
}


function assignTextures(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['product_id']) || !isset($input['texture_ids'])) {
        sendJSON(false, 'Product ID and texture IDs are required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Delete existing assignments
        $stmt = $conn->prepare('DELETE FROM product_textures WHERE product_id = ?');
        $stmt->execute([$input['product_id']]);

        // Insert new assignments
        if (!empty($input['texture_ids'])) {
            foreach ($input['texture_ids'] as $index => $textureId) {
                $sql = "INSERT INTO product_textures (product_id, texture_id, display_order) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$input['product_id'], $textureId, $index]);
            }
        }

        $conn->commit();
        sendJSON(true, 'Textures assigned successfully');
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Assign textures error: " . $e->getMessage());
        sendJSON(false, 'Failed to assign textures', null, 500);
    }
}

function assignColors(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['product_id']) || !isset($input['color_ids'])) {
        sendJSON(false, 'Product ID and color IDs are required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Delete existing assignments
        $stmt = $conn->prepare('DELETE FROM product_colors WHERE product_id = ?');
        $stmt->execute([$input['product_id']]);

        // Insert new assignments
        if (!empty($input['color_ids'])) {
            foreach ($input['color_ids'] as $index => $colorId) {
                $sql = "INSERT INTO product_colors (product_id, color_id, display_order) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$input['product_id'], $colorId, $index]);
            }
        }

        $conn->commit();
        sendJSON(true, 'Colors assigned successfully');
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Assign colors error: " . $e->getMessage());
        sendJSON(false, 'Failed to assign colors', null, 500);
    }
}

function assignHandles(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['product_id']) || !isset($input['handle_ids'])) {
        sendJSON(false, 'Product ID and handle IDs are required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Delete existing assignments
        $stmt = $conn->prepare('DELETE FROM product_handles WHERE product_id = ?');
        $stmt->execute([$input['product_id']]);

        // Insert new assignments
        if (!empty($input['handle_ids'])) {
            foreach ($input['handle_ids'] as $index => $handleId) {
                $sql = "INSERT INTO product_handles (product_id, handle_id, display_order) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$input['product_id'], $handleId, $index]);
            }
        }

        $conn->commit();
        sendJSON(true, 'Handles assigned successfully');
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Assign handles error: " . $e->getMessage());
        sendJSON(false, 'Failed to assign handles', null, 500);
    }
}

// ========== FILE UPLOAD FUNCTIONS ==========
function uploadTextureImage(): void
{
    if (empty($_FILES['image'])) {
        sendJSON(false, 'No image file provided', null, 400);
    }

    // Use central helper for consistent saving
    $helperPath = __DIR__ . '/../helpers/upload.php';
    if (!file_exists($helperPath)) {
        sendJSON(false, 'Upload helper not found', null, 500);
    }
    require_once $helperPath;

    $file = $_FILES['image'];

    // Accept images only
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        sendJSON(false, 'Invalid file type. Only JPG, PNG, and WEBP allowed', null, 400);
    }

    $res = processFileUpload($file, 'textures');
    if (!$res['success']) {
        sendJSON(false, $res['message'] ?? 'Failed to upload texture image', ['error' => $res['message'] ?? null], 400);
    }

    // return filename only for backward compatibility (frontend expects just filename, not full path)
    sendJSON(true, 'Texture image uploaded successfully', [
        'filename' => $res['file_name'],
        'file_path' => $res['file_path'] // e.g. uploads/textures/name.jpg
    ]);
}

function uploadHandleImage(): void
{
    if (empty($_FILES['image'])) {
        sendJSON(false, 'No image file provided', null, 400);
    }

    // Use central helper for consistent saving
    $helperPath = __DIR__ . '/../helpers/upload.php';
    if (!file_exists($helperPath)) {
        sendJSON(false, 'Upload helper not found', null, 500);
    }
    require_once $helperPath;

    $file = $_FILES['image'];

    // Accept images only
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        sendJSON(false, 'Invalid file type. Only JPG, PNG, and WEBP allowed', null, 400);
    }

    $res = processFileUpload($file, 'handles');
    if (!$res['success']) {
        sendJSON(false, $res['message'] ?? 'Failed to upload handle image', ['error' => $res['message'] ?? null], 400);
    }

    // return filename only for backward compatibility (frontend expects just filename, not full path)
    sendJSON(true, 'Handle image uploaded successfully', [
        'filename' => $res['file_name'],
        'file_path' => $res['file_path'] // e.g. uploads/handles/name.jpg
    ]);
}
function assignTexturesParts(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['product_id']) || !isset($input['assignments'])) {
        sendJSON(false, 'Product ID and assignments are required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Delete existing part assignments for this product
        $stmt = $conn->prepare('DELETE FROM product_texture_parts WHERE product_id = ?');
        $stmt->execute([$input['product_id']]);

        // Insert new part assignments
        if (!empty($input['assignments'])) {
            $stmt = $conn->prepare('INSERT INTO product_texture_parts (product_id, texture_id, part_key) VALUES (?, ?, ?)');

            foreach ($input['assignments'] as $assignment) {
                $textureId = (int)$assignment['texture_id'];
                $parts = $assignment['parts'] ?? [];

                foreach ($parts as $part) {
                    if (in_array($part, ['body', 'door', 'interior'])) {
                        $stmt->execute([$input['product_id'], $textureId, $part]);
                    }
                }
            }
        }

        $conn->commit();
        sendJSON(true, 'Texture parts assignments saved successfully');
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        error_log("Assign texture parts error: " . $e->getMessage());
        sendJSON(false, 'Failed to save texture parts assignments', null, 500);
    }
}

function listProductTexturesParts(PDO $conn): void
{
    if (empty($_GET['product_id'])) {
        sendJSON(false, 'Product ID is required', null, 400);
    }

    try {
        $productId = (int)$_GET['product_id'];

        // Get textures assigned to this product with their allowed parts
        $sql = "SELECT t.*, ptp.part_key, pt.display_order
                FROM textures t
                JOIN product_textures pt ON pt.texture_id = t.id
                LEFT JOIN product_texture_parts ptp ON ptp.texture_id = t.id AND ptp.product_id = pt.product_id
                WHERE pt.product_id = ? AND t.is_active = 1
                ORDER BY pt.display_order ASC, t.texture_name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by texture and collect parts
        $texturesMap = [];
        foreach ($rows as $row) {
            $textureId = (int)$row['id'];
            if (!isset($texturesMap[$textureId])) {
                $texturesMap[$textureId] = [
                    'id' => $textureId,
                    'texture_name' => $row['texture_name'],
                    'texture_code' => $row['texture_code'],
                    'texture_image' => $row['texture_image'],
                    'base_price' => $row['base_price'],
                    'description' => $row['description'],
                    'display_order' => $row['display_order'],
                    'allowed_parts' => []
                ];
            }

            if (!empty($row['part_key'])) {
                $texturesMap[$textureId]['allowed_parts'][] = $row['part_key'];
            }
        }

        // Convert to indexed array and remove duplicates from allowed_parts
        $textures = array_values($texturesMap);
        foreach ($textures as &$texture) {
            $texture['allowed_parts'] = array_unique($texture['allowed_parts']);
        }
        unset($texture);

        sendJSON(true, 'Product texture parts retrieved successfully', $textures);
    } catch (Throwable $e) {
        error_log("List product texture parts error: " . $e->getMessage());
        sendJSON(false, 'Failed to retrieve product texture parts', null, 500);
    }
}
