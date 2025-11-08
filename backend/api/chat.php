<?php

/**
 * RADS-TOOLING Chat Support API
 */

declare(strict_types=1);

// Prevent output before JSON
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/backend/logs/chat_errors.log');

ob_start();

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize database connection
require_once __DIR__ . '/../config/database.php';

/* try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
    exit;
} */

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // CRITICAL: Ensure autocommit is enabled
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    jsonResponse(false, [], 'Database connection failed', 500);
}

// Get action
$action = $_GET['action'] ?? '';

// Helper functions
function jsonResponse($success, $data = [], $message = '', $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAdmin(): void
{
    // Check if user is logged in as admin
    if (empty($_SESSION['admin_logged_in'])) {
        jsonResponse(false, [], 'Unauthorized: Admin access required', 403);
    }
}

function generateThreadCode(): string
{
    return strtoupper(bin2hex(random_bytes(16)));
}

// FIX: Updated authentication check to match your session structure
function isAdminAuthenticated(): bool
{
    // Check if staff session exists (your admin system uses 'staff' key)
    if (isset($_SESSION['staff']) && isset($_SESSION['staff']['id'])) {
        return true;
    }

    // Legacy check for old admin sessions
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return true;
    }

    return false;
}

function getAdminId()
{
    if (isset($_SESSION['staff']['id'])) {
        return $_SESSION['staff']['id'];
    }

    if (isset($_SESSION['admin_id'])) {
        return $_SESSION['admin_id'];
    }

    return null;
}

/**
 * Calculate similarity between customer message and FAQ question
 */
function calculateSimilarity($customerMsg, $faqQuestion)
{
    $customerMsg = strtolower(trim($customerMsg));
    $faqQuestion = strtolower(trim($faqQuestion));

    if ($customerMsg === $faqQuestion) {
        return 1.0;
    }

    $stopWords = ['do', 'you', 'can', 'i', 'what', 'where', 'when', 'how', 'is', 'are', 'the', 'a', 'an'];

    $customerWords = array_diff(
        explode(' ', preg_replace('/[^a-z0-9\s]/i', '', $customerMsg)),
        $stopWords
    );

    $faqWords = array_diff(
        explode(' ', preg_replace('/[^a-z0-9\s]/i', '', $faqQuestion)),
        $stopWords
    );

    $customerWords = array_filter($customerWords);
    $faqWords = array_filter($faqWords);

    if (empty($customerWords) || empty($faqWords)) {
        return 0;
    }

    $matchCount = 0;
    foreach ($customerWords as $word) {
        foreach ($faqWords as $faqWord) {
            if ($word === $faqWord) {
                $matchCount++;
                break;
            }

            $distance = levenshtein($word, $faqWord);
            $maxLen = max(strlen($word), strlen($faqWord));

            if ($maxLen > 0 && ($distance / $maxLen) <= 0.3) {
                $matchCount += 0.5;
                break;
            }
        }
    }

    $totalWords = max(count($customerWords), count($faqWords));
    return $totalWords > 0 ? ($matchCount / $totalWords) : 0;
}

// Admin-only actions
$adminActions = ['threads', 'send_admin', 'faqs_save', 'faqs_delete'];

if (in_array($action, $adminActions) && !isAdminAuthenticated()) {
    jsonResponse(false, [], 'Unauthorized: Admin access required', 403);
}

