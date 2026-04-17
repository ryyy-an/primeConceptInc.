<?php

declare(strict_types=1);

require_once '../include/config.php';
require_once '../include/dbh.inc.php';
require_once '../include/session_js.php';
require_once '../include/inc.admin/admin.model.php';
require_once '../include/inc.admin/admin.view.php';


/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection not established.');
}

if (isset($_SESSION['user_id'])) {
    // User is logged in → you can store values in variables for later use in HTML
    $userId = $_SESSION['user_id'];
    $username = htmlspecialchars($_SESSION['username']);
    $role = htmlspecialchars($_SESSION['role']);

    // Fetch Admin Statistics
    $stats = get_admin_order_stats($pdo);
    $totalProducts = $stats['total_products'];
    $totalTransactionsCount = $stats['total_transactions'];
    $pendingRequestsCount = $stats['pending_requests'];
} else {
    // Not logged in → redirect
    header("Location: ../../public/index.php");
    exit;
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
    <script src="../../public/assets/js/global.js?v=<?= time() ?>" defer></script>
    <script src="../../public/assets/js/order.js?v=<?= time() ?>" defer></script>


    <style>
        /* Shrink entire UI by 10% */
        html {
            zoom: 90%;
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
            </a> <?php include '../include/logout-modal.php'; ?>

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
                'subtext' => 'All recorded system sales.'
            ],
            [
                'label'      => 'Pending Request',
                'value'      => $pendingRequestsCount,
                'subtext'    => 'Current pending order request.',
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
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 
                     2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 
                     0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                        </svg>
                        <span>POS System</span>
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

                <!-- Order Request (Active) -->
                <li>
                    <a class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition"
                        href="../-admin/order-req-page.php">
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

    <!-- Product Tabs -->
    <div class="w-full px-5 flex flex-col gap-5">

        <?php
        $activeTab = isset($_GET['tab']) ? (int) $_GET['tab'] : 0;
        $cartItems = get_cart_items($pdo, (int) ($_SESSION['user_id'] ?? 0));
        $totalCartItems = count($cartItems);
        ?>

        <div class="flex flex-center w-full">
            <div class="flex-center bg-gray-100 rounded-3xl px-1 py-1 gap-5 shadow-sm w-312.5">
                <!-- Product Catalog Tab -->
                <button onclick="refreshAndShowTab(0)" id="tabBtn0"
                    class="w-full flex-center h-10 gap-2 px-4 rounded-3xl bg-white border border-gray-300 text-red-600 font-semibold hover:bg-red-100 transition">
                    <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                    </svg>
                    <span>Product Catalog</span>
                </button>

                <!-- Product Cart Tab -->
                <button onclick="refreshAndShowTab(1)" id="tabBtn1"
                    class="relative flex items-center justify-center w-full gap-2 h-10 px-4 rounded-3xl text-gray-700 font-medium hover:bg-red-100 transition">
                    <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>

                    <span>Product Cart</span>

                    <span id="cart-badge-admin"
                        class="cart-badge <?= $totalCartItems > 0 ? 'flex' : 'hidden' ?> absolute top-0 right-2 bg-red-600 text-white text-[10px] font-black w-5 h-5 items-center justify-center rounded-full shadow-md border-2 border-white transform translate-x-1/2 -translate-y-1/2 transition-all duration-300">
                        <?= $totalCartItems ?>
                    </span>
                </button>
            </div>
        </div>

        <script>
            async function refreshAndShowTab(tabIndex) {
                // 1. Instantly switch the UI tabs for a seamless feel
                showTab(tabIndex);
                window.history.replaceState({}, '', window.location.pathname + '?tab=' + tabIndex);

                // Update badge too
                if (typeof updateCartBadgeCount === 'function') updateCartBadgeCount();

                // 2. If we are switching to the cart, silently fetch the freshest server cart data 
                if (tabIndex === 1) {
                    try {
                        const response = await fetch(window.location.pathname + '?tab=1');
                        const html = await response.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        // Replace the cart container content silently
                        const newTab1 = doc.getElementById('tabContent1');
                        if (newTab1) {
                            document.getElementById('tabContent1').innerHTML = newTab1.innerHTML;
                        }
                    } catch (e) {
                        console.error('Failed to sync latest cart.', e);
                    }
                }
            }

            // after reload, show the correct tab
            document.addEventListener("DOMContentLoaded", () => {
                const activeTab = <?= $activeTab ?>;
                showTab(activeTab);
            });
        </script>


        <!-- Catalog -->
        <div class="flex flex-center w-full">
            <div class="border border-gray-300 rounded-2xl p-12 gap w-312.5">

                <div id="tabContent0">
                    <h2 class="text-2xl font-semibold mb-2">Product Catalog</h2>
                    <p class="text-gray-600">Browse and add products to your order.</p>

                    <!-- Searchbox -->
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
                                        class="flex items-center justify-center w-9 h-9  text-gray-500 hover:text-red-600 active:scale-90 transition-all cursor-pointer">
                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
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
                    </div>

                    <!-- Product Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-5">
                        <?php
                        $productsRaw = get_inventory_cards($pdo);
                        $inStock = [];
                        $outOfStock = [];
                        foreach ($productsRaw as $pid => $p) {
                            if ($p['overall'] > 0) {
                                $inStock[$pid] = $p;
                            } else {
                                $outOfStock[$pid] = $p;
                            }
                        }
                        $products = $inStock + $outOfStock;

                        if (empty($products)): ?>
                            <p class="text-gray-500">No products available.</p>
                        <?php else: ?>
                            <?php foreach ($products as $pid => $p):
                                $encodedProduct = rawurlencode(json_encode($p));
                            ?>
                                <div class="card-style p-6 border border-gray-200 rounded-2xl bg-white shadow-sm relative h-full flex flex-col hover:border-red-200 transition-colors product-card"
                                    data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-wh="<?= $p['total_wh'] ?>"
                                    data-sr="<?= $p['total_sr'] ?>">

                                    <?php if ($p['overall'] <= 0): ?>
                                        <div class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-10 rounded-2xl flex items-center justify-center pointer-events-none">
                                            <div class="border-4 border-gray-300 text-gray-500 font-black px-6 py-2 rounded-lg transform -rotate-12 shadow-sm text-xl tracking-widest uppercase bg-white/90">
                                                Out of Stock
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="relative">
                                        <img src="<?= htmlspecialchars($p['image']) ?>"
                                            onerror="this.onerror=null; this.src='<?= htmlspecialchars($p['placeholder']) ?>';"
                                            alt="<?= htmlspecialchars($p['name']) ?>"
                                            class="w-full h-48 object-contain mx-auto rounded-lg">
                                    </div>

                                    <div class="mt-4 grow">
                                        <h2 class="text-xs text-gray-400 font-bold uppercase tracking-widest leading-none">
                                            <?= htmlspecialchars($p['code']) ?></h2>

                                        <div class="flex justify-between items-start mt-1">
                                            <h1 class="text-2xl font-bold text-gray-900 leading-tight">
                                                <?= htmlspecialchars($p['name']) ?></h1>

                                            <div class="text-right">
                                                <p class="text-2xl font-black text-green-600 leading-none">
                                                    <?= $p['overall'] ?> <span
                                                        class="text-sm font-medium text-gray-500">Total</span>
                                                </p>
                                                <div class="flex gap-1 mt-2 justify-end">
                                                    <span
                                                        class="bg-blue-50 text-blue-700 text-[9px] px-2 py-0.5 rounded border border-blue-100 font-bold uppercase">WH:
                                                        <?= $p['total_wh'] ?></span>
                                                    <span
                                                        class="bg-orange-50 text-orange-700 text-[9px] px-2 py-0.5 rounded border border-orange-100 font-bold uppercase">SR:
                                                        <?= $p['total_sr'] ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <p class="text-sm text-gray-500 mt-2 line-clamp-2 italic">
                                            <?= htmlspecialchars($p['desc']) ?></p>

                                        <div class=" pt-3 border-t border-gray-100">
                                            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">
                                                Availability per Variant</h3>

                                            <div class="flex gap-2 overflow-x-auto pb-1 hide-scrollbar">
                                                <?php foreach ($p['variants'] as $v): ?>
                                                    <div class="shrink-0 w-28 bg-gray-50 p-2 rounded-lg border border-gray-100">
                                                        <p class="text-[10px] font-bold text-gray-700 truncate mb-1"
                                                            title="<?= htmlspecialchars($v['name']) ?>">
                                                            <?= htmlspecialchars($v['name']) ?></p>
                                                        <div class="flex justify-between items-center text-[11px]">
                                                            <span class="text-blue-600 font-black" title="Warehouse Stock">WH:
                                                                <?= $v['wh'] ?></span>
                                                            <span class="text-orange-600 font-black" title="Showroom Stock">SR:
                                                                <?= $v['sr'] ?></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h1 class="text-2xl font-black text-gray-900 tracking-tighter leading-none mt-1">
                                            ₱<?= number_format($p['price'], 2) ?></h1>
                                    </div>

                                    <div class="flex justify-between items-end border-t border-gray-50 pt-4">
                                        <div class="space-y-1">
                                            <h2 class="text-[10px] text-gray-400 uppercase font-bold tracking-tighter">Category
                                            </h2>
                                            <h1 class="text-base font-bold text-gray-800 leading-none">
                                                <?= htmlspecialchars($p['category']) ?></h1>
                                        </div>
                                    </div>

                                    <div class="mt-6 flex gap-2 relative z-20">
                                        <?php if ($p['overall'] <= 0): ?>
                                            <button disabled
                                                class="flex-1 py-3 bg-gray-100 border-2 border-gray-100 rounded-xl font-bold text-gray-400 cursor-not-allowed uppercase tracking-widest text-[11px]">
                                                Out of Stock
                                            </button>
                                        <?php else: ?>
                                            <button onclick="openProductModal('<?= $encodedProduct ?>')"
                                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-all shadow-lg shadow-red-100 active:scale-[0.98] flex items-center justify-center cursor-pointer">
                                                Add to Cart
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div id="posNoResults" class="hidden flex-col items-center justify-center py-20 text-center w-full">
                        <div class="bg-gray-50 p-6 rounded-full mb-4">
                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800">No products found</h3>
                        <p class="text-sm text-gray-500">We couldn't find anything matching your search or filter.</p>
                    </div>

                    <!-- View Add Cart Modal -->
                    <div id="addToCartModal"
                        class="fixed inset-0 z-60 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
                        <div class="absolute inset-0 bg-black/40 backdrop"></div>

                        <div
                            class="modal-box relative bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden flex flex-col h-fit max-h-[90vh] transition-all duration-300 font-sans">

                            <div
                                class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-white shrink-0">
                                <div>
                                    <h3 class="text-lg font-black text-gray-900 tracking-tight">Confirm Selection</h3>
                                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Item
                                        Code: <span class="text-black" id="modalProductCode">EC-001</span></p>
                                </div>
                                <button onclick="closeModal('addToCartModal')"
                                    class="p-2 text-gray-400 hover:text-black transition-colors rounded-xl hover:bg-gray-50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 18L18 6M6 6l12 12" stroke-width="2.5" stroke-linecap="round"
                                            stroke-linejoin="round"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 custom-scrollbar">

                                <div
                                    class="flex items-center justify-between bg-gray-50/50 p-5 rounded-3xl border border-gray-100">
                                    <div class="flex items-center gap-5">
                                        <div
                                            class="w-20 h-20 bg-white rounded-2xl border border-gray-200 shrink-0 p-2 shadow-sm">
                                            <img id="modalMainImg"
                                                src="../../public/assets/img/furnitures/chair/Executive Chair/main.png"
                                                class="object-contain w-full h-full transition-all duration-300">
                                        </div>
                                        <div>
                                            <h1 id="modalName"
                                                class="text-xl font-black text-gray-900 leading-none tracking-tight">
                                                Executive Chair</h1>
                                            <h2 id="modalPrice"
                                                class="text-xl font-black text-blue-600 mt-2 tracking-tight">₱8,500.00
                                            </h2>
                                        </div>
                                    </div>

                                    <div class="text-right border-l-2 border-gray-100 pl-6 shrink-0">
                                        <span
                                            class="block text-[9px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">Overall
                                            Stock</span>
                                        <div class="flex items-baseline justify-end gap-1">
                                            <span id="overallStock"
                                                class="text-3xl font-black text-gray-900 leading-none tracking-tighter">42</span>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase">pcs</span>
                                        </div>
                                        <span
                                            class="block text-[8px] font-bold text-green-500 uppercase mt-1 italic tracking-tighter">Available
                                            in PIS</span>
                                    </div>
                                </div>

                                <div class="bg-blue-50/40 rounded-2xl p-4 border border-blue-100/50 mt-4">
                                    <label
                                        class="block text-[9px] font-black text-blue-500 uppercase tracking-widest mb-1.5 ml-1">Variant
                                        Description</label>
                                    <p id="variantDesc"
                                        class="text-xs font-medium text-gray-600 leading-relaxed italic">
                                        Select a variant to view specific material details and finish descriptions.
                                    </p>
                                </div>

                                <div class="space-y-3">
                                    <label
                                        class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">1.
                                        Select Variant</label>
                                    <div id="variant-list" class="grid grid-cols-2 gap-3">
                                        <label class="cursor-pointer group">
                                            <input type="radio" name="variant" value="Matte Black"
                                                data-img="path/to/black.png"
                                                data-desc="Smooth matte black finish with scratch-resistant coating."
                                                class="hidden peer" checked onchange="updateVariantDetails(this)">

                                            <div
                                                class="p-2.5 border-2 border-gray-100 rounded-2xl peer-checked:border-blue-600 peer-checked:bg-blue-50/30 transition-all flex items-center justify-between shadow-sm hover:border-gray-200">

                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 bg-white rounded-xl border border-gray-100 p-1.5 shrink-0 shadow-sm">
                                                        <img src="path/to/black.png"
                                                            class="object-contain w-full h-full">
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-[11px] font-black text-gray-800 uppercase leading-none tracking-tight">Matte
                                                            Black</span>
                                                        <span
                                                            class="text-[8px] font-bold text-gray-400 uppercase mt-1">Variant</span>
                                                    </div>
                                                </div>

                                                <div class="text-right border-l border-gray-100 pl-3 shrink-0">
                                                    <span
                                                        class="block text-[11px] font-black text-gray-900 leading-none">12</span>
                                                    <span
                                                        class="text-[7px] font-black text-blue-500 uppercase tracking-tighter">Stocks</span>
                                                </div>
                                            </div>
                                        </label>

                                        <label class="cursor-pointer group">
                                            <input type="radio" name="variant" value="Wood Finish"
                                                data-img="path/to/wood.png"
                                                data-desc="Natural oak wood texture with a polished protective seal."
                                                class="hidden peer" onchange="updateVariantDetails(this)">

                                            <div
                                                class="p-2.5 border-2 border-gray-100 rounded-2xl peer-checked:border-blue-600 peer-checked:bg-blue-50/30 transition-all flex items-center justify-between shadow-sm hover:border-gray-200">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-10 h-10 bg-white rounded-xl border border-gray-100 p-1.5 shrink-0 shadow-sm">
                                                        <img src="path/to/wood.png"
                                                            class="object-contain w-full h-full">
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="text-[11px] font-black text-gray-800 uppercase leading-none tracking-tight">Wood
                                                            Finish</span>
                                                        <span
                                                            class="text-[8px] font-bold text-gray-400 uppercase mt-1">Variant</span>
                                                    </div>
                                                </div>

                                                <div class="text-right border-l border-gray-100 pl-3 shrink-0">
                                                    <span
                                                        class="block text-[11px] font-black text-gray-900 leading-none">8</span>
                                                    <span
                                                        class="text-[7px] font-black text-blue-500 uppercase tracking-tighter">Stocks</span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                                    <div class="space-y-3">
                                        <label
                                            class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">2.
                                            Item Source</label>
                                        <div class="space-y-2">
                                            <label class="cursor-pointer block">
                                                <input type="radio" name="source" value="SR" class="hidden peer"
                                                    checked>
                                                <div
                                                    class="px-4 py-2.5 border-2 border-gray-100 rounded-xl peer-checked:border-black peer-checked:bg-black peer-checked:text-white transition-all flex justify-between items-center">
                                                    <span
                                                        class="text-[10px] font-black uppercase tracking-tight">Showroom</span>
                                                    <span
                                                        class="text-[9px] font-bold text-orange-500 peer-checked:text-orange-300">1
                                                        Stock</span>
                                                </div>
                                            </label>
                                            <label class="cursor-pointer block">
                                                <input type="radio" name="source" value="WH" class="hidden peer">
                                                <div
                                                    class="px-4 py-2.5 border-2 border-gray-100 rounded-xl peer-checked:border-black peer-checked:bg-black peer-checked:text-white transition-all flex justify-between items-center">
                                                    <span
                                                        class="text-[10px] font-black uppercase tracking-tight">Warehouse</span>
                                                    <span
                                                        class="text-[9px] font-bold text-blue-500 peer-checked:text-blue-300">3
                                                        Stock</span>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <label
                                            class="block text-[10px] font-black text-gray-400 uppercase tracking-widest ml-1">3.
                                            Quantity</label>
                                        <div
                                            class="flex items-center bg-gray-50 border-2 border-gray-100 rounded-xl p-1.5 h-12">
                                            <button onclick="changeQty(-1)"
                                                class="w-10 h-full bg-white rounded-lg font-black hover:bg-gray-100 transition-colors shadow-sm text-sm">-</button>
                                            <input type="number" id="cartQty" value="1" min="1"
                                                class="flex-1 bg-transparent text-center font-black text-lg outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                            <button onclick="changeQty(1)"
                                                class="w-10 h-full bg-white rounded-lg font-black hover:bg-gray-100 transition-colors shadow-sm text-sm">+</button>
                                        </div>
                                        <p
                                            class="text-[9px] text-center text-gray-400 font-bold uppercase tracking-tighter italic">
                                            Check stock before adding</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-5 border-t border-gray-100 flex gap-3 shrink-0">
                                <button type="button" onclick="closeModal('addToCartModal')"
                                    class="flex-1 py-4 border-2 border-gray-200 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 hover:shadow-md hover:-translate-y-0.5 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
                                <button type="button" onclick="handleAddToCart()"
                                    class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl transition-all shadow-lg shadow-red-100 active:scale-[0.98] flex items-center justify-center cursor-pointer">Add
                                    to Cart</button>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- end of tab1 -->

                <div id="tabContent1" class="hidden ">
                    <div class="flex flex-col gap-5">
                        <div>
                            <h2 class="text-2xl font-semibold mb-2">Product Cart</h2>
                            <p class="text-gray-600">Review items before submitting your order.</p>
                        </div>

                        <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl shadow-sm">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Source</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Product Details</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Quantity</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-right">Subtotal</th>
                                        <th class="px-6 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php
                                    $cartSubtotal = 0;
                                    if (empty($cartItems)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-20">
                                                <div class="flex flex-col items-center justify-center text-center">
                                                    <div class="bg-gray-50 p-6 rounded-full mb-4">
                                                        <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                        </svg>
                                                    </div>
                                                    <h3 class="text-lg font-bold text-gray-800">No item is in the cart yet</h3>
                                                    <p class="text-[11px] text-gray-500 max-w-[200px] mt-1">Browse the product catalog to add items to your cart.</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php else:
                                        foreach ($cartItems as $cItem):
                                            $itemTotal = $cItem['price'] * $cItem['qty'];
                                            $cartSubtotal += $itemTotal;
                                            $srcBadge = $cItem['source'] === 'SR' ? 'bg-red-600' : 'bg-gray-800';
                                            $srcLabel = $cItem['source'] === 'SR' ? 'Showroom' : 'Warehouse';
                                        ?>
                                            <tr class="group hover:bg-gray-50/50 transition-colors">
                                                <td class="px-6 py-4 text-center">
                                                    <span class="<?= $srcBadge ?> text-white text-[8px] px-2 py-1 rounded-md font-black shadow-sm border border-white uppercase italic">
                                                        <?= $cItem['source'] ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-4">
                                                        <div class="w-12 h-12 bg-gray-50 rounded-lg border border-gray-100 shrink-0 p-1.5">
                                                            <img src="<?= htmlspecialchars($cItem['image']) ?>" class="object-contain w-full h-full">
                                                        </div>
                                                        <div class="min-w-0">
                                                            <h4 class="text-sm font-bold text-gray-900 leading-tight truncate">
                                                                <?= htmlspecialchars($cItem['name']) ?>
                                                            </h4>
                                                            <p class="text-[9px] font-bold text-gray-500 uppercase tracking-tight mt-0.5">
                                                                <?= htmlspecialchars($cItem['variant']) ?> &bull; <?= $srcLabel ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center justify-center">
                                                        <div class="flex items-center bg-gray-50 rounded-lg p-1 border border-gray-100 h-8">
                                                            <button onclick="updateCartItem(<?= $cItem['cart_id'] ?>, <?= $cItem['qty'] - 1 ?>, <?= $cItem['available_stock'] ?>)"
                                                                class="w-6 h-6 flex items-center justify-center text-xs font-bold hover:bg-white hover:shadow-sm rounded transition-all cursor-pointer">-</button>
                                                            <span class="px-3 text-xs font-black text-gray-800"><?= $cItem['qty'] ?></span>
                                                            <button onclick="updateCartItem(<?= $cItem['cart_id'] ?>, <?= $cItem['qty'] + 1 ?>, <?= $cItem['available_stock'] ?>)"
                                                                class="w-6 h-6 flex items-center justify-center text-xs font-bold hover:bg-white hover:shadow-sm rounded transition-all cursor-pointer">+</button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <span class="text-sm font-black text-gray-900">₱<?= number_format($itemTotal, 2) ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <button onclick="removeCartItem(<?= $cItem['cart_id'] ?>)"
                                                        class="text-gray-300 hover:text-red-500 transition-colors cursor-pointer group-hover:scale-110 active:scale-95">
                                                        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($cartItems)): ?>
                            <div class="mt-4 pt-6 border-t border-gray-100 space-y-4">
                                <div class="flex justify-between items-center px-1">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Estimated
                                        Total</span>
                                    <span
                                        class="text-xl font-bold text-red-600">₱<?= number_format($cartSubtotal ?? 0, 2) ?></span>
                                </div>

                                <div class="px-1">
                                    <button type="button" onclick="openProceedModal('reviewCartModal')"
                                        class="group w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl transition-all shadow-lg shadow-red-100 active:scale-[0.98] flex items-center justify-center gap-3">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.2em]">Process
                                            Checkout</span>
                                        <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3"
                                                d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                        </svg>
                                    </button>

                                    <p
                                        class="text-center text-[9px] font-bold text-gray-400 uppercase tracking-tighter mt-4 leading-relaxed">
                                        By processing, you are updating the <br>
                                        <span class="text-gray-900 italic">Prime-In-Sync Inventory Ledger</span>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Order Proceed Modal -->
                    <div id="reviewCartModal"
                        class="fixed inset-0 z-100 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
                        <div class="absolute inset-0 bg-black/60"></div>

                        <div
                            class="modal-box relative bg-white w-full max-w-5xl h-[85vh] rounded-2xl shadow-xl overflow-hidden flex font-sans border border-gray-200">
                            <div class="flex-1 flex flex-col bg-white overflow-hidden">
                                <div class="p-8 border-b border-gray-100 flex justify-between items-center">
                                    <div>
                                        <h2 class="text-2xl font-bold text-gray-900">Finalize Transaction</h2>
                                        <p class="text-sm text-gray-500">Order Processing & Billing</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <span id="customerBadge"
                                            class="px-3 py-1 text-xs font-medium bg-blue-50 text-blue-600 rounded-full">New
                                            Account</span>
                                        <button onclick="closeModalWithCheck('reviewCartModal', 'clientName')"
                                            class="p-2 text-gray-400 hover:text-black transition-colors rounded-xl hover:bg-gray-50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M6 18L18 6M6 6l12 12" stroke-width="2.5" stroke-linecap="round"
                                                    stroke-linejoin="round"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex-1 overflow-y-auto p-8 space-y-8 custom-scrollbar">

                                    <section class="space-y-3">
                                        <div class="flex items-center justify-between px-2">
                                            <h3 class="text-[11px] font-black text-gray-400 uppercase tracking-[0.2em]">
                                                Current Order</h3>
                                            <span id="summaryItemCount"
                                                class="bg-black text-white text-[10px] px-3 py-1 rounded-full font-black italic">0
                                                Items</span>
                                        </div>

                                        <div
                                            class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                                            <table class="w-full text-left border-collapse">
                                                <thead>
                                                    <tr class="bg-gray-50 border-b border-gray-100">
                                                        <th
                                                            class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase">
                                                            Source</th>
                                                        <th
                                                            class="px-6 py-3 text-[10px] font-black text-gray-400 uppercase">
                                                            Product Details</th>
                                                        <th
                                                            class="px-6 py-3 text-center text-[10px] font-black text-gray-400 uppercase">
                                                            Qty</th>
                                                        <th
                                                            class="px-6 py-3 text-right text-[10px] font-black text-gray-400 uppercase">
                                                            Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="summaryTableBody" class="divide-y divide-gray-100">
                                                    <!-- Dynamic Content -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>

                                    <section class="space-y-4 border-t border-gray-100 pt-6">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Client
                                                Information</h3>
                                        </div>

                                        <div class="grid grid-cols-3 gap-4">
                                            <div class="col-span-2 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Full Name /
                                                    Authorized Person</label>
                                                <div class="relative">
                                                    <input type="text" id="clientName" autocomplete="off"
                                                        oninput="handleCustomerSearch(this.value)"
                                                        placeholder="Enter name..."
                                                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-semibold">

                                                    <!-- Suggestions Dropdown -->
                                                    <div id="customerSuggestions"
                                                        class="absolute z-50 left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden hidden max-h-48 overflow-y-auto custom-scrollbar">
                                                        <!-- suggestions appended here -->
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-span-1 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Client Type</label>
                                                <div class="relative">
                                                    <select id="clientType" onchange="toggleGovFields(this.value)"
                                                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none appearance-none cursor-pointer font-bold text-blue-600 transition-all">
                                                        <option value="private">Private / Individual</option>
                                                        <option value="government">Government</option>
                                                    </select>
                                                    <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-blue-400">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path d="M19 9l-7 7-7-7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                            <div id="govDeptSection"
                                                class="col-span-3 hidden animate-in fade-in slide-in-from-top-1">
                                                <label class="text-xs font-semibold text-blue-600 ml-1">Government
                                                    Department Name</label>
                                                <input type="text" id="govBranch" placeholder="e.g. Department of Education"
                                                    class="w-full mt-1 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>

                                            <div class="col-span-2 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Contact
                                                    Number</label>
                                                <input type="text" id="clientContact" placeholder="09XX XXX XXXX"
                                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                                            </div>

                                            <div class="col-span-1 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Discount
                                                    (%)</label>
                                                <div class="relative">
                                                    <input type="number" id="adminDiscount" placeholder="0"
                                                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none pr-8 font-bold">
                                                    <span
                                                        class="absolute right-4 top-3 text-gray-400 font-bold">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </section>

                                    <section class="space-y-4 border-t border-gray-100">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Shipping
                                            Details</h3>
                                        <div class="grid grid-cols-1 gap-4">
                                            <div class="space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Shipping
                                                    Mode</label>
                                                <select id="shippingMode" onchange="toggleAddress(this.value)"
                                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none transition-all cursor-pointer">
                                                    <option value="pickup">Store Pickup</option>
                                                    <option value="delivery">Delivery Service</option>
                                                </select>
                                            </div>

                                            <div id="deliveryAddressSection"
                                                class="hidden animate-in fade-in slide-in-from-top-2">
                                                <label class="text-xs font-semibold text-orange-600 ml-1">Exact Delivery
                                                    Address</label>
                                                <textarea id="deliveryAddress" rows="2" placeholder="House/Bldg No., Street, Brgy, City..."
                                                    class="w-full mt-1 bg-orange-50/30 border border-orange-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none resize-none font-medium"></textarea>
                                            </div>
                                        </div>
                                    </section>

                                    <section class="space-y-4 border-t border-gray-100">
                                        <div class="flex items-center justify-between px-1">
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Payment
                                                Configuration</h3>
                                            <span id="typeBadge"
                                                class="text-[9px] font-black px-2 py-0.5 rounded bg-blue-100 text-blue-600 uppercase">Standard
                                                Sale</span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div class="col-span-2 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Transaction
                                                    Type</label>
                                                <div class="relative">
                                                    <select id="transactionType"
                                                        onchange="toggleInstallmentView(this.value)"
                                                        class="w-full bg-gray-900 text-white border border-gray-800 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none appearance-none cursor-pointer font-bold">
                                                        <option value="full">💰 Full Payment (One-time)</option>
                                                        <option value="installment">⏳ Installment Plan
                                                            (Partial/Downpayment)</option>
                                                    </select>
                                                    <div
                                                        class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path d="M19 9l-7 7-7-7" stroke-width="2"
                                                                stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Installment Specific Settings -->
                                            <div id="installmentSettings"
                                                class="col-span-2 hidden grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-2">
                                                <div class="space-y-1.5">
                                                    <label class="text-xs font-semibold text-blue-600 ml-1">Interest
                                                        Rate (%)</label>
                                                    <div class="relative">
                                                        <input type="number" id="interestRate" value="0"
                                                            oninput="calculateInstallment()"
                                                            class="w-full bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                                                        <span
                                                            class="absolute right-4 top-3.5 text-[10px] font-black text-blue-400">%</span>
                                                    </div>
                                                </div>
                                                <div class="space-y-1.5">
                                                    <label class="text-xs font-semibold text-blue-600 ml-1">Installment
                                                        Term</label>
                                                    <select id="installmentTerm"
                                                        class="w-full bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-bold">
                                                        <option value="3">3 Months</option>
                                                        <option value="6">6 Months</option>
                                                        <option value="12">12 Months</option>
                                                        <option value="24">24 Months</option>
                                                    </select>
                                                </div>
                                                <div class="col-span-2 bg-blue-600/5 border border-blue-100 p-4 rounded-xl flex justify-between items-center">
                                                    <div>
                                                        <p class="text-[10px] font-black text-blue-600 uppercase">Total with Interest</p>
                                                        <h4 id="totalWithInterest" class="text-xl font-black text-blue-700">₱0.00</h4>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-[10px] font-black text-gray-400 uppercase">Monthly Amortization</p>
                                                        <h4 id="monthlyAmort" class="text-sm font-bold text-gray-600">₱0.00 / mo</h4>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-span-2 md:col-span-1 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Payment
                                                    Method</label>
                                                <select id="paymentMethod"
                                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-red-500 outline-none font-semibold text-gray-900">
                                                    <option value="cash">💵 Cash Payment</option>
                                                    <option value="gcash">📱 GCash</option>
                                                    <option value="maya">💳 Maya (formerly PayMaya)</option>
                                                    <option value="bdo">🏦 Bank Transfer - BDO</option>
                                                    <option value="bpi">🏦 Bank Transfer - BPI</option>
                                                    <option value="check">✍️ Check Payment</option>
                                                    <option value="card">💳 Credit / Debit Card</option>
                                                </select>
                                            </div>

                                            <div class="col-span-2 md:col-span-1 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Reference
                                                    No.</label>
                                                <input type="text" id="paymentRef" placeholder="Ref ID / Trace #"
                                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-medium">
                                            </div>

                                            <div class="col-span-2 space-y-1.5">
                                                <label id="amountLabel"
                                                    class="text-xs font-semibold text-gray-700 ml-1">Initial / Downpayment</label>
                                                <div class="relative">
                                                    <span
                                                        class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-blue-600">₱</span>
                                                    <input type="number" id="amountPaid" oninput="calculateInstallment()"
                                                        class="w-full bg-blue-50 border border-blue-100 rounded-xl pl-8 pr-4 py-4 text-lg focus:ring-2 focus:ring-blue-500 outline-none font-black text-blue-700">
                                                </div>
                                            </div>

                                            <div class="col-span-2 space-y-1.5">
                                                <label class="text-xs font-semibold text-gray-700 ml-1">Payment Remarks / Bank Details</label>
                                                <textarea id="paymentRemarks" rows="2" placeholder="e.g. Paid via BDO Transfer, Check No. XXXX"
                                                    class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-gray-300 outline-none font-medium resize-none"></textarea>
                                            </div>

                                            <div id="balanceAlert"
                                                class="col-span-2 hidden bg-orange-50 border-l-4 border-orange-400 p-4 rounded-r-xl">
                                                <div class="flex justify-between items-center">
                                                    <p class="text-xs font-bold text-orange-700 uppercase">Projected
                                                        Balance:</p>
                                                    <p id="calcBalance" class="text-lg font-black text-orange-800">₱
                                                        0.00</p>
                                                </div>
                                            </div>
                                        </div>
                                    </section>
                                </div>

                                <div class="p-8 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                                    <div>
                                        <p class="text-xs font-bold text-gray-500 uppercase">Grand Total</p>
                                        <h1 id="summaryGrandTotal" class="text-3xl font-bold text-gray-900">₱0.00</h1>
                                    </div>
                                    <button
                                        id="completeSaleBtn"
                                        onclick="completeSale()"
                                        class="bg-red-600 text-white px-10 py-4 rounded-xl font-bold text-sm hover:bg-red-700 transition-all shadow-lg shadow-red-200">
                                        Complete Sale
                                    </button>
                                </div>
                            </div>

                            <div class="w-80 bg-gray-50 border-l border-gray-100 flex flex-col shrink-0 overflow-hidden">
                                <div class="p-6 text-center border-b border-gray-200 bg-white">
                                    <div
                                        class="w-12 h-12 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                stroke-width="2"></path>
                                        </svg>
                                    </div>
                                    <h2 id="historyClientName" class="text-lg font-bold text-gray-900 truncate">New
                                        Client</h2>
                                    <p id="historyClientType" class="text-xs text-gray-500 uppercase tracking-tight">Customer Profile</p>
                                </div>

                                <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                                    <div class="flex items-center justify-between mb-4 px-1">
                                        <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Previous Transactions</h3>
                                        <span id="historyCount" class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded">0</span>
                                    </div>

                                    <div id="customerHistoryList" class="space-y-3">
                                        <!-- Placeholder message -->
                                        <div class="text-center py-10">
                                            <div class="bg-gray-100 w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                            </div>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase">No history found</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
                <!-- end of tab2 -->

            </div>
        </div>

    </div>

    <!-- Custom Alert Modal -->
    <div id="customAlertModal"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/40 backdrop"></div>
        <div
            class="modal-box relative bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden flex flex-col transition-all duration-300 font-sans p-6 text-center">
            <div
                class="mx-auto flex items-center justify-center h-14 w-14 rounded-full bg-red-50 mb-4 ring-8 ring-red-50/50">
                <svg class="h-7 w-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Notice</h3>
            <div class="mt-2 text-sm font-medium text-gray-500 mb-6" id="customAlertMessage">
                Message goes here.
            </div>
            <button onclick="closeModal('customAlertModal')"
                class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-3.5 bg-red-600 text-sm font-bold text-white hover:bg-red-700 transition-colors">
                Okay, got it!
            </button>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="customConfirmModal"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/40 backdrop"></div>
        <div
            class="modal-box relative bg-white w-full max-w-sm rounded-3xl shadow-2xl overflow-hidden flex flex-col transition-all duration-300 font-sans p-6 text-center border border-gray-100">
            <div
                class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-50 mb-4 ring-8 ring-red-50/50">
                <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Confirmation Required</h3>
            <div class="mt-2 text-sm font-medium text-gray-500 mb-8" id="customConfirmMessage">
                Are you sure you want to proceed?
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal('customConfirmModal')"
                    class="flex-1 inline-flex justify-center rounded-xl border border-gray-200 shadow-sm px-4 py-3.5 bg-white text-sm font-bold text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-all uppercase tracking-widest text-[10px]">
                    Cancel
                </button>
                <button id="customConfirmBtn"
                    class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-3.5 bg-red-600 text-sm font-bold text-white hover:bg-red-700 transition-all uppercase tracking-widest text-[10px]">
                    Proceed
                </button>
            </div>
        </div>
    </div>



    <!-- Discard Changes Modal -->
    <div id="discardModal"
        class="fixed inset-0 z-[1000] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="modal-box relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden flex flex-col transition-all duration-300 font-sans p-8 text-center border border-gray-100">
            <div class="w-16 h-16 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-orange-50/50">
                <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Unsaved Changes</h3>
            <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">Mayroon kang mga sinimulang ilagay. Sigurado ka ba na gusto mong i-discard ang mga changes na ito?</p>
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('discardModal')"
                    class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Keep Editing</button>
                <button type="button" id="confirmDiscardBtn"
                    class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
            </div>
        </div>
    </div>

    <?php include '../include/toast.php'; ?>
</body>

</html>