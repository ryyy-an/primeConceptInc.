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
    $activities = get_recent_activities($pdo, 5);

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
    <title>Prime-In-Sync | Order Requests</title>
    <link rel="icon" type="image/x-icon" href="../../public/assets/img/primeLogo.ico">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js" defer></script>
    <script src="../../public/assets/js/order.js" defer></script>
    <?php include '../include/toast.php'; ?>


    <style>
        /* Shrink entire UI by 10% */
        html {
            zoom: 90%;
        }
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-[100px]">
    <?php include '../include/loading-splash.php'; ?>

    <header
        class="sticky top-0 z-40 flex h-[100px] items-center justify-between border-b border-gray-200 px-6 bg-white container">

        <div class="flex container">
            <a href="../-admin/dashboard-page.php" class="flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/primeLogo.ico" alt="Prime Concept Logo"
                        class="h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-red-600">Prime-In-Sync</h1>
                    <h4 class="text-base text-gray-500">Welcome, <?= h($username) ?></h4>
                </div>
            </a>
        </div>

        <!-- Right: Role + Icons -->
        <div class="flex items-center gap-4 justify-end w-1/2">
            <div class="rounded-md bg-red-100 px-3 py-1 text-sm text-red-600 font-medium">
                <?= h(ucfirst($role)) ?> User
            </div>

            <!-- Notifications -->
            <div class="relative inline-block">
                <button id="notifButton"
                    class="flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition active:scale-95">
                    <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                    </svg>
                </button>

                <div id="notifDropdown"
                    class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-2xl z-50 overflow-hidden transition-all duration-300">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-tight">Recent Activity</h3>
                    </div>

                    <div id="notifList" class="overflow-y-auto transition-all duration-500 ease-in-out"
                        style="max-height: 200px;">
                        <div class="divide-y divide-gray-50 bg-white">
                            <?php if (empty($activities)): ?>
                                <div class="px-4 py-6 text-center text-gray-400 text-xs italic">
                                    No recent activities found.
                                </div>
                            <?php else: ?>
                                <?php foreach ($activities as $act): ?>
                                    <div class="px-4 py-3 hover:bg-red-50/50 transition-colors">
                                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1">
                                            <?= h($act['type']) ?>
                                        </p>
                                        <div class="text-xs text-gray-700 leading-relaxed">
                                            <?= h($act['action']) ?>
                                        </div>
                                        <span class="text-[10px] text-gray-400 mt-2 block italic"><?= format_activity_time($act['timestamp']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button id="viewAllBtn"
                        class="block w-full py-3 text-center text-[11px] font-extrabold text-red-600 bg-gray-50 hover:bg-red-100 border-t border-gray-100 transition-all uppercase tracking-widest">
                        View All Notifications
                    </button>
                </div>
            </div>

            <!-- Settings -->
            <a href="../-admin/settings.php"
                class="flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition active:scale-95">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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

    <section class="px-6 py-4">
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
                'subtext' => 'All recorded transactions.'
            ],
            [
                'label'      => 'Pending Requests',
                'value'      => $pendingRequestsCount,
                'subtext'    => 'Awaiting your review.',
                'isCritical' => true,
                'animate'    => true
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
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 
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
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 
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
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.879 7.519c1.171-1.025 3.071-1.025 
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
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 
                     21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 
                     0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                        </svg>
                        <span>Order Request</span>
                    </a>
                </li>

            </ul>
        </div>
    </nav>

    <!-- Product Request -->
    <div class="flex justify-center w-full">
        <div class="border border-gray-300 rounded-2xl p-12 w-full max-w-312.5 bg-white">

            <div>
                <h2 class="text-2xl font-semibold mb-2">Product Requests</h2>
                <p class="text-gray-600 mb-6 font-medium">Review and manage your product requests below.</p>
            </div>

            <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900 ">
                <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                    <thead class="bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 w-[15%]">Request ID</th>
                            <th class="px-4 py-4 w-[20%]">Requested By</th>
                            <th class="px-4 py-4 w-[25%]">Customer Name</th>
                            <th class="px-4 py-4 w-[15%]">Date Requested</th>
                            <th class="px-4 py-4 text-center w-[15%]">Current Status</th>
                            <th class="px-6 py-4 text-center w-[10%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productRequestsContent" class="divide-y divide-gray-50">
                        <!-- Data injected via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Table Footer for View More / Pagination -->
            <div id="productRequestsFooter" class="mt-6 flex justify-center py-4 border-t border-gray-50">
                <!-- Buttons injected via JavaScript -->
            </div>



            <div id="viewModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6 transition-all duration-300">
                <div id="modalBackdrop" class="absolute inset-0 bg-slate-900/60 opacity-0 transition-opacity duration-300"></div>

                <div class="relative flex flex-col md:flex-row items-stretch transform transition-all scale-95 opacity-0 duration-300 h-[92vh] max-w-6xl w-full bg-white shadow-[0_20px_60px_-15px_rgba(0,0,0,0.2)] rounded-3xl overflow-hidden border border-gray-100" id="modalWrapper">

                    <div class="flex-1 flex flex-col min-w-0 bg-white">
                        <div class="px-8 py-5 border-b border-gray-100 flex justify-between items-center bg-white sticky top-0 z-20">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 bg-red-50 rounded-xl flex items-center justify-center border border-red-100">
                                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 tracking-tight leading-none">Review Order Request</h3>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span id="modal-status" class="text-[9px] font-black text-red-600 bg-red-50 px-2 py-0.5 rounded-md uppercase border border-red-100 tracking-wider">Pending Review</span>
                                        <span class="text-[11px] text-gray-400 font-medium tracking-tight">Internal System Request</span>
                                    </div>
                                </div>
                            </div>
                            <button onclick="closeReviewModal()" class="p-2 hover:bg-gray-100 text-gray-400 rounded-lg transition-all">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="p-8 space-y-6 overflow-y-auto flex-1 bg-white custom-scrollbar">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Transaction ID</label>
                                    <div class="flex items-center justify-between">
                                        <p id="modal-id" class="font-mono text-sm font-bold text-gray-800 tracking-tight">--</p>
                                        <span class="text-[9px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 uppercase">Auto-Gen</span>
                                    </div>
                                </div>
                                <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                                    <label class="text-[9px] font-bold text-gray-400 uppercase tracking-widest block mb-2">Customer Details</label>
                                    <p class="text-[12px] font-bold text-gray-700 flex items-center gap-2 uppercase tracking-tight">
                                        <span id="modal-customer" class="text-gray-900">--</span>
                                        <span class="text-gray-300">/</span>
                                        <span id="modal-by" class="text-blue-600">--</span>
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex justify-between items-center px-1">
                                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Requested Products</h4>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Total Items: <span id="item-count" class="text-gray-900">0</span></span>
                                </div>
                                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden shadow-sm">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="bg-gray-50 text-gray-400 text-[9px] uppercase font-bold tracking-widest border-b border-gray-100">
                                            <tr>
                                                <th class="px-6 py-3">Product Info</th>
                                                <th class="px-6 py-3 text-center">Qty</th>
                                                <th class="px-6 py-3 text-right">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal-items-body" class="divide-y divide-gray-50 text-[12px] text-gray-700 font-medium">
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="bg-white border-2 border-gray-100 rounded-2xl p-6 relative overflow-hidden">
                                <div class="relative z-10 flex flex-col lg:flex-row gap-6">
                                    <div class="flex-1 space-y-2.5">
                                        <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Reviewer's Note</label>
                                        <textarea id="admin-notes" rows="2"
                                            class="w-full bg-gray-50 border border-gray-100 rounded-xl p-3 text-sm font-medium outline-none focus:bg-white focus:border-red-400 transition-all placeholder:text-gray-300 resize-none"
                                            placeholder="Add remarks..."></textarea>
                                    </div>
                                    <div class="w-full lg:w-52 space-y-2.5">
                                        <div class="flex justify-between items-center">
                                            <label class="text-[10px] font-bold uppercase text-gray-400 tracking-widest">Adjustment</label>
                                            <div class="flex bg-gray-100 rounded-lg p-0.5 border border-gray-200">
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="discount_type" value="currency" checked onclick="toggleDiscountType()" class="hidden peer">
                                                    <div class="px-3 py-1 text-[10px] font-bold text-gray-400 peer-checked:bg-white peer-checked:text-red-600 peer-checked:shadow-sm rounded-md transition-all uppercase">₱</div>
                                                </label>
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="discount_type" value="percentage" onclick="toggleDiscountType()" class="hidden peer">
                                                    <div class="px-3 py-1 text-[10px] font-bold text-gray-400 peer-checked:bg-white peer-checked:text-red-600 peer-checked:shadow-sm rounded-md transition-all uppercase">%</div>
                                                </label>
                                            </div>
                                        </div>
                                        <input type="number" id="admin-discount" oninput="calculateFinalTotal()" placeholder="0.00"
                                            class="w-full mt-2 bg-white border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 font-bold text-lg outline-none focus:border-red-400 transition-all shadow-inner">
                                    </div>
                                </div>

                                <div class="mt-6 pt-5 border-t border-gray-100 flex justify-between items-end relative z-10">
                                    <div>
                                        <span class="text-[9px] font-bold uppercase text-gray-400 tracking-[0.2em] block mb-0.5">Final Amount Due</span>
                                        <span id="modal-total" class="text-3xl font-black text-gray-900 tracking-tighter leading-none">₱0.00</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-3 py-1.5 bg-gray-50 text-gray-500 rounded-full text-[9px] font-bold uppercase tracking-widest border border-gray-100">Philippine Peso</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-8 py-5 bg-white border-t border-gray-100 flex gap-3">
                            <button id="approveBtn" onclick="handleAction('approve')"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3.5 rounded-xl transition-all active:scale-[0.98] text-[11px] uppercase tracking-widest shadow-lg shadow-red-100">
                                Confirm & Approve
                            </button>
                            <button id="rejectBtn" onclick="handleAction('reject')"
                                class="px-8 py-3.5 text-gray-500 hover:bg-gray-50 border border-gray-200 font-bold rounded-xl transition-all text-[11px] uppercase tracking-widest group relative">
                                Reject
                                <span id="reject-hint" class="absolute -top-10 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-[9px] py-1.5 px-3 rounded-lg opacity-0 transition-opacity pointer-events-none whitespace-nowrap">
                                    Requires a reason in remarks
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="bg-gray-50 md:w-80 flex flex-col border-l border-gray-100 h-full">

                        <div class="p-6 border-b border-gray-100 bg-white space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-[0.2em] text-gray-800 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-600"></span> History Log
                            </h4>

                            <div class="relative group">
                                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 group-focus-within:text-red-500 transition-colors">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input type="text" id="history-search" autocomplete="off" oninput="handleHistoryAutocomplete(this)" placeholder="Search customer name..."
                                    class="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-[11px] font-bold text-gray-700 outline-none focus:bg-white focus:border-red-200 focus:ring-4 focus:ring-red-50 transition-all">
                            </div>
                        </div>

                        <div id="history-items-container" class="p-5 flex-1 overflow-y-auto custom-scrollbar">

                            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="text-[12px] font-bold text-gray-800 uppercase leading-tight">Juan Dela Cruz</h4>
                                        <p class="text-[10px] text-gray-500 font-medium">0912 345 6789</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-blue-100 text-blue-700">
                                        Government
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5 mb-3">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-7h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <p class="text-[10px] font-semibold text-gray-600">DepEd </p>
                                </div>

                                <div class="flex gap-3 border-t border-gray-50 pt-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[9px] font-bold text-gray-500 uppercase">Transaction Date</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[9px] font-bold text-gray-500 uppercase">Status</span>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Progressive Table Logic
            let allRequestsData = [];
            let displayLimit = 3;
            let paginationPageSize = 5;
            let currentPage = 1;

            window.fetchProductRequests = function() {
                const tbody = document.getElementById('productRequestsContent');
                tbody.innerHTML = `<tr><td colspan="6" class="py-20 text-center"><div class="flex flex-col items-center gap-2"><div class="size-8 border-4 border-gray-100 border-t-red-600 rounded-full animate-spin"></div><p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Loading requests...</p></div></td></tr>`;

                // Reusing the general report action but with All status
                fetch(`../include/inc.admin/admin.ctrl.php?action=get_orders_report&status=All`)
                    .then(res => res.json())
                    .then(response => {
                        if (response.success) {
                            allRequestsData = response.data;
                            renderRequestsTable();
                        }
                    });
            };

            window.renderRequestsTable = function() {
                const tbody = document.getElementById('productRequestsContent');
                const footer = document.getElementById('productRequestsFooter');
                
                if (allRequestsData.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="py-20 text-center"><p class="text-[11px] font-black text-gray-300 uppercase tracking-widest">No request records found</p></td></tr>`;
                    footer.innerHTML = '';
                    return;
                }

                let dataToShow = [];
                let total = allRequestsData.length;

                // Logic: If limit is 3, show first 3. If limit expanded, show pagination with page size 5.
                if (displayLimit > 3) {
                    let start = (currentPage - 1) * paginationPageSize;
                    let end = start + paginationPageSize;
                    dataToShow = allRequestsData.slice(start, end);
                } else {
                    dataToShow = allRequestsData.slice(0, Math.min(total, displayLimit));
                }

                tbody.innerHTML = dataToShow.map(row => {
                    const status = row.status.toLowerCase();
                    let statusClass = '';
                    if (status === 'approved') statusClass = 'bg-green-50 text-green-600 border-green-100';
                    else if (status === 'rejected') statusClass = 'bg-red-50 text-red-600 border-red-100';
                    else if (status === 'cancelled') statusClass = 'bg-orange-50 text-orange-600 border-orange-100';
                    else if (status === 'success' || status === 'completed') statusClass = 'bg-blue-50 text-blue-600 border-blue-100';
                    else statusClass = 'bg-yellow-50 text-yellow-600 border-yellow-100';

                    return `
                        <tr class="hover:bg-gray-50 transition-colors group">
                            <td class="px-6 py-5 font-bold text-red-600 font-mono text-[13px] tracking-tight">PR-${row.id}</td>
                            <td class="px-4 py-5 text-gray-800 font-medium tracking-tight">${row.requested_by || 'System'}</td>
                            <td class="px-4 py-5 text-gray-800 font-medium tracking-tight">${row.customer_name || 'N/A'}</td>
                            <td class="px-4 py-5 font-mono text-sm text-gray-600">${new Date(row.created_at).toLocaleDateString()}</td>
                            <td class="px-4 py-5 text-center">
                                <span class="inline-block px-2 py-1 text-[10px] font-bold rounded border uppercase ${statusClass}">
                                    ${row.status}
                                </span>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <button onclick="openViewModal('${row.id}')" class="p-2 rounded-lg hover:bg-gray-200 transition-colors text-gray-400 hover:text-gray-900">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');

                // Render Footer Controls
                if (displayLimit === 3 && total > 3) {
                    footer.innerHTML = `
                        <button onclick="expandRequestsTable()" class="flex items-center gap-2 text-[10px] font-black text-gray-900 uppercase tracking-widest hover:text-red-600 transition group">
                            View More (${total})
                            <svg class="size-4 group-hover:translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" /></svg>
                        </button>`;
                } else if (displayLimit > 3) {
                    let totalPages = Math.ceil(total / paginationPageSize);
                    let pagesHtml = '';
                    for (let i = 1; i <= totalPages; i++) {
                        pagesHtml += `<button onclick="goToPage(${i})" class="size-8 rounded-lg text-xs font-black transition ${currentPage === i ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'}">${i}</button>`;
                    }
                    footer.innerHTML = `
                        <div class="flex flex-col items-center gap-4">
                            <button onclick="showLessRequests()" class="text-[10px] font-black text-gray-400 uppercase tracking-widest hover:text-red-600 transition group flex items-center gap-2">
                                <svg class="size-4 group-hover:-translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="3" /></svg>
                                Show Less
                            </button>
                            <div class="flex items-center gap-2">${pagesHtml}</div>
                        </div>`;
                } else {

                    footer.innerHTML = '';
                }
            };

            window.expandRequestsTable = function() {
                displayLimit = 5; // Start showing 5 per page
                renderRequestsTable();
            };

            window.showLessRequests = function() {
                displayLimit = 3;
                currentPage = 1;
                renderRequestsTable();
            };


            window.goToPage = function(page) {
                currentPage = page;
                renderRequestsTable();
            };

            // Initial load
            fetchProductRequests();
        });
    </script>
</body>
</html>