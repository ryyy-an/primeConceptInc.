<?php

declare(strict_types=1);

require_once "../include/config.php";
require_once "../include/dbh.inc.php";
require_once "../include/inc.showroom/sr.model.php";
require_once "../include/inc.warehouse/wh.model.php";
require_once "../include/inc.admin/admin.view.php";

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection not established.");
}

if (isset($_SESSION["user_id"])) {
    // User is logged in
    $userId = $_SESSION["user_id"];
    $username = htmlspecialchars($_SESSION["username"]);
    $role = htmlspecialchars($_SESSION["role"]);
} else {
    // Not logged in → redirect
    header("Location: ../../public/index.php");
    exit();
}

// Fetch Dashboard Stats & Fulfillment Data
$stats = get_warehouse_dashboard_stats($pdo, (int)$userId);
$totalProducts = $stats['total_products'];
$userTransactions = $stats['user_transactions'];
$pendingWH = $stats['pending_wh'];
$pendingSR = $stats['pending_sr'];

// Fetch Fulfillment Orders
$fulfillmentOrders = get_fulfillment_ready_orders($pdo);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime-In-Sync</title>
    <link rel="icon" href="../../public/assets/img/favIcon.png" type="image/png">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js?v=1.2" defer></script>

    <style>
        /* Global UI Tweaks - Removed zoom for native support */
        /* html {
            zoom: 90%;
        } */

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
    </style>
