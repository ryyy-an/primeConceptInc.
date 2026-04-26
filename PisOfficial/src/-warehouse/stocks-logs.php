<?php

declare(strict_types=1);

require_once "../include/config.php";
require_once "../include/dbh.inc.php";
require_once "../include/global.model.php";
require_once "../include/inc.showroom/sr.model.php";
require_once "../include/inc.warehouse/wh.model.php";
require_once "../include/inc.admin/admin.view.php";

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection not established.");
}

if (isset($_SESSION["user_id"])) {
    $userId = $_SESSION["user_id"];
    $username = htmlspecialchars($_SESSION["username"]);
    $role = htmlspecialchars($_SESSION["role"]);
} else {
    header("Location: ../../public/index.php");
    exit();
}

// Fetch Aggregated Inventory
$inventory = get_inventory_cards($pdo);

// Dashboard Stats
$stats = get_warehouse_dashboard_stats($pdo, (int)$userId);
$totalProducts = $stats['total_products'];
$userTransactions = $stats['user_transactions'];
$pendingWH = $stats['pending_wh'];
$pendingSR = $stats['pending_sr'];

// --- PAGINATION & FILTERING ---
$currentPage = (int)($_GET["page"] ?? 1);
if ($currentPage < 1) {
    $currentPage = 1;
}
$limit = 5; // Max row of data is 5
$offset = ($currentPage - 1) * $limit;

$start_date = $_GET["start_date"] ?? "";
$end_date = $_GET["end_date"] ?? "";

$filters = [
    "start_date" => $start_date,
    "end_date" => $end_date,
];

