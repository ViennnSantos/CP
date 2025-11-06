<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';  // adjust if needed

// --- must exist BEFORE any call to sendJSON() ---
if (!function_exists('sendJSON')) {
    function sendJSON(bool $success, string $message = '', $data = null, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$db = $GLOBALS['db'] ?? $db ?? null;

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

$user = $_SESSION['user'] ?? null;
$aud  = $user['aud']  ?? '';   // 'staff' | 'admin' | 'customer' (legacy)
$role = $user['role'] ?? '';   // 'Owner' | 'Admin' | 'Secretary'

// ✅ Unified auth:
// - Non-view actions: kailangan staff/admin audience
// - View: pwede staff/admin/customer
if ($action !== 'view') {
    if (!in_array($aud, ['staff', 'admin'], true)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'data' => null]);
        exit;
    }
} else {
    if (!$user || !in_array($aud, ['staff', 'admin', 'customer'], true)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'data' => null]);
        exit;
    }
}

// Error handler
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
    sendJSON(false, 'Server error occurred', null, 500);
});

// Exception handler
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

// FIXED: Check authentication with multiple fallbacks
function requireStaffAuth(): void
{
    // Check new session format
    if (!empty($_SESSION['user']) && in_array($_SESSION['user']['aud'] ?? '', ['staff','admin'], true)) {
        return;
    }
    // Check staff-specific session
    if (!empty($_SESSION['staff'])) {
        return;
    }
    // Check legacy admin session
    if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return;
    }

    sendJSON(false, 'Unauthorized access', null, 401);
}

// Allow 'view' for logged-in admin OR customer; other actions require staff/admin
if ($action !== 'view') {
    requireStaffAuth();
} else {
    if (!in_array($aud, ['customer', 'staff', 'admin'], true)) {
        sendJSON(false, 'Unauthorized access', null, 401);
    }
}

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
        case 'toggle_release':
            toggleRelease($conn);
            break;
        case 'list':
            listProducts($conn);
            break;
        case 'view':
            viewProduct($conn);
            break;
        case 'add':
            addProduct($conn);
            break;
        case 'update':
            updateProduct($conn);
            break;
        case 'delete':
            deleteProduct($conn);
            break;
        case 'upload_image':
            uploadImage();
            break;
        case 'upload_model':
            uploadModel();
            break;
        default:
            sendJSON(false, 'Invalid action', null, 400);
    }
} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage());
    sendJSON(false, 'An error occurred while processing your request', null, 500);
}

// ========== FUNCTIONS ==========

function listProducts(PDO $conn): void
{
    try {
        $search = $_GET['search'] ?? '';
        $type = $_GET['type'] ?? '';

        $whereClauses = [];
        $params = [];

        if (!empty($search)) {
            $whereClauses[] = '(p.name LIKE ? OR p.description LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($type)) {
            $whereClauses[] = 'p.type = ?';
            $params[] = $type;
        }

        $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "SELECT 
                    p.*,
                    COUNT(DISTINCT pc.color_id) as color_count,
                    COUNT(DISTINCT pt.texture_id) as texture_count,
                    COUNT(DISTINCT ph.handle_id) as handle_count
                FROM products p
                LEFT JOIN product_colors pc ON p.id = pc.product_id
                LEFT JOIN product_textures pt ON p.id = pt.product_id
                LEFT JOIN product_handles ph ON p.id = ph.product_id
                $whereClause
                GROUP BY p.id
                ORDER BY p.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJSON(true, 'Products retrieved successfully', $products);
    } catch (Throwable $e) {
        error_log("List products error: " . $e->getMessage());
        sendJSON(false, 'Failed to retrieve products', null, 500);
    }
}

