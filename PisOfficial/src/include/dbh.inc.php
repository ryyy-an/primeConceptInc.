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
} catch (PDOException $e) {
    die("Connection Failed:" . $e->getMessage());
}
