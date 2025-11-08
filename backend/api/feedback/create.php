<?php
// ==========================================
// FEEDBACK CREATION API
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
    }

    public function createFeedback(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(false, 'Method not allowed', null, 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['order_id']) || !isset($input['rating'])) {
            $this->sendResponse(false, 'Order ID and rating are required', null, 400);
        }

        $orderId = (int)$input['order_id'];
        $rating = (int)$input['rating'];
        $comment = trim($input['comment'] ?? '');

        // Validate rating
        if ($rating < 1 || $rating > 5) {
            $this->sendResponse(false, 'Rating must be between 1 and 5', null, 400);
        }

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