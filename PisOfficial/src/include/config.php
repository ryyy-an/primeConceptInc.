if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'secure' => true,
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
