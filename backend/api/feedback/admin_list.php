<?php
// backend/api/feedback/admin_list.php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Prevent any HTML output before JSON
ob_start();

try {
    // Check authentication - adjust based on your session structure
    $isAdmin = false;
    
    // Check different possible session structures
    if (isset($_SESSION['staff']) && !empty($_SESSION['staff'])) {
        $isAdmin = true;
    } elseif (isset($_SESSION['admin']) && !empty($_SESSION['admin'])) {
        $isAdmin = true;
    } elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $isAdmin = true;
    } elseif (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && 
              in_array($_SESSION['user']['role'], ['Owner', 'Admin', 'Secretary'])) {
        $isAdmin = true;
    }

    if (!$isAdmin) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
        exit;
    }

    // Try to include config using multiple possible paths
    $configPaths = [
        __DIR__ . '/../../config/app.php',
        __DIR__ . '/../../../backend/config/app.php',
        __DIR__ . '/../../config/database.php'
    ];

    $configLoaded = false;
    foreach ($configPaths as $configPath) {
        if (file_exists($configPath)) {
            require_once $configPath;
            $configLoaded = true;
            break;
        }
    }

    if (!$configLoaded) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Configuration file not found']);
        exit;
    }

    // Get database connection - try different methods
    $db = null;
    
    // Method 1: Check if $pdo exists from config
    if (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    }
    // Method 2: Try Database singleton pattern
    elseif (class_exists('Database')) {
        try {
            $db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            // Continue to next method
        }
    }
    // Method 3: Try direct connection
    if (!$db) {
        $host = 'localhost';
        $dbname = 'rads_tooling';
        $username = 'root';
        $password = '';
        
        try {
            $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }

    // Main query to fetch all feedback with related data
    // FIXED: Uses c.full_name directly since customers table doesn't have first_name/last_name
    // FIXED: Added product names via JOIN with order_items
    $sql = "
        SELECT
            f.id,
            f.rating,
            f.comment,
            f.status,
            f.is_released,
            f.created_at,
            f.released_at,
            o.id AS order_id,
            o.order_code,
            c.id AS customer_id,
            COALESCE(c.full_name, 'Customer') AS customer_name,
            GROUP_CONCAT(oi.name SEPARATOR ', ') AS product_names
        FROM feedback f
        INNER JOIN orders o ON o.id = f.order_id
        INNER JOIN customers c ON c.id = f.customer_id
        LEFT JOIN order_items oi ON oi.order_id = f.order_id
        GROUP BY f.id
        ORDER BY f.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $feedbackList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clean output buffer and send JSON
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'data' => $feedbackList,
        'count' => count($feedbackList)
    ]);

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}