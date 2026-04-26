<?php
$dirs = [
    'd:/Xampp/phpMyAdmin/htdocs/primeConceptInc/PisOfficial/src/-admin',
    'd:/Xampp/phpMyAdmin/htdocs/primeConceptInc/PisOfficial/src/-showroom',
    'd:/Xampp/phpMyAdmin/htdocs/primeConceptInc/PisOfficial/src/-warehouse'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (strpos($content, '<head>') !== false && strpos($content, 'csrf-token') === false) {
            echo "Missing CSRF Tag: $file\n";
        }
    }
}
