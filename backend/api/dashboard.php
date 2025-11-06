<?php
// backend/api/dashboard.php - Admin dashboard data (JSON only)

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

header('Content-Type: application/json');

// CORS: only keep this if you are calling from a different origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// ---- AuthZ: staff-only (matches the session we set at login) ----
function require_staff_api()
{
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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_staff_api();

try {
    $pdo = (new Database())->getConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection error']);
    exit;
}

$action = $_GET['action'] ?? 'stats';

switch ($action) {
    case 'stats':
        get_stats($pdo);
        break;
    case 'recent_orders':
        get_recent_orders($pdo);
        break;
    case 'recent_feedback':
        get_recent_feedback($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
    case 'sales_chart':
        getSalesChartData($pdo);
        break;
}

function get_stats(PDO $pdo)
{
    try {
        // totals
        $q1 = $pdo->query("SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_amount),0) AS total_sales FROM orders");
        $o  = $q1->fetch(PDO::FETCH_ASSOC) ?: ['total_orders' => 0, 'total_sales' => 0];

        $q2 = $pdo->query("SELECT COUNT(*) AS total_customers FROM customers");
        $c  = $q2->fetch(PDO::FETCH_ASSOC) ?: ['total_customers' => 0];

        $q3 = $pdo->query("SELECT COUNT(*) AS total_feedback FROM feedback");
        $f  = $q3->fetch(PDO::FETCH_ASSOC) ?: ['total_feedback' => 0];

        echo json_encode([
            'success' => true,
            'message' => 'Dashboard stats retrieved',
            'data' => [
                'total_orders'    => (int)($o['total_orders'] ?? 0),
                'total_sales'     => (float)($o['total_sales'] ?? 0),
                'total_customers' => (int)($c['total_customers'] ?? 0),
                'total_feedback'  => (int)($f['total_feedback'] ?? 0),
            ]
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
function getSalesChartData(PDO $pdo)
{
    $period = $_GET['period'] ?? 'week'; // day, week, month, year

    try {
        $data = [];
        $labels = [];

        switch ($period) {
            case 'day':
                // Last 24 hours by hour
                $sql = "
                    SELECT 
                        HOUR(order_date) as time_unit,
                        COALESCE(SUM(total_amount), 0) as total
                    FROM orders
                    WHERE order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        AND status != 'Cancelled'
                    GROUP BY HOUR(order_date)
                    ORDER BY time_unit
                ";
                $stmt = $pdo->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Fill all 24 hours
                for ($i = 0; $i < 24; $i++) {
                    $hour = date('H:00', strtotime("-" . (23 - $i) . " hours"));
                    $labels[] = $hour;
                    $found = false;
                    foreach ($results as $row) {
                        if ((int)$row['time_unit'] === (int)date('H', strtotime("-" . (23 - $i) . " hours"))) {
                            $data[] = (float)$row['total'];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $data[] = 0;
                    }
                }
                break;

            case 'week':
                // Last 7 days
                $sql = "
                    SELECT 
                        DATE(order_date) as date,
                        COALESCE(SUM(total_amount), 0) as total
                    FROM orders
                    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        AND status != 'Cancelled'
                    GROUP BY DATE(order_date)
                    ORDER BY date
                ";
                $stmt = $pdo->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $labels[] = date('D', strtotime($date));
                    $data[] = isset($results[$date]) ? (float)$results[$date] : 0;
                }
                break;

            case 'month':
                // Last 30 days grouped by week
                $sql = "
                    SELECT 
                        WEEK(order_date, 1) as week_num,
                        COALESCE(SUM(total_amount), 0) as total
                    FROM orders
                    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        AND status != 'Cancelled'
                    GROUP BY WEEK(order_date, 1)
                    ORDER BY week_num
                ";
                $stmt = $pdo->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Group by weeks
                for ($i = 4; $i >= 0; $i--) {
                    $weekStart = date('M d', strtotime("-" . ($i * 7) . " days"));
                    $labels[] = "Week of " . $weekStart;
                    $data[] = 0;
                }

                foreach ($results as $row) {
                    $data[count($data) - 1] = (float)$row['total'];
                }
                break;

            case 'year':
                // Last 12 months
                $sql = "
                    SELECT 
                        DATE_FORMAT(order_date, '%Y-%m') as month,
                        COALESCE(SUM(total_amount), 0) as total
                    FROM orders
                    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        AND status != 'Cancelled'
                    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                    ORDER BY month
                ";
                $stmt = $pdo->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                for ($i = 11; $i >= 0; $i--) {
                    $month = date('Y-m', strtotime("-$i months"));
                    $labels[] = date('M Y', strtotime($month . '-01'));
                    $data[] = isset($results[$month]) ? (float)$results[$month] : 0;
                }
                break;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'values' => $data,
                'period' => $period
            ]
        ]);
    } catch (Throwable $e) {
        error_log("Sales chart error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to load sales chart data']);
    }
}
function get_recent_orders(PDO $pdo)
{
    try {
        $sql = "SELECT o.order_code,
                       c.full_name AS customer_name,
                       o.order_date,
                       o.status,
                       o.total_amount
                  FROM orders o
                  JOIN customers c ON o.customer_id = c.id
              ORDER BY o.order_date DESC
                 LIMIT 5";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'message' => 'Recent orders retrieved', 'data' => $rows]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function get_recent_feedback(PDO $pdo)
{
    try {
        $sql = "SELECT f.rating,
                       f.comment,
                       c.full_name AS customer_name,
                       f.created_at
                  FROM feedback f
                  JOIN customers c ON f.customer_id = c.id
              ORDER BY f.created_at DESC
                 LIMIT 5";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success' => true, 'message' => 'Recent feedback retrieved', 'data' => $rows]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