</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-4 md:px-8">
    <?php include '../include/loading-splash.php'; ?>
    <?php include '../include/toast.php'; ?>
    <header
        class="sticky top-0 z-40 flex h-25 items-center justify-between border-b border-gray-200 px-6 bg-white w-full max-w-7xl mx-auto">
        <div class="flex flex-1">
            <a href="../-warehouse/dashboard-page.php" class="flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/favIcon.png" alt="Prime Concept Logo"
                        class="h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-red-600">Prime-In-Sync</h1>
                    <h4 class="text-base text-gray-500">Welcome,
                        <?= htmlspecialchars(
                            $username,
                        ) ?>
                    </h4>
                </div>
            </a>
        </div>

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

            <a href="javascript:void(0)" class="logout-trigger flex items-center gap-2 border border-gray-300 px-4 h-9 rounded-lg hover:bg-red-50 hover:border-red-200 transition group">
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
                <li>
                    <a href="order-fulfilment-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                        </svg>
                        <span>Order Fulfillment</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span>Inventory</span>
                    </a>
                </li>
                <li>
                    <a href="stocks-logs.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>Warehouse Stocks Report</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <section id="fulfillment-container" data-orders="<?= htmlspecialchars(json_encode($fulfillmentOrders), ENT_QUOTES, 'UTF-8') ?>" class="flex flex-col items-center w-full max-w-7xl mx-auto px-6">
        <div class="border border-gray-300 rounded-2xl p-6 md:p-12 w-full">
            <h2 class="text-2xl font-semibold mb-2">Orders Ready for Fulfillment</h2>
            <p class="text-gray-500">Process approved orders by picking products from inventory</p>

            <!-- Status Tabs Removed -->

            <div class="grid grid-cols-3 items-stretch gap-6 mt-8">
                <?php if (empty($fulfillmentOrders)): ?>
                    <div class="col-span-3 py-20 text-center">
                        <div class="bg-gray-50 border border-dashed border-gray-200 rounded-3xl p-12 inline-block">
                            <p class="text-gray-400 font-medium">No orders ready for fulfillment at this time.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($fulfillmentOrders as $order):
                        // Calculate Status
                        $isModified = false;
                        foreach ($order['products'] as $p) {
                            if ($p['wh_item_status'] === 'ready') {
                                $isModified = true;
                                break;
                            }
                        }
                    ?>
                        <div id="order-card-<?= $order['order_id'] ?>" data-order-id="<?= $order['order_id'] ?>"
                            class="fulfillment-card card-style bg-white p-8 border border-gray-200 rounded-[2.5rem] shadow-sm hover:shadow-2xl hover:border-red-500 transition-all duration-500 flex flex-col group h-full">
                            <!-- Order Badge Area -->
                            <div class="flex justify-between items-start mb-8">
                                <div class="flex items-center gap-3">
                                    <div class="bg-red-600 text-white px-4 py-1.5 rounded-xl shadow-md shadow-red-50">
                                        <span class="text-[10px] font-black uppercase tracking-widest">Order
                                            #
                                            <?= $order['order_id'] ?>
                                        </span>
                                    </div>
                                    <div id="status-pill-<?= $order['order_id'] ?>">
                                        <?php if ($isModified): ?>
                                            <div class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-md border border-amber-200">
                                                <span class="text-[8px] font-black uppercase tracking-wider">In Progress</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-md border border-blue-100">
                                                <span class="text-[8px] font-black uppercase tracking-wider">New</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-4 py-2.5 rounded-xl border border-gray-100">
                                    <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">
                                        <?= count($order['products']) ?>
                                        Product
                                        <?= count($order['products']) === 1 ? '' : 's' ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Source Info -->
                            <div class="mb-10">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-2">Request Source
                                </p>
                                <h3 class="text-2xl font-black text-gray-900 leading-tight">
                                    <?= htmlspecialchars($order['source']) ?>
                                </h3>
                            </div>

                            <!-- Meta Details -->
                            <div class="space-y-4 mb-10 py-6 border-y border-gray-50">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Requested
                                        by</span>
                                    <span class="text-xs font-black text-gray-700">
                                        <?= htmlspecialchars($order['requested_by'] ?: 'Anonymous') ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Date
                                        Approved</span>
                                    <span class="text-xs font-black text-gray-500">
                                        <?= $order['formatted_date'] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mt-auto">
                                <button onclick="openOrderFulfillmentModal(event, '<?= $order['order_id'] ?>')"
                                    class="w-full py-5 bg-black text-white rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] hover:bg-gray-800 active:scale-95 transition-all shadow-xl shadow-gray-200 flex items-center justify-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path
                                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                                        </path>
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                    </svg>
                                    Review & Fulfill
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- Order Fulfillment Modal -->
    <!-- Order Fulfillment Modal -->
    <div id="fulfillmentModal"
        class="hidden fixed inset-0 flex items-center justify-center bg-black/60 p-4 transition-opacity duration-300 opacity-0 pointer-events-none"
        style="z-index: 99999;">
        <div class="bg-white rounded-[1.5rem] shadow-2xl w-full max-w-6xl overflow-hidden flex flex-col h-[85vh]">

            <!-- Header Section (Snapshot Style) -->
            <div class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-white">
                <div>
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight" id="modalOrderHeadline">Product Review
                    </h2>
                    <p class="text-sm text-gray-500 font-medium mt-1" id="fulfillmentProgressHeader">0 of 0 products
                        ready</p>
                </div>
                <button id="closeModal"
                    class="p-2 hover:bg-gray-100 rounded-lg transition-all border border-transparent group">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="text-gray-400 group-hover:text-black">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Main Body (Sidebar + Content) -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Sidebar -->
                <div class="w-80 border-r border-gray-100 bg-gray-50/30 flex flex-col overflow-hidden">
                    <div class="px-6 py-4">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Pending Review</p>
                    </div>
                    <div class="flex-1 overflow-y-auto px-4 pb-6 space-y-2 custom-scrollbar" id="modalSidebar">
                        <!-- Products injected here -->
                    </div>

                    <!-- Sidebar Footer (Unmark All) -->
                    <div class="p-6 border-t border-gray-100 bg-white shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                        <label
                            class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-transparent hover:border-gray-200 hover:bg-white transition-all cursor-pointer group">
                            <div class="flex flex-col">
                                <span
                                    class="text-[9px] font-bold text-gray-400 uppercase tracking-widest leading-none">Unmark
                                    All</span>
                            </div>
                            <div class="relative">
                                <input type="checkbox" id="unmarkAllCheckbox" class="sr-only peer"
                                    onchange="resetAllOrderItems()">
                                <div
                                    class="w-12 h-6 bg-gray-200 rounded-full peer peer-checked:bg-red-600 transition-all duration-500 peer-focus:ring-4 peer-focus:ring-red-50/50">
                                </div>
                                <div
                                    class="absolute left-1 top-1 w-4 h-4 bg-white rounded-full transition-all duration-500 peer-checked:translate-x-6 shadow-sm flex items-center justify-center">
                                    <div
                                        class="size-1.5 bg-gray-300 rounded-full transition-colors peer-checked:bg-red-600">
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Product Details Area -->
                <div class="flex-1 flex flex-col bg-white overflow-hidden" id="modalDetailPane">
                    <!-- Detail Scrollable Content -->
                    <div class="flex-1 overflow-y-auto p-10 custom-scrollbar bg-gray-50" id="detailScrollArea">
                        <div id="noSelectionState"
                            class="h-full flex flex-col items-center justify-center text-center py-20">
                            <div class="bg-gray-50 p-8 rounded-full mb-6">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round" class="text-gray-300">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2 tracking-tight">Select an item to review
                            </h3>
                            <p class="text-sm text-gray-500 max-w-[280px]">Pick a product from the list to verify its
                                components and staging status.</p>
                        </div>

                        <div id="productDetailContent" class="hidden space-y-8 pb-10">
                            <!-- Image + Info Header Row -->
                            <div class="flex gap-12 items-start">
                                <!-- Col 1: Visuals -->
                                <div class="w-72 flex-shrink-0">
                                    <div class="h-72 bg-gray-50 rounded-[2rem] overflow-hidden border border-gray-100 shadow-sm transition-all duration-500 hover:shadow-md">
                                        <img id="detailImg" src="" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='../../public/assets/img/favIcon.png'">
                                    </div>
                                </div>

                                <!-- Col 2: Primary Info & Metrics & Locations -->
                                <div class="flex-1 space-y-8">
                                    <div>
                                        <h3 class="text-3xl font-black text-gray-900 tracking-tight" id="detailName">Product Name</h3>

                                        <!-- Minimalist Horizontal Metrics -->
                                        <div class="flex items-center gap-6 mt-4">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none">Code</span>
                                                <span class="text-xs font-bold text-gray-700 leading-none" id="detailCode">CODE-000</span>
                                            </div>
                                            <div class="flex items-center gap-2 px-6 border-x border-gray-100">
                                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none">Ordered</span>
                                                <span class="text-xs font-black text-red-600 leading-none" id="detailQty">0</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest leading-none">Available</span>
                                                <span class="text-xs font-black text-blue-600 leading-none" id="detailStock">0</span>
                                            </div>
                                        </div>
                                    </div>

                                    <p class="text-gray-500 text-sm leading-relaxed" id="detailDescription">Product description goes here.</p>

                                    <!-- Storage Locations -->
                                    <div class="space-y-4 pt-4 border-t border-gray-50">
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Product Locations</p>
                                        <div id="detailLocations" class="grid grid-cols-2 gap-3">
                                            <!-- Locations injected here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Pane Footer (Sticky Actions) -->
                    <div id="detailPaneFooter" class="px-10 py-6 border-t border-gray-100 hidden bg-white">
                        <div class="flex justify-between items-center">
                            <div class="max-w-[200px]">
                                <p class="text-xs text-gray-500 font-medium leading-relaxed">Review and mark products as
                                    ready before fulfilling</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <button id="markReadyBtn"
                                    class="px-10 py-3.5 bg-green-600 text-white rounded-xl font-bold text-sm hover:bg-green-700 tracking-tight active:scale-95 transition-all shadow-lg">
                                    Mark as Ready
                                </button>
                                <button id="cancelReadyBtn"
                                    class="hidden px-10 py-3.5 bg-red-50 text-red-600 rounded-xl font-bold text-sm border border-red-200 hover:bg-red-100 transition-all active:scale-95">
                                    Cancel
                                </button>
                                <button id="finalFulfillBtn"
                                    class="hidden px-10 py-3.5 bg-gray-100 text-gray-400 rounded-xl font-bold text-sm tracking-tight cursor-not-allowed border border-gray-200"
                                    disabled>
                                    Fulfill Order
                                </button>
                                <button id="activeFulfillBtn"
                                    class="hidden px-10 py-3.5 bg-black text-white rounded-xl font-bold text-sm tracking-tight hover:opacity-90 active:scale-95 transition-all shadow-xl">
                                    Fulfill Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../public/assets/js/warehouse.js?v=1.2" defer></script>
    <script src="../../public/assets/js/warehouse-fulfillment.js?v=1.3" defer></script>
    <?php include '../include/logout-modal.php'; ?>
</body>

</html>