// Handle actions
try {
    switch ($action) {

        // ========== FAQ LIST (Public - No Auth Required) ==========
        case 'faqs_list':
            $stmt = $pdo->query("
                SELECT id, question, answer, is_active
                FROM rt_faqs 
                WHERE is_active = 1 
                ORDER BY created_at DESC
            ");
            jsonResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        // ========== FAQ SAVE (Admin Only) ==========
        case 'faqs_save':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = isset($input['id']) ? (int)$input['id'] : null;
            $question = trim($input['question'] ?? '');
            $answer = trim($input['answer'] ?? '');

            if (empty($question) || empty($answer)) {
                jsonResponse(false, [], 'Question and answer are required', 400);
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE rt_faqs 
                    SET question = ?, answer = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$question, $answer, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO rt_faqs (question, answer, is_active, created_at, updated_at) 
                    VALUES (?, ?, 1, NOW(), NOW())
                ");
                $stmt->execute([$question, $answer]);
                $id = (int)$pdo->lastInsertId();
            }

            jsonResponse(true, ['id' => $id], 'FAQ saved successfully');
            break;

        // ========== FAQ DELETE (Admin Only) ==========
        case 'faqs_delete':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $id = (int)($input['id'] ?? 0);

            if ($id <= 0) {
                jsonResponse(false, [], 'Invalid FAQ ID', 400);
            }

            $stmt = $pdo->prepare("DELETE FROM rt_faqs WHERE id = ?");
            $stmt->execute([$id]);

            jsonResponse(true, ['id' => $id], 'FAQ deleted successfully');
            break;

        // ========== THREADS LIST (Admin Only) ==========
        case 'threads':
            requireAdmin();

            try {
                $stmt = $pdo->query("
            SELECT 
                t.id,
                t.thread_code,
                t.customer_id,
                t.customer_name,
                t.customer_email,
                t.customer_phone,
                t.status,
                t.last_message_at,
                t.created_at,
                t.admin_last_read,
                COALESCE(
                    c.full_name,
                    t.customer_name,
                    c.username,
                    c.email,
                    t.customer_email,
                    CONCAT('Guest-', SUBSTRING(t.thread_code, 1, 6))
                ) as display_name,
                (
                    SELECT COUNT(*) 
                    FROM rt_chat_messages m 
                    WHERE m.thread_id = t.id 
                    AND m.sender_type = 'customer'
                    AND m.created_at > COALESCE(t.admin_last_read, '1970-01-01')
                ) as unread_count
            FROM rt_chat_threads t
            LEFT JOIN customers c ON t.customer_id = c.id
            WHERE t.status = 'open'
            ORDER BY t.last_message_at DESC
        ");

                $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Format data
                foreach ($threads as &$thread) {
                    $thread['unread_count'] = (int)$thread['unread_count'];
                    $thread['has_unread'] = $thread['unread_count'] > 0;
                }

                jsonResponse(true, $threads);
            } catch (PDOException $e) {
                error_log('Threads query error: ' . $e->getMessage());
                jsonResponse(false, [], 'Database error', 500);
            }
            break;

        // ========== MARK THREAD AS READ (Admin) ==========
        case 'mark_read':
            requireAdmin();

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $threadCode = trim($input['thread_code'] ?? '');

            if (empty($threadCode)) {
                jsonResponse(false, [], 'Thread code required', 400);
            }

            try {
                // Update admin_last_read timestamp
                $stmt = $pdo->prepare("
            UPDATE rt_chat_threads 
            SET admin_last_read = NOW() 
            WHERE thread_code = ?
        ");
                $stmt->execute([$threadCode]);

                jsonResponse(true, [], 'Thread marked as read');
            } catch (PDOException $e) {
                error_log('Mark read error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to mark as read', 500);
            }
            break;

        // ========== THREAD INIT (Customer) ==========
        case 'thread_init':
            $customerId = $_SESSION['user']['id'] ?? null;
            $customerName = $_SESSION['user']['full_name'] ?? $_SESSION['user']['name'] ?? $_SESSION['user']['username'] ?? null;
            $customerEmail = $_SESSION['user']['email'] ?? null;

            error_log("Thread init - Customer ID: $customerId, Name: $customerName");

            // CRITICAL: Require login
            if (!$customerId || !$customerName) {
                jsonResponse(false, [], 'Please login or create an account to use chat support', 401);
            }

            try {
                // Check for existing OPEN thread for THIS specific customer
                $stmt = $pdo->prepare("
            SELECT thread_code, id
            FROM rt_chat_threads 
            WHERE customer_id = ? 
            AND status = 'open'
            ORDER BY created_at DESC 
            LIMIT 1
        ");
                $stmt->execute([$customerId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    error_log("Found existing thread: " . $existing['thread_code']);
                    jsonResponse(true, [
                        'thread_code' => $existing['thread_code'],
                        'thread_id' => (int)$existing['id'],
                        'user_id' => $customerId
                    ]);
                }

                // Create new thread for this customer
                $threadCode = generateThreadCode();

                error_log("Creating new thread: $threadCode for customer: $customerId ($customerName)");

                $stmt = $pdo->prepare("
            INSERT INTO rt_chat_threads (
                thread_code, 
                customer_id, 
                customer_name,
                customer_email,
                status,
                created_at, 
                last_message_at
            ) 
            VALUES (?, ?, ?, ?, 'open', NOW(), NOW())
        ");
                $stmt->execute([$threadCode, $customerId, $customerName, $customerEmail]);

                $threadId = $pdo->lastInsertId();
                error_log("Created thread ID: $threadId");

                jsonResponse(true, [
                    'thread_code' => $threadCode,
                    'thread_id' => (int)$threadId,
                    'user_id' => $customerId
                ]);
            } catch (PDOException $e) {
                error_log('Thread init error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to initialize chat', 500);
            }
            break;

        // ========== FETCH MESSAGES ==========
        case 'messages_fetch':
            $threadCode = trim($_GET['thread_code'] ?? '');
            $afterId = (int)($_GET['after_id'] ?? 0);

            if (empty($threadCode)) {
                jsonResponse(false, [], 'Thread code required', 400);
            }

            try {
                $stmt = $pdo->prepare("
            SELECT id, customer_id, customer_cleared_at 
            FROM rt_chat_threads 
            WHERE thread_code = ?
        ");
                $stmt->execute([$threadCode]);
                $thread = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$thread) {
                    jsonResponse(false, [], 'Thread not found', 404);
                }

                // Check authorization
                $isAdmin = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['staff']);
                $isOwner = !empty($_SESSION['user']['id']) &&
                    $_SESSION['user']['id'] == $thread['customer_id'];

                if (!$isAdmin && !$isOwner) {
                    jsonResponse(false, [], 'Unauthorized', 403);
                }

                // CRITICAL: Admin sees ALL messages, customer sees filtered messages
                if ($isAdmin) {
                    // ===== ADMIN: See ALL messages (no filtering by customer_cleared_at) =====
                    $stmt = $pdo->prepare("
                SELECT 
                    id,
                    sender_type,
                    sender_id,
                    body,
                    created_at
                FROM rt_chat_messages 
                WHERE thread_id = ? 
                AND id > ?
                ORDER BY created_at ASC, id ASC
            ");
                    $stmt->execute([$thread['id'], $afterId]);
                } else {
                    // ===== CUSTOMER: Only see messages AFTER they cleared (if they did) =====
                    if ($thread['customer_cleared_at']) {
                        // Customer cleared - only show new messages
                        $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        sender_type,
                        sender_id,
                        body,
                        created_at
                    FROM rt_chat_messages 
                    WHERE thread_id = ? 
                    AND id > ?
                    AND created_at > ?
                    ORDER BY created_at ASC, id ASC
                ");
                        $stmt->execute([$thread['id'], $afterId, $thread['customer_cleared_at']]);
                    } else {
                        // Customer hasn't cleared - show all messages
                        $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        sender_type,
                        sender_id,
                        body,
                        created_at
                    FROM rt_chat_messages 
                    WHERE thread_id = ? 
                    AND id > ?
                    ORDER BY created_at ASC, id ASC
                ");
                        $stmt->execute([$thread['id'], $afterId]);
                    }
                }

                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                jsonResponse(true, $messages);
            } catch (PDOException $e) {
                error_log('Fetch messages error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to fetch messages', 500);
            }
            break;

        // ========== SEND MESSAGE (Customer) ==========
        case 'send_customer':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, [], 'Method not allowed', 405);
            }

            $customerId = $_SESSION['user']['id'] ?? null;

            if (!$customerId) {
                jsonResponse(false, [], 'Not authenticated', 401);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $threadCode = trim($input['thread_code'] ?? '');
            $body = trim($input['body'] ?? '');

            if (empty($threadCode) || empty($body)) {
                jsonResponse(false, [], 'Thread code and message required', 400);
            }

            try {
                // Get thread
                $stmt = $pdo->prepare("
            SELECT id, customer_id 
            FROM rt_chat_threads 
            WHERE thread_code = ?
        ");
                $stmt->execute([$threadCode]);
                $thread = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$thread) {
                    jsonResponse(false, [], 'Thread not found', 404);
                }

                // Verify ownership
                if ($thread['customer_id'] != $customerId) {
                    jsonResponse(false, [], 'Unauthorized', 403);
                }

                // Save customer message (NO transaction - let autocommit handle it)
                $stmt = $pdo->prepare("
            INSERT INTO rt_chat_messages (thread_id, sender_type, sender_id, body, created_at) 
            VALUES (?, 'customer', ?, ?, NOW())
        ");
                $stmt->execute([$thread['id'], $customerId, $body]);
                $customerMsgId = $pdo->lastInsertId();

                error_log("CUSTOMER MSG SAVED: ID={$customerMsgId}, Thread={$thread['id']}, Body={$body}");

                // Update thread last_message_at
                $stmt = $pdo->prepare("
            UPDATE rt_chat_threads 
            SET last_message_at = NOW() 
            WHERE id = ?
        ");
                $stmt->execute([$thread['id']]);

                // Check for FAQ match
                $stmt = $pdo->prepare("
            SELECT answer 
            FROM rt_faqs 
            WHERE is_active = 1 
            AND LOWER(?) LIKE CONCAT('%', LOWER(question), '%')
            LIMIT 1
        ");
                $stmt->execute([$body]);
                $faq = $stmt->fetch(PDO::FETCH_ASSOC);

                $botMsgId = null;
                $botResponse = null;

                if ($faq) {
                    // Save bot response (separate query, autocommit handles it)
                    $stmt = $pdo->prepare("
                INSERT INTO rt_chat_messages (thread_id, sender_type, sender_id, body, created_at) 
                VALUES (?, 'bot', NULL, ?, NOW())
            ");
                    $stmt->execute([$thread['id'], $faq['answer']]);
                    $botMsgId = $pdo->lastInsertId();
                    $botResponse = $faq['answer'];

                    error_log("BOT MSG SAVED: ID={$botMsgId}, Thread={$thread['id']}, Answer={$faq['answer']}");
                } else {
                    error_log("NO FAQ MATCH for: {$body}");
                }

                // Return both message IDs
                jsonResponse(true, [
                    'customer_msg_id' => $customerMsgId,
                    'bot_msg_id' => $botMsgId,
                    'bot_response' => $botResponse
                ], 'Message sent');
            } catch (PDOException $e) {
                error_log('Send customer message error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to send message', 500);
            }
            break;

        // ========== CLEAR CHAT (Customer) ==========
        case 'clear_chat':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, [], 'Method not allowed', 405);
            }

            $customerId = $_SESSION['user']['id'] ?? null;

            if (!$customerId) {
                jsonResponse(false, [], 'Not authenticated', 401);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $threadCode = trim($input['thread_code'] ?? '');

            if (empty($threadCode)) {
                jsonResponse(false, [], 'Thread code required', 400);
            }

            try {
                // Verify thread belongs to this customer
                $stmt = $pdo->prepare("
            SELECT id 
            FROM rt_chat_threads 
            WHERE thread_code = ? 
            AND customer_id = ?
        ");
                $stmt->execute([$threadCode, $customerId]);
                $thread = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$thread) {
                    jsonResponse(false, [], 'Thread not found or unauthorized', 404);
                }

                // Count messages before clearing
                $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM rt_chat_messages 
            WHERE thread_id = ?
        ");
                $stmt->execute([$thread['id']]);
                $messageCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

                // CRITICAL: Set customer_cleared_at timestamp
                // Customer won't see messages before this time
                $stmt = $pdo->prepare("
            UPDATE rt_chat_threads 
            SET customer_cleared_at = NOW() 
            WHERE id = ?
        ");
                $stmt->execute([$thread['id']]);

                jsonResponse(true, [
                    'message_count' => $messageCount,
                    'cleared_at' => date('Y-m-d H:i:s')
                ], 'Chat cleared from your view');
            } catch (PDOException $e) {
                error_log('Clear chat error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to clear chat', 500);
            }
            break;

        // ========== SEND ADMIN MESSAGE ==========
        case 'send_admin':
            requireAdmin();

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $threadCode = trim($input['thread_code'] ?? '');
            $body = trim($input['body'] ?? '');

            if (empty($threadCode) || empty($body)) {
                jsonResponse(false, [], 'Missing thread_code or body', 400);
            }

            $stmt = $pdo->prepare("SELECT id FROM rt_chat_threads WHERE thread_code = ?");
            $stmt->execute([$threadCode]);
            $thread = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$thread) {
                jsonResponse(false, [], 'Thread not found', 404);
            }

            $adminId = getAdminId();

            $stmt = $pdo->prepare("
                INSERT INTO rt_chat_messages (thread_id, sender_type, sender_id, body, created_at) 
                VALUES (?, 'admin', ?, ?, NOW())
            ");
            $stmt->execute([$thread['id'], $adminId, $body]);

            $stmt = $pdo->prepare("UPDATE rt_chat_threads SET last_message_at = NOW() WHERE id = ?");
            $stmt->execute([$thread['id']]);

            jsonResponse(true, [], 'Message sent');
            break;

        // ========== FAQs LIST (Admin) ==========
        case 'faqs_list_admin':
            requireAdmin();

            try {
                $stmt = $pdo->query("
            SELECT id, question, answer, is_active 
            FROM rt_faqs 
            WHERE is_active = 1
            ORDER BY created_at DESC
        ");

                $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                jsonResponse(true, $faqs);
            } catch (PDOException $e) {
                error_log('FAQs list error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to load FAQs', 500);
            }
            break;

        // ========== UPDATE FAQ (Admin) ==========
        case 'faq_update':
            requireAdmin();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, [], 'Method not allowed', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $faqId = (int)($input['faq_id'] ?? 0);
            $question = trim($input['question'] ?? '');
            $answer = trim($input['answer'] ?? '');

            if ($faqId <= 0) {
                jsonResponse(false, [], 'Invalid FAQ ID', 400);
            }

            if (empty($question) || empty($answer)) {
                jsonResponse(false, [], 'Question and answer required', 400);
            }

            try {
                $stmt = $pdo->prepare("
            UPDATE rt_faqs 
            SET question = ?, answer = ?, updated_at = NOW() 
            WHERE id = ?
        ");
                $stmt->execute([$question, $answer, $faqId]);

                jsonResponse(true, [], 'FAQ updated successfully');
            } catch (PDOException $e) {
                error_log('Update FAQ error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to update FAQ', 500);
            }
            break;

        // ========== SAVE FAQ (Admin) ==========
        case 'faq_save':
            requireAdmin();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, [], 'Method not allowed', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $question = trim($input['question'] ?? '');
            $answer = trim($input['answer'] ?? '');

            if (empty($question) || empty($answer)) {
                jsonResponse(false, [], 'Question and answer required', 400);
            }

            try {
                $stmt = $pdo->prepare("
            INSERT INTO rt_faqs (question, answer, is_active) 
            VALUES (?, ?, 1)
        ");
                $stmt->execute([$question, $answer]);

                jsonResponse(true, ['id' => $pdo->lastInsertId()], 'FAQ saved successfully');
            } catch (PDOException $e) {
                error_log('Save FAQ error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to save FAQ', 500);
            }
            break;

        // ========== DELETE FAQ (Admin) ==========
        case 'faq_delete':
            requireAdmin();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(false, [], 'Method not allowed', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $faqId = (int)($input['faq_id'] ?? 0);

            if ($faqId <= 0) {
                jsonResponse(false, [], 'Invalid FAQ ID', 400);
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM rt_faqs WHERE id = ?");
                $stmt->execute([$faqId]);

                jsonResponse(true, [], 'FAQ deleted successfully');
            } catch (PDOException $e) {
                error_log('Delete FAQ error: ' . $e->getMessage());
                jsonResponse(false, [], 'Failed to delete FAQ', 500);
            }
            break;

        default:
            jsonResponse(false, [], 'Invalid action: ' . $action, 400);
    }
} catch (PDOException $e) {
    error_log('Chat API Database Error: ' . $e->getMessage());
    jsonResponse(false, [], 'Database error occurred', 500);
} catch (Exception $e) {
    error_log('Chat API Error: ' . $e->getMessage());
    jsonResponse(false, [], 'Server error occurred', 500);
}

// Flush any buffered output
$output = ob_get_clean();
if (!empty($output)) {
    error_log('Unexpected output before JSON: ' . $output);
}

// After saving customer message
error_log("CUSTOMER MSG SAVED: ID={$customerMsgId}, Thread={$thread['id']}, Body={$body}");

// After saving bot response
if ($faq) {
    error_log("BOT MSG SAVED: ID={$botMsgId}, Thread={$thread['id']}, Answer={$faq['answer']}");
} else {
    error_log("NO FAQ MATCH for: {$body}");
}

// After commit
error_log("TRANSACTION COMMITTED for thread {$thread['id']}");
