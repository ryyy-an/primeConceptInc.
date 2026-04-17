<?php

// Priority: Railway Env Vars -> System getenv -> Localhost fallback
$dbHost = $_ENV['MYSQLHOST']     ?? getenv('MYSQLHOST')     ?? "localhost";
$dbName = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? "pis-sys-db";
$dbUser = $_ENV['MYSQLUSER']     ?? getenv('MYSQLUSER')     ?? "root";
$dbPass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? "";
$dbPort = $_ENV['MYSQLPORT']     ?? getenv('MYSQLPORT')     ?? "3306";

// DSN (Data Source Name)
$dsn = "mysql:host=" . $dbHost . ";port=" . $dbPort . ";dbname=" . $dbName;

try {
    // PDO (PHP Data Object)
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // For security
} catch (PDOException $e) {
    // Logs the actual error internally but shows a generic one to the user
    error_log("Database Connection Error: " . $e->getMessage());
    die("<h1>Service Unavailable</h1><p>Our systems are currently undergoing maintenance. Please try again later.</p>");
}
