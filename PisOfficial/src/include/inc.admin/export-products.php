<?php
session_start();
require_once '../config.php';
require_once '../dbh.inc.php';
require_once 'admin.model.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$filename = 'Product_Catalog_' . date('Y-m-d_H-i') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Product Code', 'Product Name', 'Category', 'Material', 'Variants Count', 'Warehouse Stock', 'Showroom Stock', 'Base Price']);

$products = get_products($pdo);

if (!empty($products)) {
    foreach ($products as $row) {
        $p_id = $row['id'];
        $variants = get_variants($pdo, $p_id);
        $totalWh = 0;
        $totalSr = 0;
        foreach($variants as $v) {
            $totalWh += (int)$v['wh_qty'];
            $totalSr += (int)$v['sr_qty'];
        }
        
        fputcsv($output, [
            $row['code'] ?? 'N/A',
            $row['name'],
            $row['category'],
            $row['material'],
            count($variants),
            $totalWh,
            $totalSr,
            number_format($row['price'], 2, '.', '')
        ]);
    }
} else {
    fputcsv($output, ['No products found.']);
}

fclose($output);
exit;
