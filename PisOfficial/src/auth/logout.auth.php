<?php
require_once '../include/config.php';
require_once '../include/dbh.inc.php'; // Ensure database connection is available

if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        // Set status to offline (0)
        $stmt = $pdo->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Silently fail if DB is unreachable to ensure logout still happens
        error_log("Logout DB Error: " . $e->getMessage());
    }
}

// Remove all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../../public/index.php");
exit;
