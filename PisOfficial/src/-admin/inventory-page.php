<?php

declare(strict_types=1);
require_once "../include/config.php";
require_once "../include/dbh.inc.php";
require_once "../include/inc.admin/admin.model.php";
require_once "../include/inc.admin/admin.view.php";

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection not established.");
}

if (isset($_SESSION["user_id"])) {
    $userId = (int) $_SESSION["user_id"];
    $username = htmlspecialchars($_SESSION["username"]);
    $role = htmlspecialchars($_SESSION["role"]);

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
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime-In-Sync</title>
    <link rel="icon" type="image/x-icon" href="../../public/assets/img/primeLogo.ico">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/inventory.js?v=1.1" defer></script>
    <script src="../../public/assets/js/global.js?v=1.1" defer></script>
    <script src="../../public/assets/js/order.js" defer></script>
    <?php include '../include/toast.php'; ?>

    <style>
        /* Shrink entire UI by 10% */
        html {
            zoom: 90%;
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e5e7eb;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #d1d5db;
        }
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-25">
    <header
        class="sticky top-0 z-40 flex h-25 items-center justify-between border-b border-gray-200 px-6 bg-white container">

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
                <?= htmlspecialchars(ucfirst($role)) ?> User
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
                            <?php if (!empty($activities)): ?>
                                <?php foreach ($activities as $activity): ?>
                                    <div class="px-4 py-3 hover:bg-gray-50 transition-colors">
                                        <div class="flex flex-col gap-1">
                                            <p class="text-xs text-gray-800 leading-relaxed font-semibold">
                                                <?= htmlspecialchars($activity['fname'] . " " . $activity['action']) ?>
                                            </p>
                                            <span class="text-[10px] text-gray-400 font-medium">
                                                <?= format_activity_time($activity['timestamp']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-xs text-gray-400 italic">No recent activities</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="px-4 py-2 bg-gray-50 border-t border-gray-100 text-center">
                        <button
                            class="text-[10px] font-black text-gray-400 uppercase tracking-widest hover:text-red-500 transition-colors">View
                            All Logs</button>
                    </div>
                </div>
            </div>

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
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
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
                            <span
                                class="absolute top-0 right-2 bg-red-600 text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-full shadow-md border-2 border-white transform translate-x-1/2 -translate-y-1/2 transition-all duration-300">
                                <?= $adminCartCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>

                <!-- Inventory Management (Active) -->
                <li>
                    <a href="../-admin/inventory-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
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

                <!-- Reports and Analytics -->
                <li>
                    <a href="../-admin/reports-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
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

    <section class="flex flex-center w-full">
        <div class="border border-gray-300 rounded-2xl p-12 gap w-312.5">
            <h2 class="text-2xl font-semibold mb-2">Product Inventory</h2>
            <p>Manage warehouse and showroom inventory with enhanced stock tracking</p>


            <!-- search and filter -->
            <div class="relative w-full flex flex-row items-center gap-4 mt-5 mb-5">

                <div class="relative flex-grow h-11 group">
                    <div
                        class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400 group-focus-within:text-red-500 transition-colors">
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>

                    <input id="searchInput"
                        class="w-full h-full bg-gray-100 border border-gray-300 rounded-xl pl-11 pr-16 outline-none text-md text-black placeholder-gray-500 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all shadow-sm"
                        type="text" placeholder="Search products..." />

                    <div class="absolute inset-y-0 right-0 flex items-center pr-2">
                        <div class="h-6 w-[1.5px] bg-gray-300 mx-2 opacity-60"></div>

                        <div class="relative inline-block text-left">
                            <button type="button" onclick="toggleFilterMenu(event)"
                                class="flex items-center justify-center w-9 h-9 rounded-xl text-gray-500 hover:bg-white hover:text-red-600 hover:shadow-sm active:scale-90 transition-all cursor-pointer">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                                </svg>
                            </button>

                            <div id="filterMenu"
                                class="hidden absolute left-1/2 -translate-x-1/2 mt-3 w-48 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 overflow-hidden ring-1 ring-black/5">
                                <div class="py-1">
                                    <button onclick="selectFilter('all')"
                                        class="group flex items-center w-full px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 transition-all">
                                        <span
                                            class="mr-3 opacity-70 group-hover:scale-110 transition-transform">📍</span>
                                        <span class="font-medium">General</span>
                                    </button>
                                    <button onclick="selectFilter('warehouse')"
                                        class="group flex items-center w-full px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 border-t border-gray-100 transition-all">
                                        <span
                                            class="mr-3 opacity-70 group-hover:scale-110 transition-transform">📦</span>
                                        <span class="font-medium">Warehouse</span>
                                    </button>
                                    <button onclick="selectFilter('showroom')"
                                        class="group flex items-center w-full px-4 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-600 border-t border-gray-100 transition-all">
                                        <span
                                            class="mr-3 opacity-70 group-hover:scale-110 transition-transform">🏢</span>
                                        <span class="font-medium">Showroom</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button onclick="window.formHasUnsavedChanges = false; openModal('addProductModal')"
                    class="w-[17%] h-11 bg-black hover:bg-gray-800 text-white rounded-xl flex items-center justify-center gap-2 font-bold text-sm transition-all active:scale-95 shadow-lg shadow-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4">
                        </path>
                    </svg>
                    <span class="whitespace-nowrap">Add Product</span>
                </button>
            </div>

            <!-- Inventory -->
            <?php $inventory = get_inventory_cards($pdo); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-5">
                <?php foreach ($inventory as $item): ?>

                    <div
                        class="card-style p-6 border border-gray-200 rounded-2xl bg-white shadow-sm relative h-full flex flex-col hover:shadow-md transition-all duration-300">

                        <div class="relative">
                            <div class="absolute inset-x-0 top-0 flex justify-between items-start z-10 pointer-events-none">
                                <?php if ($item['is_on_sale']): ?>
                                    <span
                                        class="bg-yellow-400 text-black text-[10px] px-3 py-1 rounded-full font-black uppercase tracking-wider shadow-sm pointer-events-auto">
                                        <?= $item['discount'] ?>% OFF
                                    </span>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>

                                <?php if ($item['overall'] <= 5): ?>
                                    <span
                                        class="bg-red-600 text-white text-[10px] px-3 py-1 rounded-full font-bold uppercase tracking-wider shadow-sm pointer-events-auto">
                                        Low Stock
                                    </span>
                                <?php endif; ?>
                            </div>

                            <button onclick="openStockModal('<?= $item['code'] ?>')"
                                class="absolute bottom-2 right-2 bg-white p-2 rounded-full shadow-lg border border-gray-100 hover:bg-green-600 hover:text-white transition-all z-20 group">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                        d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>

                            <div
                                class="w-full h-48 bg-gray-50 rounded-xl overflow-hidden flex items-center justify-center p-4">
                                <img src="<?= $item['image'] ?>" alt="<?= htmlspecialchars($item['name']) ?>"
                                    class="max-w-full max-h-full object-contain hover:scale-105 transition-transform duration-500"
                                    onerror="this.onerror=null; this.src='<?= $item['placeholder'] ?>';">
                            </div>
                        </div>

                        <div class="mt-4 grow">
                            <h2 class="text-[10px] text-gray-400 font-black uppercase tracking-widest leading-none">
                                <?= htmlspecialchars($item['code']) ?>
                            </h2>

                            <div class="flex justify-between items-start mt-2">
                                <h1 class="text-xl font-extrabold text-gray-900 leading-tight">
                                    <?= htmlspecialchars($item['name']) ?>
                                </h1>
                                <div class="text-right">
                                    <p class="text-xl font-black text-green-600 leading-none">
                                        <span class="overall-qty" data-wh="<?= $item['total_wh'] ?>"
                                            data-sr="<?= $item['total_sr'] ?>"><?= $item['overall'] ?></span> <span
                                            class="text-[10px] font-bold text-gray-400 uppercase">Qty</span>
                                    </p>
                                    <div class="flex gap-1 mt-1.5 justify-end">
                                        <span
                                            class="loc-wh bg-blue-50 text-blue-700 text-[8px] px-1.5 py-0.5 rounded border border-blue-100 font-bold uppercase transition-all duration-300">WH:
                                            <?= $item['total_wh'] ?></span>
                                        <span
                                            class="loc-sr bg-orange-50 text-orange-700 text-[8px] px-1.5 py-0.5 rounded border border-orange-100 font-bold uppercase transition-all duration-300">SR:
                                            <?= $item['total_sr'] ?></span>
                                    </div>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 line-clamp-2 italic leading-relaxed h-8">
                                <?= htmlspecialchars($item['desc']) ?>
                            </p>

                            <div class="pt-3 border-t border-gray-100">
                                <h3 class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2">Inventory Per
                                    Variant</h3>
                                <div class="flex gap-2 overflow-x-auto pb-2 no-scrollbar">
                                    <?php foreach ($item['variants'] as $v): ?>
                                        <div
                                            class="shrink-0 w-24 bg-gray-50 p-2 rounded-lg border border-gray-100 hover:bg-white transition-colors">
                                            <p class="text-[9px] font-bold text-gray-600 truncate">
                                                <?= htmlspecialchars($v['name']) ?>
                                            </p>
                                            <div class="flex justify-between mt-1 text-[9px] font-black italic">
                                                <span class="loc-wh text-blue-500 transition-all duration-300">W:
                                                    <?= $v['wh'] ?></span>
                                                <span class="loc-sr text-orange-500 transition-all duration-300">S:
                                                    <?= $v['sr'] ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 flex justify-between items-end border-t border-gray-50">
                            <div class="flex flex-col">
                                <?php if ($item['is_on_sale']):
                                    $discountedPrice = $item['price'] - ($item['price'] * ($item['discount'] / 100));
                                ?>
                                    <span class="text-[10px] text-gray-400 line-through font-bold leading-none mb-1">
                                        ₱<?= number_format($item['price'], 2) ?>
                                    </span>
                                    <h1 class="text-2xl font-black text-gray-900 tracking-tighter leading-none">
                                        ₱<?= number_format($discountedPrice, 2) ?>
                                    </h1>
                                <?php else: ?>
                                    <h2 class="text-[9px] text-gray-400 uppercase font-bold tracking-tighter leading-none">Price
                                    </h2>
                                    <h1 class="text-2xl font-black text-gray-900 tracking-tighter leading-none mt-1">
                                        ₱<?= number_format($item['price'], 2) ?>
                                    </h1>
                                <?php endif; ?>
                            </div>

                            <div class="text-right">
                            </div>
                        </div>

                        <div class="mt-5 flex gap-2">
                            <button onclick="openEditModal('<?= $item['code'] ?>')"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-all shadow-lg shadow-red-100 active:scale-[0.98] flex items-center justify-center cursor-pointer">Edit
                                Details</button>
                            <button
                                onclick="openDeleteModal('<?= htmlspecialchars($item['code']) ?>', '<?= addslashes(htmlspecialchars($item['name'])) ?>')"
                                class="px-3 border-2 border-red-50 rounded-xl hover:bg-red-50 transition-all group">
                                <svg class="w-5 h-5 text-red-400 group-hover:text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Edit Product Modal -->
            <div id="editModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
                <div class="absolute inset-0 bg-black/50 "></div>

                <form id="editProductForm" oninput="window.formHasUnsavedChanges = true"
                    onchange="window.formHasUnsavedChanges = true"
                    class="modal-box relative bg-white w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden flex flex-col h-[90vh] min-h-[90vh] transition-all duration-300 font-sans scale-95 opacity-0">
                    <input type="hidden" name="prod_id" id="editProdId">
                    <input type="hidden" name="old_code" id="editOldCode">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 shrink-0">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 tracking-tight">Edit Product Details</h3>
                            <p class="text-[10px] font-medium text-gray-400 uppercase tracking-[0.1em] mt-0.5">
                                Inventory / Product Management / Code: <span class="text-black font-black"
                                    id="editCodeHeader">...</span>
                            </p>
                        </div>
                        <button type="button" onclick="closeModalWithCheck('editModal', 'editProductForm')"
                            class="p-2 hover:bg-gray-100 rounded-xl text-gray-400 hover:text-gray-600 transition-all">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-8 space-y-10 custom-scrollbar bg-white">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                            <div class="md:col-span-1">
                                <label
                                    class="block text-[11px] font-black text-gray-400 uppercase mb-3 tracking-widest">Main
                                    Photo</label>
                                <div
                                    class="relative group h-72 bg-gray-50 border border-gray-200 rounded-[2rem] overflow-hidden flex items-center justify-center">
                                    <img id="editImagePreview" src=""
                                        class="object-contain w-full h-full p-6 transition-transform duration-500 group-hover:scale-105"
                                        onerror="this.onerror=null; this.src='../../public/assets/img/furnitures/default.png';">

                                    <label
                                        class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center cursor-pointer backdrop-blur-[2px]">
                                        <input type="file" name="default_image" class="hidden" accept=".jpg,.jpeg,.png"
                                            onchange="previewImage(this, 'editImagePreview')">
                                        <svg class="w-6 h-6 text-white mb-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path
                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                        <p class="text-white text-[10px] font-bold uppercase tracking-widest">Change
                                            Photo</p>
                                    </label>
                                </div>
                            </div>

                            <div class="md:col-span-2 grid grid-cols-2 gap-5">
                                <div class="col-span-2">
                                    <label
                                        class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Product
                                        Name</label>
                                    <input type="text" name="name" id="editName"
                                        class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800 outline-none focus:border-black transition-all"
                                        required>
                                </div>

                                <div>
                                    <label
                                        class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Code</label>
                                    <input type="text" name="code" id="editCode"
                                        class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800 outline-none focus:border-black transition-all"
                                        required>
                                </div>

                                <div class="col-span-1">
                                    <label
                                        class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Category</label>
                                    <input type="text" name="category" id="editCategory" list="categorySuggestion"
                                        class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800 outline-none focus:border-black transition-all"
                                        required>
                                </div>

                                <div class="relative">
                                    <div class="flex justify-between items-center mb-2">
                                        <label
                                            class="block text-[11px] font-black text-orange-500 uppercase tracking-widest">
                                            Discount (%)
                                        </label>

                                        <label class="flex items-center gap-2 cursor-pointer group">
                                            <input type="checkbox" name="is_on_sale" id="editSaleToggle"
                                                class="w-4 h-4 text-orange-500 bg-gray-100 border-gray-300 rounded focus:ring-orange-500 focus:ring-2 cursor-pointer transition-all"
                                                onchange="handleSaleToggle(this)">
                                            <span
                                                class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter group-hover:text-orange-500 transition-colors">
                                                Sale Active
                                            </span>
                                        </label>
                                    </div>

                                    <div class="relative">
                                        <input type="number" name="discount" id="editDiscountInput"
                                            class="w-full px-5 py-3.5 bg-orange-50/30 border border-orange-100 rounded-xl font-black text-orange-600 outline-none focus:border-orange-400 transition-all pr-12 disabled:opacity-50 disabled:bg-gray-100 disabled:border-gray-200 disabled:text-gray-400"
                                            placeholder="0" disabled>
                                        <span
                                            class="absolute right-5 top-1/2 -translate-y-1/2 font-black text-orange-300 pointer-events-none peer-disabled:text-gray-300">%</span>
                                    </div>
                                </div>

                                <div>
                                    <label
                                        class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Price
                                        (₱)</label>
                                    <input type="number" step="0.01" name="price" id="editPrice"
                                        class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-blue-600 outline-none focus:border-black transition-all"
                                        required>
                                </div>

                                <div class="col-span-2">
                                    <label
                                        class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Description</label>
                                    <textarea name="description" id="editDescription" rows="3"
                                        class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-medium text-gray-700 outline-none focus:border-black transition-all resize-none text-sm"
                                        required></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 pt-4">

                            <div class="space-y-4">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                    <h4 class="text-[11px] font-black text-gray-900 uppercase tracking-widest">Product
                                        Component</h4>
                                    <button type="button" onclick="addEditComponentRow()"
                                        class="text-[10px] font-black uppercase text-blue-600 hover:underline">+ Add
                                        Part</button>
                                </div>
                                <div id="editComponentsContainer" class="space-y-3">
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                                    <h4 class="text-[11px] font-black text-gray-900 uppercase tracking-widest">Style
                                        Variants</h4>
                                    <button type="button" onclick="addEditVariantRow()"
                                        class="text-[10px] font-black uppercase text-blue-600 hover:underline">+ Add
                                        Variant</button>
                                </div>
                                <div id="editVariantsContainer" class="space-y-3">
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="p-6 bg-white border-t border-gray-100 flex gap-4 shrink-0">
                        <button type="button" onclick="closeModalWithCheck('editModal', 'editProductForm')"
                            class="flex-1 py-4 border-2 border-gray-200 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 hover:shadow-md hover:-translate-y-0.5 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
                        <button type="button" onclick="handleEditSave()"
                            class="flex-1 py-2.5 bg-red-500 border-2 border-gray-100 rounded-xl font-bold text-white hover:bg-gray-900 hover:text-white transition text-xs uppercase tracking-widest">Update
                            Product</button>
                    </div>
                </form>
            </div>

            <div id="noResults" class="hidden flex-col items-center justify-center py-20 text-center w-full">
                <div class="bg-gray-50 p-6 rounded-full mb-4">
                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800">No products found</h3>
                Try adjusting your keywords or <span onclick="resetFilters()"
                    class="text-red-600 font-bold cursor-pointer hover:underline active:text-red-800 active:scale-95 inline-block transition-all">clear
                    searching filter</span>
            </div>

        </div>

    </section>

    <!-- Add Product Modal -->
    <?php
    $existing_categories = get_unique_categories($pdo);
    $existing_components = get_all_components($pdo);
    ?>

    <div id="addProductModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50">
        </div>

        <form id="addProductForm" oninput="window.formHasUnsavedChanges = true"
            onchange="window.formHasUnsavedChanges = true"
            class="modal-box relative bg-white w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden flex flex-col h-[90vh] min-h-[90vh] transition-all duration-300 font-sans scale-95 opacity-0">

            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 shrink-0">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 tracking-tight">Add New Product</h3>
                    <p class="text-[10px] font-medium text-gray-400 uppercase tracking-[0.1em] mt-0.5">
                        Inventory / Product Management / Create
                    </p>
                </div>
                <button type="button" onclick="closeModalWithCheck('addProductModal', 'addProductForm')"
                    class="p-2 hover:bg-gray-100 rounded-xl text-gray-400 hover:text-gray-600 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8 space-y-10 custom-scrollbar bg-white">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                    <div class="md:col-span-1">
                        <label class="block text-[11px] font-black text-gray-400 uppercase mb-3 tracking-widest">Default
                            Image</label>
                        <div
                            class="relative group h-72 bg-gray-50 border border-gray-200 rounded-[2rem] overflow-hidden flex items-center justify-center">
                            <div id="placeholderIcon" class="flex flex-col items-center text-gray-300">
                                <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </div>
                            <img id="mainImagePreview"
                                class="hidden object-contain w-full h-full p-6 transition-transform duration-500 group-hover:scale-105">

                            <label
                                class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center cursor-pointer backdrop-blur-[2px]">
                                <input type="file" name="default_image" class="hidden" accept=".jpg,.jpeg,.png"
                                    onchange="previewImage(this, 'mainImagePreview')" required>
                                <p class="text-white text-[10px] font-bold uppercase tracking-widest">Upload Image</p>
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-2 grid grid-cols-2 gap-5">
                        <div class="col-span-2">
                            <label
                                class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Product
                                Name</label>
                            <input type="text" name="name" placeholder="Enter product name..."
                                class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800 outline-none focus:border-black transition-all"
                                required>
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Code</label>
                            <input type="text" name="code" placeholder="e.g. EC-01"
                                class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800 outline-none focus:border-black transition-all"
                                required>
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Category</label>
                            <input type="text" name="category" list="categorySuggestion" placeholder="Search or type..."
                                class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-gray-800 outline-none focus:border-black transition-all"
                                required>
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Price
                                (₱)</label>
                            <input type="number" name="price" step="0.01" placeholder="0.00"
                                class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-bold text-blue-600 outline-none focus:border-black transition-all"
                                required>
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Description</label>
                            <textarea name="description" rows="3" placeholder="Add short product description..."
                                class="w-full px-5 py-3.5 bg-gray-50 border border-gray-200 rounded-xl font-medium text-gray-700 outline-none focus:border-black transition-all resize-none text-sm"
                                required></textarea>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 pt-4">

                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                            <h4 class="text-[11px] font-black text-gray-900 uppercase tracking-widest">Product Component
                            </h4>
                            <button type="button" onclick="addComponentRow()"
                                class="text-[10px] font-black uppercase text-blue-600 hover:underline">+ Add
                                Part</button>
                        </div>
                        <div id="componentsContainer" class="space-y-3">
                            <div
                                class="flex items-center gap-3 p-3 bg-gray-50 rounded-2xl group border border-transparent hover:border-gray-200 transition-all">
                                <div class="flex-1">
                                    <input type="text" name="comp_names[]" list="compSuggestion"
                                        placeholder="Search or type part..."
                                        class="w-full bg-transparent font-bold text-gray-800 outline-none text-sm"
                                        required>
                                    <div
                                        class="flex items-center gap-2 mt-2 border border-gray-200 rounded-lg px-3 py-1 bg-white w-full">
                                        <span
                                            class="text-[10px] font-black text-blue-500 uppercase tracking-widest shrink-0">LOC:</span>
                                        <input type="text" name="comp_locs[]" placeholder="Aisle 0-0"
                                            class="w-full bg-transparent outline-none text-[11px] font-bold uppercase text-gray-700"
                                            value="" required>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 px-2 border-l border-gray-200">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Qty</span>
                                    <input type="number" name="comp_qtys[]" placeholder="0"
                                        class="w-10 bg-white border border-gray-200 rounded-lg text-center font-bold text-sm py-1 outline-none"
                                        required>
                                </div>
                                <button type="button" onclick="this.parentElement.remove()"
                                    class="text-gray-300 hover:text-red-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
                            <h4 class="text-[11px] font-black text-gray-900 uppercase tracking-widest">Style Variants
                            </h4>
                            <button type="button" onclick="addVariantRow()"
                                class="text-[10px] font-black uppercase text-blue-600 hover:underline">+ Add
                                Variant</button>
                        </div>
                        <div id="variantsContainer" class="space-y-3">
                            <div
                                class="flex items-center gap-4 p-3 bg-gray-50 rounded-2xl group border border-transparent hover:border-gray-200 transition-all">
                                <div
                                    class="w-14 h-14 bg-white rounded-xl border border-gray-200 flex items-center justify-center shrink-0 relative">
                                    <img id="v-prev-0" class="hidden object-cover w-full h-full rounded-xl">
                                    <svg id="v-svg-0" class="w-4 h-4 text-gray-200" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                    <label
                                        class="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 flex items-center justify-center cursor-pointer rounded-xl transition-all">
                                        <input type="file" name="variant_imgs[]" class="hidden"
                                            onchange="previewVariantImage(this, 0)" required>
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                                        </svg>
                                    </label>
                                </div>
                                <div class="flex-1">
                                    <p class="text-[9px] font-black text-gray-400 uppercase mb-0.5 tracking-widest">
                                        Variant Name</p>
                                    <input type="text" name="variant_names[]" placeholder="e.g. Matte Black"
                                        class="w-full bg-transparent font-bold text-gray-800 outline-none text-sm"
                                        required>
                                </div>
                                <div class="w-20 border-l border-gray-200 pl-3">
                                    <p class="text-[9px] font-black text-red-500 uppercase mb-0.5 tracking-widest">Low
                                        Stock</p>
                                    <input type="number" name="variant_low_stocks[]" placeholder="10"
                                        class="w-full bg-transparent font-bold text-red-600 outline-none text-sm"
                                        required>
                                </div>
                                <div class="flex items-center gap-2 border-l border-gray-200 pl-2">
                                    <button type="button" onclick="this.closest('.group').remove()"
                                        class="text-gray-300 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Outer Low Stock Alert merged into variant -->

                    </div>

                </div>
            </div>

            <datalist id="categorySuggestion">
                <?php foreach ($existing_categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>">
                    <?php endforeach; ?>
            </datalist>

            <datalist id="compSuggestion">
                <?php foreach ($existing_components as $comp): ?>
                    <option value="<?= htmlspecialchars($comp) ?>">
                    <?php endforeach; ?>
            </datalist>

            <div class="p-6 bg-white border-t border-gray-100 flex gap-4 shrink-0">
                <button type="button" onclick="closeModalWithCheck('addProductModal', 'addProductForm')"
                    class="flex-1 py-4 border-2 border-gray-200 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 hover:shadow-md hover:-translate-y-0.5 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
                <button type="button" onclick="saveNewProduct()"
                    class="flex-1 py-2.5 bg-red-500 border-2 border-gray-100 rounded-xl font-bold text-white hover:bg-gray-900 hover:text-white transition text-xs uppercase tracking-widest">Save
                    Product</button>
            </div>
        </form>
    </div>

    <!-- Removed unused editProductModal to prevent ID conflict -->

    <!-- Delete Product Modal -->
    <div id="deleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50 "></div>

        <div
            class="modal-box relative bg-white rounded-3xl shadow-2xl overflow-hidden text-center p-8 transform scale-95 opacity-0 transition-all duration-300 max-w-sm">
            <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 class="text-2xl font-black text-gray-900 mb-2 text-red-600">Confirm Deletion</h3>
            <p class="text-gray-500 mb-8 text-sm leading-relaxed">
                You are about to permanently delete <span class="font-bold text-gray-900"
                    id="deleteProductName">...</span>.
                <br><br>
                <span class="text-red-500 font-black uppercase tracking-widest text-[10px] block mb-1">Permanent Data
                    Loss Warning</span>
                This will erase all associated inventory logs, warehouse stocks, and <span
                    class="text-red-600 font-bold underline">sales history</span> for this product.
            </p>
            <div class="flex gap-3">
                <button type="button" onclick="closeDeleteModal()"
                    class="flex-1 py-3 border-2 border-gray-100 rounded-xl font-bold text-gray-500 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="button" onclick="confirmDeleteProduct()"
                    class="flex-1 py-3 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition-all shadow-lg shadow-red-200">Delete
                    Product</button>
            </div>
        </div>
    </div>


    <!-- adjust Stock Modal -->
    <div id="stockModal"
        class="fixed inset-0 z-60 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/40 "></div>

        <div
            class="modal-box relative bg-white w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden flex flex-col h-[88vh] max-h-[920px] transition-all duration-300 border border-gray-100 scale-95 opacity-0">

            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 shrink-0">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 tracking-tight">Stock Adjustment</h3>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Product: <span
                            class="text-black font-black" id="stockModalName">...</span> | Code: <span
                            class="text-black font-black" id="stockModalCode">...</span></p>
                </div>
                <button onclick="closeModal('stockModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>


            <div class="flex-1 flex overflow-hidden">
                <div id="locationSidebar" class="w-56 bg-gray-50 border-r border-gray-100 p-5 flex flex-col shrink-0">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1 mb-4">Update
                        Location</label>
                    <div id="stockTabsContainer" class="space-y-3 flex-1 overflow-y-auto custom-scrollbar">
                        <!-- Dynamic Tabs -->
                    </div>
                </div>

                <div id="stockContentArea" class="flex-1 overflow-y-auto p-8 bg-white custom-scrollbar">
                    <div id="warehouseContent" class="space-y-6">
                        <!-- Dynamic WH Content -->
                    </div>

                    <div id="showroomContent" class="hidden space-y-6">
                        <!-- Dynamic SR Content -->
                    </div>
                </div>
            </div>


            <div class="p-6 bg-white border-t border-gray-100 flex gap-4 shrink-0">
                <button type="button" onclick="closeModal('stockModal')"
                    class="flex-1 py-4 border-2 border-gray-200 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 hover:shadow-md hover:-translate-y-0.5 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
                <button type="button" id="stockUpdateBtn" onclick="handleStockUpdate()"
                    class="flex-1 py-2.5 bg-red-500 border-2 border-gray-100 rounded-xl font-bold text-white hover:bg-gray-900 hover:text-white transition text-xs uppercase tracking-widest">Update
                    Stocks</button>
            </div>
        </div>
    </div>

    <!-- Custom Alert Modal (Warning Style) -->
    <div id="customAlertModal"
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/40"></div>
        <div
            class="modal-box relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden flex flex-col transition-all duration-300 font-sans p-8 text-center border border-gray-100 scale-95 opacity-0">
            <div
                class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-orange-50 mb-6 ring-8 ring-orange-50/50">
                <svg class="h-8 w-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Notice</h3>
            <div class="mt-2 text-sm font-medium text-gray-500 mb-8 leading-relaxed" id="customAlertMessage">
                Message goes here.
            </div>
            <button onclick="closeAlertAndResetSpinners()"
                class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">
                Okay, got it!
            </button>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="customConfirmModal"
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50"></div>
        <div
            class="modal-box relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden transform transition-all duration-300 scale-95 opacity-0 p-8 text-center border border-gray-100">
            <div id="customConfirmIconContainer"
                class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-red-50/50">
                <svg id="customConfirmIcon" class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path id="customConfirmIconPath" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 id="customConfirmTitle" class="text-xl font-black text-gray-900 tracking-tight mb-2">Are you sure?</h3>
            <p id="customConfirmMessage" class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">This action cannot be undone. Are you sure you want to proceed?</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('customConfirmModal')"
                    class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Cancel</button>
                <button type="button" id="customConfirmBtn"
                    class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Stock Reduction Confirmation Modal -->
    <div id="reductionConfirmModal"
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50"></div>

        <div
            class="modal-box relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden transform transition-all duration-300 p-8 text-center border border-gray-100 scale-95 opacity-0">
            <div
                class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-red-50/50">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Reduce Stock?</h3>
            <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">We noticed you're reducing the stock
                level. Are you sure you want to proceed with this inventory reduction?</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('reductionConfirmModal')"
                    class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Cancel</button>
                <button type="button" onclick="executeStockUpdate()"
                    class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Yes,
                    Reduce</button>
            </div>
        </div>
    </div>

    <!-- Discard Changes Modal -->
    <div id="discardModal"
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50"></div>

        <div
            class="modal-box relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden transform transition-all duration-300 p-8 text-center border border-gray-100 scale-95 opacity-0">
            <div
                class="w-16 h-16 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-orange-50/50">
                <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Unsaved Changes</h3>
            <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">You have unsaved changes. Are you sure you
                want to discard them? This action cannot be undone.</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('discardModal')"
                    class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Keep
                    Editing</button>
                <button type="button" id="confirmDiscardBtn"
                    class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
            </div>
        </div>
    </div>

    <!-- Edit Product Confirmation Modal -->
    <div id="editConfirmModal"
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50"></div>

        <div
            class="modal-box relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden transform transition-all duration-300 p-8 text-center border border-gray-100 scale-95 opacity-0">
            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">No Changes Made</h3>
            <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">It looks like you haven't made any edits
                yet. Please update the fields before saving.</p>
            <button onclick="closeModal('editConfirmModal')"
                class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-3.5 bg-gray-900 text-sm font-bold text-white hover:bg-gray-800 transition-colors uppercase tracking-widest">
                Return to Edit
            </button>
        </div>
    </div>

</body>

</html>