function viewProduct(PDO $conn): void
{
    $productId = $_GET['id'] ?? '';

    if (empty($productId)) {
        sendJSON(false, 'Product ID is required', null, 400);
        return;
    }

    try {
        // Get product details
        $sql = "SELECT * FROM products WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            sendJSON(false, 'Product not found', null, 404);
            return;
        }

        // Get size configuration
        $sql = "SELECT * FROM product_size_config WHERE product_id = ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId]);
        $sizeConfig = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get assigned textures WITH their allowed parts
        $sql = "SELECT pt.*, t.texture_name, t.texture_code, t.texture_image, t.base_price as texture_base_price,
                       GROUP_CONCAT(DISTINCT ptp.part_key) as allowed_parts
                FROM product_textures pt
                JOIN textures t ON pt.texture_id = t.id
                LEFT JOIN product_texture_parts ptp ON ptp.texture_id = t.id AND ptp.product_id = pt.product_id
                WHERE pt.product_id = ?
                GROUP BY pt.texture_id, pt.id
                ORDER BY pt.display_order, t.texture_name";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId]);
        $textures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get assigned colors
        $sql = "SELECT pc.*, c.color_name, c.color_code, c.hex_value, c.base_price as color_base_price
                FROM product_colors pc
                JOIN colors c ON pc.color_id = c.id
                WHERE pc.product_id = ?
                ORDER BY pc.display_order, c.color_name";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId]);
        $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get assigned handles
        $sql = "SELECT ph.*, h.handle_name, h.handle_code, h.handle_image, h.base_price as handle_base_price
                FROM product_handles ph
                JOIN handle_types h ON ph.handle_id = h.id
                WHERE ph.product_id = ?
                ORDER BY ph.display_order, h.handle_name";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId]);
        $handles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $product['size_config'] = $sizeConfig;
        $product['textures'] = $textures;
        $product['colors'] = $colors;
        $product['handles'] = $handles;

        $sizeConfs = [];
        foreach ($sizeConfig as $rowSC) {
            $sizeConfs[] = [
                'dimension'       => $rowSC['dimension_type'],               // 'width' | 'height' | 'depth'
                'min'             => (float)($rowSC['min_value'] ?? 0),
                'max'             => (float)($rowSC['max_value'] ?? 0),
                'default'         => (float)($rowSC['default_value'] ?? 0),
                'step'            => (float)($rowSC['step_value'] ?? 1),
                'price_per_unit'  => (float)($rowSC['price_per_unit'] ?? 0), // ₱ per cm (internal base unit)
                'unit'            => $rowSC['measurement_unit'] ?? 'cm',
                'price_block_cm'  => (float)($rowSC['price_block_cm'] ?? 0),   // cm per block (always cm)
                'price_per_block' => (float)($rowSC['price_per_block'] ?? 0),  // ₱ per block
            ];
        }

        // ✅ NEW: Build allowed options based on actual part assignments
        $allowed = [
            'door'     => ['textures' => [], 'colors' => []],
            'body'     => ['textures' => [], 'colors' => []],
            'interior' => ['textures' => [], 'colors' => []],
            'handle'   => ['handles'  => []],
        ];

        // Process textures by their allowed parts
        foreach ($textures as $t) {
            $textureData = [
                'id' => (int)$t['texture_id'],
                'name' => $t['texture_name'],
                'file' => $t['texture_image']
            ];

            // Get allowed parts for this texture (comma-separated from GROUP_CONCAT)
            $allowedParts = !empty($t['allowed_parts']) ? array_filter(array_map('trim', explode(',', $t['allowed_parts']))) : [];

            // If no specific parts assigned, texture is available for all parts (backward compatibility)
            if (empty($allowedParts)) {
                $allowed['door']['textures'][] = $textureData;
                $allowed['body']['textures'][] = $textureData;
                $allowed['interior']['textures'][] = $textureData;
            } else {
                // Only add to allowed parts
                foreach ($allowedParts as $part) {
                    if ($part === 'door') {
                        $allowed['door']['textures'][] = $textureData;
                    } elseif ($part === 'body') {
                        $allowed['body']['textures'][] = $textureData;
                    } elseif ($part === 'interior') {
                        $allowed['interior']['textures'][] = $textureData;
                    }
                }
            }
        }

        // Colors remain available for all parts (no part-specific logic yet)
        foreach ($colors as $c) {
            $colorData = [
                'id' => (int)$c['color_id'],
                'name' => $c['color_name'],
                'hex' => $c['hex_value']
            ];
            $allowed['door']['colors'][] = $colorData;
            $allowed['body']['colors'][] = $colorData;
            $allowed['interior']['colors'][] = $colorData;
        }

        // Handles
        foreach ($handles as $h) {
            $allowed['handle']['handles'][] = [
                'id'      => (int)$h['handle_id'],
                'name'    => $h['handle_name'],
                'preview' => $h['handle_image'] // front-end will prefix with HANDLE_DIR
            ];
        }

        // 3) pricing maps
        $basePrice = $product['price'] ?? 0;

        // Derive per_cm (₱ per cm) from size_config
        $perCm = ['w' => 0, 'h' => 0, 'd' => 0];
        foreach ($sizeConfs as $sc) {
            $rate = (float)$sc['price_per_unit'];
            if ($sc['dimension'] === 'width')  $perCm['w'] = $rate;
            if ($sc['dimension'] === 'height') $perCm['h'] = $rate;
            if ($sc['dimension'] === 'depth')  $perCm['d'] = $rate;
        }

        // Build surcharge maps (by ID)
        $textureMap = [];
        foreach ($textures as $t) {
            $textureMap[(string)$t['texture_id']] = (float)($t['texture_base_price'] ?? 0);
        }
        $colorMap = [];
        foreach ($colors as $c) {
            $colorMap[(string)$c['color_id']] = (float)($c['color_base_price'] ?? 0);
        }
        $handleMap = [];
        foreach ($handles as $h) {
            $handleMap[(string)$h['handle_id']] = (float)($h['handle_base_price'] ?? 0);
        }

        $pricing = [
            'base_price' => $basePrice,
            'per_cm'     => $perCm,
            'textures'   => $textureMap,
            'colors'     => $colorMap,
            'handles'    => $handleMap,
        ];

               // ----------------------------
        // Attach normalized fields for frontend (IMPROVED)
        // ----------------------------
        // Defensive: ensure $product is array
        if (!isset($product) || !is_array($product)) {
            throw new Exception('Invalid product record');
        }

        // Base path used by frontend for images/models (adjust if necessary)
        $publicBasePrefix = '/RADS-TOOLING/'; // <- change if different
        $uploadsProductsPrefix = 'uploads/products/';

        // Normalize main image (if stored as filename or relative path)
        $product['image'] = $product['image'] ?? null;
        if (!empty($product['image'])) {
            $img = (string)$product['image'];
            if (preg_match('#^https?://#i', $img)) {
                $product['image_public'] = $img;
            } else {
                $imgClean = ltrim($img, '/');
                if (!preg_match('#^uploads/#', $imgClean)) {
                    $imgClean = $uploadsProductsPrefix . basename($imgClean);
                }
                $product['image_public'] = rtrim($publicBasePrefix, '/') . '/' . ltrim($imgClean, '/');
            }
        } else {
            $product['image_public'] = null;
        }

        // Normalize 3D model path (if any)
        $product['model_3d'] = $product['model_3d'] ?? null;
        if (!empty($product['model_3d']) && !preg_match('#^https?://#i', $product['model_3d'])) {
            $m = ltrim((string)$product['model_3d'], '/');
            // if model stored under uploads/models or uploads/models/..., adjust as needed
            $product['model_3d_public'] = rtrim($publicBasePrefix, '/') . '/' . $m;
        } else {
            $product['model_3d_public'] = $product['model_3d'] ?: null;
        }

        // Attach computed size configs to product (frontend expects size_confs)
        // $sizeConfs was built earlier from $sizeConfig
        $product['size_confs'] = $sizeConfs ?? [];

        // Keep raw DB arrays for reference
        $product['raw_textures'] = $textures;
        $product['raw_colors']   = $colors;
        $product['raw_handles']  = $handles;

        // Attach allowed options (textures/colors per part) computed earlier
        $product['allowed_options'] = $allowed;

        // Build pricing map and attach
        $product['pricing'] = $pricing;

        // Fetch product images from product_images table and normalize
        $stmt = $conn->prepare("
            SELECT image_id, image_path, display_order, is_primary
            FROM product_images
            WHERE product_id = ?
            ORDER BY is_primary DESC, display_order ASC, image_id ASC
        ");
        $stmt->execute([$productId]);
        $imgRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $imagesOut = [];
        foreach ($imgRows as $ir) {
            $rawPath = trim((string)($ir['image_path'] ?? ''));
            if ($rawPath === '') continue;

            if (preg_match('#^https?://#i', $rawPath)) {
                $publicPath = $rawPath;
                $rel = preg_replace('#^https?://[^/]+/#i', '', $rawPath);
                $rel = ltrim($rel, '/');
            } else {
                $rel = ltrim($rawPath, '/');
                if (!preg_match('#^uploads/#', $rel)) {
                    $rel = $uploadsProductsPrefix . basename($rel);
                }
                $publicPath = rtrim($publicBasePrefix, '/') . '/' . $rel;
            }

            $imagesOut[] = [
                'image_id'     => (int)$ir['image_id'],
                'filename'     => basename($rel),
                'image_path'   => $rel,
                'public_path'  => $publicPath,
                'display_order'=> (int)$ir['display_order'],
                'is_primary'   => (int)$ir['is_primary'],
            ];
        }
        $product['images'] = $imagesOut;

        // If there's a primary image in images[], ensure product.image_public points to it (fallback)
        if (empty($product['image_public']) && !empty($imagesOut)) {
            foreach ($imagesOut as $im) {
                if (!empty($im['is_primary'])) {
                    $product['image_public'] = $im['public_path'];
                    break;
                }
            }
            // if still empty, set to first image
            if (empty($product['image_public'])) {
                $product['image_public'] = $imagesOut[0]['public_path'] ?? null;
            }
        }

        // Finally send response
        sendJSON(true, 'Product details retrieved successfully', $product);
        return;

    } catch (Throwable $e) {
        error_log("View product error: " . $e->getMessage());
        sendJSON(false, 'Failed to retrieve product details', null, 500);
    }
}

