<?php
require 'src/include/config.php';
require 'src/include/dbh.inc.php';
try {
    $pdo = $pdo ?? $conn;
    $sql = "SELECT 
                o.id, 
                t.total_with_interest,
                (t.total_with_interest - COALESCE((SELECT SUM(pt.amount_paid) FROM payment_tracker pt WHERE pt.trans_id = t.id AND pt.status = 'Paid'), 0)) as balance,
                t.status as status
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            JOIN transactions t ON o.id = t.order_id
            WHERE t.payment_type = 'Installment'
              AND t.status = 'Ongoing'
              AND LOWER(c.client_type) = 'government'";
    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "RESULTS WITHOUT HAVING:\n";
    var_dump($results);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
