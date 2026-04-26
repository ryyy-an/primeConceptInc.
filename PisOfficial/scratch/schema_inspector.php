<?php
require_once 'd:/Xampp/phpMyAdmin/htdocs/primeConceptInc/PisOfficial/src/include/dbh.inc.php';

try {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "Table: $table\n";
        $columns = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']}) Key: {$col['Key']}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
