<?php
// Admin customer management API
// ✅ FIXED: Removed delete function, show created_at in list
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');  // ✅ REMOVED DELETE
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Error handler to ensure JSON responses
function handleError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Set error handler
set_error_handler(function($severity, $message, $file, $line) {
    error_log("PHP Error: $message in $file on line $line");
    if (!headers_sent()) {
        handleError('Server error occurred');
    }
});

try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    error_log("Database config error: " . $e->getMessage());
    handleError('Database configuration error');
}

// Check if user is staff/admin - simplified check
function require_staff_api() {
    // Check multiple session formats for compatibility
    if (!empty($_SESSION['user']) && ($_SESSION['user']['aud'] ?? null) === 'staff') {
        return; // Valid staff session
    }
    if (!empty($_SESSION['staff'])) {
        return; // Valid staff session
    }
    if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return; // Legacy admin session
    }
    handleError('Unauthorized', 401);
}

// Check authentication
require_staff_api();

// Get database connection
try {
    $pdo = (new Database())->getConnection();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    handleError('Database connection error');
}

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            get_customers($pdo);
            break;
        case 'view':
            view_customer($pdo);
            break;
        case 'update':
            update_customer($pdo);
            break;
        // ✅ REMOVED: delete case
        default:
            handleError('Invalid action', 400);
            break;
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    handleError('An error occurred while processing your request');
}

function get_customers(PDO $pdo) {
    try {
        $search = $_GET['search'] ?? '';
        $limit = min((int)($_GET['limit'] ?? 50), 100); // Cap at 100
        $offset = max((int)($_GET['offset'] ?? 0), 0);
        
        $where = '';
        $params = [];
        
        if ($search) {
            $where = 'WHERE full_name LIKE ? OR email LIKE ? OR username LIKE ?';
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        // ✅ FIXED: Added created_at to results
        $sql = "SELECT id, username, full_name, email, phone, 
                       email_verified, created_at, profile_image,
                       DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') as formatted_date
                FROM customers 
                $where
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM customers $where";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data' => [
                'customers' => $customers ?: [],
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Database error in get_customers: " . $e->getMessage());
        handleError('Database error while fetching customers');
    }
}

function view_customer(PDO $pdo) {
    try {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            handleError('Customer ID required', 400);
        }
        
        // ✅ FIXED: Show formatted created date
        $stmt = $pdo->prepare('
            SELECT id, username, full_name, email, phone, address,
                   email_verified, created_at,
                   DATE_FORMAT(created_at, "%M %d, %Y %h:%i %p") as formatted_date,
                   profile_image
            FROM customers 
            WHERE id = ?
        ');
        $stmt->execute([$id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            handleError('Customer not found', 404);
        }
        
        // Get customer's order count and total spent
        $orderStmt = $pdo->prepare('
            SELECT COUNT(*) as order_count, 
                   COALESCE(SUM(total_amount), 0) as total_spent
            FROM orders 
            WHERE customer_id = ?
        ');
        $orderStmt->execute([$id]);
        $orderStats = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        $customer['order_count'] = (int)($orderStats['order_count'] ?? 0);
        $customer['total_spent'] = (float)($orderStats['total_spent'] ?? 0);
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer details retrieved',
            'data' => $customer
        ]);
    } catch (PDOException $e) {
        error_log("Database error in view_customer: " . $e->getMessage());
        handleError('Database error while fetching customer details');
    }
}

function update_customer(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('Method not allowed', 405);
    }
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            handleError('Invalid JSON data', 400);
        }
        
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            handleError('Customer ID required', 400);
        }
        
        $updateFields = [];
        $params = [];
        
        if (isset($input['full_name']) && trim($input['full_name']) !== '') {
            $updateFields[] = 'full_name = ?';
            $params[] = trim($input['full_name']);
        }
        
        if (isset($input['email']) && trim($input['email']) !== '') {
            if (!filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL)) {
                handleError('Invalid email format', 400);
            }
            $updateFields[] = 'email = ?';
            $params[] = trim($input['email']);
        }
        
        if (isset($input['phone'])) {
            $updateFields[] = 'phone = ?';
            $params[] = trim($input['phone']);
        }
        
        if (isset($input['address'])) {
            $updateFields[] = 'address = ?';
            $params[] = trim($input['address']);
        }
        
        if (isset($input['email_verified'])) {
            $updateFields[] = 'email_verified = ?';
            $params[] = (int)$input['email_verified'];
        }
        
        if (empty($updateFields)) {
            handleError('No fields to update', 400);
        }
        
        $params[] = $id; // for WHERE clause
        
        $sql = 'UPDATE customers SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            handleError('Customer not found or no changes made', 404);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully'
        ]);
    } catch (PDOException $e) {
        error_log("Database error in update_customer: " . $e->getMessage());
        if ($e->errorInfo[1] === 1062) { // Duplicate entry
            handleError('Email already exists');
        } else {
            handleError('Database error while updating customer');
        }
    }
}

// ✅ REMOVED: delete_customer() function completely
?>