<?php
// Account management API with role-based permissions
// ✅ FIXED: Delete replaced with Active/Inactive Toggle
declare(strict_types=1);

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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/guard.php';

class AdminAccountsAPI
{
    private PDO $conn;
    private array $currentUser;
    private string $currentRole;

    public function __construct()
    {
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
        $this->currentRole = $this->currentUser['role'];
    }   

    private function resetPassword(): void
    {
        // Only Owner can reset passwords
        if ($this->currentRole !== 'Owner') {
            $this->send(false, 'You do not have permission to reset passwords');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id']) || empty($input['new_password'])) {
            $this->send(false, 'User ID and new password are required');
            return;
        }

        $userId = (int)$input['id'];
        $newPassword = trim((string)$input['new_password']);

        // Validate password
        if (strlen($newPassword) < 6) {
            $this->send(false, 'Password must be at least 6 characters long');
            return;
        }

        try {
            // Check if user exists
            $stmt = $this->conn->prepare('SELECT id, username FROM admin_users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->send(false, 'User not found');
                return;
            }

            // Reset password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare('UPDATE admin_users SET password = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$hashedPassword, $userId]);

            $this->send(true, 'Password reset successfully');
        } catch (Throwable $e) {
            error_log("Reset password error: " . $e->getMessage());
            $this->send(false, 'Failed to reset password');
        }
    }

    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? '';

