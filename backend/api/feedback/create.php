<?php
// ==========================================
// FEEDBACK CREATION API WITH IMAGE UPLOAD // NEW
// ==========================================
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

class FeedbackAPI {
    private PDO $conn;
    private int $customerId;
    private string $uploadDir; // NEW

    public function __construct() {
        // Check if customer is logged in
        if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {
            $this->sendResponse(false, 'Unauthorized access', null, 401);
        }

        try {
            $database = new Database();
            $this->conn = $database->getConnection();
        } catch (Throwable $e) {
            $this->sendResponse(false, 'Database connection failed', null, 500);
        }

        $this->customerId = (int)($_SESSION['user']['id'] ?? 0);
        
        // NEW: Set upload directory
        $this->uploadDir = __DIR__ . '/../../../uploads/feedback/'; // NEW
        
        // NEW: Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) { // NEW
            mkdir($this->uploadDir, 0755, true); // NEW
        } // NEW
    }

    public function createFeedback(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(false, 'Method not allowed', null, 405);
        }

        // NEW: Handle both JSON and multipart/form-data
        $orderId = null; // NEW
        $rating = null; // NEW
        $comment = ''; // NEW

        if (!empty($_POST)) { // NEW
            // Form data (with image) // NEW
            $orderId = (int)($_POST['order_id'] ?? 0); // NEW
            $rating = (int)($_POST['rating'] ?? 0); // NEW
            $comment = trim($_POST['comment'] ?? ''); // NEW
        } else { // NEW
            // JSON data (without image - backward compatible) // NEW
            $input = json_decode(file_get_contents('php://input'), true); // NEW
            if (!$input) { // NEW
                $this->sendResponse(false, 'Invalid request data', null, 400); // NEW
            } // NEW
            $orderId = (int)($input['order_id'] ?? 0); // NEW
            $rating = (int)($input['rating'] ?? 0); // NEW
            $comment = trim($input['comment'] ?? ''); // NEW
        } // NEW

        if (!$orderId || !$rating) { // MODIFIED
            $this->sendResponse(false, 'Order ID and rating are required', null, 400);
        }

        $orderId = (int)$input['order_id'];
        $rating = (int)$input['rating'];
        $comment = trim($input['comment'] ?? '');

        // Validate rating
        if ($rating < 1 || $rating > 5) {
            $this->sendResponse(false, 'Rating must be between 1 and 5', null, 400);
        }
// NEW: Handle image upload
        $imagePath = null; // NEW
        $imageUploadedAt = null; // NEW
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) { // NEW
            $uploadResult = $this->handleImageUpload($_FILES['image']); // NEW
            if ($uploadResult['success']) { // NEW
                $imagePath = $uploadResult['path']; // NEW
                $imageUploadedAt = date('Y-m-d H:i:s'); // NEW
            } else { // NEW
                $this->sendResponse(false, $uploadResult['message'], null, 400); // NEW
            } // NEW
        } // NEW

        // NEW: Insert feedback with image fields
        $insertSql = "
            INSERT INTO feedback (
                order_id, 
                customer_id, 
                rating, 
                comment, 
                image_path,          // NEW
                image_uploaded_at,   // NEW
                status, 
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW()) // NEW: added 2 more placeholders
        ";
        try {
            // Verify order belongs to customer and is completed
            $orderSql = "
                SELECT id, status 
                FROM orders 
                WHERE id = ? AND customer_id = ?
                LIMIT 1
            ";
            $orderStmt = $this->conn->prepare($orderSql);
            $orderStmt->execute([$orderId, $this->customerId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->sendResponse(false, 'Order not found', null, 404);
            }

            // Check if feedback already exists
            $checkSql = "SELECT id FROM feedback WHERE order_id = ? LIMIT 1";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->execute([$orderId]);
            
            if ($checkStmt->fetch()) {
                $this->sendResponse(false, 'Feedback already submitted for this order', null, 400);
            }

            // Insert feedback
            $insertSql = "
                INSERT INTO feedback (order_id, customer_id, rating, comment, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ";
            $insertStmt = $this->conn->prepare($insertSql);
            $insertStmt->execute([$orderId, $this->customerId, $rating, $comment]);

            $this->sendResponse(true, 'Feedback submitted successfully. It will be published after review.', [
                'feedback_id' => (int)$this->conn->lastInsertId()
            ]);
        } catch (Throwable $e) {
            error_log("Create feedback error: " . $e->getMessage());
            $this->sendResponse(false, 'Failed to submit feedback', null, 500);
        }
    }
// NEW: Handle image upload with validation
    private function handleImageUpload(array $file): array { // NEW ENTIRE FUNCTION
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        $fileType = strtolower($file['type']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return [
                'success' => false,
                'message' => 'Invalid file type. Only JPG, JPEG, and PNG are allowed.'
            ];
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return [
                'success' => false,
                'message' => 'Image size must be less than 5MB.'
            ];
        }

        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $extension = 'jpg'; // Default to jpg
        }

        // Generate unique filename
        $filename = 'feedback_' . $this->customerId . '_' . time() . '_' . uniqid() . '.' . $extension;
        $targetPath = $this->uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return [
                'success' => false,
                'message' => 'Failed to save image. Please try again.'
            ];
        }

        // Optional: Strip EXIF data for privacy (requires GD or Imagick)
        $this->stripExifData($targetPath, $extension);

        // Return relative path for database storage
        $relativePath = 'uploads/feedback/' . $filename;
        
        return [
            'success' => true,
            'path' => $relativePath,
            'filename' => $filename
        ];
    }

    // NEW: Strip EXIF data from images for privacy
    private function stripExifData(string $filepath, string $extension): void { // NEW ENTIRE FUNCTION
        try {
            if (!function_exists('imagecreatefromjpeg')) {
                return; // GD not available
            }

            $image = null;
            
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($filepath);
                    if ($image) {
                        imagejpeg($image, $filepath, 90);
                    }
                    break;
                case 'png':
                    $image = imagecreatefrompng($filepath);
                    if ($image) {
                        imagepng($image, $filepath, 9);
                    }
                    break;
            }

            if ($image) {
                imagedestroy($image);
            }
        } catch (Throwable $e) {
            error_log("EXIF strip error: " . $e->getMessage());
            // Non-critical, continue without stripping
        }
    }
    private function sendResponse(bool $success, string $message, ?array $data = null, ?int $code = null): void {
        if ($code !== null) {
            http_response_code($code);
        }

        $response = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            $response = array_merge($response, $data);
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$api = new FeedbackAPI();
$api->createFeedback();