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

    // Fetch recent activities for the notification dropdown

    // Fetch Dashboard Specific Items
    $lowStockItems = get_dashboard_low_stock($pdo);
    $govOrders = get_pending_government_orders($pdo);
    $totalGovOutstanding = get_total_government_outstanding($pdo);

    // NEW: Dashboard Analytics
    $recentOrders = get_recent_orders($pdo, 5);
    $salesTrend = get_dashboard_sales_trend($pdo);
    $inventoryStats = get_dashboard_inventory_analytics($pdo);

    // Fetch total cart items for Admin POS badge
    $cartItems = get_cart_items($pdo, $userId);
    $adminCartCount = count($cartItems);
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
    <title>Prime-In-Sync | Dashboard</title>
    <link rel="icon" type="image/png" href="../../public/assets/img/favIcon.png">
    <link rel="stylesheet" href="../output.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="../../public/assets/js/global.js" defer></script>
    <script src="../../public/assets/js/order.js" defer></script>
    <?php include '../include/toast.php'; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        h1,
        h2,
        h3,
        h4,
        th,
        .font-outfit {
            font-family: 'Outfit', sans-serif;
        }

        /* Shrink entire UI by 10% - Removed for native zoom support */
        /* html {
            zoom: 90%;
        } */
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-4 md:px-8">
    <?php include '../include/loading-splash.php'; ?>
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
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
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

    <section class="px-6 py-4 w-full max-w-7xl mx-auto">
        <?php
        render_admin_stats_cards([
            [
                'label'   => 'Available Products',
                'value'   => $totalProducts,
                'subtext' => 'Total products in the system.'
            ],
            [
                'label'   => 'Total Transactions',
                'value'   => $totalTransactionsCount,
                'subtext' => 'All recorded sales transactions.'
            ],
            [
                'label'      => 'Pending Request',
                'value'      => $pendingRequestsCount,
                'subtext'    => 'Needs Review',
                'isCritical' => true,
                'animate'    => true
            ],
            [
                'label'     => 'System Status',
                'value'     => 'Operational',
                'subtext'   => 'All systems performing normally.',
                'indicator' => '<span class="size-3 bg-green-500 rounded-full animate-ping"></span>',
                'valueClass' => 'text-2xl font-black text-green-600 uppercase tracking-tighter'
            ]
        ], 4, 300);
        ?>
    </section>

    <nav class="px-5 flex justify-center w-full max-w-7xl mx-auto">
        <div class="max-w-7xl w-full">
            <ul class="grid grid-cols-4 bg-gray-100 rounded-3xl h-12 shadow-sm px-5 items-center gap-2">

                <!-- POS System -->
                <li>
                    <a href="../-admin/pos-page.php"
                        class="relative flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
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
                        <svg class="w-5 h-5 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
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

                <!-- Reports and Analytics -->
                <li>
                    <a href="../-admin/reports-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 
                     4.242 0 1.172 1.025 1.172 2.687 0 
                     3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 
                     1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 
                     1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>Reports & Analytics</span>
                    </a>
                </li>

                <!-- Order Request (Active) -->
                <li>
                    <a href="../-admin/order-req-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor">
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

    <section class="flex flex-col items-center w-full max-w-7xl mx-auto px-6">
        <div class="border border-gray-300 rounded-2xl p-6 md:p-12 w-full bg-gray-50 mx-auto">

            <div class="max-w-[1400px] mx-auto space-y-8">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    <div class="bg-white p-6 rounded-[1.5rem] shadow-sm border border-gray-100">
                        <div class="mb-4">
                            <h3 class="text-xl font-bold text-gray-800">Sales Trend</h3>
                        </div>
                        <div class="h-[300px] w-full">
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                        <div class="flex justify-center mt-4 gap-4">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full border-2 border-black"></span>
                                <span class="text-sm font-medium">Revenue (₱)</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-[1.5rem] shadow-sm border border-gray-100">
                        <div class="mb-4">
                            <h3 class="text-xl font-bold text-gray-800">Inventory by Location</h3>
                        </div>
                        <div class="h-[300px] w-full">
                            <canvas id="inventoryChart"></canvas>
                        </div>
                        <div class="flex justify-center mt-4 gap-6">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-black text-black"></div>
                                <span class="text-sm font-medium text-gray-600">Warehouse</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-gray-400"></div>
                                <span class="text-sm font-medium text-gray-600">Showroom</span>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 overflow-hidden mt-8">
                    <div class="p-8 border-b border-gray-50 flex flex-col lg:flex-row justify-between items-center bg-white gap-6">
                        <div class="flex items-center gap-5">
                            <div class="size-14 bg-orange-600 rounded-2xl flex items-center justify-center border border-orange-700 shadow-lg shadow-orange-100">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 tracking-tight leading-none mb-1">Accounts Receivable</h3>
                                <p id="receivableCount" class="text-[11px] font-bold text-gray-400 uppercase tracking-widest leading-none">0 pending accounts</p>
                            </div>
                        </div>



                        <div class="text-right">
                            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">Total Outstanding</p>
                            <p id="receivableTotal" class="text-2xl font-black text-red-600 tracking-tighter leading-none">₱0</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto min-h-[300px]">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50 text-gray-400 text-[11px] font-extrabold uppercase tracking-widest border-b border-gray-100">
                                <tr>
                                    <th class="px-8 py-5">ID</th>
                                    <th class="px-8 py-5">Client / Agency</th>
                                    <th class="px-8 py-5 text-center">Type</th>
                                    <th class="px-8 py-5 text-center">Due</th>
                                    <th class="px-8 py-5 text-right">Balance</th>
                                    <th class="px-8 py-5 text-center">Status</th>
                                    <th class="px-8 py-5 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="receivablesContent" class="divide-y divide-gray-50 text-sm">
                                <!-- Data injected via JS -->
                            </tbody>
                        </table>
                    </div>
                    <div id="collectionModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 transition-all duration-300">
                        <div class="absolute inset-0 bg-slate-900/60" onclick="toggleCollectionModal(false)"></div>

                        <div class="relative bg-white rounded-2xl shadow-2xl h-[85vh] max-w-6xl w-full overflow-hidden animate-in fade-in zoom-in duration-300 flex flex-col border border-gray-200">

                            <div class="flex-none px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-white z-20">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-8 bg-red-600 rounded-full"></div>
                                    <h3 class="text-xl font-black text-gray-900 tracking-tight uppercase">Collection Details</h3>
                                </div>
                                <button onclick="toggleCollectionModal(false)" class="p-2 hover:bg-gray-100 rounded-xl transition-all group">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="flex-1 flex flex-row overflow-hidden">

                                <!-- Left Column: Order Stats & Base Info -->
                                <div class="flex-1 border-r border-gray-100 overflow-y-auto p-8 space-y-8 bg-gray-50/10 custom-scrollbar">

                                    <div class="max-w-xl py-4 px-2 space-y-5 bg-white rounded-xl shadow-sm border border-gray-100">
                                        <div class="border-b border-gray-100 pb-3 mb-6 px-4">
                                            <div class="flex items-baseline gap-2">
                                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Order Ref:</span>
                                                <span id="modal-id" class="text-sm font-mono font-bold text-gray-950">--</span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-x-16 px-4">
                                            <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-tight mb-0.5">TXN Date</label>
                                                <p id="modal-date" class="text-sm font-semibold text-gray-800">--</p>
                                            </div>
                                            <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-tight mb-0.5">Contact No.</label>
                                                <p id="modal-contact" class="text-sm font-medium text-gray-600">--</p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-x-16 pt-2 px-4">
                                            <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-tight mb-0.5">Customer Name</label>
                                                <p id="modal-customer" class="text-sm font-extrabold text-gray-900 tracking-tight uppercase leading-none">--</p>
                                            </div>
                                            <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-tight mb-0.5">Account Status</label>
                                                <div id="modal-status">
                                                    <span id="modal-status-badge" class="text-[11px] font-extrabold text-orange-600 tracking-tighter uppercase italic">
                                                        ● Ongoing Installment
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="h-4"></div>
                                    </div>

                                    <!-- Financial summary highlight in the requested style -->
                                    <div class="pt-4 space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="p-4 rounded-xl border border-blue-100 bg-blue-50/20 flex flex-col justify-center">
                                                <span class="text-[10px] font-black text-blue-700 uppercase tracking-widest leading-none mb-1.5">Principal Total:</span>
                                                <span id="modal-principal" class="text-lg font-black text-blue-900 leading-none tracking-tighter">₱ 0.00</span>
                                            </div>
                                            <div class="p-4 rounded-xl border border-gray-200 bg-gray-50 flex flex-col justify-center">
                                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1.5">Interest Rate:</span>
                                                <span id="modal-interest" class="text-lg font-black text-gray-600 leading-none tracking-tighter">0%</span>
                                            </div>
                                        </div>

                                        <div class="p-5 rounded-xl border border-green-100 bg-green-50/20 flex justify-between items-center">
                                            <span class="text-[10px] font-black text-green-700 uppercase tracking-widest">Total Payment Collected:</span>
                                            <span id="modal-paid" class="text-xl font-black text-green-800 tracking-tighter">₱ 0.00</span>
                                        </div>

                                        <div class="p-6 rounded-2xl border-2 border-red-100 bg-red-50/30 flex justify-between items-center shadow-sm">
                                            <div>
                                                <span class="text-[10px] font-black text-red-500 uppercase tracking-[0.2em] leading-none mb-1.5 block">Outstanding Balance</span>
                                                <span id="modal-balance" class="text-4xl font-extrabold text-red-600 leading-none tracking-tighter">₱ 0.00</span>
                                            </div>
                                            <div class="size-14 bg-red-600 shadow-lg shadow-red-100 rounded-2xl flex items-center justify-center text-white">
                                                <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2.5" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Tracker Table in left Column -->
                                    <section class="pt-6 space-y-4">
                                        <div class="flex items-center justify-between px-1">
                                            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em]">Payment tracker history</h3>
                                            <span id="collNextDue" class="bg-black text-white text-[9px] px-3 py-1 rounded-full font-black italic uppercase tracking-widest">Next Term Pending</span>
                                        </div>

                                        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                            <table class="w-full text-left border-collapse">
                                                <thead>
                                                    <tr class="bg-gray-50/50 border-b border-gray-100">
                                                        <th class="px-6 py-4 text-[9px] font-black text-gray-400 uppercase tracking-widest">Term</th>
                                                        <th class="px-6 py-4 text-[9px] font-black text-gray-400 uppercase tracking-widest text-center">Due Date</th>
                                                        <th class="px-6 py-4 text-center text-[9px] font-black text-gray-400 uppercase tracking-widest">Status</th>
                                                        <th class="px-6 py-4 text-right text-[9px] font-black text-gray-400 uppercase tracking-widest">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="collTrackerBody" class="divide-y divide-gray-100 text-gray-800">
                                                    <!-- Dynamic Content -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                </div>

                                <!-- Right Column: Finalize Transaction -->
                                <div id="modal-right-column" class="flex-[1.5] flex flex-col bg-white overflow-hidden shadow-xl">
                                    <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-white shrink-0">
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-8 bg-red-600 rounded-full"></div>
                                            <div class="flex flex-col">
                                                <h3 class="text-xl font-black text-gray-900 tracking-tight uppercase">Post Collection</h3>
                                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-1">Order Processing & Billing</p>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="px-3 py-1 text-[9px] font-black bg-blue-50 text-blue-600 rounded-full border border-blue-100 uppercase tracking-wider">Verified Account</span>
                                        </div>
                                    </div>

                                    <div class="flex-1 overflow-y-auto p-8 space-y-8 custom-scrollbar">
                                        <input type="hidden" id="collOrderId">

                                        <div class="p-5 rounded-2xl bg-orange-50 border border-orange-100 flex items-center justify-between">
                                            <div class="space-y-0.5">
                                                <p class="text-[10px] font-black text-orange-400 uppercase tracking-widest">Recording For</p>
                                                <span id="termIndicator" class="text-base font-black text-orange-700 uppercase tracking-tight">Checking Next Term...</span>
                                            </div>
                                            <div class="size-10 bg-orange-600 text-white rounded-xl flex items-center justify-center shadow-md shadow-orange-100">
                                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2.5" />
                                                </svg>
                                            </div>
                                        </div>

                                        <section id="modal-collection-form" class="space-y-6">
                                            <div class="grid grid-cols-2 gap-6">
                                                <div class="col-span-2 space-y-2.5">
                                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1 leading-none">Received Amount (PHP)</label>
                                                    <div class="relative group">
                                                        <span class="absolute left-6 top-1/2 -translate-y-1/2 font-black text-orange-600 text-3xl transition-transform group-focus-within:scale-110 duration-300 pointer-events-none z-10">₱</span>
                                                        <input type="number" id="collAmount"
                                                            oninput="setCollectionFormDirty(); handleAmountInput(this.value)"
                                                            class="w-full bg-white border border-gray-200 rounded-2xl pl-20 pr-6 py-6 text-3xl font-black text-gray-900 outline-none focus:ring-4 focus:ring-orange-500/10 focus:border-orange-500 transition-all shadow-sm"
                                                            placeholder="0.00">
                                                    </div>
                                                </div>

                                                <div class="col-span-2 md:col-span-1 space-y-2.5">
                                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1 leading-none">Payment Method</label>
                                                    <select id="collMethod" onchange="setCollectionFormDirty()" class="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer transition-all shadow-sm">
                                                        <option value="Cash">💵 Cash Payment</option>
                                                        <option value="gcash">📱 GCash</option>
                                                        <option value="maya">💳 Maya</option>
                                                        <option value="bdo">🏦 Bank Transfer - BDO</option>
                                                        <option value="bpi">🏦 Bank Transfer - BPI</option>
                                                        <option value="check">✍️ Check Payment</option>
                                                        <option value="card">💳 Credit / Debit Card</option>
                                                    </select>
                                                </div>

                                                <div class="col-span-2 md:col-span-1 space-y-2.5">
                                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1 leading-none">Reference No.</label>
                                                    <input type="text" id="collRef" oninput="setCollectionFormDirty()" autocomplete="off" class="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 outline-none focus:ring-2 focus:ring-orange-500 uppercase tracking-widest shadow-sm" placeholder="TRACE-XXX">
                                                </div>

                                                <div class="col-span-2 space-y-2.5">
                                                    <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-1 leading-none">Collection Remarks</label>
                                                    <textarea id="collRemarks" oninput="setCollectionFormDirty()" rows="2" class="w-full bg-white border border-gray-200 rounded-2xl px-6 py-4 text-sm font-semibold text-gray-900 outline-none focus:ring-2 focus:ring-orange-500 resize-none transition-all shadow-sm custom-scrollbar" placeholder="Optional notes..."></textarea>
                                                </div>
                                            </div>

                                            <div class="p-6 bg-gray-900 rounded-2xl flex justify-between items-center shadow-lg shadow-gray-100">
                                                <div class="space-y-0.5">
                                                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-[0.2em]">Total Payable</p>
                                                    <h1 id="collFooterTotal" class="text-xl font-black text-white tracking-tight leading-none">₱ 0.00</h1>
                                                </div>
                                                <div class="text-right space-y-0.5">
                                                    <p class="text-[9px] font-bold text-orange-400 uppercase tracking-[0.2em]">Recording Now</p>
                                                    <h1 class="text-xl font-black text-orange-500 tracking-tight leading-none" id="currentCapturingDisplay">₱ 0.00</h1>
                                                </div>
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            </div>

                            <div class="p-8 flex justify-between items-center gap-4 bg-white border-t border-gray-100 z-20">
                                <button onclick="toggleCollectionModal(false)" class="flex-1 justify-center bg-black hover:bg-zinc-800 text-white rounded-xl py-4 px-6 font-black text-[11px] uppercase tracking-[0.2em] transition-all shadow-xl shadow-gray-200 active:scale-95 flex items-center gap-2">
                                    <span>Close Window</span>
                                </button>

                                <button onclick="submitCollection()" class="flex-1 justify-center bg-red-600 hover:bg-red-700 text-white rounded-xl py-4 px-6 font-black text-[11px] uppercase tracking-[0.2em] transition-all shadow-xl shadow-red-100 active:scale-95 flex items-center gap-2">
                                    <span>Finalize Collection</span>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-gray-50 text-gray-800">
                            <h3 class="text-xl font-bold">Recent Orders</h3>
                        </div>
                        <div class="overflow-x-auto min-h-[220px]">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-400 text-[11px] font-extrabold uppercase tracking-widest border-b border-gray-50">
                                    <tr>
                                        <th class="px-6 py-4">Order ID</th>
                                        <th class="px-6 py-4">Date</th>
                                        <th class="px-6 py-4 text-right">Amount</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="recentOrdersContent" class="divide-y divide-gray-50 text-sm font-medium text-gray-600 bg-white">
                                    <!-- Dynamic -->
                                </tbody>
                            </table>
                        </div>
                        <div id="recentOrdersTableFooter" class="p-4 border-t border-gray-50 bg-gray-50/10 flex items-center justify-center">
                            <!-- Show More / Pagination -->
                        </div>
                    </div>

                    <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-gray-50 text-red-600 flex items-center gap-2">
                            <h3 class="text-xl font-bold">Low Stock Alerts</h3>
                        </div>
                        <div class="overflow-x-auto min-h-[220px]">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-400 text-[11px] font-extrabold uppercase tracking-widest border-b border-gray-50">
                                    <tr>
                                        <th class="px-6 py-4">Product</th>
                                        <th class="px-6 py-4">Variant</th>
                                        <th class="px-6 py-4 text-center">Location</th>
                                        <th class="px-6 py-4 text-right">Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="lowStockAlertsContent" class="divide-y divide-gray-50 text-sm font-medium text-gray-600 bg-white">
                                    <!-- Dynamic -->
                                </tbody>
                            </table>
                        </div>
                        <div id="lowStockTableFooter" class="p-4 border-t border-gray-50 bg-gray-50/10 flex items-center justify-center">
                            <!-- Show More / Pagination -->
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // --- RECENT ORDERS PROGRESSIVE LOGIC ---
            const allRecentOrdersData = <?= json_encode($recentOrders) ?>;
            let recentOrdersDisplayLimit = 3;
            let recentOrdersPage = 1;
            const recentOrdersThreshold = 5;

            window.renderRecentOrdersTable = function() {
                const content = document.getElementById('recentOrdersContent');
                const footer = document.getElementById('recentOrdersTableFooter');

                if (allRecentOrdersData.length === 0) {
                    content.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-gray-400 italic font-medium">No recent orders</td></tr>`;
                    footer.innerHTML = '';
                    return;
                }

                // Determine range
                let dataToShow = [];
                let total = allRecentOrdersData.length;

                if (total > recentOrdersThreshold && recentOrdersDisplayLimit === recentOrdersThreshold) {
                    let start = (recentOrdersPage - 1) * recentOrdersThreshold;
                    let end = start + recentOrdersThreshold;
                    dataToShow = allRecentOrdersData.slice(start, end);
                } else {
                    dataToShow = allRecentOrdersData.slice(0, recentOrdersDisplayLimit);
                }

                // Render Table
                content.innerHTML = dataToShow.map(order => {
                    const status = order.status.toLowerCase();
                    const statusClass = status === 'completed' ? 'bg-green-100 text-green-600' : (status === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600');

                    const dateObj = new Date(order.created_at);
                    const formattedDate = dateObj.toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric'
                    });

                    return `
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-gray-900 leading-none">#ORD-${order.id.toString().padStart(5, '0')}</td>
                            <td class="px-6 py-4 text-gray-400 font-mono text-[11px] font-medium">${formattedDate}</td>
                            <td class="px-6 py-4 text-right font-black text-gray-900 leading-none">₱${parseFloat(order.total).toLocaleString()}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-3 py-1 ${statusClass} rounded-full text-[10px] font-bold italic uppercase tracking-tighter">
                                    ${order.status}
                                </span>
                            </td>
                        </tr>
                    `;
                }).join('');

                // Render Controls
                if (total > 3 && recentOrdersDisplayLimit === 3) {
                    footer.innerHTML = `
                        <button onclick="expandRecentOrdersTable()" class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                            Show More (${total})
                            <svg class="size-4 group-hover:translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" /></svg>
                        </button>
                    `;
                } else if (total > recentOrdersThreshold) {
                    let totalPages = Math.ceil(total / recentOrdersThreshold);
                    let pagesHtml = `<button onclick="collapseRecentOrdersTable()" class="text-[10px] font-black text-gray-300 uppercase tracking-tighter hover:text-red-600 transition mr-2">Show Less</button>`;
                    for (let i = 1; i <= totalPages; i++) {
                        pagesHtml += `<button onclick="goToRecentOrdersPage(${i})" class="size-7 rounded-lg text-xs font-black transition ${recentOrdersPage === i ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'}">${i}</button>`;
                    }
                    footer.innerHTML = `<div class="flex items-center gap-2">${pagesHtml}</div>`;
                } else if (recentOrdersDisplayLimit > 3) {
                    footer.innerHTML = `
                        <button onclick="collapseRecentOrdersTable()" class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                            Show Less
                            <svg class="size-4 group-hover:-translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="3" /></svg>
                        </button>
                    `;
                } else {
                    footer.innerHTML = '';
                }
            };

            window.expandRecentOrdersTable = function() {
                recentOrdersDisplayLimit = recentOrdersThreshold;
                renderRecentOrdersTable();
            };

            window.collapseRecentOrdersTable = function() {
                recentOrdersDisplayLimit = 3;
                recentOrdersPage = 1;
                renderRecentOrdersTable();
            };

            window.goToRecentOrdersPage = function(p) {
                recentOrdersPage = p;
                renderRecentOrdersTable();
            };

            // --- LOW STOCK PROGRESSIVE LOGIC ---
            const allLowStockData = <?= json_encode($lowStockItems) ?>;
            let lowStockDisplayLimit = 3;
            let lowStockPage = 1;
            const lowStockPageThreshold = 5;

            window.renderLowStockTable = function() {
                const content = document.getElementById('lowStockAlertsContent');
                const footer = document.getElementById('lowStockTableFooter');

                if (allLowStockData.length === 0) {
                    content.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-gray-400 italic font-medium">No low stock alerts</td></tr>`;
                    footer.innerHTML = '';
                    return;
                }

                // Determine display range
                let dataToShow = [];
                let total = allLowStockData.length;

                if (total > lowStockPageThreshold && lowStockDisplayLimit === lowStockPageThreshold) {
                    let start = (lowStockPage - 1) * lowStockPageThreshold;
                    let end = start + lowStockPageThreshold;
                    dataToShow = allLowStockData.slice(start, end);
                } else {
                    dataToShow = allLowStockData.slice(0, lowStockDisplayLimit);
                }

                // Render Table
                content.innerHTML = dataToShow.map(item => {
                    const threshold = parseInt(item.min_buildable_qty);
                    const srQty = parseInt(item.sr_qty);
                    const whQty = parseInt(item.wh_qty);

                    let location = '';
                    let displayQty = '';
                    if (whQty <= threshold && srQty <= threshold) {
                        location = 'Both';
                        displayQty = `WH: ${whQty} | SR: ${srQty}`;
                    } else if (whQty <= threshold) {
                        location = 'Warehouse';
                        displayQty = whQty;
                    } else {
                        location = 'Showroom';
                        displayQty = srQty;
                    }

                    return `
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-gray-800">${item.prod_name}</td>
                            <td class="px-6 py-4 text-gray-400 font-medium">${item.variant}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-[9px] font-black uppercase tracking-widest border border-gray-200">
                                    ${location}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-red-600 font-black">${displayQty}</td>
                        </tr>
                    `;
                }).join('');

                // Render Controls
                if (total > 3 && lowStockDisplayLimit === 3) {
                    footer.innerHTML = `
                        <button onclick="expandLowStockTable()" class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                            Show More (${total})
                            <svg class="size-4 group-hover:translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" /></svg>
                        </button>
                    `;
                } else if (total > lowStockPageThreshold) {
                    let totalPages = Math.ceil(total / lowStockPageThreshold);
                    let pagesHtml = `<button onclick="collapseLowStockTable()" class="text-[10px] font-black text-gray-300 uppercase tracking-tighter hover:text-red-600 transition mr-2">Show Less</button>`;
                    for (let i = 1; i <= totalPages; i++) {
                        pagesHtml += `<button onclick="goToLowStockPage(${i})" class="size-7 rounded-lg text-xs font-black transition ${lowStockPage === i ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'}">${i}</button>`;
                    }
                    footer.innerHTML = `<div class="flex items-center gap-2">${pagesHtml}</div>`;
                } else if (lowStockDisplayLimit > 3) {
                    footer.innerHTML = `
                        <button onclick="collapseLowStockTable()" class="flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                            Show Less
                            <svg class="size-4 group-hover:-translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="3" /></svg>
                        </button>
                    `;
                } else {
                    footer.innerHTML = '';
                }
            };

            window.expandLowStockTable = function() {
                lowStockDisplayLimit = lowStockPageThreshold;
                renderLowStockTable();
            };

            window.collapseLowStockTable = function() {
                lowStockDisplayLimit = 3;
                lowStockPage = 1;
                renderLowStockTable();
            };

            window.goToLowStockPage = function(p) {
                lowStockPage = p;
                renderLowStockTable();
            };

            window.fetchReceivables = function() {
                const content = document.getElementById('receivablesContent');
                const countDisplay = document.getElementById('receivableCount');
                const totalDisplay = document.getElementById('receivableTotal');

                fetch(`../include/inc.admin/admin.ctrl.php?action=get_receivables`)
                    .then(response => response.json())
                    .then(response => {
                        if (response.success) {
                            countDisplay.innerText = `${response.stats.count} pending accounts`;
                            totalDisplay.innerText = `₱${response.stats.total.toLocaleString()}`;

                            if (response.data.length === 0) {
                                content.innerHTML = `<tr><td colspan="7" class="py-20 text-center"><p class="text-[10px] font-black text-gray-300 uppercase tracking-widest">No pending collections found</p></td></tr>`;
                                return;
                            }

                            content.innerHTML = response.data.map(row => `
                                <tr class="hover:bg-orange-50/20 transition group">
                                    <td class="px-8 py-5 font-bold text-gray-900 leading-none">
                                        <p class="text-[10px] text-gray-400 font-mono mb-1 tracking-tighter">#ORD-${row.id.toString().padStart(5, '0')}</p>
                                        <p class="text-[9px] font-black text-orange-600 uppercase border-l-2 border-orange-500 pl-2 leading-none">${row.or_number || 'NO-REF'}</p>
                                    </td>
                                    <td class="px-8 py-5">
                                        <div class="font-black text-gray-800 uppercase tracking-tighter leading-tight">${row.client_name}</div>
                                        <p class="text-[10px] text-gray-400 font-medium">${row.branch || 'Independent Branch'}</p>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-blue-50 text-blue-600">
                                            ${row.client_type}
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-tighter leading-tight">
                                        ${new Date(row.created_at).toLocaleDateString()}
                                    </td>
                                    <td class="px-8 py-5 text-right font-black text-gray-900 leading-none">
                                        <p class="text-sm font-black">₱${parseFloat(row.balance).toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
                                        <p class="text-[9px] text-gray-300 font-medium mt-1">OF ₱${parseFloat(row.total).toLocaleString()}</p>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border bg-orange-50 text-orange-600 border-orange-100">
                                            ${row.status}
                                        </span>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <button onclick='openCollectionModal(${JSON.stringify({id: row.id, name: row.client_name, balance: row.balance, or: row.or_number}).replace(/'/g, "&apos;")})' 
                                            class="size-8 rounded-lg bg-white border border-gray-100 flex items-center justify-center text-gray-400 hover:bg-orange-600 hover:text-white hover:border-orange-700 transition-all shadow-sm">
                                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            `).join('');
                        }
                    });
            };

            let isCollectionFormDirty = false;
            window.setCollectionFormDirty = () => isCollectionFormDirty = true;

            window.toggleCollectionModal = function(show) {
                if (!show && isCollectionFormDirty) {
                    if (!confirm("You have unsaved changes in this collection form. Are you sure you want to close and discard them?")) {
                        return;
                    }
                }

                document.getElementById('collectionModal').classList.toggle('hidden', !show);
                if (!show) {
                    document.getElementById('collAmount').value = '';
                    document.getElementById('collRef').value = '';
                    document.getElementById('collRemarks').value = '';
                    document.getElementById('collMethod').value = 'Cash';
                    isCollectionFormDirty = false;
                }
            };

            window.openCollectionModal = async function(data) {
                document.getElementById('collOrderId').value = data.id;

                // Reset fields to loading state
                document.getElementById('modal-id').innerText = data.or || "REF-SYNC...";
                document.getElementById('modal-customer').innerText = "Loading...";
                document.getElementById('modal-contact').innerText = "---";
                document.getElementById('modal-date').innerText = "---";

                document.getElementById('modal-principal').innerText = "₱ 0.00";
                document.getElementById('modal-interest').innerText = "0%";
                document.getElementById('modal-paid').innerText = "₱ 0.00";
                document.getElementById('modal-balance').innerText = "₱ 0.00";
                document.getElementById('collFooterTotal').innerText = "₱ 0.00";

                document.getElementById('termIndicator').innerText = "Checking...";
                document.getElementById('collNextDue').innerText = "Syncing...";
                document.getElementById('currentCapturingDisplay').innerText = "₱ 0.00";

                const tbody = document.getElementById('collTrackerBody');
                tbody.innerHTML = `<tr><td colspan="4" class="py-10 text-center"><div class="animate-spin size-6 border-4 border-gray-100 border-t-orange-600 rounded-full mx-auto"></div></td></tr>`;

                // Named handler for real-time update and dirty state tracking
                window.handleAmountInput = (value) => {
                    const val = parseFloat(value || 0);
                    document.getElementById('currentCapturingDisplay').innerText = `₱ ${val.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                };

                toggleCollectionModal(true);

                try {
                    const res = await fetch(`../include/inc.admin/admin.ctrl.php?action=get_order_details&order_id=${data.id}`);
                    const response = await res.json();

                    if (response.success && response.summary) {
                        const s = response.summary;
                        document.getElementById('modal-customer').innerText = s.customer_name;
                        document.getElementById('modal-contact').innerText = s.contact_no || 'N/A';
                        document.getElementById('modal-id').innerText = s.or_number ? `#${s.or_number}` : 'NO-REF';
                        document.getElementById('modal-date').innerText = new Date(s.created_at).toLocaleDateString('en-US', {
                            month: 'long',
                            day: 'numeric',
                            year: 'numeric'
                        });

                        document.getElementById('modal-principal').innerText = `₱ ${parseFloat(s.principal_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                        document.getElementById('modal-interest').innerText = `${s.interest_rate}%`;
                        document.getElementById('collFooterTotal').innerText = `₱ ${parseFloat(s.total_with_interest || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                    }

                    if (response.success && response.schedule) {
                        let totalPaidSum = 0;
                        let nextDue = null;
                        let nextTermLabel = "Fully Paid";

                        tbody.innerHTML = response.schedule.map((term, index) => {
                            const isPaid = term.status === 'Paid';
                            if (isPaid) totalPaidSum += parseFloat(term.amount_paid || 0);

                            if (!isPaid && !nextDue) {
                                nextDue = term;
                                nextTermLabel = term.remarks || `Term ${index + 1}`;
                            }

                            const statusClass = isPaid ? 'bg-green-100/50 text-green-600 border-green-100' : 'bg-orange-50 text-orange-600 border-orange-100';

                            return `
                                <tr class="hover:bg-gray-50/50 transition border-b border-gray-50 last:border-0 group">
                                    <td class="px-6 py-4">
                                        <div class="font-extrabold text-gray-900 uppercase tracking-tighter text-[12px]">${term.remarks || `Term ${index + 1}`}</div>
                                        <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-0.5 whitespace-nowrap">${isPaid ? 'Payment Confirmed' : 'Scheduled'}</p>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <p class="text-[11px] font-black text-gray-500 uppercase tracking-tighter leading-none">${term.due_date ? new Date(term.due_date).toLocaleDateString() : 'N/A'}</p>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-widest border ${statusClass}">
                                            ${term.status}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <p class="text-[12px] font-extrabold text-gray-950 leading-none">₱${parseFloat(term.amount_paid || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
                                    </td>
                                </tr>
                            `;
                        }).join('');

                        document.getElementById('modal-paid').innerText = `₱ ${totalPaidSum.toLocaleString(undefined, {minimumFractionDigits: 2})}`;

                        const payable = parseFloat(response.summary.total_with_interest || 0);
                        const currentBalance = payable - totalPaidSum;
                        document.getElementById('modal-balance').innerText = `₱ ${currentBalance.toLocaleString(undefined, {minimumFractionDigits: 2})}`;

                        document.getElementById('collNextDue').innerText = nextDue ? `Next: ${nextTermLabel}` : "Cleared";
                        document.getElementById('termIndicator').innerText = nextTermLabel;

                        if (nextDue) {
                            document.getElementById('collAmount').value = parseFloat(nextDue.amount_paid || 0).toFixed(2);
                            document.getElementById('currentCapturingDisplay').innerText = `₱ ${parseFloat(nextDue.amount_paid || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
                        } else {
                            document.getElementById('collAmount').value = '';
                            document.getElementById('currentCapturingDisplay').innerText = "₱ 0.00";
                        }
                        
                        // Reset dirty flag after successful data load
                        isCollectionFormDirty = false;
                    } else {
                        tbody.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-red-500 text-xs font-bold uppercase tracking-widest">Failed to load schedule</td></tr>`;
                    }
                } catch (e) {
                    console.error("Error loading collection details:", e);
                    tbody.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-red-500 text-xs font-bold uppercase tracking-widest">Sync Error</td></tr>`;
                }
            };

            window.submitCollection = function() {
                const orderId = document.getElementById('collOrderId').value;
                const amount = document.getElementById('collAmount').value;
                const ref = document.getElementById('collRef').value;
                const method = document.getElementById('collMethod').value;
                const remarks = document.getElementById('collRemarks').value;

                if (!amount || parseFloat(amount) <= 0) {
                    alert('Please enter a valid amount');
                    return;
                }

                const formData = new FormData();
                formData.append('order_id', orderId);
                formData.append('amount', amount);
                formData.append('reference', ref);
                formData.append('payment_method', method);
                formData.append('remarks', remarks);

                fetch(`../include/inc.admin/admin.ctrl.php?action=record_collection`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(response => {
                        if (response.success) {
                            isCollectionFormDirty = false;
                            toggleCollectionModal(false);
                            
                            // Show toast after modal closes
                            if (typeof showToast === 'function') {
                                showToast('Collection finalized successfully!', 'success');
                            }
                            
                            fetchReceivables();
                            renderRecentOrdersTable();
                        } else {
                            alert(response.error || 'Failed to record payment');
                        }
                    });
            };



            document.addEventListener('DOMContentLoaded', function() {
                renderLowStockTable();
                renderRecentOrdersTable();
                fetchReceivables();

                const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
                const salesData = <?= json_encode($salesTrend) ?>;
                new Chart(salesCtx, {
                    type: 'line',
                    data: {
                        labels: salesData.map(d => d.month_name),
                        datasets: [{
                            data: salesData.map(d => d.revenue),
                            borderColor: '#111827',
                            borderWidth: 2.5,
                            tension: 0.45,
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });

                const invCtx = document.getElementById('inventoryChart').getContext('2d');
                const invData = <?= json_encode($inventoryStats) ?>;
                new Chart(invCtx, {
                    type: 'bar',
                    data: {
                        labels: invData.map(d => d.category),
                        datasets: [{
                            label: 'Warehouse',
                            data: invData.map(d => d.wh_qty),
                            backgroundColor: '#111827',
                            borderRadius: 4
                        }, {
                            label: 'Showroom',
                            data: invData.map(d => d.sr_qty),
                            backgroundColor: '#d1d5db',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            });
        </script>
    </section>
</body>

</html>