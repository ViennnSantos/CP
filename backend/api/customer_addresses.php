<?php
/**
 * Customer Address Management API
 * Handles CRUD operations for customer addresses with PSGC support
 *
 * Actions:
 * - list: Get all addresses for logged-in customer
 * - get: Get single address by ID
 * - create: Create new address
 * - update: Update existing address
 * - delete: Delete address
 * - set_default: Set an address as default
 */

// Set JSON header FIRST before any includes (to prevent HTML errors from leaking)
header('Content-Type: application/json; charset=utf-8');

// Suppress errors to prevent HTML output before JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files (using correct paths)
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/phone_util.php';

// Initialize database connection
try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Require customer authentication
if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

$customer_id = (int)$_SESSION['user']['id'];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF protection for state-changing operations
$method = $_SERVER['REQUEST_METHOD'];

if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrf_token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }
}

 

// Get action from request

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

 

try {

    switch ($action) {

        case 'list':

            list_addresses($customer_id);

            break;

 

        case 'get':

            get_address($customer_id);

            break;

 

        case 'create':

            create_address($customer_id);

            break;

 

        case 'update':

            update_address($customer_id);

            break;

 

        case 'delete':

            delete_address($customer_id);

            break;

 

        case 'set_default':

            set_default_address($customer_id);

            break;

 

        default:

            throw new Exception('Invalid action');

    }

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()

    ]);

}

 

/**

 * List all addresses for customer

 */

function list_addresses($customer_id) {
    global $conn;

    $sql = "SELECT * FROM customer_addresses
            WHERE customer_id = ?
            ORDER BY is_default DESC, created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$customer_id]);
    $addresses = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'addresses' => $addresses
    ]);
}

 

/**

 * Get single address by ID

 */

function get_address($customer_id) {
    global $conn;

    $address_id = $_GET['id'] ?? null;
    if (!$address_id) {
        throw new Exception('Address ID required');
    }

    $sql = "SELECT * FROM customer_addresses
            WHERE id = ? AND customer_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$address_id, $customer_id]);
    $address = $stmt->fetch();

    if (!$address) {
        throw new Exception('Address not found');
    }

    echo json_encode([
        'success' => true,
        'address' => $address
    ]);
}

 

/**

 * Create new address

 */

