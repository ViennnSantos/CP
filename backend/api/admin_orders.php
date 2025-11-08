<?php
// backend/api/admin_orders.php -  API (rewritten)
// compute payment status on-the-fly: Fully Paid / With Balance / Pending
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../includes/guard.php';

class AdminOrdersAPI {
    private PDO $conn;
    private array $currentUser;
    private string $currentRole;

    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
        } catch (Throwable $e) {
            $this->send(false, 'Database connection failed', null, 500);
        }

        // require staff logged in
        if (empty($_SESSION['staff'])) {
            $this->send(false, 'Unauthorized access', null, 401);
        }

        $this->currentUser = $_SESSION['staff'];
        $this->currentRole = $this->currentUser['role'] ?? 'Staff';
    }

    public function handleRequest(): void {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'list':
                $this->listOrders();
                break;
            case 'view':
                $this->viewOrder();
                break;
            case 'details':
                $this->getOrderDetails();
                break;
            case 'update_status':
                $this->updateOrderStatus();
                break;
            case 'update_payment':
                $this->updatePaymentStatus();
                break;
            default:
                $this->send(false, 'Invalid action');
        }
    }

    private function listOrders(): void {
        try {
            $search = trim((string)($_GET['search'] ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));
            $paymentStatus = trim((string)($_GET['payment_status'] ?? ''));
            $limit = max(1, (int)($_GET['limit'] ?? 50));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $whereClauses = [];
            $params = [];

            if ($search !== '') {
                $whereClauses[] = '(o.order_code LIKE ? OR c.full_name LIKE ? OR c.email LIKE ?)';
                $like = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            if ($status !== '') {
                $whereClauses[] = 'o.status = ?';
                $params[] = $status;
            }

            // Map paymentStatus filter (UI sends friendly labels) to SQL conditions
            if ($paymentStatus !== '') {
                $ps = strtolower($paymentStatus);
                if ($ps === 'fully paid' || $ps === 'fully_paid' || $ps === 'fullypaid') {
                    $whereClauses[] = "(
                        COALESCE((SELECT SUM(p2.amount_paid) FROM payments p2 WHERE p2.order_id = o.id AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')),0)
                        >= COALESCE(o.total_amount,0)
                    )";
                } elseif ($ps === 'with balance' || $ps === 'with_balance' || $ps === 'withbalance') {
                    $whereClauses[] = "(
                        COALESCE((SELECT SUM(p2.amount_paid) FROM payments p2 WHERE p2.order_id = o.id AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')),0) > 0
                        AND COALESCE((SELECT SUM(p2.amount_paid) FROM payments p2 WHERE p2.order_id = o.id AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')),0) < COALESCE(o.total_amount,0)
                    )";
                } elseif ($ps === 'pending') {
                    $whereClauses[] = "(
                        COALESCE((SELECT SUM(p2.amount_paid) FROM payments p2 WHERE p2.order_id = o.id AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')),0) = 0
                    )";
                }
            }

            // Default behavior: if admin did NOT explicitly pick payment_status, hide pure drafts
            $selectedPayment = strtolower(trim((string)($paymentStatus)));
            $selectedStatus = strtolower(trim((string)($status)));
            $isAllPayment = ($selectedPayment === '' || $selectedPayment === 'all' || stripos($selectedPayment, 'all') !== false);
            $isAllStatus = ($selectedStatus === '' || $selectedStatus === 'all' || stripos($selectedStatus, 'all') !== false);

            if ($isAllPayment) {
    // ✅ Show only orders that already have verified/approved payments (hide purely pending)
    $whereClauses[] = "(
        COALESCE((
            SELECT SUM(p2.amount_paid)
            FROM payments p2
            WHERE p2.order_id = o.id 
            AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')
        ), 0) > 0
    )";
}


            if ($isAllStatus) {
                $whereClauses[] = "LOWER(COALESCE(o.status,'')) NOT IN ('draft')";
            }

            $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

            // Select with computed amount_paid (sum of verified/approved payments)
            $sql = "
                SELECT
                    o.id,
                    o.order_code,
                    o.total_amount,
                    o.payment_status AS original_payment_status,
                    o.status,
                    o.order_date,
                    o.mode,
                    c.full_name AS customer_name,
                    c.email AS customer_email,
                    GROUP_CONCAT(DISTINCT oi.name SEPARATOR ', ') AS product_name,
                    COALESCE((
                        SELECT SUM(p2.amount_paid) FROM payments p2
                        WHERE p2.order_id = o.id AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')
                    ), 0) AS amount_paid
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN order_items oi ON oi.order_id = o.id
                $whereClause
                GROUP BY o.id
                ORDER BY o.order_date DESC
                LIMIT ? OFFSET ?
            ";

            // append limit/offset params
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count (use same where clause but without limit/offset)
            $countSql = "
                SELECT COUNT(DISTINCT o.id) as total
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN order_items oi ON oi.order_id = o.id
                $whereClause
            ";
            // count params = params without the last two (limit, offset)
            $countParams = $params;
            if (count($countParams) >= 2) {
                $countParams = array_slice($countParams, 0, -2);
            }

            $countStmt = $this->conn->prepare($countSql);
            $countStmt->execute($countParams);
            $total = (int)$countStmt->fetchColumn();

            // Post-process rows: compute friendly payment_status label + remaining_balance
            foreach ($orders as &$ord) {
                $totalAmt = (float)($ord['total_amount'] ?? 0.0);
                $paid = (float)($ord['amount_paid'] ?? 0.0);

                // decide label
                if ($totalAmt <= 0.0) {
                    $label = $ord['original_payment_status'] ?? 'Pending';
                } else {
                    if ($paid >= $totalAmt - 0.01) {
                        $label = 'Fully Paid';
                    } elseif ($paid > 0.0) {
                        $label = 'With Balance';
                    } else {
                        $label = 'Pending';
                    }
                }

                $ord['payment_status'] = $label;
                // standardize numeric fields for UI
                $ord['amount_paid'] = number_format($paid, 2, '.', '');
                $ord['remaining_balance'] = number_format(max(0.0, $totalAmt - $paid), 2, '.', '');
            }
            unset($ord);

            $this->send(true, 'Orders retrieved successfully', [
                'orders' => $orders,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'current_user_role' => $this->currentRole
            ]);
        } catch (Throwable $e) {
            error_log("List orders error: " . $e->getMessage());
            $this->send(false, 'Failed to retrieve orders', null, 500);
        }
    }

    private function viewOrder(): void {
        $orderId = (int)($_GET['id'] ?? 0);
        if ($orderId <= 0) {
            $this->send(false, 'Order ID is required', null, 400);
        }

        try {
            $sql = "SELECT 
                        o.*,
                        c.full_name as customer_name,
                        c.email as customer_email,
                        c.phone as customer_phone,
                        c.address as customer_address
                    FROM orders o
                    LEFT JOIN customers c ON o.customer_id = c.id
                    WHERE o.id = ?
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->send(false, 'Order not found', null, 404);
            }

            $itemsSql = "SELECT * FROM order_items WHERE order_id = ?";
            $itemsStmt = $this->conn->prepare($itemsSql);
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            $order['items'] = $items;

            $this->send(true, 'Order details retrieved successfully', $order);
        } catch (Throwable $e) {
            error_log("View order error: " . $e->getMessage());
            $this->send(false, 'Failed to retrieve order details', null, 500);
        }
    }

    private function getOrderDetails(): void {
        $orderId = (int)($_GET['id'] ?? 0);
        if ($orderId <= 0) {
            $this->send(false, 'Order ID is required', null, 400);
        }

        try {
            $sql = "SELECT
                        o.*,
                        c.full_name as customer_name,
                        c.email as customer_email,
                        c.phone as customer_phone,
                        COALESCE((SELECT SUM(p2.amount_paid) FROM payments p2 WHERE p2.order_id = o.id AND UPPER(COALESCE(p2.status,'')) IN ('VERIFIED','APPROVED')),0) AS amount_paid,
                        pv.status as payment_verification_status,
                        p.method as payment_method
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN payment_verifications pv ON pv.order_id = o.id
                    LEFT JOIN payments p ON p.order_id = o.id
                    WHERE o.id = ?
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->send(false, 'Order not found', null, 404);
            }

            // Get items
            $itemsSql = "SELECT * FROM order_items WHERE order_id = ?";
            $itemsStmt = $this->conn->prepare($itemsSql);
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate items subtotal and tax
            $itemsSubtotal = 0;
            foreach ($items as &$item) {
                $itemTotal = !empty($item['line_total']) ? (float)$item['line_total'] : (float)$item['subtotal'];
                if ($itemTotal <= 0) {
                    $itemTotal = (float)$item['unit_price'] * (float)$item['qty'];
                }
                $item['item_total'] = $itemTotal;
                $itemsSubtotal += $itemTotal;
            }
            unset($item);

            $totalAmount = (float)($order['total_amount'] ?? 0);
            $taxAmount = max(0, $totalAmount - $itemsSubtotal);
            $taxPercentage = $itemsSubtotal > 0 ? (($taxAmount / $itemsSubtotal) * 100) : 0;

            // Get delivery address
            $addressSql = "SELECT
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
                            LIMIT 1";
            $addressStmt = $this->conn->prepare($addressSql);
            $addressStmt->execute([$orderId]);
            $address = $addressStmt->fetch(PDO::FETCH_ASSOC);

            $deliveryAddress = 'N/A';
            if ($address) {
                $addrType = strtolower(trim($address['type'] ?? ''));
                if ($addrType === 'pickup') {
                    $deliveryAddress = 'For Pickup';
                } else {
                    $parts = [];
                    if (!empty(trim($address['street'] ?? ''))) $parts[] = trim($address['street']);
                    if (!empty(trim($address['barangay'] ?? ''))) $parts[] = trim($address['barangay']);
                    if (!empty(trim($address['city'] ?? ''))) $parts[] = trim($address['city']);
                    if (!empty(trim($address['province'] ?? ''))) $parts[] = trim($address['province']);
                    if (!empty(trim($address['postal'] ?? ''))) $parts[] = trim($address['postal']);

                    $addressLine = !empty($parts) ? implode(', ', $parts) : '';

                    $contact = [];
                    $firstName = trim($address['first_name'] ?? '');
                    $lastName = trim($address['last_name'] ?? '');
                    if (!empty($firstName) || !empty($lastName)) {
                        $name = trim($firstName . ' ' . $lastName);
                        if (!empty($name)) $contact[] = $name;
                    }
                    if (!empty(trim($address['phone'] ?? ''))) $contact[] = trim($address['phone']);

                    $result = '';
                    if (!empty($contact)) {
                        $result = implode(' - ', $contact);
                        if (!empty($addressLine)) {
                            $result .= "\n" . $addressLine;
                        }
                    } else {
                        $result = $addressLine;
                    }

                    if (!empty(trim($result))) {
                        $deliveryAddress = $result;
                    }
                }
            }

            $order['items'] = $items;
            $order['items_subtotal'] = $itemsSubtotal;
            $order['tax_amount'] = $taxAmount;
            $order['tax_percentage'] = $taxPercentage;
            $order['delivery_address'] = $deliveryAddress;

            $amountPaid = (float)$order['amount_paid'];
            $remainingBalance = max(0, $totalAmount - $amountPaid);
            $order['remaining_balance'] = number_format($remainingBalance, 2, '.', '');

            // Compute friendly payment status
            if ($remainingBalance <= 0.01) {
                $order['payment_status_text'] = 'Fully Paid';
            } elseif ($amountPaid > 0) {
                $order['payment_status_text'] = 'With Balance';
            } else {
                $order['payment_status_text'] = 'Pending';
            }

            $this->send(true, 'Order details retrieved successfully', [
                'order' => $order,
                'items' => $items
            ]);
        } catch (Throwable $e) {
            error_log("Get order details error: " . $e->getMessage());
            $this->send(false, 'Failed to retrieve order details', null, 500);
        }
    }

    private function updateOrderStatus(): void {
        if (!in_array($this->currentRole, ['Owner', 'Admin'])) {
            $this->send(false, 'You do not have permission to update order status', null, 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed', null, 405);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['order_id']) || empty($input['status'])) {
            $this->send(false, 'Order ID and status are required', null, 400);
        }
        $orderId = (int)$input['order_id'];
        $newStatus = trim((string)$input['status']);
        $validStatuses = ['Pending', 'Processing', 'Completed', 'Cancelled'];
        if (!in_array($newStatus, $validStatuses)) {
            $this->send(false, 'Invalid status value', null, 400);
        }
        try {
            // Get order details including payment info
            $stmt = $this->conn->prepare('
                SELECT
                    o.id,
                    o.status,
                    o.total_amount,
                    COALESCE((
                        SELECT SUM(p2.amount_paid)
                        FROM payments p2
                        WHERE p2.order_id = o.id
                        AND UPPER(COALESCE(p2.status,\'\')) IN (\'VERIFIED\',\'APPROVED\')
                    ), 0) AS amount_paid
                FROM orders o
                WHERE o.id = ?
                LIMIT 1
            ');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) $this->send(false, 'Order not found', null, 404);

            // Calculate remaining balance
            $totalAmount = (float)($order['total_amount'] ?? 0);
            $amountPaid = (float)($order['amount_paid'] ?? 0);
            $remainingBalance = max(0, $totalAmount - $amountPaid);

            // BUSINESS RULE: Cannot set status to Completed if remaining balance > 0
            if ($newStatus === 'Completed' && $remainingBalance > 0.01) {
                $this->send(false, "Cannot set status to Completed — remaining balance ₱" . number_format($remainingBalance, 2) . ". Approve payments first.", null, 400);
            }

            // Update order status
            $stmt = $this->conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $orderId]);

            // Add audit log entry
            $logStmt = $this->conn->prepare('
                INSERT INTO order_status_history (order_id, status, changed_by, notes, changed_at)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $adminId = $this->currentUser['id'] ?? 0;
            $notes = "Status changed from '{$order['status']}' to '{$newStatus}' by {$this->currentRole}";
            $logStmt->execute([$orderId, $newStatus, $adminId, $notes]);

            $this->send(true, 'Order status updated successfully', [
                'order_id' => $orderId,
                'old_status' => $order['status'],
                'new_status' => $newStatus
            ]);
        } catch (Throwable $e) {
            error_log("Update order status error: " . $e->getMessage());
            $this->send(false, 'Failed to update order status', null, 500);
        }
    }

    private function updatePaymentStatus(): void {
        if (!in_array($this->currentRole, ['Owner', 'Admin'])) {
            $this->send(false, 'You do not have permission to update payment status', null, 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send(false, 'Method not allowed', null, 405);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['id']) || empty($input['payment_status'])) {
            $this->send(false, 'Order ID and payment status are required', null, 400);
        }

        $orderId = (int)$input['id'];
        $newPaymentStatus = trim((string)$input['payment_status']);

        // allow only these target statuses for manual override; we removed 'Partially Paid'
        $validPaymentStatuses = ['Fully Paid', 'With Balance', 'Pending'];
        if (!in_array($newPaymentStatus, $validPaymentStatuses)) {
            $this->send(false, 'Invalid payment status value', null, 400);
        }

        try {
            $stmt = $this->conn->prepare('SELECT id, payment_status FROM orders WHERE id = ? LIMIT 1');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) $this->send(false, 'Order not found', null, 404);

            $stmt = $this->conn->prepare('UPDATE orders SET payment_status = ? WHERE id = ?');
            $stmt->execute([$newPaymentStatus, $orderId]);

            // Add audit log entry
            $logStmt = $this->conn->prepare('
                INSERT INTO order_status_history (order_id, status, changed_by, notes, changed_at)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $adminId = $this->currentUser['id'] ?? 0;
            $notes = "Payment status changed from '{$order['payment_status']}' to '{$newPaymentStatus}' by {$this->currentRole}";
            $logStmt->execute([$orderId, $newPaymentStatus, $adminId, $notes]);

            $this->send(true, 'Payment status updated successfully', [
                'order_id' => $orderId,
                'old_payment_status' => $order['payment_status'],
                'new_payment_status' => $newPaymentStatus
            ]);
        } catch (Throwable $e) {
            error_log("Update payment status error: " . $e->getMessage());
            $this->send(false, 'Failed to update payment status', null, 500);
        }
    }

    private function send(bool $success, string $message, array|object|null $data = null, ?int $code = null): void {
        if ($code !== null) {
            http_response_code($code);
        }
        $response = [
            'success' => $success,
            'message' => $message
        ];
        if ($data !== null) $response['data'] = $data;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$api = new AdminOrdersAPI();
$api->handleRequest();
