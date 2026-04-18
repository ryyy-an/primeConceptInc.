<?php
session_start();
require_once '../config.php';
require_once '../dbh.inc.php';
require_once 'admin.model.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

if ($type !== 'wh' && $type !== 'sr') {
    die("Invalid export type.");
}

$filename = ($type === 'wh' ? 'Warehouse_Activity_Logs' : 'Showroom_Activity_Logs') . '_' . date('Y-m-d_H-i') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add business standard CSV headers
fputcsv($output, ['Product Name', 'Variant', 'Product Code', 'Adjustment (Qty)', 'Date', 'Time']);

$logs = ($type === 'wh') ? get_wh_stock_logs($pdo, 1000, $from, $to) : get_sr_stock_logs($pdo, 1000, $from, $to);

if (!empty($logs)) {
    foreach ($logs as $row) {
        $date = date('Y-m-d', strtotime($row['log_date']));
        $time = date('h:i A', strtotime($row['log_date']));
        fputcsv($output, [
            $row['product_name'],
            $row['variant_name'] ?: 'Standard',
            $row['prod_code'] ?? 'N/A',
            $row['qty'],
            $date,
            $time
        ]);
    }
} else {
    fputcsv($output, ['No records found within the selected date range.']);
}

fclose($output);
exit;
