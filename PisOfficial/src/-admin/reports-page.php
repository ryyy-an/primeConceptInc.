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
    $orderSummary = get_order_status_summary($pdo);
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
    <link rel="icon" type="image/x-icon" href="../../public/assets/img/primeLogo.ico">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js" defer></script>
    <script src="../../public/assets/js/order.js" defer></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include '../include/toast.php'; ?>


    <style>
        /* Shrink entire UI by 10% */
        html {
            zoom: 90%;
        }
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-[100px]">
    <header
        class="sticky top-0 z-40 flex h-[100px] items-center justify-between border-b border-gray-200 px-6 bg-white container">

        <div class="flex container">
            <a href="../-admin/dashboard-page.php" class=" flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/primeLogo.ico" alt="Prime Concept Logo"
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
                    class="flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition active:scale-95">
                    <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                    </svg>
                </button>
            </div>
            <?php include '../include/sidebar-notif.php'; ?>

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
            <a href="javascript:void(0)" onclick="toggleLogoutModal(true)"
                class="flex items-center gap-2 border border-gray-300 px-4 h-9 rounded-lg hover:bg-red-50 hover:border-red-200 transition group">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                </svg>
                <span class="text-sm text-red-600 font-medium">Logout</span>
            </a>
            <?php include '../include/logout-modal.php'; ?>
        </div>
    </header>

    <section class="px-6 py-4">
        <?php
        render_admin_stats_cards([
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
        ]);
        ?>
    </section>

    <nav class="px-5 flex justify-center">
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
    <div class="flex justify-center w-full">
        <div class="border border-gray-300 rounded-2xl p-12 w-full max-w-312.5 bg-white">

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
                        <select id="topProductsFilter" onchange="fetchTopProducts()" class="text-xs font-bold text-gray-400 bg-transparent focus:outline-none cursor-pointer hover:text-red-500 transition-colors">
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

                        <!-- Filters Container -->
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Date Range -->
                            <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-2xl p-1.5 shadow-sm">
                                <div class="flex flex-col px-3">
                                    <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-0.5">Start Date</span>
                                    <input type="date" id="reportStartDate" onchange="fetchOrdersReport()" 
                                        class="text-[11px] font-bold text-gray-800 outline-none bg-transparent">
                                </div>
                                <div class="w-px h-8 bg-gray-100"></div>
                                <div class="flex flex-col px-3">
                                    <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-0.5">End Date</span>
                                    <input type="date" id="reportEndDate" onchange="fetchOrdersReport()" 
                                        class="text-[11px] font-bold text-gray-800 outline-none bg-transparent">
                                </div>
                            </div>

                            <!-- Status Filter -->
                            <div class="relative min-w-44 group">
                                <select id="txnStatusFilter" onchange="fetchOrdersReport()"
                                    class="w-full appearance-none bg-white border border-gray-200 rounded-2xl px-5 py-3 text-xs font-black uppercase tracking-widest text-gray-700 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-50 transition-all cursor-pointer shadow-sm">
                                    <option value="All">All Status</option>
                                    <option value="Success">Success</option>
                                    <option value="Ongoing">Ongoing</option>
                                </select>
                                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-400 group-hover:text-red-500 transition-colors">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 9l-7 7-7-7" stroke-width="3" />
                                    </svg>
                                </div>
                            </div>

                            <!-- Payment Type Filter -->
                            <div class="relative min-w-44 group">
                                <select id="txnPlanFilter" onchange="fetchOrdersReport()"
                                    class="w-full appearance-none bg-white border border-gray-200 rounded-2xl px-5 py-3 text-xs font-black uppercase tracking-widest text-gray-700 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-50 transition-all cursor-pointer shadow-sm">
                                    <option value="All">All Plans</option>
                                    <option value="Full">Full Payment</option>
                                    <option value="Installment">Installment</option>
                                </select>
                                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-gray-400 group-hover:text-red-500 transition-colors">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 9l-7 7-7-7" stroke-width="3" />
                                    </svg>
                                </div>
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
                <!-- Total Revenue (35% Width) -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between" style="width: 35%;">
                    <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-6">
                        <h3 class="font-bold text-gray-700 uppercase text-sm tracking-widest text-red-600">Total Sales Revenue</h3>
                    </div>
                    <div class="text-center py-4">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Year To Date (Overall)</p>
                        <p class="text-3xl font-black text-red-600">₱<?= number_format($revenueStats['total'], 2) ?></p>
                        <div class="w-full border-t border-dashed border-gray-200 my-6"></div>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">This Month Performance</p>
                        <p class="text-3xl font-black text-red-500">₱<?= number_format($revenueStats['monthly'], 2) ?></p>
                    </div>
                </div>

                <!-- Sales Order Status (65% Width) -->
                <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm" style="width: 65%;">
                    <h3 class="font-bold text-gray-700 uppercase text-sm tracking-widest border-b border-gray-100 pb-3 mb-4">Sales Order Status</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-gray-400 font-bold border-b border-gray-50">
                                    <th class="py-4 w-1/3">Source</th>
                                    <th class="py-4 text-center w-1/6">Confirmed</th>
                                    <th class="py-4 text-center w-1/6">Partial</th>
                                    <th class="py-4 text-center w-1/6">Shipped</th>
                                    <th class="py-4 text-center w-1/6">Delivered</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php if (!empty($orderSummary)): ?>
                                    <?php foreach ($orderSummary as $row): ?>
                                        <tr class="text-gray-600 font-medium hover:bg-slate-50 transition">
                                            <td class="py-4 font-bold text-gray-800">
                                                <?= $row['source_role'] === 'admin' ? 'Showroom POS' : 'Online / Showroom' ?>
                                            </td>
                                            <td class="py-4 text-center font-bold text-blue-600"><?= (int)$row['approved'] ?></td>
                                            <td class="py-4 text-center font-bold text-orange-400"><?= (int)$row['partial'] ?></td>
                                            <td class="py-4 text-center font-bold text-blue-400"><?= (int)$row['shipped'] ?></td>
                                            <td class="py-4 text-center font-bold text-green-500"><?= (int)$row['delivered'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-gray-400 italic">No order status data recorded</td>
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
                        <button onclick="closeTxnModal()" class="size-10 rounded-full flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Health Chart (Doughnut)
            const healthCtx = document.getElementById('healthChart').getContext('2d');
            new Chart(healthCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Healthy', 'Low', 'Out'],
                    datasets: [{
                        data: [
                            <?= $stockHealth['healthy'] ?>,
                            <?= $stockHealth['low_stock'] ?>,
                            <?= $stockHealth['out_of_stock'] ?>
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // 2. Sales Chart (Line)
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            window.salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: [<?= "'" . implode("','", array_column($salesTrend, 'month_name')) . "'" ?>],
                    datasets: [{
                        label: 'Revenue',
                        data: [<?= implode(",", array_column($salesTrend, 'revenue')) ?>],
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#dc2626'
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                callback: value => '₱' + value.toLocaleString()
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // --- ORDERS REPORT PROGRESSIVE LOGIC ---
            let allOrdersData = [];
            let currentStatus = 'All';
            let displayLimit = 3;
            let paginationThreshold = 5;
            let currentPage = 1;

            window.changeOrderStatusFilter = function(status) {
                fetchOrdersReport();
            };

            window.fetchOrdersReport = function() {
                const content = document.getElementById('ordersReportContent');
                const start = document.getElementById('reportStartDate').value;
                const end = document.getElementById('reportEndDate').value;
                const status = document.getElementById('txnStatusFilter')?.value || 'All';
                const plan = document.getElementById('txnPlanFilter')?.value || 'All';
                
                content.innerHTML = `<tr><td colspan="8" class="py-20 text-center"><div class="flex flex-col items-center gap-2"><div class="size-8 border-4 border-gray-100 border-t-red-600 rounded-full animate-spin"></div><p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Loading transactions...</p></div></td></tr>`;

                fetch(`../include/inc.admin/admin.ctrl.php?action=get_report_sales&status=${status}&plan=${plan}&start=${start}&end=${end}`)
                    .then(res => res.json())
                    .then(response => {
                        if (response.success) {
                            allOrdersData = response.data;
                            renderOrdersTable();
                        }
                    });

                if (typeof updateSalesTrendChart === 'function') updateSalesTrendChart();
                window.renderOrdersTable = function() {
                    const content = document.getElementById('ordersReportContent');
                    const footer = document.getElementById('orderTableFooter');

                    if (allOrdersData.length === 0) {
                        content.innerHTML = `<tr><td colspan="7" class="py-20 text-center"><p class="text-[11px] font-black text-gray-300 uppercase tracking-widest">No transaction records found</p></td></tr>`;
                        footer.innerHTML = '';
                        return;
                    }

                    // Determine display range
                    let dataToShow = [];
                    let total = allOrdersData.length;

                    if (total > paginationThreshold && displayLimit === paginationThreshold) {
                        let start = (currentPage - 1) * paginationThreshold;
                        let end = start + paginationThreshold;
                        dataToShow = allOrdersData.slice(start, end);
                    } else {
                        dataToShow = allOrdersData.slice(0, displayLimit);
                    }

                    // Render Table
                    content.innerHTML = dataToShow.map(row => {
                        const statusColors = {
                            'Approved': 'bg-green-100 text-green-700',
                            'Rejected': 'bg-red-100 text-red-700',
                            'Cancelled': 'bg-gray-100 text-gray-600',
                            'For Review': 'bg-amber-100 text-amber-700'
                        };
                        const color = statusColors[row.order_status] || 'bg-blue-100 text-blue-700';

                        return `
                        <tr class="hover:bg-red-50/30 transition group">
                            <td class="px-8 py-5">
                                <p class="font-mono text-[11px] text-gray-400 font-bold tracking-tighter leading-none mb-1">#TXN-${row.trans_id.toString().padStart(5, '0')}</p>
                                <p class="text-[9px] font-black text-gray-900 border-l-2 border-red-500 pl-2 uppercase tracking-widest" title="OR Number">${row.or_number || 'NO-REF'}</p>
                            </td>
                            <td class="px-8 py-5 text-sm font-bold text-gray-700 leading-tight">
                                ${new Date(row.transaction_date).toLocaleDateString()}<br>
                                <span class="text-[9px] text-gray-400 font-medium font-mono">${new Date(row.transaction_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                            </td>
                            <td class="px-8 py-5 text-sm font-black text-gray-900 leading-tight">
                                ${row.customer_name}
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest whitespace-nowrap ${row.client_type === 'Government' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-50 text-slate-500'}">
                                    ${row.client_type || 'Private'}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest whitespace-nowrap ${row.plan === 'Installment' ? 'bg-orange-50 text-orange-600 border border-orange-100' : 'bg-blue-50 text-blue-600 border border-blue-100'}">
                                    ${row.plan === 'Installment' ? 'Installment' : 'Full Paid'}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-right font-black text-gray-900">₱${parseFloat(row.amount_paid).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                            <td class="px-8 py-5 text-center">
                                ${(() => {
                                    const s = (row.trans_status || '').toUpperCase();
                                    const colors = {
                                        'SUCCESS':  'bg-green-50 text-green-600 border-green-200',
                                        'ONGOING':  'bg-orange-50 text-orange-600 border-orange-200',
                                        'PENDING':  'bg-yellow-50 text-yellow-600 border-yellow-200',
                                        'FAILED':   'bg-red-50 text-red-600 border-red-200',
                                    };
                                    const cls = colors[s] || 'bg-gray-50 text-gray-600 border-gray-200';
                                    return `<span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border ${cls}">${row.trans_status}</span>`;
                                })()}
                            </td>
                            <td class="px-8 py-5 text-center">
                                <button onclick="viewOrderDetails(${row.order_id})" class="size-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-red-600 hover:text-white transition shadow-sm border border-gray-100" title="View Parent Order Details">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            </td>
                        </tr>
                    `;
                    }).join('');

                    // Same footer logic...
                    if (total > 3 && displayLimit === 3) {
                        footer.innerHTML = `
                        <button onclick="expandOrderTable()" class="flex items-center gap-2 text-[10px] font-black text-gray-900 uppercase tracking-widest hover:text-red-600 transition group">
                            Show All (${total})
                            <svg class="size-4 group-hover:translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" /></svg>
                        </button>
                    `;
                    } else if (displayLimit === paginationThreshold) {
                        let totalPages = Math.ceil(total / paginationThreshold);
                        let pagesHtml = '';
                        for (let i = 1; i <= totalPages; i++) {
                            pagesHtml += `<button onclick="goToOrderPage(${i})" class="size-8 rounded-lg text-xs font-black transition ${currentPage === i ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'}">${i}</button>`;
                        }
                        footer.innerHTML = `
                        <div class="flex flex-col items-center gap-4">
                            <button onclick="showLessOrders()" class="text-[10px] font-black text-gray-400 uppercase tracking-widest hover:text-red-600 transition group flex items-center gap-2">
                                <svg class="size-4 group-hover:-translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="3" /></svg>
                                Show Less
                            </button>
                            <div class="flex items-center gap-2">${pagesHtml}</div>
                        </div>`;
                    } else {

                        footer.innerHTML = '';
                    }
                };

                window.viewOrderDetails = function(orderId) {
                    const modal = document.getElementById('txnDetailModal');
                    const content = document.getElementById('modalItemsContent');
                    const summaryHeader = document.getElementById('modalSummaryHeader');
                    const financialHeader = document.getElementById('modalFinancialHeader');
                    const scheduleContent = document.getElementById('modalScheduleContent');
                    const scheduleWrapper = document.getElementById('modalScheduleWrapper');
                    const title = document.getElementById('modalTxnId');
                    const totalDisplay = document.getElementById('modalTotalAmount');

                    title.innerText = `Order #ORD-${orderId.toString().padStart(5, '0')}`;
                    summaryHeader.innerHTML = '<div class="col-span-full py-4 animate-pulse bg-gray-50 rounded-xl"></div>';
                    financialHeader.innerHTML = '<div class="col-span-full py-4 animate-pulse bg-gray-50 rounded-xl"></div>';
                    content.innerHTML = `<tr><td colspan="4" class="py-10 text-center"><div class="animate-spin size-6 border-4 border-gray-100 border-t-red-600 rounded-full mx-auto mb-2"></div><p class="text-[10px] font-black text-gray-300 uppercase tracking-widest">Fetching data...</p></td></tr>`;
                    
                    scheduleWrapper.classList.add('hidden');
                    modal.classList.remove('hidden');

                    fetch(`../include/inc.admin/admin.ctrl.php?action=get_order_details&order_id=${orderId}`)
                        .then(res => res.json())
                        .then(response => {
                            if (response.success && response.summary) {
                                const s = response.summary;

                                // 1. Summary Badges
                                summaryHeader.innerHTML = `
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Customer</p>
                                        <p class="text-xs font-black text-gray-900">${s.customer_name}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                                        <p class="text-xs font-black text-gray-900">${s.contact_no || 'N/A'}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">OR Number</p>
                                        <p class="text-xs font-black text-red-600 uppercase">${s.or_number || 'N/A'}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Payment Mode</p>
                                        <p class="text-xs font-black text-gray-900 uppercase">${s.payment_mode || 'N/A'}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Txn Date</p>
                                        <p class="text-xs font-black text-gray-900 uppercase">${new Date(s.transaction_date).toLocaleDateString()}</p>
                                    </div>
                                `;

                                // 2. Financial Overview
                                const totalPaid = (response.schedule || []).reduce((acc, row) => acc + (row.status === 'Paid' ? parseFloat(row.amount_paid) : 0), 0) || parseFloat(s.total) - parseFloat(s.balance);
                                
                                financialHeader.innerHTML = `
                                    <div>
                                        <p class="text-[9px] font-black text-red-400 uppercase tracking-widest mb-1">Principal</p>
                                        <p class="text-lg font-black text-red-600 leading-none">₱${parseFloat(s.total).toLocaleString()}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-red-400 uppercase tracking-widest mb-1">Interest</p>
                                        <p class="text-lg font-black text-red-600 leading-none">${s.interest_rate || 0}%</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-red-400 uppercase tracking-widest mb-1">Total Payable</p>
                                        <p class="text-lg font-black text-red-600 leading-none">₱${parseFloat(s.total_with_interest || s.total).toLocaleString()}</p>
                                    </div>
                                    <div class="border-l border-red-200 pl-6">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Paid</p>
                                        <p class="text-lg font-black text-green-600 leading-none">₱${totalPaid.toLocaleString()}</p>
                                    </div>
                                    <div>
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Balance</p>
                                        <p class="text-lg font-black ${parseFloat(s.balance) > 0 ? 'text-red-500' : 'text-green-500'} leading-none">₱${parseFloat(s.balance).toLocaleString()}</p>
                                    </div>
                                `;

                                // 3. Items
                                let itemsHtml = '';
                                response.items.forEach(item => {
                                    const subtotal = item.quantity * item.price;
                                    itemsHtml += `
                                        <tr class="text-[11px] font-bold">
                                            <td class="px-5 py-4">
                                                <p class="text-gray-900 uppercase tracking-tighter">${item.prod_name}</p>
                                                <p class="text-[10px] text-gray-400 leading-none mt-1 uppercase">${item.variant}</p>
                                            </td>
                                            <td class="py-4 text-center text-gray-600">${item.quantity}</td>
                                            <td class="py-4 text-right text-gray-400">₱${parseFloat(item.price).toLocaleString()}</td>
                                            <td class="px-5 py-4 text-right text-gray-900 font-black">₱${subtotal.toLocaleString()}</td>
                                        </tr>
                                    `;
                                });
                                content.innerHTML = itemsHtml;
                                totalDisplay.innerText = `₱${parseFloat(s.total_with_interest || s.total).toLocaleString(undefined, {minimumFractionDigits: 2})}`;

                                // 4. Schedule (if Installment)
                                if (s.payment_type === 'Installment' && response.schedule && response.schedule.length > 0) {
                                    scheduleWrapper.classList.remove('hidden');
                                    let scheduleHtml = '';
                                    response.schedule.forEach(row => {
                                        const statusCls = row.status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                                        scheduleHtml += `
                                            <tr class="text-[10px] font-bold">
                                                <td class="px-5 py-3">
                                                    <span class="px-2 py-0.5 rounded-full uppercase tracking-widest text-[8px] font-black ${statusCls}">${row.status}</span>
                                                </td>
                                                <td class="py-3 text-gray-600">${row.due_date ? new Date(row.due_date).toLocaleDateString() : 'N/A'}</td>
                                                <td class="py-3 text-right text-gray-900">₱${parseFloat(row.amount_paid).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                                <td class="px-5 py-3 text-right text-gray-400 uppercase tracking-tighter">${row.remarks || '---'}</td>
                                            </tr>
                                        `;
                                    });
                                    scheduleContent.innerHTML = scheduleHtml;
                                }
                            }
                        });
                };

                window.closeTxnModal = function() {
                    document.getElementById('txnDetailModal').classList.add('hidden');
                };

                window.expandOrderTable = function() {
                    displayLimit = paginationThreshold;
                    renderOrdersTable();
                };

                window.showLessOrders = function() {
                    displayLimit = 3;
                    currentPage = 1;
                    renderOrdersTable();
                };

                window.goToOrderPage = function(page) {
                    currentPage = page;
                    renderOrdersTable();
                };

                window.updateSalesTrendChart = function() {
                    const start = document.getElementById('reportStartDate').value;
                    const end = document.getElementById('reportEndDate').value;
                    
                    fetch(`../include/inc.admin/admin.ctrl.php?action=get_revenue_trend&start=${start}&end=${end}`)
                        .then(res => res.json())
                        .then(res => {
                            if (res.success && window.salesChart) {
                                window.salesChart.data.labels = res.labels;
                                window.salesChart.data.datasets[0].data = res.data;
                                window.salesChart.update();
                            }
                        });
                };

                window.fetchTopProducts = function() {
                    const period = document.getElementById('topProductsFilter').value;
                    const container = document.getElementById('topProductsContainer');
                    
                    // Loading state
                    container.innerHTML = `<div class="col-span-3 py-10 text-center text-gray-400 text-xs italic">Updating...</div>`;
                    
                    fetch(`../include/inc.admin/admin.ctrl.php?action=get_top_products&period=${period}`)
                        .then(res => res.json())
                        .then(res => {
                            if (res.success && res.data) {
                                if (res.data.length === 0) {
                                    container.innerHTML = `<div class="col-span-3 py-10 text-center text-gray-400 text-xs italic uppercase">Walang data sa period na ito</div>`;
                                    return;
                                }
                                container.innerHTML = res.data.map(tp => {
                                    const img = tp.variant_image || tp.default_image || 'default-placeholder.png';
                                    const path = "../../public/assets/img/furnitures/" + encodeURIComponent(img.trim());
                                    return `
                                        <div class="group animate-in zoom-in duration-300">
                                            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto bg-gray-50 rounded-lg mb-3 flex items-center justify-center overflow-hidden border border-gray-100 group-hover:border-red-300 transition shadow-sm">
                                                <img src="${path}" alt="${tp.name}" class="object-contain h-full w-full">
                                            </div>
                                            <p class="text-[10px] font-bold text-gray-700 truncate px-1">${tp.name}</p>
                                            <p class="text-xs font-black text-blue-600 mt-1">${parseInt(tp.total_sold)} <span class="text-[9px] text-gray-400 uppercase">Sold</span></p>
                                        </div>
                                    `;
                                }).join('');
                            }
                        });
                };
            };

            // Initial Load
            fetchOrdersReport();
        });
    </script>
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