<?php
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\config.php';
require 'd:\Xampp\phpMyAdmin\htdocs\primeConceptInc\PisOfficial\src\include\dbh.inc.php';

$stmt = $pdo->query("SHOW COLUMNS FROM warehouse_stocks");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . "\n";
}