        match ($action) {
            'list' => $this->listUsers(),
            'create' => $this->createUser(),
            'update' => $this->updateUser(),
            'toggle_status' => $this->toggleUserStatus(),  // ✅ NEW: Replace delete with toggle
            'reset_password' => $this->resetPassword(),
            'view' => $this->viewUser(),
            default => $this->send(false, 'Invalid action')
        };
    }

    private function listUsers(): void
    {
        try {
            $search = trim($_GET['search'] ?? '');
            
            $whereClause = '';
            $params = [];

            if ($search) {
                $whereClause = 'WHERE (username LIKE ? OR full_name LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params = [$searchTerm, $searchTerm];
            }

            // ✅ FIXED: Added status column
            $sql = "SELECT id, username, full_name, role, status, created_at, 
                           CASE WHEN id = ? THEN 0 ELSE 1 END as can_modify
                    FROM admin_users $whereClause
                    ORDER BY 
                        CASE role 
                            WHEN 'Owner' THEN 1 
                            WHEN 'Admin' THEN 2 
                            WHEN 'Secretary' THEN 3 
                        END,
                        full_name";

            $stmt = $this->conn->prepare($sql);

            if ($whereClause) {
                $stmt->execute([...$params, $this->currentUser['id']]);
            } else {
                $stmt->execute([$this->currentUser['id']]);
            }

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ✅ FIXED: Changed can_delete to can_toggle
            foreach ($users as &$user) {
                $user['can_edit'] = $this->canModifyUser($user);
                $user['can_toggle'] = $this->canToggleStatus($user);  // Changed from can_delete
                $user['can_modify'] = (bool)$user['can_modify'];
            }

            $this->send(true, 'Users retrieved successfully', [
                'users' => $users,
                'current_user_role' => $this->currentRole,
                'permissions' => $this->getUserPermissions()
            ]);
        } catch (Throwable $e) {
            error_log("List users error: " . $e->getMessage());
            $this->send(false, 'Failed to retrieve users');
        }
    }

    private function createUser(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->send(false, 'Invalid JSON data');
            return;
        }

        $username = trim((string)($input['username'] ?? ''));
        $fullName = trim((string)($input['full_name'] ?? ''));
        $role = trim((string)($input['role'] ?? ''));
        $password = trim((string)($input['password'] ?? ''));

        // Validate required fields
        if (empty($username) || empty($fullName) || empty($role) || empty($password)) {
            $this->send(false, 'All fields are required');
            return;
        }

        // Validate role
        $validRoles = ['Owner', 'Admin', 'Secretary'];
        if (!in_array($role, $validRoles, true)) {
            $this->send(false, 'Invalid role specified');
            return;
        }

        // Check permission to create role
        if (!$this->canCreateRole($role)) {
            $this->send(false, 'You do not have permission to create this role');
            return;
        }

        // Validate password
        if (strlen($password) < 6) {
            $this->send(false, 'Password must be at least 6 characters long');
            return;
        }

        try {
            // Check if username exists
            $stmt = $this->conn->prepare('SELECT id FROM admin_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $this->send(false, 'Username already exists');
                return;
            }

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // ✅ FIXED: Default status is 'active'
            $stmt = $this->conn->prepare(
                'INSERT INTO admin_users (username, full_name, role, password, status, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, "active", NOW(), NOW())'
            );
            $stmt->execute([$username, $fullName, $role, $hashedPassword]);

            $this->send(true, 'User created successfully', ['id' => (int)$this->conn->lastInsertId()]);
        } catch (Throwable $e) {
            error_log("Create user error: " . $e->getMessage());
            $this->send(false, 'Failed to create user');
        }
    }

    private function viewUser(): void
    {
        $userId = (int)($_GET['id'] ?? 0);
        if (!$userId) {
            $this->send(false, 'User ID is required');
            return;
        }

        try {
            // ✅ FIXED: Added status column
            $stmt = $this->conn->prepare(
                'SELECT id, username, full_name, role, status, created_at, updated_at 
                 FROM admin_users 
                 WHERE id = ? 
                 LIMIT 1'
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->send(false, 'User not found');
                return;
            }

            $user['can_edit'] = $this->canModifyUser($user);
            $user['can_toggle'] = $this->canToggleStatus($user);

            $this->send(true, 'User retrieved successfully', ['user' => $user]);
        } catch (Throwable $e) {
            error_log("View user error: " . $e->getMessage());
            $this->send(false, 'Failed to retrieve user details');
        }
    }

    private function updateUser(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id'])) {
            $this->send(false, 'User ID is required');
            return;
        }

        $userId = (int)$input['id'];

        // Get target user
        $stmt = $this->conn->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            $this->send(false, 'User not found');
            return;
        }

        // Check permissions
        if (!$this->canModifyUser($targetUser)) {
            $this->send(false, 'You do not have permission to edit this user');
            return;
        }

        $updateFields = [];
        $params = [];

        // Update username
        if (isset($input['username']) && trim($input['username']) !== '') {
            $newUsername = trim($input['username']);
            // Check if username is taken by another user
            $stmt = $this->conn->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ? LIMIT 1');
            $stmt->execute([$newUsername, $userId]);
            if ($stmt->fetch()) {
                $this->send(false, 'Username already exists');
                return;
            }
            $updateFields[] = 'username = ?';
            $params[] = $newUsername;
        }

        // Update full name
        if (isset($input['full_name']) && trim($input['full_name']) !== '') {
            $updateFields[] = 'full_name = ?';
            $params[] = trim($input['full_name']);
        }

        // Update role
        if (isset($input['role']) && trim($input['role']) !== '') {
            $newRole = trim($input['role']);
            // Validate role
            if (!in_array($newRole, ['Owner', 'Admin', 'Secretary'], true)) {
                $this->send(false, 'Invalid role specified');
                return;
            }
            // Check permission to assign role
            if (!$this->canCreateRole($newRole)) {
                $this->send(false, 'You do not have permission to assign this role');
                return;
            }
            $updateFields[] = 'role = ?';
            $params[] = $newRole;
        }

        if (empty($updateFields)) {
            $this->send(false, 'No fields to update');
            return;
        }

        try {
            $params[] = $userId;
            $sql = 'UPDATE admin_users SET ' . implode(', ', $updateFields) . ', updated_at = NOW() WHERE id = ?';
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            $this->send(true, 'User updated successfully');
        } catch (Throwable $e) {
            error_log("Update user error: " . $e->getMessage());
            $this->send(false, 'Failed to update user');
        }
    }

    // ✅ NEW FUNCTION: Toggle user status (active/inactive)
    private function toggleUserStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id'])) {
            $this->send(false, 'User ID is required');
            return;
        }

        $userId = (int)$input['id'];

        // Can't toggle own status
        if ($userId === $this->currentUser['id']) {
            $this->send(false, 'You cannot change your own account status');
            return;
        }

        // Get target user
        $stmt = $this->conn->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            $this->send(false, 'User not found');
            return;
        }

        // Check permissions
        if (!$this->canToggleStatus($targetUser)) {
            $this->send(false, 'You do not have permission to change this user\'s status');
            return;
        }

        try {
            // Toggle status
            $newStatus = $targetUser['status'] === 'active' ? 'inactive' : 'active';
            $stmt = $this->conn->prepare('UPDATE admin_users SET status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$newStatus, $userId]);

            $this->send(true, 'User status updated to ' . $newStatus, ['new_status' => $newStatus]);
        } catch (Throwable $e) {
            error_log("Toggle user status error: " . $e->getMessage());
            $this->send(false, 'Failed to update user status');
        }
    }

    // Permission check methods
    private function canCreateRole(string $role): bool
    {
        switch ($this->currentRole) {
            case 'Owner':
                return true; // Owner can create all roles
            case 'Admin':
                return $role === 'Secretary'; // Admin can only create Secretary
            case 'Secretary':
                return false; // Secretary cannot create users
            default:
                return false;
        }
    }

    private function canModifyUser(array $targetUser): bool
    {
        // Cannot modify self
        if ($targetUser['id'] === $this->currentUser['id']) {
            return false;
        }

        $targetRole = $targetUser['role'];

        switch ($this->currentRole) {
            case 'Owner':
                return true; // Owner can modify anyone except themselves
            case 'Admin':
                return $targetRole === 'Secretary'; // Admin can only modify Secretary
            case 'Secretary':
                return false; // Secretary cannot modify users
            default:
                return false;
        }
    }

    // ✅ NEW FUNCTION: Permission check for toggling status
    private function canToggleStatus(array $targetUser): bool
    {
        // Cannot toggle self
        if ($targetUser['id'] === $this->currentUser['id']) {
            return false;
        }

        $targetRole = $targetUser['role'];

        switch ($this->currentRole) {
            case 'Owner':
                // Owner can toggle anyone except if they're toggling another owner 
                // and it would leave no active owners
                if ($targetRole === 'Owner' && $targetUser['status'] === 'active') {
                    // Check if there are other active owners
                    $stmt = $this->conn->prepare(
                        'SELECT COUNT(*) as count FROM admin_users 
                         WHERE role = "Owner" AND status = "active" AND id != ?'
                    );
                    $stmt->execute([$targetUser['id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result['count'] > 0; // Can only toggle if there are other active owners
                }
                return true;
            case 'Admin':
                return $targetRole === 'Secretary'; // Admin can only toggle Secretary
            case 'Secretary':
                return false; // Secretary cannot toggle users
            default:
                return false;
        }
    }

    private function getUserPermissions(): array
    {
        return [
            'can_create_owner' => $this->currentRole === 'Owner',
            'can_create_admin' => in_array($this->currentRole, ['Owner', 'Admin'], true),
            'can_create_secretary' => $this->currentRole !== 'Secretary',
            'can_toggle_users' => $this->currentRole !== 'Secretary'  // ✅ Changed from can_delete_users
        ];
    }

    private function send(bool $success, string $message, ?array $data = null, int $statusCode = 200): never
    {
        http_response_code($success ? $statusCode : ($statusCode === 200 ? 400 : $statusCode));
        
        $response = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response);
        exit;
    }
}

// Initialize and handle request
try {
    $api = new AdminAccountsAPI();
    $api->handleRequest();
} catch (Throwable $e) {
    error_log("Admin Accounts API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
}