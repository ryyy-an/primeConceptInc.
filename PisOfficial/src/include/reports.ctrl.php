<?php

declare(strict_types=1);

require_once 'config.php';
require_once 'dbh.inc.php';
require_once 'inc.admin/admin.model.php';

/**
 * Full System Report Controller
 * Generates an Excel-compatible report for administrators.
 */

// Basic access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access.');
}

$action = $_GET['action'] ?? '';

if ($action === 'generate_full_report') {
    generate_full_system_report($pdo);
}

function generate_full_system_report(PDO $pdo)
{
    // 1. GATHER DATA
    $stats = get_admin_order_stats($pdo);
    $revenue = get_revenue_stats($pdo);
    $userStats = get_user_summary_stats($pdo);
    
    $inventory = get_full_inventory_report_data($pdo);
    $sales = get_sales_report_data($pdo); // All time
    $users = get_all_users($pdo);

    $filename = "Full_System_Report_" . date('Y-m-d_His') . ".xls";

    // 2. SET HEADERS
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    // 3. GENERATE HTML TABLE (EXCEL COMPATIBLE)
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"><style>
        .header { background-color: #f7fafc; font-weight: bold; border: 1px solid #e2e8f0; }
        .title { font-size: 18px; font-weight: bold; color: #c53030; }
        td, th { border: 1px solid #e2e8f0; padding: 5px; text-align: left; }
        .section-header { background-color: #edf2f7; font-weight: bold; font-size: 14px; }
        .currency { text-align: right; }
    </style></head><body>';

    // --- SECTION 1: SYSTEM OVERVIEW ---
    echo '<table>';
    echo '<tr><td colspan="4" class="title">PRIME-IN-SYNC | FULL SYSTEM REPORT</td></tr>';
    echo '<tr><td colspan="4">Generated on: ' . date('F j, Y, g:i a') . '</td></tr>';
    echo '<tr><td colspan="4"></td></tr>';

    echo '<tr><td colspan="4" class="section-header">SYSTEM OVERVIEW</td></tr>';
    echo '<tr><td class="header">Metric</td><td class="header" colspan="3">Value</td></tr>';
    echo '<tr><td>Total Revenue</td><td colspan="3">₱' . number_format($revenue['total'], 2) . '</td></tr>';
    echo '<tr><td>Monthly Revenue (This Month)</td><td colspan="3">₱' . number_format($revenue['monthly'], 2) . '</td></tr>';
    echo '<tr><td>Total Transactions</td><td colspan="3">' . number_format($stats['total_transactions']) . '</td></tr>';
    echo '<tr><td>Total Unique Products</td><td colspan="3">' . number_format($stats['total_products']) . '</td></tr>';
    echo '<tr><td>Total Staff</td><td colspan="3">' . number_format($userStats['total_staff']) . '</td></tr>';
    echo '<tr><td>Active Sessions</td><td colspan="3">' . number_format($userStats['active_now']) . '</td></tr>';
    echo '<tr><td colspan="4"></td></tr>';

    // --- SECTION 2: INVENTORY ---
    echo '<tr><td colspan="4" class="section-header">INVENTORY STATUS</td></tr>';
    echo '<tr>
            <td class="header">Product Code</td>
            <td class="header">Product Name</td>
            <td class="header">Category</td>
            <td class="header">Variant</td>
            <td class="header">Price</td>
            <td class="header">Showroom Stock</td>
            <td class="header">Warehouse Stock</td>
          </tr>';
    foreach ($inventory as $row) {
        echo '<tr>
                <td>' . $row['code'] . '</td>
                <td>' . $row['prod_name'] . '</td>
                <td>' . $row['category'] . '</td>
                <td>' . $row['variant_name'] . '</td>
                <td class="currency">₱' . number_format((float)$row['price'], 2) . '</td>
                <td>' . $row['sr_qty'] . '</td>
                <td>' . $row['wh_qty'] . '</td>
              </tr>';
    }
    echo '<tr><td colspan="4"></td></tr>';

    // --- SECTION 3: RECENT SALES ---
    echo '<tr><td colspan="4" class="section-header">SALES HISTORY</td></tr>';
    echo '<tr>
            <td class="header">Transaction ID</td>
            <td class="header">Date</td>
            <td class="header">OR Number</td>
            <td class="header">Customer Name</td>
            <td class="header">Client Type</td>
            <td class="header">Payment Mode</td>
            <td class="header">Total Amount</td>
            <td class="header">Status</td>
          </tr>';
    foreach ($sales as $row) {
        echo '<tr>
                <td>TXN-' . $row['trans_id'] . '</td>
                <td>' . $row['transaction_date'] . '</td>
                <td>' . ($row['or_number'] ?? 'N/A') . '</td>
                <td>' . $row['customer_name'] . '</td>
                <td>' . $row['client_type'] . '</td>
                <td>' . $row['payment_mode'] . '</td>
                <td class="currency">₱' . number_format((float)$row['amount_paid'], 2) . '</td>
                <td>' . $row['trans_status'] . '</td>
              </tr>';
    }
    echo '<tr><td colspan="4"></td></tr>';

    // --- SECTION 4: STAFF OVERVIEW ---
    echo '<tr><td colspan="4" class="section-header">STAFF OVERVIEW</td></tr>';
    echo '<tr>
            <td class="header">Staff Name</td>
            <td class="header">Username</td>
            <td class="header">Role</td>
            <td class="header">Account Created</td>
            <td class="header">Status</td>
          </tr>';
    foreach ($users as $row) {
        echo '<tr>
                <td>' . $row['full_name'] . '</td>
                <td>' . $row['username'] . '</td>
                <td>' . ucfirst($row['role']) . '</td>
                <td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>
                <td>' . ($row['is_online'] ? 'Online' : 'Offline') . '</td>
              </tr>';
    }

    echo '</table></body></html>';
    exit;
}
