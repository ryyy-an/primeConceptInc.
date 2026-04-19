<?php
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\config.php';
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\dbh.inc.php';

$variantId = 1;

$sqlComp = "SELECT pc.id as comp_row_id, pc.comp_id, pc.qty_needed, ws.qty_on_hand
            FROM product_components pc
            JOIN product_variant pv ON pc.prod_id = pv.prod_id
            JOIN warehouse_stocks ws ON pc.id = ws.product_comp_id AND ws.variant_id = pv.id
            WHERE pv.id = ? AND pc.is_deleted = 0";
$stmtComp = $pdo->prepare($sqlComp);
$stmtComp->execute([$variantId]);
$components = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

print_r($components);
