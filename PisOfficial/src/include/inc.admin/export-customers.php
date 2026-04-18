<?php
session_start();
require_once '../config.php';
require_once '../dbh.inc.php';
require_once 'admin.model.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$filename = 'Customer_Directory_' . date('Y-m-d_H-i') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Customer ID', 'Name', 'Contact No', 'Client Type', 'Gov Branch / Dept', 'Total Orders', 'Total Spend']);

$customers = get_report_customers($pdo, 5000);

if (!empty($customers)) {
    foreach ($customers as $row) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['contact_no'],
            $row['client_type'],
            $row['gov_branch'] ?? 'N/A',
            $row['total_orders'],
            number_format((float)($row['total_spend'] ?? 0), 2, '.', '')
        ]);
    }
} else {
    fputcsv($output, ['No customer records found.']);
}

fclose($output);
exit;
