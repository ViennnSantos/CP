<?php
//Profile management API
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/guard.php';

class AdminProfileAPI {
    private PDO $conn;
    private array $currentUser;

    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
        } catch (Throwable $e) {
            $this->send(false, 'Database connection failed');
        }

        // Check if user is authenticated staff
        if (empty($_SESSION['staff'])) {
            $this->send(false, 'Unauthorized access', null, 401);
        }

        $this->currentUser = $_SESSION['staff'];
    }

    public function handleRequest(): void {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'get_profile':
                $this->getProfile();
                break;
            case 'update_profile':
                $this->updateProfile();
                break;
            case 'change_password':
                $this->changePassword();
                break;
            case 'upload_avatar':
                $this->uploadAvatar();
                break;
            default:
                $this->send(false, 'Invalid action');
        }
    }

    private function getProfile(): void {
        try {
            $stmt = $this->conn->prepare('
                SELECT id, username, full_name, role, profile_image, created_at 
                FROM admin_users 
                WHERE id = ? 
                LIMIT 1
            ');
            $stmt->execute([$this->currentUser['id']]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                $this->send(false, 'Profile not found');
                return;
            }

            // Remove sensitive data
            unset($profile['password']);

            $this->send(true, 'Profile retrieved successfully', $profile);

        } catch (Throwable $e) {
            error_log("Get profile error: " . $e->getMessage());
            $this->send(false, 'Failed to retrieve profile');
        }
    }

    private function updateProfile(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->send(false, 'Invalid JSON data');
            return;
        }

        $fullName = trim((string)($input['full_name'] ?? ''));
        $username = trim((string)($input['username'] ?? ''));

        if (empty($fullName) || empty($username)) {
            $this->send(false, 'Full name and username are required');
            return;
        }

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $this->send(false, 'Username must be 3-20 characters (letters, numbers, underscore only)');
            return;
        }

        try {
            // Check if username is taken by another user
            $stmt = $this->conn->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ? LIMIT 1');
            $stmt->execute([$username, $this->currentUser['id']]);
            if ($stmt->fetch()) {
                $this->send(false, 'Username already exists');
                return;
            }

            // Update profile
            $stmt = $this->conn->prepare('
                UPDATE admin_users 
                SET full_name = ?, username = ?, updated_at = NOW() 
                WHERE id = ?
            ');
            $stmt->execute([$fullName, $username, $this->currentUser['id']]);

            // Update session data
            $_SESSION['staff']['full_name'] = $fullName;
            $_SESSION['staff']['username'] = $username;
            $_SESSION['admin_name'] = $fullName;

            if (!empty($_SESSION['user'])) {
                $_SESSION['user']['name'] = $fullName;
                $_SESSION['user']['username'] = $username;
            }

            $this->send(true, 'Profile updated successfully', [
                'full_name' => $fullName,
                'username' => $username
            ]);

        } catch (Throwable $e) {
            error_log("Update profile error: " . $e->getMessage());
            $this->send(false, 'Failed to update profile');
        }
    }

    private function changePassword(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->send(false, 'Invalid JSON data');
            return;
        }

        $currentPassword = (string)($input['current_password'] ?? '');
        $newPassword = (string)($input['new_password'] ?? '');
        $confirmPassword = (string)($input['confirm_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->send(false, 'All password fields are required');
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->send(false, 'New passwords do not match');
            return;
        }

        if (strlen($newPassword) < 6) {
            $this->send(false, 'New password must be at least 6 characters long');
            return;
        }

        try {
            // Verify current password
            $stmt = $this->conn->prepare('SELECT password FROM admin_users WHERE id = ? LIMIT 1');
            $stmt->execute([$this->currentUser['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $this->send(false, 'Current password is incorrect');
                return;
            }

            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare('
                UPDATE admin_users 
                SET password = ?, updated_at = NOW() 
                WHERE id = ?
            ');
            $stmt->execute([$hashedPassword, $this->currentUser['id']]);

            $this->send(true, 'Password changed successfully');

        } catch (Throwable $e) {
            error_log("Change password error: " . $e->getMessage());
            $this->send(false, 'Failed to change password');
        }
    }

    private function uploadAvatar(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $this->send(false, 'No valid file uploaded');
            return;
        }

        $file = $_FILES['avatar'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if ($file['size'] > $maxSize) {
            $this->send(false, 'File size too large (max 2MB)');
            return;
        }

        if (!in_array($file['type'], $allowedTypes)) {
            $this->send(false, 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed');
            return;
        }

        try {
            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/../../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $this->currentUser['id'] . '_' . time() . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $this->send(false, 'Failed to save file');
                return;
            }

            // Delete old avatar if exists
            $stmt = $this->conn->prepare('SELECT profile_image FROM admin_users WHERE id = ? LIMIT 1');
            $stmt->execute([$this->currentUser['id']]);
            $oldAvatar = $stmt->fetchColumn();
            
            if ($oldAvatar && file_exists(__DIR__ . '/../../' . $oldAvatar)) {
                unlink(__DIR__ . '/../../' . $oldAvatar);
            }

            // Update database
            $dbPath = 'uploads/avatars/' . $filename;
            $stmt = $this->conn->prepare('
                UPDATE admin_users 
                SET profile_image = ?, updated_at = NOW() 
                WHERE id = ?
            ');
            $stmt->execute([$dbPath, $this->currentUser['id']]);

            // Update session
            $_SESSION['staff']['avatar'] = $dbPath;

            $this->send(true, 'Avatar updated successfully', [
                'avatar_url' => '/' . $dbPath
            ]);

        } catch (Throwable $e) {
            error_log("Upload avatar error: " . $e->getMessage());
            $this->send(false, 'Failed to upload avatar');
        }
    }

    private function send(bool $success, string $message, $data = null, int $code = null): void {
        if ($code) {
            http_response_code($code);
        }

        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$api = new AdminProfileAPI();
$api->handleRequest();