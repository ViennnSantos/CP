<?php
// ==========================================
// CUSTOMER ORDERS API - WITH ORDER_ADDRESSES
// IMPROVED VERSION WITH BETTER DEBUGGING
// ==========================================
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Check if customer is logged in
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$customerId = (int)($_SESSION['user']['id'] ?? 0);

if ($customerId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit;
}

// Include database
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? 'list';

// ==========================================
// IMPROVED DELIVERY ADDRESS FUNCTION
// ==========================================
function getDeliveryAddress($conn, $orderId) {
    try {
        // First, check if any address exists
        $checkSql = "SELECT COUNT(*) as count FROM order_addresses WHERE order_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$orderId]);
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            error_log("No address found for order_id: $orderId");
            return 'N/A';
        }
        
        // Get the address with priority: delivery > pickup
        $sql = "
            SELECT 
                first_name,
                last_name,
                phone,
                email,
                street,
                barangay,
                city,
                province,
                postal,
                type
            FROM order_addresses 
            WHERE order_id = ?
            ORDER BY 
                CASE 
                    WHEN LOWER(TRIM(type)) = 'delivery' THEN 1
                    WHEN LOWER(TRIM(type)) = 'pickup' THEN 2
                    ELSE 3
                END
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId]);
        $addr = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$addr) {
            error_log("Query returned no results for order_id: $orderId");
            return 'N/A';
        }
        
        // Log what we got
        error_log("Address data for order $orderId: type=" . ($addr['type'] ?? 'null'));
        
        // Check address type
        $addrType = strtolower(trim($addr['type'] ?? ''));
        
        // If pickup type, return "For Pickup"
        if ($addrType === 'pickup') {
            return 'For Pickup';
        }
        
        // Build address string for delivery
        $parts = [];
        
        if (!empty(trim($addr['street'] ?? ''))) {
            $parts[] = trim($addr['street']);
        }
        if (!empty(trim($addr['barangay'] ?? ''))) {
            $parts[] = trim($addr['barangay']);
        }
        if (!empty(trim($addr['city'] ?? ''))) {
            $parts[] = trim($addr['city']);
        }
        if (!empty(trim($addr['province'] ?? ''))) {
            $parts[] = trim($addr['province']);
        }
        if (!empty(trim($addr['postal'] ?? ''))) {
            $parts[] = trim($addr['postal']);
        }
        
        $addressLine = !empty($parts) ? implode(', ', $parts) : '';
        
        // Build contact info
        $contact = [];
        $firstName = trim($addr['first_name'] ?? '');
        $lastName = trim($addr['last_name'] ?? '');
        
        if (!empty($firstName) || !empty($lastName)) {
            $name = trim($firstName . ' ' . $lastName);
            if (!empty($name)) {
                $contact[] = $name;
            }
        }
        
        if (!empty(trim($addr['phone'] ?? ''))) {
            $contact[] = trim($addr['phone']);
        }
        
        // Combine contact and address
        $result = '';
        if (!empty($contact)) {
            $result = implode(' - ', $contact);
            if (!empty($addressLine)) {
                $result .= "\n" . $addressLine;
            }
        } else {
            $result = $addressLine;
        }
        
        // If still empty, return N/A
        if (empty(trim($result))) {
            error_log("Address data exists but all fields are empty for order_id: $orderId");
            return 'N/A';
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error in getDeliveryAddress for order $orderId: " . $e->getMessage());
        return 'N/A';
    }
}

