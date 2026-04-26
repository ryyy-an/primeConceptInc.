<?php
require_once 'd:/Xampp/phpMyAdmin/htdocs/primeConceptInc/PisOfficial/src/include/dbh.inc.php';

$queries = [
    "ALTER TABLE products ADD INDEX idx_products_is_deleted (is_deleted);",
    "ALTER TABLE product_variant ADD INDEX idx_pv_is_deleted (prod_id, is_deleted);",
    "ALTER TABLE product_components ADD INDEX idx_pc_is_deleted (prod_id, is_deleted);",
    "ALTER TABLE orders ADD INDEX idx_orders_status_date (status, created_at);",
    "ALTER TABLE transactions ADD INDEX idx_trans_status_type (payment_type, status);",
    "ALTER TABLE transactions ADD INDEX idx_trans_date (transaction_date);",
    "ALTER TABLE warehouse_logs ADD INDEX idx_wh_logs_date (log_date);",
    "ALTER TABLE warehouse_logs ADD INDEX idx_wh_logs_ids (prod_id, variant_id);",
    "ALTER TABLE warehouse_stocks ADD INDEX idx_wh_qty (qty_on_hand);"
];

echo "Starting Database Indexing...\n";

foreach ($queries as $sql) {
    try {
        echo "Executing: $sql ... ";
        $pdo->exec($sql);
        echo "SUCCESS\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ALREADY EXISTS (Skipped)\n";
        } else {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nIndexing Complete.\n";
