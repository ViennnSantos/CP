<?php
// backend/api/generate_report.php
declare(strict_types=1);
session_start();

require_once dirname(__DIR__, 2) . '/includes/guard.php';
require_once dirname(__DIR__) . '/config/database.php';

$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    die('Error: PDF library not installed. Please run "composer install"');
}
require_once $autoloadPath;

guard_require_staff();

if (!class_exists('TCPDF')) {
    die('TCPDF class not found. Please install via composer: composer require tecnickcom/tcpdf');
}

$db = new Database();
$conn = $db->getConnection();

// Get date range from query parameters
$from_date = $_GET['from'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to'] ?? date('Y-m-d'); // Today

try {
    // Validate dates
    $from = new DateTime($from_date);
    $to = new DateTime($to_date);
    
    if ($from > $to) {
        throw new Exception('Invalid date range');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    die('Invalid date range provided');
}

// Fetch report data
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
    
    // Recent Orders for detail section
    $stmt = $conn->prepare("
        SELECT 
            o.order_code,
            c.full_name as customer_name,
            o.order_date,
            o.total_amount,
            o.status,
            o.payment_status
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.order_date BETWEEN ? AND ?
        ORDER BY o.order_date DESC
        LIMIT 20
    ");
    $stmt->execute([$from_date, $to_date]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
    http_response_code(500);
    die('Failed to generate report');
}

// Create PDF
//$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('RADS TOOLING');
$pdf->SetAuthor('RADS TOOLING INC.');
$pdf->SetTitle('Sales Report - ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y'));
$pdf->SetSubject('Sales and Order Report');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Company Header
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(47, 91, 136); // Brand color
$pdf->Cell(0, 10, 'RADS TOOLING INC.', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Green Breeze, Piela, Dasmarinas, Cavite', 0, 1, 'C');
$pdf->Cell(0, 5, 'Phone: +63 976 228 4270 | Email: radstooling@gmail.com', 0, 1, 'C');

$pdf->Ln(5);

// Report Title
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'SALES REPORT', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, 'Report Period: ' . $from->format('F d, Y') . ' to ' . $to->format('F d, Y'), 0, 1, 'C');
$pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y h:i A'), 0, 1, 'C');

$pdf->Ln(10);

// Summary Section
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(47, 91, 136);
$pdf->Cell(0, 8, 'SUMMARY STATISTICS', 0, 1, 'L');
$pdf->SetLineStyle(['width' => 0.5, 'color' => [47, 91, 136]]);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

// Summary Grid
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

$summaryData = [
    ['Total Sales', '₱' . number_format($salesData['total_sales'], 2)],
    ['Total Orders', number_format($ordersData['total_orders'])],
    ['Avg. Order Value', '₱' . number_format($avgOrder, 2)],
    ['Fully Paid Orders', number_format($fullyPaidData['fully_paid'])],
    ['Cancelled Orders', number_format($cancelledData['cancelled'])],
    ['Pending Orders', number_format($pendingData['pending'])],
    ['New Customers', number_format($customersData['new_customers'])],
    ['Feedbacks Received', number_format($feedbackData['feedbacks'])],
    ['Most Ordered Item', $mostOrderedData['name'] ?? 'N/A']
];

$colWidth = 90;
$rowHeight = 8;
$x = 15;
$y = $pdf->GetY();

foreach ($summaryData as $index => $row) {
    if ($index % 2 == 0) {
        $pdf->SetXY($x, $y);
    } else {
        $pdf->SetXY($x + $colWidth, $y);
    }
    
    // Background color for label
    $pdf->SetFillColor(247, 250, 252);
    $pdf->Cell($colWidth * 0.6, $rowHeight, $row[0] . ':', 0, 0, 'L', true);
    
    // Value
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($colWidth * 0.4, $rowHeight, $row[1], 0, 0, 'R');
    $pdf->SetFont('helvetica', '', 10);
    
    if ($index % 2 == 1) {
        $y += $rowHeight;
    }
}

if (count($summaryData) % 2 == 1) {
    $y += $rowHeight;
}

$pdf->SetY($y + 5);

// Recent Orders Section
if (count($recentOrders) > 0) {
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(47, 91, 136);
    $pdf->Cell(0, 8, 'RECENT ORDERS', 0, 1, 'L');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    // Table Header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(47, 91, 136);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(35, 7, 'Order Code', 1, 0, 'C', true);
    $pdf->Cell(45, 7, 'Customer', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Date', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Amount', 1, 0, 'C', true);
    $pdf->Cell(25, 7, 'Status', 1, 1, 'C', true);
    
    // Table Rows
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    $fill = false;
    foreach ($recentOrders as $order) {
        $pdf->SetFillColor(247, 250, 252);
        
        $pdf->Cell(35, 6, $order['order_code'], 1, 0, 'L', $fill);
        $pdf->Cell(45, 6, substr($order['customer_name'], 0, 25), 1, 0, 'L', $fill);
        $pdf->Cell(30, 6, date('M d, Y', strtotime($order['order_date'])), 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, '₱' . number_format($order['total_amount'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(25, 6, $order['status'], 1, 1, 'C', $fill);
        
        $fill = !$fill;
    }
}

// Footer
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell(0, 5, 'This is a computer-generated report. No signature required.', 0, 1, 'C');
$pdf->Cell(0, 5, '© ' . date('Y') . ' RADS TOOLING INC. All rights reserved.', 0, 1, 'C');

// Output PDF
$filename = 'Sales_Report_' . $from->format('Y-m-d') . '_to_' . $to->format('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D'); // D = download