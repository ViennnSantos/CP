<?php
/**
 * RADS TOOLING - Unified Upload Helper
 * Handles upload of images, videos, and 3D models securely.
 * Now group-aware: saves files under uploads/<group>/ consistently.
 */

function processFileUpload($file, $group = 'general')
{
    // === CONFIG ===
    $maxFileSize = 30 * 1024 * 1024; // 30 MB
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', 'avi', 'glb'];
    $allowedMimes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/x-msvideo',
        'model/gltf-binary', 'application/octet-stream'
    ];

    // === VALIDATION ===
    if (!isset($file) || !is_array($file)) {
        return ['success' => false, 'message' => 'No file provided'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . getUploadErrorMessage($file['error'])];
    }

    if ($file['size'] > $maxFileSize) {
        return ['success' => false, 'message' => 'File size exceeds 30MB limit'];
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid uploaded file'];
    }

    // === FILE INFO ===
    $originalName = basename($file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)];
    }

    // MIME type check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedMimes, true)) {
        // allow GLB flexibility
        if (!($extension === 'glb' && in_array($mimeType, ['application/octet-stream', 'application/x-unknown'], true))) {
            return ['success' => false, 'message' => 'Invalid file MIME type: ' . $mimeType];
        }
    }

    // === DETERMINE UPLOAD DIR ===
    $baseDir = dirname(dirname(__DIR__)); // go from /backend/helpers/ to project root
    $groupClean = sanitizeGroupName($group);
    if ($groupClean === '') $groupClean = 'general';

    // Allow known groups only
    $allowedGroups = ['products', 'cms', 'models', 'avatars', 'handles', 'textures', 'general'];
    if (!in_array($groupClean, $allowedGroups, true)) {
        $groupClean = 'general';
    }

    $uploadDir = $baseDir . '/uploads/' . $groupClean . '/';

    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }

    // Ensure directory writable
    if (!is_writable($uploadDir)) {
        return ['success' => false, 'message' => 'Upload directory not writable: ' . $uploadDir];
    }

    // === SAVE FILE ===
    $uniqueName = generateUniqueFileName($originalName, $extension);
    $filePath = $uploadDir . $uniqueName;
    $relativePath = 'uploads/' . $groupClean . '/' . $uniqueName; // consistent with other scripts

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    // === OPTIONAL IMAGE DETAILS ===
    $width = 0; $height = 0;
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $dims = @getimagesize($filePath);
        if ($dims) {
            $width = $dims[0]; $height = $dims[1];
        }

        // Auto-convert to webp (optional)
        if ($extension !== 'webp' && function_exists('imagewebp')) {
            @createWebPVersion($filePath, $extension);
        }
    }

    // === RETURN RESULT ===
    return [
        'success' => true,
        'file_path' => $relativePath, // e.g. uploads/products/image.jpg
        'file_name' => $uniqueName,
        'mime' => $mimeType,
        'width' => $width,
        'height' => $height
    ];
}

/**
 * Generate unique, safe filename
 */
function generateUniqueFileName($originalName, $extension)
{
    $timestamp = time();
    $random = bin2hex(random_bytes(6));
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
    $safe = substr($safe, 0, 30);
    return $safe . '_' . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Sanitize group name (folder name)
 */
function sanitizeGroupName($group)
{
    return preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower((string)$group));
}

/**
 * Return human-readable upload error message
 */
function getUploadErrorMessage($errorCode)
{
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    return $errors[$errorCode] ?? 'Unknown upload error';
}

/**
 * Optionally create a .webp version of image
 */
function createWebPVersion($filePath, $extension)
{
    try {
        $image = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = @imagecreatefrompng($filePath);
                break;
        }

        if (!$image) return false;

        $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $filePath);
        $result = @imagewebp($image, $webpPath, 85);
        @imagedestroy($image);
        return $result;
    } catch (Throwable $e) {
        return false;
    }
}
