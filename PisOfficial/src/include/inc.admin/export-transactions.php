<?php
session_start();
require_once '../config.php';
require_once '../dbh.inc.php';
require_once 'admin.model.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

$filename = 'Transaction_Report_' . date('Y-m-d_H-i') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Trans ID', 'Customer Name', 'Client Type', 'Amount', 'Payment Type', 'Status', 'Date', 'Time']);

$transactions = get_report_transactions($pdo, 2000, $from, $to);

if (!empty($transactions)) {
    foreach ($transactions as $row) {
        $date = date('Y-m-d', strtotime($row['transaction_date']));
        $time = date('h:i A', strtotime($row['transaction_date']));
        fputcsv($output, [
            $row['trans_id'],
            $row['customer_name'],
            $row['client_type'],
            number_format((float)$row['amount'], 2, '.', ''),
            $row['payment_type'],
            $row['trans_status'],
            $date,
            $time
        ]);
    }
} else {
    fputcsv($output, ['No transaction records found.']);
}

fclose($output);
exit;
