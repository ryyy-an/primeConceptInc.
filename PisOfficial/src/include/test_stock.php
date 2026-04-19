<?php
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\config.php';
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\dbh.inc.php';
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\global.model.php';

// Get first 5 variants
$stmt = $pdo->query("SELECT id FROM product_variant WHERE is_deleted = 0 LIMIT 5");
$variants = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($variants as $vId) {
    echo "=========== Variant $vId ===========\n";
    
    // SR Stock Check
    $stmt = $pdo->prepare("SELECT COALESCE(qty_on_hand, 0) FROM showroom_stocks WHERE variant_id = ?");
    $stmt->execute([$vId]);
    $onHandSR = (int)$stmt->fetchColumn();

    $reservedStatuses = ['For Review', 'Approved', 'Pending'];
    $statusPlaceholders = implode(',', array_fill(0, count($reservedStatuses), '?'));
    $sqlRes = "SELECT SUM(oi.qty) 
               FROM order_items oi 
               JOIN orders o ON oi.order_id = o.id 
               WHERE oi.variant_id = ? AND oi.get_from = 'SR' AND o.status IN ($statusPlaceholders)";
    $stmtRes = $pdo->prepare($sqlRes);
    $stmtRes->execute(array_merge([$vId], $reservedStatuses));
    $reservedSR = (int)$stmtRes->fetchColumn();
    
    echo "SR onHand: $onHandSR, reserved: $reservedSR, max(0, onHand - reserved): " . max(0, $onHandSR - $reservedSR) . "\n";
    
    echo "Function SR: " . get_effective_available_stock($pdo, $vId, 'SR') . "\n";
    echo "Function WH: " . get_effective_available_stock($pdo, $vId, 'WH') . "\n";
}