// ==========================================
// LIST ORDERS
// ==========================================
if ($action === 'list') {
    try {
        $status = $_GET['status'] ?? 'all';
        
        $whereClause = "WHERE o.customer_id = ?";
        $params = [$customerId];

        if ($status !== 'all') {
            $whereClause .= " AND LOWER(o.status) = LOWER(?)";
            $params[] = $status;
        }

        // Query orders with payment info
        $sql = "
            SELECT 
                o.*,
                p.method as payment_method,
                COALESCE(
                    (SELECT SUM(pv.amount_reported) 
                     FROM payment_verifications pv 
                     WHERE pv.order_id = o.id 
                     AND UPPER(COALESCE(pv.status,'')) IN ('VERIFIED','APPROVED')
                    ), 0
                ) as amount_paid,
                (SELECT COUNT(*) FROM feedback f WHERE f.order_id = o.id) as has_feedback
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            $whereClause
            ORDER BY o.order_date DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process each order
        foreach ($orders as &$order) {
            // Get items
            $itemsSql = "
                SELECT 
                    id,
                    order_id,
                    name,
                    quantity,
                    unit_price as price,
                    subtotal,
                    line_total,
                    image
                FROM order_items 
                WHERE order_id = ?
            ";
            
            $itemsStmt = $conn->prepare($itemsSql);
            $itemsStmt->execute([$order['id']]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $itemsSubtotal = 0;
            foreach ($items as &$item) {
                $itemTotal = !empty($item['line_total']) ? (float)$item['line_total'] : (float)$item['subtotal'];
                if ($itemTotal <= 0) {
                    $itemTotal = (float)$item['price'] * (float)$item['quantity'];
                }
                $item['subtotal'] = $itemTotal;
                $itemsSubtotal += $itemTotal;
                
                if (empty($item['image'])) {
                    $item['image'] = '/RADS-TOOLING/assets/images/cab1.jpg';
                }
            }
            unset($item);
            
            $order['items'] = $items;
            $order['items_subtotal'] = $itemsSubtotal;
            $order['has_feedback'] = (int)$order['has_feedback'] > 0;
            
            // Calculate payment status
            $total = (float)($order['total_amount'] ?? 0);
            $paid = (float)($order['amount_paid'] ?? 0);
            $remaining = max(0, $total - $paid);
            
            $order['remaining_balance'] = $remaining;
            
            if ($remaining <= 0.01) {
                $order['payment_status_text'] = 'Fully Paid';
            } elseif ($paid > 0) {
                $order['payment_status_text'] = 'Partially Paid (' . number_format(($paid/$total)*100, 0) . '%)';
            } else {
                $order['payment_status_text'] = 'Pending Payment';
            }
            
            // Get delivery address
            $order['delivery_address'] = getDeliveryAddress($conn, $order['id']);
        }
        unset($order);

        // Get counts
        $countsSql = "
            SELECT 
                COUNT(*) as all_count,
                SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN LOWER(status) = 'processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN LOWER(status) = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
            FROM orders
            WHERE customer_id = ?
        ";
        
        $countsStmt = $conn->prepare($countsSql);
        $countsStmt->execute([$customerId]);
        $counts = $countsStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'orders' => $orders,
            'counts' => [
                'all' => (int)$counts['all_count'],
                'pending' => (int)$counts['pending_count'],
                'processing' => (int)$counts['processing_count'],
                'completed' => (int)$counts['completed_count'],
                'cancelled' => (int)$counts['cancelled_count']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// ==========================================
// ORDER DETAILS
// ==========================================
elseif ($action === 'details') {
    $orderId = (int)($_GET['id'] ?? 0);
    
    if ($orderId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit;
    }

    try {
        // Get order with payment info
        $sql = "
            SELECT 
                o.*,
                p.method as payment_method,
                COALESCE(
                    (SELECT SUM(pv.amount_reported) 
                     FROM payment_verifications pv 
                     WHERE pv.order_id = o.id 
                     AND UPPER(COALESCE(pv.status,'')) IN ('VERIFIED','APPROVED')
                    ), 0
                ) as amount_paid,
                (SELECT COUNT(*) FROM feedback f WHERE f.order_id = o.id) as has_feedback
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.id = ? AND o.customer_id = ?
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$orderId, $customerId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        // Get items
        $itemsSql = "
            SELECT 
                id,
                name,
                quantity,
                unit_price as price,
                subtotal,
                line_total,
                image
            FROM order_items 
            WHERE order_id = ?
        ";
        $itemsStmt = $conn->prepare($itemsSql);
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $itemsSubtotal = 0;
        foreach ($items as &$item) {
            $itemTotal = !empty($item['line_total']) ? (float)$item['line_total'] : (float)$item['subtotal'];
            if ($itemTotal <= 0) {
                $itemTotal = (float)$item['price'] * (float)$item['quantity'];
            }
            $item['subtotal'] = $itemTotal;
            $itemsSubtotal += $itemTotal;
            
            if (empty($item['image'])) {
                $item['image'] = '/RADS-TOOLING/assets/images/cab1.jpg';
            }
        }
        unset($item);

        $order['items'] = $items;
        $order['items_subtotal'] = $itemsSubtotal;
        $order['has_feedback'] = (int)$order['has_feedback'] > 0;
        
        // Calculate payment details
        $total = (float)($order['total_amount'] ?? 0);
        $paid = (float)($order['amount_paid'] ?? 0);
        $remaining = max(0, $total - $paid);
        
        $order['remaining_balance'] = $remaining;
        
        // Calculate tax
        $tax = max(0, $total - $itemsSubtotal);
        $order['tax_amount'] = $tax;
        
        // Payment status text
        if ($remaining <= 0.01) {
            $order['payment_status_text'] = 'Fully Paid';
        } elseif ($paid > 0) {
            $order['payment_status_text'] = 'Partially Paid (' . number_format(($paid/$total)*100, 0) . '%)';
        } else {
            $order['payment_status_text'] = 'Pending Payment';
        }
        
        // Get delivery address
        $order['delivery_address'] = getDeliveryAddress($conn, $orderId);

        echo json_encode([
            'success' => true,
            'message' => 'Order details retrieved',
            'order' => $order
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}