function create_address($customer_id) {

    global $conn;

 

    // Validate required fields

    $required = ['full_name', 'mobile_number', 'province', 'city_municipality', 'barangay', 'street_block_lot'];

    foreach ($required as $field) {

        if (empty($_POST[$field])) {

            throw new Exception("Field '$field' is required");

        }

    }

 

    // Normalize phone number

    $mobile = normalize_ph_phone($_POST['mobile_number']);

    if (!$mobile) {

        throw new Exception('Invalid Philippine mobile number. Must be 10 digits (e.g., 9123456789)');

    }

 

    // Validate phone format (must be +639XXXXXXXXX)

    if (!preg_match('/^\+639\d{9}$/', $mobile)) {

        throw new Exception('Invalid mobile number format');

    }

 

    // Get form data

    $address_nickname = $_POST['address_nickname'] ?? null;

    $full_name = trim($_POST['full_name']);

    $email = !empty($_POST['email']) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : null;

 

    $province = trim($_POST['province']);

    $province_code = $_POST['province_code'] ?? null;

    $city_municipality = trim($_POST['city_municipality']);

    $city_code = $_POST['city_code'] ?? null;

    $barangay = trim($_POST['barangay']);

    $barangay_code = $_POST['barangay_code'] ?? null;

    $street_block_lot = trim($_POST['street_block_lot']);

    $postal_code = !empty($_POST['postal_code']) ? trim($_POST['postal_code']) : null;

 

    // Validate postal code if provided (4-5 digits)

    if ($postal_code && !preg_match('/^\d{4,5}$/', $postal_code)) {

        throw new Exception('Postal code must be 4-5 digits');

    }

 

    // Set as default if requested or if this is the first address

    $is_default = isset($_POST['is_default']) && $_POST['is_default'] == '1';

 

    // Check if customer has any addresses
    $check_sql = "SELECT COUNT(*) as count FROM customer_addresses WHERE customer_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$customer_id]);
    $check_result = $check_stmt->fetch();

    // If no addresses exist, make this the default
    if ($check_result['count'] == 0) {
        $is_default = true;
    }

    // If setting as default, unset other defaults
    if ($is_default) {
        $unset_sql = "UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?";
        $unset_stmt = $conn->prepare($unset_sql);
        $unset_stmt->execute([$customer_id]);
    }

    // Insert new address
    $sql = "INSERT INTO customer_addresses
            (customer_id, address_nickname, full_name, mobile_number, email,
             province, province_code, city_municipality, city_code,
             barangay, barangay_code, street_block_lot, postal_code, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $success = $stmt->execute([
        $customer_id,
        $address_nickname,
        $full_name,
        $mobile,
        $email,
        $province,
        $province_code,
        $city_municipality,
        $city_code,
        $barangay,
        $barangay_code,
        $street_block_lot,
        $postal_code,
        $is_default ? 1 : 0
    ]);

    if (!$success) {
        throw new Exception('Failed to create address');
    }

    $address_id = $conn->lastInsertId();

 

    echo json_encode([

        'success' => true,

        'message' => 'Address created successfully',

        'address_id' => $address_id

    ]);

}

 

/**

 * Update existing address

 */

function update_address($customer_id) {
    global $conn;

    $address_id = $_POST['id'] ?? null;
    if (!$address_id) {
        throw new Exception('Address ID required');
    }

    // Verify ownership
    $check_sql = "SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$address_id, $customer_id]);

    if (!$check_stmt->fetch()) {
        throw new Exception('Address not found or access denied');
    }

 

    // Validate required fields

    $required = ['full_name', 'mobile_number', 'province', 'city_municipality', 'barangay', 'street_block_lot'];

    foreach ($required as $field) {

        if (empty($_POST[$field])) {

            throw new Exception("Field '$field' is required");

        }

    }

 

    // Normalize phone number

    $mobile = normalize_ph_phone($_POST['mobile_number']);

    if (!$mobile || !preg_match('/^\+639\d{9}$/', $mobile)) {

        throw new Exception('Invalid Philippine mobile number');

    }

 

    // Get form data

    $address_nickname = $_POST['address_nickname'] ?? null;

    $full_name = trim($_POST['full_name']);

    $email = !empty($_POST['email']) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : null;

 

    $province = trim($_POST['province']);

    $province_code = $_POST['province_code'] ?? null;

    $city_municipality = trim($_POST['city_municipality']);

    $city_code = $_POST['city_code'] ?? null;

    $barangay = trim($_POST['barangay']);

    $barangay_code = $_POST['barangay_code'] ?? null;

    $street_block_lot = trim($_POST['street_block_lot']);

    $postal_code = !empty($_POST['postal_code']) ? trim($_POST['postal_code']) : null;

 

    // Validate postal code

    if ($postal_code && !preg_match('/^\d{4,5}$/', $postal_code)) {

        throw new Exception('Postal code must be 4-5 digits');

    }

 

    // Update address
    $sql = "UPDATE customer_addresses SET
            address_nickname = ?,
            full_name = ?,
            mobile_number = ?,
            email = ?,
            province = ?,
            province_code = ?,
            city_municipality = ?,
            city_code = ?,
            barangay = ?,
            barangay_code = ?,
            street_block_lot = ?,
            postal_code = ?
            WHERE id = ? AND customer_id = ?";

    $stmt = $conn->prepare($sql);
    $success = $stmt->execute([
        $address_nickname,
        $full_name,
        $mobile,
        $email,
        $province,
        $province_code,
        $city_municipality,
        $city_code,
        $barangay,
        $barangay_code,
        $street_block_lot,
        $postal_code,
        $address_id,
        $customer_id
    ]);

    if (!$success) {
        throw new Exception('Failed to update address');
    }

 

    echo json_encode([

        'success' => true,

        'message' => 'Address updated successfully'

    ]);

}

 

/**

 * Delete address

 */

function delete_address($customer_id) {
    global $conn;

    $address_id = $_POST['id'] ?? $_GET['id'] ?? null;
    if (!$address_id) {
        throw new Exception('Address ID required');
    }

    // Check if address is default
    $check_sql = "SELECT is_default FROM customer_addresses WHERE id = ? AND customer_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$address_id, $customer_id]);
    $address = $check_stmt->fetch();

    if (!$address) {
        throw new Exception('Address not found or access denied');
    }

    $was_default = $address['is_default'];

    // Delete address
    $delete_sql = "DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);

    if (!$delete_stmt->execute([$address_id, $customer_id])) {
        throw new Exception('Failed to delete address');
    }

    // If we deleted the default address, set another one as default
    if ($was_default) {
        $set_new_default_sql = "UPDATE customer_addresses SET is_default = 1
                                WHERE customer_id = ?
                                ORDER BY created_at DESC LIMIT 1";
        $set_stmt = $conn->prepare($set_new_default_sql);
        $set_stmt->execute([$customer_id]);
    }

 

    echo json_encode([

        'success' => true,

        'message' => 'Address deleted successfully'

    ]);

}

 

/**

 * Set address as default

 */

function set_default_address($customer_id) {
    global $conn;

    try {
        $address_id = $_POST['id'] ?? null;
        if (!$address_id) {
            throw new Exception('Address ID required');
        }

        // Verify ownership
        $check_sql = "SELECT id FROM customer_addresses WHERE id = ? AND customer_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$address_id, $customer_id]);

        if (!$check_stmt->fetch()) {
            $check_stmt->closeCursor();
            throw new Exception('Address not found or access denied');
        }

        // Close cursor before starting transaction
        $check_stmt->closeCursor();
        $check_stmt = null;

        // Check if transaction is already active (PDO doesn't have inTransaction in all versions)
        // So we'll just wrap the transaction carefully
        $inTransaction = false;
        try {
            $conn->beginTransaction();
            $inTransaction = true;
        } catch (PDOException $e) {
            // Transaction might already be active, log and continue
            error_log("Warning: Could not begin transaction - " . $e->getMessage());
        }

        // Unset all defaults for this customer
        $unset_sql = "UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?";
        $unset_stmt = $conn->prepare($unset_sql);
        $unset_stmt->execute([$customer_id]);

        // Set new default
        $set_sql = "UPDATE customer_addresses SET is_default = 1 WHERE id = ? AND customer_id = ?";
        $set_stmt = $conn->prepare($set_sql);
        $set_stmt->execute([$address_id, $customer_id]);

        if ($inTransaction) {
            $conn->commit();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Default address updated'
        ]);

    } catch (Exception $e) {
        error_log("Set default address error: " . $e->getMessage());

        // Try to rollback if transaction is active
        try {
            if (isset($inTransaction) && $inTransaction) {
                $conn->rollBack();
            }
        } catch (Exception $rollbackError) {
            error_log("Rollback error: " . $rollbackError->getMessage());
        }

        throw $e;
    }
}