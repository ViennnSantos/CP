<?php

declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/app.php';

// Helper function for JSON responses
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

// Authentication check
$user = $_SESSION['user'] ?? null;
$aud  = $user['aud'] ?? '';

// Allow staff/admin for write operations, customer for read
$action = $_GET['action'] ?? '';
$isReadAction = in_array($action, ['list', 'view'], true);

if ($isReadAction) {
    // Read operations: allow customer, staff, admin
    if (!in_array($aud, ['customer', 'staff', 'admin'], true)) {
        sendJSON(false, 'Unauthorized access', null, 401);
    }
} else {
    // Write operations: require staff/admin
    if (!in_array($aud, ['staff', 'admin'], true)) {
        sendJSON(false, 'Unauthorized access', null, 401);
    }
}

// Get database connection
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $conn = $database->getConnection();
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());
    sendJSON(false, 'Database connection failed', null, 500);
}

// Route to action handlers
try {
    switch ($action) {
        case 'list':
            listProductImages($conn);
            break;
        case 'insert_direct':
            insertProductImageDirect($conn);
            break;
        case 'delete':
            deleteProductImage($conn);
            break;
        case 'set_primary':
            setPrimaryImage($conn);
            break;
        case 'upload':
            uploadProductImages($conn);
            break;
        default:
            sendJSON(false, 'Invalid action', null, 400);
    }
} catch (Throwable $e) {
    error_log("API Error in product_images.php: " . $e->getMessage());
    sendJSON(false, 'An error occurred while processing your request', null, 500);
}

// ========== ACTION HANDLERS ==========

/**
 * List all images for a product
 * GET ?action=list&product_id=123
 */
function listProductImages(PDO $conn): void
{
    $productId = $_GET['product_id'] ?? '';

    if (empty($productId)) {
        sendJSON(false, 'Product ID is required', null, 400);
    }

    try {
        $sql = "SELECT image_id, product_id, image_path, display_order, is_primary, created_at
                FROM product_images
                WHERE product_id = ?
                ORDER BY is_primary DESC, display_order ASC, image_id ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJSON(true, 'Images retrieved successfully', ['images' => $images]);
    } catch (Throwable $e) {
        error_log("List product images error: " . $e->getMessage());
        sendJSON(false, 'Failed to retrieve images', null, 500);
    }
}

/**
 * Insert a product image record directly (for already uploaded files)
 * POST ?action=insert_direct
 * Form data: product_id, image_path, display_order, is_primary
 */