function addProduct(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
        sendJSON(false, 'Invalid JSON payload', null, 400);
    }

    if (empty($input['name']) || empty($input['type'])) {
        sendJSON(false, 'Product name and type are required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // ✅ NEW: Check for duplicate product name
        // If input provides id (shouldn't for add), exclude it; otherwise 0
        $excludeId = isset($input['id']) ? (int)$input['id'] : 0;
        $stmt = $conn->prepare('SELECT id FROM products WHERE name = ? AND id != ? LIMIT 1');
        $stmt->execute([$input['name'], $excludeId]);
        if ($stmt->fetch()) {
            $conn->rollBack();
            sendJSON(false, 'A product with this name already exists. Please use a different name.', null, 409);
        }

        // Get current user ID
        $createdBy = $_SESSION['staff']['id'] ?? $_SESSION['user']['id'] ?? 1;

        $sql = "INSERT INTO products (name, type, description, price, stock, image, model_3d, measurement_unit, is_customizable, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $input['name'],
            $input['type'],
            $input['description'] ?? null,
            $input['price'] ?? 0.00,
            $input['stock'] ?? 0,
            $input['image'] ?? null,
            $input['model_3d'] ?? null,
            $input['measurement_unit'] ?? 'cm',
            $input['is_customizable'] ?? 0,
            $createdBy
        ]);

        $productId = (int)$conn->lastInsertId();

        // If customizable, insert size configuration
        if (!empty($input['is_customizable']) && !empty($input['size_config']) && is_array($input['size_config'])) {
            foreach ($input['size_config'] as $dimension => $config) {
                $sql = "INSERT INTO product_size_config (product_id, dimension_type, min_value, max_value, 
                        default_value, step_value,
                        price_per_unit, measurement_unit, 
                        price_block_cm, price_per_block)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $productId,
                    $dimension,
                    $config['min_value'] ?? 0,
                    $config['max_value'] ?? 300,
                    $config['default_value'] ?? 100,
                    $config['step_value'] ?? 1,
                    $config['price_per_unit'] ?? 0.00,
                    $input['measurement_unit'] ?? 'cm',
                    (float)($config['price_block_cm'] ?? 0),
                    (float)($config['price_per_block'] ?? 0),
                ]);
            }
        }

        $conn->commit();

        sendJSON(true, 'Product added successfully', ['id' => $productId]);
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Add product error: " . $e->getMessage());
        sendJSON(false, 'Failed to add product: ' . $e->getMessage(), null, 500);
    }
}

