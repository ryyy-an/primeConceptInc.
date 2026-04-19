<?php
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\config.php';
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\dbh.inc.php';
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\global.model.php';

$cards = get_inventory_cards($pdo);
foreach ($cards as $pid => $p) {
    foreach ($p['variants'] as $v) {
        $eff_sr = get_effective_available_stock($pdo, $v['id'], 'SR');
        $eff_wh = get_effective_available_stock($pdo, $v['id'], 'WH');
        
        if ($v['sr'] !== $eff_sr || $v['wh'] !== $eff_wh) {
            echo "MISMATCH Variant {$v['id']} ({$p['name']} - {$v['name']}):\n";
            echo "  Cards: SR={$v['sr']}, WH={$v['wh']}\n";
            echo "  Effective: SR=$eff_sr, WH=$eff_wh\n";
        }
    }
}
echo "Check done.\n";
