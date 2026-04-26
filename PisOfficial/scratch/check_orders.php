<?php
require_once 'd:/Xampp/phpMyAdmin/htdocs/primeConceptInc/PisOfficial/src/include/dbh.inc.php';
$cols = $pdo->query("DESCRIBE `orders`")->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) echo "  - {$c['Field']}\n";