function updateProduct(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    if (empty($input['id']) && !empty($_GET['id'])) {
        $input['id'] = (int)$_GET['id'];
    }
    if (empty($input['id'])) {
        sendJSON(false, 'Product ID is required', null, 400);
    }

    try {
        $conn->beginTransaction();

        $updateFields = [];
        $params = [];

        if (isset($input['name'])) {
            // check duplicate except this id
            $stmt = $conn->prepare('SELECT id FROM products WHERE name = ? AND id != ? LIMIT 1');
            $stmt->execute([$input['name'], (int)$input['id']]);
            if ($stmt->fetch()) {
                $conn->rollBack();
                sendJSON(false, 'A product with this name already exists. Please use a different name.', null, 409);
            }

            $updateFields[] = 'name = ?';
            $params[] = $input['name'];
        }
        if (isset($input['type'])) {
            $updateFields[] = 'type = ?';
            $params[] = $input['type'];
        }
        if (isset($input['description'])) {
            $updateFields[] = 'description = ?';
            $params[] = $input['description'];
        }
        if (isset($input['price'])) {
            $updateFields[] = 'price = ?';
            $params[] = $input['price'];
        }
        if (isset($input['stock'])) {
            $updateFields[] = 'stock = ?';
            $params[] = $input['stock'];
        }
        if (isset($input['image'])) {
            $updateFields[] = 'image = ?';
            $params[] = $input['image'];
        }
        if (isset($input['model_3d'])) {
            $updateFields[] = 'model_3d = ?';
            $params[] = $input['model_3d'];
        }
        if (isset($input['measurement_unit'])) {
            $updateFields[] = 'measurement_unit = ?';
            $params[] = $input['measurement_unit'];
        }
        if (isset($input['is_customizable'])) {
            $updateFields[] = 'is_customizable = ?';
            $params[] = $input['is_customizable'];
        }

        if (empty($updateFields)) {
            sendJSON(false, 'No fields to update', null, 400);
        }

        $params[] = $input['id'];
        $sql = 'UPDATE products SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $conn->commit();

        sendJSON(true, 'Product updated successfully', null);
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Update product error: " . $e->getMessage());
        sendJSON(false, 'Failed to update product', null, 500);
    }
}

