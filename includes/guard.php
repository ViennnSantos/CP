<?php
// includes/guard.php
// Shared session + role guards for staff vs. customers.

if (session_status() === PHP_SESSION_NONE) {
    // Secure cookies; tweak 'secure' to true when you switch to HTTPS.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
}

/** Call right after a successful login (in your auth.php). */
function guard_after_login_hardening(): void {
    session_regenerate_id(true);               // prevent fixation
    $_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['last_seen'] = time();
}

/** Basic session health checks: idle timeout + light fingerprint. */
function guard_session_hardening(): void {
    $MAX_IDLE = 30 * 60; // 30 minutes
    // Idle timeout
    $last = $_SESSION['last_seen'] ?? time();
    if (time() - $last > $MAX_IDLE) guard_logout(false);
    $_SESSION['last_seen'] = time();

    // Very light fingerprinting (relax if needed)
    if (!empty($_SESSION['ua']) && $_SESSION['ua'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        guard_logout(false);
    }
    if (!empty($_SESSION['ip'])) {
        $cur = explode('.', $_SERVER['REMOTE_ADDR'] ?? '');
        $pin = explode('.', $_SESSION['ip']);
        if (count($cur) >= 3 && count($pin) >= 3) {
            if ($cur[0] !== $pin[0] || $cur[1] !== $pin[1] || $cur[2] !== $pin[2]) {
                guard_logout(false);
            }
        }
    }
}

function guard_require_login(string $redirect = '/customer/login.php'): void {
    if (empty($_SESSION['user'])) {
        header('Location: '.$redirect);
        exit;
    }
    guard_session_hardening();
}

function guard_require_staff(): void {
    guard_require_login('/admin/login.php');
    if (($_SESSION['user']['aud'] ?? '') !== 'staff') {
        header('Location: /index.php');
        exit;
    }
}

function guard_require_customer(): void {
    guard_require_login('/customer/login.php');
    if (($_SESSION['user']['aud'] ?? '') !== 'customer') {
        header('Location: /admin/index.php');
        exit;
    }
}

function guard_logout(bool $redirect = true): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    if ($redirect) { header('Location: /'); exit; }
}

function guard_require_customer_api(string $loginUrl = '/customer/login.php'): void {
    header('Content-Type: application/json');
    if (empty($_SESSION['user']) || ($_SESSION['user']['aud'] ?? null) !== 'customer') {
        http_response_code(401);
        $next = $_SERVER['REQUEST_URI'] ?? '/customer/homepage.php';
        echo json_encode([
            'success'  => false,
            'code'     => 'AUTH',
            'message'  => 'Login required',
            'redirect' => $loginUrl . '?next=' . urlencode($next),
        ]);
        exit;
    }
}