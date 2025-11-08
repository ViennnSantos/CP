<?php
// ==========================================
// DEBUG SCRIPT - Test Connection & Session
// ==========================================
// Place this file in: /backend/api/debug_orders.php
// Access it: http://yourdomain.com/backend/api/debug_orders.php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

header('Content-Type: application/json; charset=utf-8');

$debug = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Check if session exists
$debug['tests']['session_exists'] = !empty($_SESSION);
$debug['tests']['session_data'] = $_SESSION ?? null;

// Test 2: Check customer session
$debug['tests']['user_session'] = $_SESSION['user'] ?? null;
$debug['tests']['customer_id'] = $_SESSION['user']['id'] ?? null;
$debug['tests']['user_audience'] = $_SESSION['user']['aud'] ?? null;

// Test 3: Check database file
$dbFile = __DIR__ . '/../config/database.php';
$debug['tests']['database_file_exists'] = file_exists($dbFile);
$debug['tests']['database_file_path'] = $dbFile;

// Test 4: Try to include database
try {
    require_once __DIR__ . '/../config/database.php';
    $debug['tests']['database_class_loaded'] = class_exists('Database');
    
    // Try to connect
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $debug['tests']['database_connection'] = 'SUCCESS';
        
        // Test query - check if orders table exists
        try {
            $stmt = $conn->query("SHOW TABLES LIKE 'orders'");
            $debug['tests']['orders_table_exists'] = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $debug['tests']['orders_table_exists'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Check if feedback table exists
        try {
            $stmt = $conn->query("SHOW TABLES LIKE 'feedback'");
            $debug['tests']['feedback_table_exists'] = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $debug['tests']['feedback_table_exists'] = 'ERROR: ' . $e->getMessage();
        }
        
        // If customer is logged in, try to get their orders
        if (!empty($_SESSION['user']['id'])) {
            try {
                $customerId = (int)$_SESSION['user']['id'];
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?");
                $stmt->execute([$customerId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $debug['tests']['customer_orders_count'] = $result['count'];
            } catch (Exception $e) {
                $debug['tests']['customer_orders_count'] = 'ERROR: ' . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $debug['tests']['database_connection'] = 'FAILED: ' . $e->getMessage();
    }
    
} catch (Exception $e) {
    $debug['tests']['database_class_loaded'] = 'ERROR: ' . $e->getMessage();
}

// Test 5: Check file paths
$debug['tests']['current_file'] = __FILE__;
$debug['tests']['current_dir'] = __DIR__;
$debug['tests']['customer_orders_file'] = __DIR__ . '/customer_orders.php';
$debug['tests']['customer_orders_exists'] = file_exists(__DIR__ . '/customer_orders.php');

// Test 6: Check permissions
$debug['tests']['file_readable'] = is_readable(__DIR__ . '/customer_orders.php');
$debug['tests']['file_writable'] = is_writable(__DIR__);

// Output results
echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);