function deleteProduct(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    $id = (int)($input['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {
        sendJSON(false, 'Product ID required', null, 400);
    }

    try {
        $stmt = $conn->prepare('SELECT id FROM products WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            sendJSON(false, 'Product not found', null, 404);
        }

        $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);

        sendJSON(true, 'Product deleted successfully', null);
    } catch (Throwable $e) {
        error_log("Delete product error: " . $e->getMessage());
        sendJSON(false, 'Failed to delete product', null, 500);
    }
}

function uploadImage(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    // Accept both 'images' (multiple) and 'image' (single)
    if (empty($_FILES)) {
        sendJSON(false, 'No files uploaded', null, 400);
    }

    // Ensure helper is available
    $helperPath = __DIR__ . '/../helpers/upload.php';
    if (!file_exists($helperPath)) {
        sendJSON(false, 'Upload helper not found', null, 500);
    }
    require_once $helperPath;

    $savedFiles = [];    // relative paths: uploads/products/xxx.jpg
    $savedNames = [];    // filenames only: xxx.jpg
    $errors = [];

    // Normalize to list of files
    $fileList = [];
    if (isset($_FILES['images'])) {
        // multiple files array (images[])
        $files = $_FILES['images'];
        $count = is_array($files['name']) ? count($files['name']) : 0;
        for ($i = 0; $i < $count; $i++) {
            $fileList[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0
            ];
        }
    } elseif (isset($_FILES['image'])) {
        $f = $_FILES['image'];
        $fileList[] = [
            'name' => $f['name'],
            'type' => $f['type'] ?? '',
            'tmp_name' => $f['tmp_name'] ?? '',
            'error' => $f['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $f['size'] ?? 0
        ];
    } else {
        // Fallback: pick first file present in $_FILES
        foreach ($_FILES as $k => $f) {
            if (!is_array($f['name'])) {
                $fileList[] = [
                    'name' => $f['name'],
                    'type' => $f['type'] ?? '',
                    'tmp_name' => $f['tmp_name'] ?? '',
                    'error' => $f['error'] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $f['size'] ?? 0
                ];
            } else {
                $count = count($f['name']);
                for ($i = 0; $i < $count; $i++) {
                    $fileList[] = [
                        'name' => $f['name'][$i],
                        'type' => $f['type'][$i] ?? '',
                        'tmp_name' => $f['tmp_name'][$i] ?? '',
                        'error' => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $f['size'][$i] ?? 0
                    ];
                }
            }
            break;
        }
    }

    if (empty($fileList)) {
        sendJSON(false, 'No image file provided', null, 400);
    }

    foreach ($fileList as $f) {
        // call the central helper to validate/save
        $res = processFileUpload($f, 'products');
        if ($res['success']) {
            // res.file_path = uploads/products/<name>
            $savedFiles[] = $res['file_path'];
            $savedNames[] = $res['file_name'];
        } else {
            // collect error but continue to try others
            $errors[] = sprintf('%s: %s', $f['name'] ?? 'file', $res['message'] ?? 'Unknown error');
        }
    }

    if (empty($savedFiles)) {
        $msg = !empty($errors) ? implode('; ', $errors) : 'No files saved';
        sendJSON(false, 'Upload failed: ' . $msg, ['errors' => $errors], 400);
    }

    // Success: return arrays that frontend expects (both relative paths and filenames)
    $payload = [
        'files'     => $savedFiles,   // e.g. ['uploads/products/abc.jpg', ...]
        'filenames' => $savedNames,   // e.g. ['abc.jpg', ...]
        'errors'    => $errors
    ];

    sendJSON(true, count($savedFiles) . ' image(s) uploaded successfully', $payload);
}


function uploadModel(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    if (empty($_FILES['model'])) {
        sendJSON(false, 'No 3D model file provided', null, 400);
    }

    // Use central helper for consistent saving
    $helperPath = __DIR__ . '/../helpers/upload.php';
    if (!file_exists($helperPath)) {
        sendJSON(false, 'Upload helper not found', null, 500);
    }
    require_once $helperPath;

    $file = $_FILES['model'];

    // Accept .glb only; helper accepts glb in allowedExtensions
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'glb') {
        sendJSON(false, 'Invalid file type. Only GLB files allowed', null, 400);
    }

    $res = processFileUpload($file, 'models');
    if (!$res['success']) {
        sendJSON(false, $res['message'] ?? 'Failed to upload model', ['error' => $res['message'] ?? null], 400);
    }

    // return relative path and filename for frontend to save into products.model_3d
    sendJSON(true, '3D model uploaded successfully', [
        'filename' => $res['file_name'],
        'file_path' => $res['file_path'] // e.g. uploads/models/name.glb
    ]);
}


