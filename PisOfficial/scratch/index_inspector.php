<?php
require_once 'd:/Xampp/phpMyAdmin/htdocs/primeConceptInc/PisOfficial/src/include/dbh.inc.php';

$tables = ['products', 'orders', 'order_items', 'showroom_stocks', 'warehouse_stocks', 'warehouse_logs', 'transactions', 'product_variant', 'product_components'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    try {
        $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $idx) {
            echo "  - {$idx['Key_name']} ({$idx['Column_name']})\n";
        }
    } catch (Exception $e) {
        echo "  - Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
