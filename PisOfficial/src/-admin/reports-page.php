<?php

declare(strict_types=1);

require_once '../include/config.php';
require_once '../include/dbh.inc.php';
require_once '../include/inc.admin/admin.model.php';
require_once '../include/inc.admin/admin.view.php';

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection not established.');
}

if (isset($_SESSION['user_id'])) {
    $userId   = (int)$_SESSION['user_id'];
    $username = htmlspecialchars($_SESSION['username']);
    $role     = htmlspecialchars($_SESSION['role']);

    // Fetch Admin Statistics
    $stats = get_admin_order_stats($pdo);
    $totalProducts = $stats['total_products'];
    $totalTransactionsCount = $stats['total_transactions'];
    $pendingRequestsCount = $stats['pending_requests'];


    // Fetch total cart items for Admin POS badge
    $cartItems = get_cart_items($pdo, $userId);
    $adminCartCount = count($cartItems);

    // Fetch Advanced Analytics Data
    $salesTrend = get_monthly_sales_trend($pdo);
    $stockHealth = get_inventory_health_stats($pdo);
    $topProducts = get_top_performing_products($pdo, 5, 'this_month');
    $categoryData = get_category_distribution($pdo);
    $revenueStats = get_revenue_stats($pdo);
    $srLogs       = get_sr_stock_logs($pdo, 3);
    $whLogs       = get_wh_stock_logs($pdo, 3);
    $pendingRequests = get_pending_order_requests($pdo, 5);
} else {
    header("Location: ../../public/index.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime-In-Sync | Reports</title>
    <link rel="icon" type="image/png" href="../../public/assets/img/favIcon.png">
    <meta name="csrf-token" content="<?= get_csrf_token() ?>">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js?v=1.4.0" defer></script>
    <script src="../../public/assets/js/order.js?v=1.4.0" defer></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include '../include/toast.php'; ?>


    <style>
        /* Shrink entire UI by 10% - Removed for native zoom support */
        /* html {
            zoom: 90%;
        } */
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-4 md:px-8">
    <header
        class="sticky top-0 z-40 flex h-[100px] items-center justify-between border-b border-gray-200 px-6 bg-white w-full max-w-7xl mx-auto px-4 md:px-8">

        <div class="flex flex-1">
            <a href="../-admin/dashboard-page.php" class=" flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/favIcon.png" alt="Prime Concept Logo"
                        class="h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-red-600">Prime-In-Sync</h1>
                    <h4 class="text-base text-gray-500">Welcome, <?= htmlspecialchars($username) ?></h4>
                </div>
            </a>
        </div>

        <!-- Right: Role + Icons -->
        <div class="flex items-center gap-4 justify-end w-1/2">
            <div class="rounded-md bg-red-100 px-3 py-1 text-sm text-red-600 font-medium">
                <?= htmlspecialchars(ucfirst($role)) ?> User</div>

            <!-- Notifications (Icon Only) -->
            <div class="relative inline-block">
                <button id="notifButton"
                    class="relative overflow-visible flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition active:scale-95">
                    <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                    </svg>
                </button>
            </div>
            <?php include '../include/sidebar-notif.php'; ?>

            <!-- Reports Shortcut -->
            <a href="../-admin/rep-generation.php" title="View Reports"
                class="flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition active:scale-95 group">
                <svg class="size-5 text-red-500 group-hover:scale-110 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </a>

            <!-- Settings -->
            <a href="../-admin/settings.php"
                class="flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </a>

            <!-- Logout -->
            <a href="javascript:void(0)" data-open-modal="logout-modal" class="logout-trigger flex items-center gap-2 border border-gray-300 px-4 h-9 rounded-lg hover:bg-red-50 hover:border-red-200 transition group">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                </svg>
                <span class="text-sm text-red-600 font-medium">Logout</span>
            </a>
            <?php include '../include/logout-modal.php'; ?>
        </div>
    </header>

    <section class="w-full max-w-7xl mx-auto px-4 md:px-6 py-4">
        <?php
        render_admin_stats_cards([
            [
                'label' => 'Total Sales Revenue',
                'value' => '₱' . number_format((float)$revenueStats['total'], 2),
                'subtext' => 'Performance: ₱' . number_format((float)$revenueStats['monthly'], 2) . ' this month',
                'indicator' => '<span class="flex h-2 w-2 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]"></span>'
            ],
            [
                'label' => 'Available Products',
                'value' => $totalProducts,
                'subtext' => 'Current inventory count.'
            ],
            [
                'label' => 'Total Transactions',
                'value' => $totalTransactionsCount,
                'subtext' => 'All recorded system sales.'
            ],
            [
                'label' => 'Pending Request',
                'value' => $pendingRequestsCount,
                'subtext' => 'Awaiting Review',
                'isCritical' => true,
                'animate' => true
            ]

        ], 4);
        ?>
    </section>

    <nav class="px-5 flex justify-center w-full max-w-7xl mx-auto">
        <div class="max-w-7xl w-full">
            <ul class="grid grid-cols-4 bg-gray-100 rounded-3xl h-12 shadow-sm px-5 items-center gap-2">

                <!-- POS System -->
                <li>
                    <a href="../-admin/pos-page.php"
                        class="relative flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 
                     2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 
                     0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <span>POS System</span>

                        <?php if ($adminCartCount > 0): ?>
                            <span class="absolute top-0 right-2 bg-red-600 text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-full shadow-md border-2 border-white transform translate-x-1/2 -translate-y-1/2 transition-all duration-300">
                                <?= $adminCartCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Inventory Management -->
                <li>
                    <a href="../-admin/inventory-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 
                                17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 
                                1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 
                                1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 
                                1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 
                                3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 
                                4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 
                                2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 
                                3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 
                                1.437 1.745-1.437m6.615 8.206L15.75 
                                15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                        </svg>
                        <span>Inventory Management</span>
                    </a>
                </li>

                <!-- Reports and Analytics (Active) -->
                <li>
                    <a href="../-admin/reports-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025  
                                4.242 0 1.172 1.025 1.172 2.687 0 
                                3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 
                                1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 
                                1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>Reports & Analytics</span>
                    </a>
                </li>

                <!-- Order Request -->
                <li>
                    <a href="../-admin/order-req-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 
                                21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 
                                0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                        </svg>
                        <span>Order Request</span>
                    </a>
                </li>

            </ul>
        </div>
    </nav>

    <!-- Reports and analytics Main Section -->
    <div id="reports-container" data-reports="<?= htmlspecialchars(json_encode(['stockHealth' => $stockHealth, 'salesTrend' => $salesTrend]), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center w-full max-w-7xl mx-auto px-6 pb-12">
        <div class="border border-gray-300 rounded-2xl p-6 md:p-12 w-full bg-white">

            <div>
                <h2 class="text-2xl font-semibold mb-2">Reports and Analytics</h2>
                <p class="text-gray-600 mb-6 font-medium">Comprehensive breakdown of inventory health, sales performance, and operational trends.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <div class="border-b border-gray-100 pb-3 mb-6">
                        <h3 class="font-bold text-gray-700 uppercase text-sm tracking-widest">Product Details</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-red-500 font-bold text-sm">Low Stock Variants</span>
                                <span class="text-2xl font-black text-gray-800"><?= $stockHealth['low_stock'] ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium text-sm">All Items</span>
                                <span class="text-xl font-bold text-gray-700"><?= $totalProducts ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500 font-medium text-sm">All Variants</span>
                                <span class="text-xl font-bold text-gray-700"><?= $stockHealth['total_variants'] ?></span>
                            </div>
                        </div>
                        <div class="flex flex-col items-center justify-center border-l border-gray-100 h-[150px]">
                            <canvas id="healthChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-6">
                        <h3 class="font-bold text-gray-700 uppercase text-sm tracking-widest">Top Selling Variants</h3>
                        <select id="topProductsFilter" class="text-xs font-bold text-gray-400 bg-transparent focus:outline-none cursor-pointer hover:text-red-500 transition-colors">
                            <option value="all">Overall</option>
                            <option value="this_month" selected>This month</option>
                            <option value="last_month">Last month</option>
                        </select>
                    </div>
                    <div id="topProductsContainer" class="grid grid-cols-3 gap-4 text-center">
                        <?php if (!empty($topProducts)): ?>
                            <?php foreach (array_slice($topProducts, 0, 3) as $tp):
                                $tpImg = !empty($tp['variant_image']) ? $tp['variant_image'] : $tp['default_image'];
                                $tpPath = "../../public/assets/img/furnitures/" . rawurlencode(trim($tpImg ?? 'default-placeholder.png'));
                            ?>
                                <div class="group cursor-pointer">
                                    <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto bg-gray-50 rounded-lg mb-3 flex items-center justify-center overflow-hidden border border-gray-100 group-hover:border-blue-300 transition">
                                        <img src="<?= $tpPath ?>" alt="<?= htmlspecialchars($tp['name']) ?>" class="object-contain h-full w-full">
                                    </div>
                                    <p class="text-[10px] sm:text-xs font-bold text-gray-700 truncate"><?= htmlspecialchars($tp['name']) ?></p>
                                    <p class="text-xs sm:text-sm font-black text-blue-600 mt-1"><?= (int)$tp['total_sold'] ?> <span class="text-[10px] text-gray-400">Sold</span></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-3 py-10 text-center text-gray-400 text-xs italic">No data available</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm mb-6">
                <h3 class="font-bold text-gray-700 uppercase text-sm tracking-widest mb-6 border-b border-gray-50 pb-4">Sales Revenue Trend</h3>
                <div class="h-[300px] w-full">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Transaction History Summary -->
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm mb-6 overflow-hidden flex flex-col">
                <div class="p-8 border-b border-gray-100 flex flex-col gap-6 bg-gray-50/20">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-5">
                            <div class="size-14 bg-red-600 rounded-2xl flex items-center justify-center border border-red-700 shadow-lg shadow-red-100">
                                <svg class="size-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-700 uppercase text-sm tracking-widest mb-1">Transaction History Summary</h3>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] leading-none">Comprehensive Payment Archives</p>
                            </div>
                        </div>

                        <!-- Transaction History Filter Bar (Exact SR Design Match) -->
                        <div class="flex flex-wrap items-end gap-3 bg-gray-50 p-4 rounded-2xl border border-gray-100">
                            <div class="flex flex-col gap-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-1">From</label>
                                <input type="date" id="reportStartDate" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:border-red-500 transition-all font-bold text-gray-700">
                            </div>

                            <div class="flex flex-col gap-1">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-1">To</label>
                                <input type="date" id="reportEndDate" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-sm outline-none focus:border-red-500 transition-all font-bold text-gray-700">
                            </div>

                            <div class="flex flex-col gap-1 min-w-[200px]">
                                <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest pl-1">Filter</label>
                                <div class="relative group">
                                    <select id="combinedTxnFilter" class="w-full h-10 appearance-none bg-white border border-gray-200 rounded-xl px-4 py-2 text-[11px] font-black uppercase tracking-widest text-gray-700 outline-none focus:border-red-500 transition-all cursor-pointer">
                                        <option value="All">All Clients</option>
                                        <option value="Government">Government</option>
                                        <option value="Private">Private</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400 group-hover:text-red-500 transition-colors">
                                        <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M19 9l-7 7-7-7" stroke-width="3" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <a href="javascript:void(0)" id="resetAdminFiltersBtn" class="h-10 px-6 bg-red-600 text-white text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-700 transition-all active:scale-95 flex items-center shadow-lg shadow-red-100">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto min-h-[300px]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[10px] uppercase font-black tracking-widest text-gray-400 bg-gray-50/50 border-b border-gray-50">
                                <th class="px-8 py-5">Transaction ID</th>
                                <th class="px-8 py-5">Date</th>
                                <th class="px-8 py-5">Customer Name</th>
                                <th class="px-8 py-5 text-center">Type</th>
                                <th class="px-8 py-5 text-center">Plan</th>
                                <th class="px-8 py-5 text-right">Total Amount</th>
                                <th class="px-8 py-5 text-center">Txn Status</th>
                                <th class="px-8 py-5 text-center">Details</th>
                            </tr>
                        </thead>
                        <tbody id="ordersReportContent" class="divide-y divide-gray-50 bg-white">
                            <!-- Data injected here -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer: Show All & Pagination -->
                <div id="orderTableFooter" class="p-6 border-t border-gray-50 bg-gray-50/10 flex items-center justify-center">
                    <!-- Buttons injected here -->
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-6 w-full mt-6">
                <!-- Showroom (SR) Logs Card -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col flex-1">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
                        <h3 class="font-bold text-gray-700 uppercase text-[10px] tracking-[0.2em] flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Showroom (SR) Activity
                        </h3>
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Recent 3 Logs</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                    <th class="pb-2 w-[45%]">Item / Variant</th>
                                    <th class="pb-2 text-center w-[20%]">Prod Code</th>
                                    <th class="pb-2 text-center w-[15%]">Qty</th>
                                    <th class="pb-2 text-right w-[20%]">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (!empty($srLogs)): ?>
                                    <?php foreach ($srLogs as $log): ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="py-2.5">
                                                <span class="font-bold text-gray-800 text-sm line-clamp-1"><?= htmlspecialchars($log['product_name']) ?></span>
                                                <span class="text-[9px] text-gray-400 font-medium uppercase tracking-tighter"><?= htmlspecialchars($log['variant_name'] ?: 'Standard') ?></span>
                                            </td>
                                            <td class="py-2.5 text-center">
                                                <span class="text-[9px] font-bold text-gray-800 uppercase tracking-[0.1em] px-2 py-1 bg-gray-100/80 rounded border border-gray-200"><?= htmlspecialchars($log['prod_code'] ?? 'N/A') ?></span>
                                            </td>
                                            <td class="py-2.5 text-center font-black <?= (int)$log['qty'] < 0 ? 'text-red-500' : 'text-green-500' ?>">
                                                <?= ($log['qty'] > 0 ? '+' : '') . (int)$log['qty'] ?>
                                            </td>
                                            <td class="py-2.5 text-right whitespace-nowrap text-gray-400 text-[10px] italic">
                                                <?= date('M d, H:i', strtotime($log['log_date'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-gray-300 italic">No recent SR activity</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Warehouse (WH) Logs Card -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col flex-1">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
                        <h3 class="font-bold text-gray-700 uppercase text-[10px] tracking-[0.2em] flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Warehouse (WH) Activity
                        </h3>
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Recent 3 Logs</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                    <th class="pb-2 w-[45%]">Item / Variant</th>
                                    <th class="pb-2 text-center w-[20%]">Prod Code</th>
                                    <th class="pb-2 text-center w-[15%]">Qty</th>
                                    <th class="pb-2 text-right w-[20%]">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (!empty($whLogs)): ?>
                                    <?php foreach ($whLogs as $log): ?>
                                        <tr class="hover:bg-slate-50 transition-colors">
                                            <td class="py-2.5">
                                                <span class="font-bold text-gray-800 text-sm line-clamp-1"><?= htmlspecialchars($log['product_name']) ?></span>
                                                <span class="text-[9px] text-gray-400 font-medium uppercase tracking-tighter"><?= htmlspecialchars($log['variant_name'] ?: 'Standard') ?></span>
                                            </td>
                                            <td class="py-2.5 text-center">
                                                <span class="text-[9px] font-bold text-gray-800 uppercase tracking-[0.1em] px-2 py-1 bg-gray-100/80 rounded border border-gray-200"><?= htmlspecialchars($log['prod_code'] ?? 'N/A') ?></span>
                                            </td>
                                            <td class="py-2.5 text-center font-black <?= (int)$log['qty'] < 0 ? 'text-red-500' : 'text-green-500' ?>">
                                                <?= ($log['qty'] > 0 ? '+' : '') . (int)$log['qty'] ?>
                                            </td>
                                            <td class="py-2.5 text-right whitespace-nowrap text-gray-400 text-[10px] italic">
                                                <?= date('M d, H:i', strtotime($log['log_date'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-gray-300 italic">No recent WH activity</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Transaction Details Modal -->
            <div id="txnDetailModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/40 p-4 transition-all duration-300">
                <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                    <div class="p-8 border-b border-gray-100 flex items-center justify-between bg-gray-50/30">
                        <div class="flex items-center gap-4">
                            <div class="size-12 bg-red-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-red-100">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div>
                                <h3 id="modalTxnId" class="text-lg font-black text-gray-900 tracking-tight leading-none mb-1 uppercase">Order #00000</h3>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Itemized Transaction Record</p>
                            </div>
                        </div>
                        <button id="closeTxnModalBtn" class="size-10 rounded-full flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div id="modalItemsScroll" class="p-8 max-h-[70vh] overflow-y-auto space-y-8">
                        <!-- 1. Top Detail Grid (Customer & Order Info) -->
                        <div id="modalSummaryHeader" class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                            <!-- Injected by JS -->
                        </div>

                        <!-- 2. Financial Overview Block -->
                        <div id="modalFinancialHeader" class="p-6 bg-red-50 rounded-2xl border border-red-100 grid grid-cols-2 lg:grid-cols-5 gap-6">
                            <!-- Injected by JS -->
                        </div>

                        <!-- 3. Itemized Products Table -->
                        <div>
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Itemized Products</h4>
                            <table class="w-full text-left bg-white border border-gray-100 rounded-xl overflow-hidden shadow-sm">
                                <thead>
                                    <tr class="text-[10px] uppercase font-black tracking-widest text-gray-400 bg-gray-50 border-b border-gray-100">
                                        <th class="px-5 py-4">Product / Item</th>
                                        <th class="py-4 text-center">Qty</th>
                                        <th class="py-4 text-right">Price</th>
                                        <th class="px-5 py-4 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="modalItemsContent" class="divide-y divide-gray-50">
                                    <!-- Injected by JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- 4. Payment Schedule (Installment Specific) -->
                        <div id="modalScheduleWrapper" class="hidden">
                            <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Automated Payment Schedule</h4>
                            <div class="border border-gray-100 rounded-xl overflow-hidden shadow-sm bg-white">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="text-[10px] uppercase font-black tracking-widest text-gray-400 bg-gray-100/50 border-b border-gray-100">
                                            <th class="px-5 py-4">Status</th>
                                            <th class="py-4">Due Date</th>
                                            <th class="py-4 text-right">Amount</th>
                                            <th class="px-5 py-4 text-right">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalScheduleContent" class="divide-y divide-gray-50">
                                        <!-- Injected by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 bg-gray-50 border-t border-gray-100 flex justify-end">
                        <div class="text-right">
                            <p class="text-[11px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Transaction Payable</p>
                            <h3 id="modalTotalAmount" class="text-2xl font-black text-gray-900 leading-none tracking-tighter">₱0.00</h3>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>



    <!-- External Logic -->
    <script src="../../public/assets/js/reports.js?v=<?= time() ?>" defer></script>

    <style>
        /* Premium Select Styling */
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
    </style>

</body>

</html>