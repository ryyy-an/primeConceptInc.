<?php
define('SYS_VERSION', '1.4.8'); // Increment this to bust asset cache
if (session_status() === PHP_SESSION_NONE) {
    // Only set these if no session has started yet
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 86400); // 1 Day on the server

    // Auto-detect secure flag based on HTTPS
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 86400, // 1 Day on the browser
        'path' => '/',
        'domain' => '', // Standard local domain
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Session Id Regeneration
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time(); // ✅ fixed spelling
} else {
    $sec = 60;
    $min = 30;
    $interval = $sec * $min;

    if (time() - $_SESSION['last_regeneration'] >= $interval) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// --- RBAC (Role-Based Access Control) ---
// Enforce module restrictions based on user's logged-in role
if (isset($_SESSION['role'])) {
    $role = strtolower($_SESSION['role']);
    $uri = $_SERVER['REQUEST_URI'];

    // Define the default landing page for each role
    $homePages = [
        'admin' => '../-admin/dashboard-page.php',
        'showroom' => '../-showroom/home-page.php',
        'warehouse' => '../-warehouse/dashboard-page.php'
    ];

    // Check if user is navigating outside their designated module
    if (strpos($uri, '/-admin/') !== false && $role !== 'admin') {
        header("Location: " . ($homePages[$role] ?? '../../public/index.php'));
        exit;
    }
    if (strpos($uri, '/-showroom/') !== false && $role !== 'showroom') {
        header("Location: " . ($homePages[$role] ?? '../../public/index.php'));
        exit;
    }
    if (strpos($uri, '/-warehouse/') !== false && $role !== 'warehouse') {
        header("Location: " . ($homePages[$role] ?? '../../public/index.php'));
        exit;
    }
}

/**
 * Global helper for shorthand HTML escaping
 */
function h($text) {
    return htmlspecialchars((string)($text ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * --- CSRF PROTECTION HELPER ---
 */

/**
 * Generates a CSRF token if one doesn't exist in the session.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gets the current CSRF token.
 */
function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? generate_csrf_token();
}

/**
 * Verifies a given CSRF token against the one stored in the session.
 */
function verify_csrf_token($token) {
    return !empty($token) && hash_equals(get_csrf_token(), $token);
}

/**
 * Returns a hidden input HTML string containing the CSRF token.
 */
function insert_csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . h(get_csrf_token()) . '">';
}

/**
 * --- GLOBAL CSRF VERIFICATION ---
 * Automatically verify CSRF tokens for all state-changing requests (POST).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Skip CSRF for specific files if necessary (e.g., webhooks), 
    // but for this system, we want it everywhere.
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        die('Security Error: Invalid CSRF Token. Please refresh the page.');
    }
}
