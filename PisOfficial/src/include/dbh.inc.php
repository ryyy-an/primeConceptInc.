<?php

// Priority: Railway Env Vars -> System getenv -> Localhost fallback
// Using (?:) to ensure we fall back even if the environment variable is an empty string
$dbHost = ($_ENV['MYSQLHOST']     ?? getenv('MYSQLHOST')) ?: "localhost";
$dbName = ($_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE')) ?: "pis-sys-db";
$dbUser = ($_ENV['MYSQLUSER']     ?? getenv('MYSQLUSER')) ?: "root";
$dbPass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? ""; // Password can legitimately be empty
$dbPort = ($_ENV['MYSQLPORT']     ?? getenv('MYSQLPORT')) ?: "3306";

// DSN (Data Source Name)
$dsn = "mysql:host=" . $dbHost . ";port=" . $dbPort . ";dbname=" . $dbName;

try {
    // PDO (PHP Data Object)
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // For security
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());

    // Better debugging for local development
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = ($host === 'localhost' || $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1');

    if ($isLocal) {
        die("<h1>Database Connection Failed</h1><p><strong>Error:</strong> " . $e->getMessage() . "</p><p>Check if MySQL is running and if the database exists.</p>");
    } else {
        die("<h1>Service Unavailable</h1><p>Our systems are currently undergoing maintenance. Please try again later.</p>");
    }
}
