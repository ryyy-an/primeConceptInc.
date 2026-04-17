<?php

$dbHost = getenv('MYSQLHOST') ?: "localhost";
$dbName = getenv('MYSQLDATABASE') ?: "pis-sys-db";
$dbUser = getenv('MYSQLUSER') ?: "root";
$dbPass = getenv('MYSQLPASSWORD') ?: "";
$dbPort = getenv('MYSQLPORT') ?: "3306";

// DSN (Data Source Name)
$dsn = "mysql:host=" . $dbHost . ";port=" . $dbPort . ";dbname=" . $dbName;

try {
    // PDO (PHP Data Object)
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection Failed:" . $e->getMessage());
}
