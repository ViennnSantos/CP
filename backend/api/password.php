<?php
// Password reset API for customers
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/mailer.php';

class PasswordResetAPI
{
    private PDO $conn;

    public function __construct()
    {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
        } catch (Throwable $e) {
            $this->send(false, 'Database connection failed');
        }
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        $action = $_GET['action'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            $this->send(false, 'Invalid JSON input');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->send(false, 'Invalid JSON input');
            return;
        }

        if (($input['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
            $this->send(false, 'Invalid CSRF');
            return;
        }


        switch ($action) {
            case 'request':
                $this->requestPasswordReset($input);
                break;
            case 'reset':
                $this->resetPassword($input);
                break;
            default:
                $this->send(false, 'Invalid action');
        }
    }

    private function requestPasswordReset(array $input): void
    {
        if (empty($input['email'])) {
            $this->send(false, 'Email is required');
            return;
        }

        $email = strtolower(trim((string)$input['email']));

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->send(false, 'Invalid email format');
            return;
        }

        try {
            // Check if customer exists
            $stmt = $this->conn->prepare('SELECT id, full_name, email FROM customers WHERE email = ? AND email_verified = 1 LIMIT 1');
            $stmt->execute([$email]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                // Don't reveal if email exists or not for security
                $this->send(true, 'If the email exists in our system, a reset code has been sent');
                return;
            }

            // Generate reset code (6-digit numeric)
            $resetCode = sprintf('%06d', random_int(100000, 999999));
            $resetExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // REPLACE: Update customer with correct column names
            $updateStmt = $this->conn->prepare('
                UPDATE customers 
                SET password_reset_code = ?, 
                    password_reset_expires = ?,
                    updated_at = NOW()
                WHERE id = ?
            ');
            $updateStmt->execute([$resetCode, $resetExpires, $customer['id']]);

            // Send password reset email
            if (class_exists('Mailer')) {
                $mailer = new Mailer();
                $emailSent = $mailer->sendPasswordResetCode($email, $customer['full_name'], $resetCode);

                if ($emailSent) {
                    error_log("Password reset email sent successfully to: {$email}");
                } else {
                    error_log("Failed to send password reset email to: {$email}");
                }
            } else {
                // Log reset code for testing without email
                error_log("Password reset code for {$email}: {$resetCode}");
            }

            $this->send(true, 'If the email exists in our system, a reset code has been sent');
        } catch (PDOException $e) {
            error_log("Password reset request error: " . $e->getMessage());
            $this->send(false, 'Failed to process password reset request');
        }
    }

    private function resetPassword(array $input): void
    {
        $required = ['email', 'code', 'new_password', 'confirm'];
        foreach ($required as $field) {
            if (empty(trim((string)($input[$field] ?? '')))) {
                $this->send(false, "Field '$field' is required");
                return;
            }
        }

        $email = strtolower(trim((string)$input['email']));
        $code = trim((string)$input['code']);
        $newPassword = (string)$input['new_password'];
        $confirmPassword = (string)$input['confirm'];

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->send(false, 'Invalid email format');
            return;
        }

        // Validate reset code format (6 digits)
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->send(false, 'Invalid reset code format');
            return;
        }

        // Validate passwords match
        if ($newPassword !== $confirmPassword) {
            $this->send(false, 'Passwords do not match');
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->send(false, 'Password must be at least 8 characters long');
            return;
        }


        try {
            // REPLACE: Check with correct column names
            $stmt = $this->conn->prepare('
            SELECT id, full_name, password_reset_code, password_reset_expires 
            FROM customers 
            WHERE email = ? AND email_verified = 1 
            LIMIT 1
        ');
            $stmt->execute([$email]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                $this->send(false, 'Invalid email or reset code');
                return;
            }

            // Check if reset code matches
            if ($customer['password_reset_code'] !== $code) {
                $this->send(false, 'Invalid reset code');
                return;
            }

            // Check if reset code has expired
            if (strtotime($customer['password_reset_expires']) < time()) {
                $this->send(false, 'Reset code has expired. Please request a new one.');
                return;
            }

            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // REPLACE: Update password with correct column names
            $updateStmt = $this->conn->prepare('
            UPDATE customers 
            SET password = ?, 
                password_reset_code = NULL, 
                password_reset_expires = NULL,
                updated_at = NOW()
            WHERE id = ?
        ');
            $updateStmt->execute([$hashedPassword, $customer['id']]);

            $this->send(true, 'Password has been reset successfully');
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $this->send(false, 'Failed to reset password');
        }
    }

    private function send(bool $success, string $message, ?array $data = null, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

// Handle the request
try {
    $api = new PasswordResetAPI();
    $api->handleRequest();
} catch (Throwable $e) {
    error_log("Password reset API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
