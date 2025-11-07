<?php
// backend/api/report_data.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 2) . '/includes/guard.php';
require_once dirname(__DIR__) . '/config/database.php';

guard_require_staff();

$db = new Database();
$conn = $db->getConnection();

$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date = $_GET['to'] ?? date('Y-m-d');

try {
    // Total Sales
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_sales
        FROM orders
        WHERE order_date BETWEEN ? AND ?
        AND status != 'Cancelled'
    ");
    $stmt->execute([$from_date, $to_date]);
    $salesData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total Orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_orders
        FROM orders
        WHERE order_date BETWEEN ? AND ?
    ");
    $stmt->execute([$from_date, $to_date]);
    $ordersData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Average Order Value
    $avgOrder = $ordersData['total_orders'] > 0 
        ? $salesData['total_sales'] / $ordersData['total_orders'] 
        : 0;
    
    // Fully Paid Orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as fully_paid
        FROM orders
        WHERE order_date BETWEEN ? AND ?
        AND payment_status = 'Fully Paid'
    ");
    $stmt->execute([$from_date, $to_date]);
    $fullyPaidData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Cancelled Orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as cancelled
        FROM orders
        WHERE order_date BETWEEN ? AND ?
        AND status = 'Cancelled'
    ");
    $stmt->execute([$from_date, $to_date]);
    $cancelledData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pending Orders
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending
        FROM orders
        WHERE order_date BETWEEN ? AND ?
        AND status = 'Pending'
    ");
    $stmt->execute([$from_date, $to_date]);
    $pendingData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // New Customers
    $stmt = $conn->prepare("
        SELECT COUNT(*) as new_customers
        FROM customers
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$from_date, $to_date]);
    $customersData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Feedbacks
    $stmt = $conn->prepare("
        SELECT COUNT(*) as feedbacks
        FROM feedback
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$from_date, $to_date]);
    $feedbackData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Most Ordered Item
    $stmt = $conn->prepare("
        SELECT oi.name, COUNT(*) as order_count
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_date BETWEEN ? AND ?
        GROUP BY oi.name
        ORDER BY order_count DESC
        LIMIT 1
    ");
    $stmt->execute([$from_date, $to_date]);
    $mostOrderedData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_sales' => (float)$salesData['total_sales'],
            'total_orders' => (int)$ordersData['total_orders'],
            'avg_order' => round($avgOrder, 2),
            'fully_paid' => (int)$fullyPaidData['fully_paid'],
            'cancelled' => (int)$cancelledData['cancelled'],
            'pending' => (int)$pendingData['pending'],
            'new_customers' => (int)$customersData['new_customers'],
            'feedbacks' => (int)$feedbackData['feedbacks'],
            'most_ordered_item' => $mostOrderedData['name'] ?? 'N/A'
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Report data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch report data']);
}