function insertProductImageDirect(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $productId = $_POST['product_id'] ?? '';
    $imagePath = $_POST['image_path'] ?? '';
    $displayOrder = (int)($_POST['display_order'] ?? 0);
    $isPrimary = (int)($_POST['is_primary'] ?? 0);

    if (empty($productId) || empty($imagePath)) {
        sendJSON(false, 'Product ID and image path are required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // If this is being set as primary, unset other primary images for this product
        if ($isPrimary == 1) {
            $stmt = $conn->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
            $stmt->execute([$productId]);
        }

        // Insert the new image record
        $sql = "INSERT INTO product_images (product_id, image_path, display_order, is_primary)
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$productId, $imagePath, $displayOrder, $isPrimary]);

        $imageId = (int)$conn->lastInsertId();

        // If this is the first/primary image, update the products table
        if ($isPrimary == 1) {
            $stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
            $stmt->execute([$imagePath, $productId]);
        }

        $conn->commit();

        sendJSON(true, 'Image inserted successfully', [
            'image_id' => $imageId,
            'product_id' => $productId,
            'image_path' => $imagePath
        ]);
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Insert product image error: " . $e->getMessage());
        sendJSON(false, 'Failed to insert image: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Delete a product image
 * POST ?action=delete
 * Form data: image_id
 */
function deleteProductImage(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $imageId = $_POST['image_id'] ?? '';

    if (empty($imageId)) {
        sendJSON(false, 'Image ID is required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Get image details before deleting
        $stmt = $conn->prepare("SELECT product_id, image_path, is_primary FROM product_images WHERE image_id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            $conn->rollBack();
            sendJSON(false, 'Image not found', null, 404);
        }

        $productId = $image['product_id'];
        $wasPrimary = (int)$image['is_primary'] === 1;

        // Delete the image record (triggers will handle shared_images reference count)
        $stmt = $conn->prepare("DELETE FROM product_images WHERE image_id = ?");
        $stmt->execute([$imageId]);

        // If this was the primary image, set a new primary
        if ($wasPrimary) {
            // Find the next image with the smallest display_order
            $stmt = $conn->prepare("
                SELECT image_id, image_path
                FROM product_images
                WHERE product_id = ?
                ORDER BY display_order ASC, image_id ASC
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            $newPrimary = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($newPrimary) {
                // Set new primary
                $stmt = $conn->prepare("UPDATE product_images SET is_primary = 1 WHERE image_id = ?");
                $stmt->execute([$newPrimary['image_id']]);

                // Update products table
                $stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
                $stmt->execute([$newPrimary['image_path'], $productId]);
            } else {
                // No more images, clear products.image
                $stmt = $conn->prepare("UPDATE products SET image = NULL WHERE id = ?");
                $stmt->execute([$productId]);
            }
        }

        $conn->commit();

        sendJSON(true, 'Image deleted successfully', null);
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Delete product image error: " . $e->getMessage());
        sendJSON(false, 'Failed to delete image', null, 500);
    }
}

/**
 * Set an image as primary
 * POST ?action=set_primary
 * Form data: image_id
 */
function setPrimaryImage(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $imageId = $_POST['image_id'] ?? '';

    if (empty($imageId)) {
        sendJSON(false, 'Image ID is required', null, 400);
    }

    try {
        $conn->beginTransaction();

        // Get the image details
        $stmt = $conn->prepare("SELECT product_id, image_path FROM product_images WHERE image_id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$image) {
            $conn->rollBack();
            sendJSON(false, 'Image not found', null, 404);
        }

        $productId = $image['product_id'];
        $imagePath = $image['image_path'];

        // Unset all primary flags for this product
        $stmt = $conn->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
        $stmt->execute([$productId]);

        // Set this image as primary
        $stmt = $conn->prepare("UPDATE product_images SET is_primary = 1 WHERE image_id = ?");
        $stmt->execute([$imageId]);

        // Update products table
        $stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
        $stmt->execute([$imagePath, $productId]);

        $conn->commit();

        sendJSON(true, 'Primary image updated successfully', null);
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Set primary image error: " . $e->getMessage());
        sendJSON(false, 'Failed to set primary image', null, 500);
    }
}

/**
 * Upload new product images and insert records
 * POST ?action=upload
 * Form data: product_id, images[] (files)
 */
function uploadProductImages(PDO $conn): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(false, 'Method not allowed', null, 405);
    }

    $productId = $_POST['product_id'] ?? '';

    if (empty($productId)) {
        sendJSON(false, 'Product ID is required', null, 400);
    }

    if (empty($_FILES['images'])) {
        sendJSON(false, 'No images provided', null, 400);
    }

    // Load upload helper
    $helperPath = __DIR__ . '/../helpers/upload.php';
    if (!file_exists($helperPath)) {
        sendJSON(false, 'Upload helper not found', null, 500);
    }
    require_once $helperPath;

    try {
        $conn->beginTransaction();

        // Get current max display_order for this product
        $stmt = $conn->prepare("SELECT COALESCE(MAX(display_order), -1) as max_order FROM product_images WHERE product_id = ?");
        $stmt->execute([$productId]);
        $maxOrder = (int)$stmt->fetchColumn();

        // Get current image count to determine if first image should be primary
        $stmt = $conn->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
        $stmt->execute([$productId]);
        $existingCount = (int)$stmt->fetchColumn();

        $files = $_FILES['images'];
        $uploadedImages = [];
        $errors = [];

        // Normalize files array (handle both single and multiple uploads)
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                'type' => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                'error' => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                'size' => is_array($files['size']) ? $files['size'][$i] : $files['size']
            ];

            // Upload file using helper
            $result = processFileUpload($file, 'products');

            if ($result['success']) {
                $imagePath = $result['file_path']; // e.g. uploads/products/filename.jpg
                $displayOrder = ++$maxOrder;
                $isPrimary = ($existingCount === 0 && count($uploadedImages) === 0) ? 1 : 0;

                // Insert image record
                $stmt = $conn->prepare("
                    INSERT INTO product_images (product_id, image_path, display_order, is_primary)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$productId, $imagePath, $displayOrder, $isPrimary]);

                $imageId = (int)$conn->lastInsertId();

                // If first image, update products table
                if ($isPrimary == 1) {
                    $stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
                    $stmt->execute([$imagePath, $productId]);
                }

                $uploadedImages[] = [
                    'image_id' => $imageId,
                    'image_path' => $imagePath,
                    'filename' => $result['file_name']
                ];

                $existingCount++;
            } else {
                $errors[] = $result['message'];
            }
        }

        if (empty($uploadedImages)) {
            $conn->rollBack();
            $msg = !empty($errors) ? implode('; ', $errors) : 'No files uploaded';
            sendJSON(false, 'Upload failed: ' . $msg, ['errors' => $errors], 400);
        }

        $conn->commit();

        $message = count($uploadedImages) . ' image(s) uploaded successfully';
        if (!empty($errors)) {
            $message .= ' (' . count($errors) . ' failed)';
        }

        sendJSON(true, $message, [
            'images' => $uploadedImages,
            'errors' => $errors
        ]);
    } catch (Throwable $e) {
        $conn->rollBack();
        error_log("Upload product images error: " . $e->getMessage());
        sendJSON(false, 'Failed to upload images: ' . $e->getMessage(), null, 500);
    }
}