function toggleRelease(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $input  = json_decode(file_get_contents('php://input'), true);
    $id     = (int)($input['product_id'] ?? 0);
    $status = $input['status'] ?? '';

    if (!$id || !in_array($status, ['draft', 'released'], true)) {
        sendJSON(false, 'Invalid payload', null, 422);
    }

    try {
        if ($status === 'released') {
            $sql = "UPDATE products SET status='released', released_at=NOW() WHERE id=? AND is_archived=0";
        } else {
            $sql = "UPDATE products SET status='draft', released_at=NULL WHERE id=?";
        }
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([$id]);

        sendJSON((bool)$ok, $ok ? 'Status updated' : 'Failed to update', null, $ok ? 200 : 500);
    } catch (Throwable $e) {
        error_log('toggleRelease error: ' . $e->getMessage());
        sendJSON(false, 'Server error', null, 500);
    }
}

// Legacy / alternative endpoint for setting availability (keeps backward compatibility)
if ($action === 'set_availability') {
    // Using sendJSON for consistent responses
    $payload = json_decode(file_get_contents('php://input'), true);
    $id = (int)($payload['id'] ?? 0);
    $is_available = isset($payload['is_available']) ? (int)$payload['is_available'] : 0;

    if ($id <= 0) {
        sendJSON(false, 'Invalid product id', null, 400);
    }

    try {
        global $conn;
        $stmt = $conn->prepare('UPDATE products SET is_available = ? WHERE id = ?');
        $ok = $stmt->execute([$is_available, $id]);

        if ($ok) {
            sendJSON(true, 'Availability updated', null, 200);
        } else {
            sendJSON(false, 'DB error updating availability', null, 500);
        }
    } catch (Throwable $e) {
        error_log('set_availability error: ' . $e->getMessage());
        sendJSON(false, 'Server error', null, 500);
    }
}