$total_records = count_warehouse_logs($pdo, $filters);
$total_pages = max(1, (int) ceil($total_records / $limit));
$logs = get_warehouse_logs($pdo, $filters, $limit, $offset);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime-In-Sync</title>
    <link rel="icon" type="image/png" href="../../public/assets/img/favIcon.png">
    <meta name="csrf-token" content="<?= get_csrf_token() ?>">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js?v=1.2" defer></script>
    <script src="../../public/assets/js/warehouse.js?v=1.2" defer></script>

    <style>
        /* Shrink entire UI by 10% - Removed for native zoom support */
        /* html {
            zoom: 90%;
        } */
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-4 md:px-8">
    <header
        class="sticky top-0 z-40 flex h-[100px] items-center justify-between border-b border-gray-200 px-6 bg-white w-full max-w-7xl mx-auto">

        <div class="flex flex-1">
            <a href="../-warehouse/dashboard-page.php" class="flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/favIcon.png" alt="Prime Concept Logo"
                        class="h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-red-600">Prime-In-Sync</h1>
                    <h4 class="text-base text-gray-500">Welcome, <?= htmlspecialchars(
                                                                        $username,
                                                                    ) ?></h4>
                </div>
            </a>
        </div>

        <!-- Right: Role + Icons -->
        <div class="flex items-center gap-4 justify-end w-1/2">
            <div class="rounded-md bg-red-100 px-3 py-1 text-sm text-red-600 font-medium">
                <?= htmlspecialchars(ucfirst($role)) ?> User
            </div>

            <button id="notifButton"
                class="relative overflow-visible flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition active:scale-95">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                </svg>
            </button>

            <?php include '../include/sidebar-notif.php'; ?>

            <a href="javascript:void(0)"
                class="logout-trigger flex items-center gap-2 border border-gray-300 px-4 h-9 rounded-lg hover:bg-red-50 hover:border-red-200 transition group">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                </svg>
                <span class="text-sm text-red-600 font-medium">Logout</span>
            </a>

        </div>
    </header>

    <section class="max-w-7xl w-full mx-auto px-6 py-4">
        <?php
        render_admin_stats_cards([
            [
                'label'   => 'Available Products',
                'value'   => $totalProducts,
                'subtext' => 'Total base furniture items.'
            ],
            [
                'label'   => 'Your Transactions',
                'value'   => $userTransactions,
                'subtext' => 'Total orders you processed.'
            ],
            [
                'label'      => 'Pending Warehouse',
                'value'      => $pendingWH,
                'subtext'    => 'Current pending WH requests.',
                'isCritical' => true,
                'animate'    => $pendingWH > 0
            ],
            [
                'label'      => 'Pending Showroom',
                'value'      => $pendingSR,
                'subtext'    => 'Current pending SR requests.',
                'isCritical' => true,
                'animate'    => $pendingSR > 0
            ]
        ], 4);
        ?>
    </section>

    <nav class="px-5 flex justify-center w-full max-w-7xl mx-auto">
        <div class="max-w-7xl w-full">
            <ul class="grid grid-cols-3 bg-gray-100 rounded-3xl h-12 shadow-sm px-5 items-center gap-2">

                <!-- Order Fulfillment -->
                <li>
                    <a href="order-fulfilment-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 
                     12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 
                     0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                        </svg>
                        <span>Order Fulfillment</span>
                    </a>
                </li>

                <!-- Inventory -->
                <li>
                    <a href="inventory.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 
                     1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 
                     1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 
                     1 5.513 7.5h12.974c.576 0 1.059.435 1.119 
                     1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 
                     .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 
                     1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span>Inventory</span>
                    </a>
                </li>

                <!-- Product Status -->
                <li>
                    <a href="stocks-logs.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 
                     4.242 0 1.172 1.025 1.172 2.687 0 
                     3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 
                     1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 
                     1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>Warehouse Stocks Reports</span>
                    </a>
                </li>

            </ul>
        </div>
    </nav>


    <section class="flex flex-col items-center w-full max-w-7xl mx-auto px-6">
        <div class="border border-gray-300 rounded-2xl p-6 md:p-12 w-full bg-gray-50">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-2xl font-semibold mb-2">Warehouse Stocks Reports</h2>
                    <p class="text-gray-600 font-medium tracking-tight">Monitor all internal stock movements and component adjustments.</p>
                </div>

                <!-- Filter Bar (Real-Time Auto-Submit) -->
                <form method="GET" class="flex flex-wrap items-end gap-3 bg-gray-50 p-4 rounded-2xl border border-gray-100">
                    <div class="flex flex-col gap-1">
                        <label for="start_date" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-1">From</label>
                        <input type="date" id="start_date" name="start_date" onchange="this.form.submit()" value="<?= htmlspecialchars($start_date) ?>" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:border-red-500 transition-all font-bold text-gray-700">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="end_date" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-1">To</label>
                        <input type="date" id="end_date" name="end_date" onchange="this.form.submit()" value="<?= htmlspecialchars($end_date) ?>" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:border-red-500 transition-all font-bold text-gray-700">
                    </div>

                    <div class="flex gap-2">
                        <a href="stocks-logs.php" class="h-10 px-6 bg-red-600 text-white text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-700 transition-all active:scale-95 flex items-center shadow-lg shadow-red-100">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Table of Stocks Logs -->
            <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900">
                <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                    <thead class="bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100">
                        <tr>
                            <th class="pl-8 pr-2 py-4 w-[6%]">Log</th>
                            <th class="px-2 py-4 w-[10%] text-center">Code</th>
                            <th class="px-4 py-4 w-[40%]">Product & Variant</th>
                            <th class="px-4 py-4 w-[24%]">Component Name</th>
                            <th class="px-4 py-4 text-center w-[8%]">Qty</th>
                            <th class="px-8 py-4 text-right w-[12%]">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">No stock logs found for the selected period.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50/50 transition duration-200">
                                    <td class="pl-8 pr-2 py-4">
                                        <span class="text-[10px] font-bold text-gray-300">#<?= htmlspecialchars((string) $log["log_id"]) ?></span>
                                    </td>
                                    <td class="px-2 py-4 text-center text-gray-400">
                                        <span class="text-xs font-black"><?= htmlspecialchars($log['product_code'] ?? '---') ?></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-black text-gray-900 leading-tight"><?= htmlspecialchars($log['product_name'] ?? 'System Movement') ?></span>
                                            <span class="text-[10px] font-bold text-red-500 uppercase tracking-tighter"><?= htmlspecialchars($log['variant_name'] ?? 'General') ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="text-sm font-bold text-gray-700"><?= htmlspecialchars($log["component_name"] ?? "Manual Adjustment") ?></span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="text-sm font-black <?= $log["qty"] < 0 ? "text-red-500" : "text-green-600" ?>">
                                            <?= ($log["qty"] > 0 ? "+" : "") . (int) $log["qty"] ?>
                                        </span>
                                    </td>
                                    <td class="px-8 py-4 text-right">
                                        <div class="flex flex-col items-end text-gray-400">
                                            <span class="text-xs font-bold leading-tight"><?= date("M d, Y", strtotime($log["log_date"])) ?></span>
                                            <span class="text-[10px] font-semibold opacity-70"><?= date("h:i A", strtotime($log["log_date"])) ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between mt-4 rounded-xl">
                <div class="text-[11px] text-gray-400 font-bold uppercase tracking-widest">
                    Showing <span class="text-gray-900"><?= $total_records > 0 ? $offset + 1 : 0 ?></span> to <span class="text-gray-900"><?= min($offset + $limit, $total_records) ?></span> of <span class="text-gray-900"><?= $total_records ?></span> results
                </div>
                <div class="flex items-center gap-1">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?= $currentPage - 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="p-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-all text-gray-400 hover:text-gray-600 active:scale-95">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                    <?php endif; ?>

                    <?php
                    $startP = max(1, $currentPage - 2);
                    $endP = min($total_pages, $currentPage + 2);
                    for ($i = $startP; $i <= $endP; $i++):
                    ?>
                        <a href="?page=<?= $i ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>"
                            class="size-8 flex items-center justify-center rounded-lg text-xs font-bold transition-all active:scale-95 <?= $i === $currentPage ? "bg-red-600 text-white shadow-md shadow-red-200" : "text-gray-400 hover:bg-gray-100" ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $total_pages): ?>
                        <a href="?page=<?= $currentPage + 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="p-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-all text-gray-400 hover:text-gray-600 active:scale-95">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>


    </section>


    <?php include '../include/logout-modal.php'; ?>
</body>

</html>