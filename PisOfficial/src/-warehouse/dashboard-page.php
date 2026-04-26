<?php

declare(strict_types=1);

require_once '../include/config.php';
require_once '../include/dbh.inc.php';
require_once '../include/inc.showroom/sr.model.php';
require_once '../include/inc.warehouse/wh.model.php';
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
    <link rel="icon" type="image/png" href="../../public/assets/img/favIcon.png">
    <meta name="csrf-token" content="<?= get_csrf_token() ?>">
    <link rel="stylesheet" href="../output.css?v=<?= SYS_VERSION ?>">
    <script src="../../public/assets/js/global.js?v=<?= SYS_VERSION ?>" defer></script>
    <script src="../../public/assets/js/warehouse.js?v=<?= SYS_VERSION ?>" defer></script>

    <style>
        /* Attention Shake Animation */
        @keyframes attention-shake {
            0% { transform: scale(1) rotate(0deg); }
            10% { transform: scale(1.1) rotate(-10deg); }
            20% { transform: scale(1.1) rotate(10deg); }
            30% { transform: scale(1.1) rotate(-10deg); }
            40% { transform: scale(1.1) rotate(10deg); }
            50% { transform: scale(1) rotate(0deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        .animate-attention {
            animation: attention-shake 2s infinite ease-in-out;
        }
        /* Custom Ping Animation for reliability */
        @keyframes custom-ping {
            0% { transform: scale(0.8); opacity: 1; }
            80%, 100% { transform: scale(2.5); opacity: 0; }
        }
        .animate-custom-ping {
            animation: custom-ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
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
        class="sticky top-0 z-40 flex h-[100px] items-center justify-between border-b border-gray-200 px-6 bg-white w-full max-w-7xl mx-auto">

        <div class="flex flex-1">
            <a href="../-warehouse/dashboard-page.php" class="flex items-center gap-4">
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
                <?= htmlspecialchars(ucfirst($role)) ?> User
            </div>

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

    <!-- Warehouse Dashboard Header Section -->
    <?php
    $stats = get_warehouse_dashboard_stats($pdo, (int)$userId);
    $totalProducts = $stats['total_products'];
    $userTransactions = $stats['user_transactions'];
    $pendingWH = $stats['pending_wh'];
    $pendingSR = $stats['pending_sr'];

    $whRequests = get_pending_warehouse_requests($pdo);
    $whStockOverview = get_warehouse_stock_overview($pdo);
    $whHealth = get_warehouse_health_stats($pdo);

    // Fetch alerts for top-level card badges
    $alerts = get_warehouse_stock_alerts($pdo);
    $oosCount = count($alerts['out_of_stock']);
    $lowCount = count($alerts['low_stock']);
    ?>

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
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
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
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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
            <h2 class="text-2xl font-semibold mb-2">Warehouse Overview</h2>
            <p class="text-gray-600">Monitor fulfillment queue, inventory levels, and warehouse operations.</p>

            <div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div data-slot="card"
                    class="card-style flex flex-col rounded-xl border border-gray-200 h-[400px] bg-white overflow-hidden">
                    <div class="px-6 pt-6 pb-4">
                        <h4 class="font-semibold flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="text-gray-700">
                                <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2">
                                </path>
                                <path d="m9 14 2 2 4-4"></path>
                            </svg>
                            Order Fulfillment Queue
                        </h4>
                        <p class="text-xs text-gray-500 mt-1">Approved orders waiting for processing</p>
                    </div>
                    <div class="px-6 pb-6 overflow-y-auto flex-1">
                        <div class="space-y-3">
                            <?php if (empty($whRequests)): ?>
                                <p class="text-xs text-gray-400 text-center py-4">No pending requests found.</p>
                            <?php else: ?>
                                <?php foreach ($whRequests as $req): ?>
                                    <div
                                        class="flex items-center gap-3 p-3 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition-colors">
                                        <div
                                            class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center text-red-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path
                                                    d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z">
                                                </path>
                                                <path d="M12 22V12"></path>
                                                <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-sm truncate text-gray-900">
                                                <?= $req['requester_role'] === 'admin' ? 'Admin' : 'Lobby' ?>
                                            </p>
                                            <p class="text-[10px] text-gray-500">ORD #<?= $req['id'] ?> •
                                                <?= $req['item_count'] ?> item<?= $req['item_count'] === 1 ? '' : 's' ?>
                                            </p>
                                        </div>
                                        <span
                                            class="px-2 py-0.5 text-[9px] font-bold uppercase rounded border border-red-300 text-red-700 bg-white"><?= htmlspecialchars($req['wh_status'] ?: 'Pending') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div data-slot="card"
                    class="card-style flex flex-col rounded-xl border border-gray-200 h-[400px] bg-white relative" style="overflow: visible !important;">
                    
                    <!-- Floating Notification Warning Icon (Upper Right) -->
                    <?php if ($oosCount > 0 || $lowCount > 0): ?>
                        <div class="absolute -top-2 -right-2 z-50">
                            <!-- Outer pulsing ring (Custom Animation) -->
                            <div class="absolute inset-0 rounded-full <?= $oosCount > 0 ? 'bg-red-500' : 'bg-amber-500' ?> animate-custom-ping opacity-75"></div>
                            <!-- Main solid circle with Warning Icon -->
                            <div class="relative flex items-center justify-center size-6 <?= $oosCount > 0 ? 'bg-red-600' : 'bg-amber-500' ?> text-white rounded-full shadow-lg border-2 border-white ring-1 ring-black/5">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                                    <path d="M12 9v4"/>
                                    <path d="M12 17h.01"/>
                                </svg>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="px-6 pt-6 pb-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="text-gray-700">
                                    <path
                                        d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z">
                                    </path>
                                    <path d="M12 22V12"></path>
                                    <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                    <path d="m7.5 4.27 9 5.15"></path>
                                </svg>
                                Inventory Status
                            </h4>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Current warehouse inventory levels</p>
                    </div>
                    <div class="px-6 pb-6 overflow-y-auto flex-1">
                        <div class="space-y-3">
                            <?php if (empty($whStockOverview)): ?>
                                <p class="text-xs text-gray-400 text-center py-4">Inventory is empty.</p>
                            <?php else: ?>
                                <?php foreach ($whStockOverview as $stock):
                                    $img = !empty($stock['variant_image']) ? $stock['variant_image'] : $stock['default_image'];
                                    $encodedImg = rawurlencode(trim($img ?? 'default-placeholder.png'));
                                    $imgPath = "../../public/assets/img/furnitures/" . $encodedImg;
                                    
                                    $qty = (int)$stock['total_qty'];
                                    $min = (int)$stock['min_buildable_qty'];
                                    
                                    $statusClass = "bg-white border-gray-300 text-slate-700";
                                    $statusText = $qty;
                                    
                                    if ($qty === 0) {
                                        $statusClass = "bg-red-600 border-red-600 text-white font-black";
                                        $statusText = "OUT";
                                    } elseif ($qty <= $min) {
                                        $statusClass = "bg-amber-100 border-amber-300 text-amber-700 font-bold";
                                        $statusText = "LOW ($qty)";
                                    }
                                ?>
                                    <div
                                        class="flex items-center gap-3 p-3 bg-gray-50/50 border border-gray-100 rounded-xl hover:bg-white hover:border-red-100 transition-all group">
                                        <div class="w-10 h-10 rounded-lg overflow-hidden border border-gray-200 shrink-0">
                                            <img src="<?= htmlspecialchars($imgPath) ?>" alt="Product"
                                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                                onerror="this.src='../../public/assets/img/favIcon.png'">
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-[13px] text-slate-800 truncate"><?= htmlspecialchars($stock['name']) ?></p>
                                            <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tighter truncate"><?= htmlspecialchars($stock['variant']) ?> •
                                                Loc: <?= htmlspecialchars($stock['location'] ?? 'N/A') ?></p>
                                        </div>
                                        <span
                                            class="px-2 py-0.5 text-[9px] uppercase tracking-tighter border rounded-md <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div data-slot="card"
                    class="card-style flex flex-col rounded-xl border border-gray-200 h-[400px] bg-white overflow-hidden">
                    <div class="px-6 pt-6 pb-4">
                        <h4 class="font-semibold flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="text-gray-700">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                <path d="M18 17V9"></path>
                                <path d="M13 17V5"></path>
                                <path d="M8 17v-3"></path>
                            </svg>
                            Product Status Summary
                        </h4>
                        <p class="text-xs text-gray-500 mt-1">Overview of product performance</p>
                    </div>
                    <div class="px-6 pb-6 flex-1">
                        <div class="space-y-5">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="text-center p-3 bg-green-50 rounded-lg border border-green-100">
                                    <div class="text-2xl font-bold text-green-700"><?= $whHealth['well_stocked'] ?>
                                    </div>
                                    <div class="text-[10px] uppercase font-bold text-green-600">Well Stocked</div>
                                </div>
                                <div class="text-center p-3 bg-orange-50 rounded-lg border border-orange-100">
                                    <div class="text-2xl font-bold text-orange-700"><?= $whHealth['restock'] ?></div>
                                    <div class="text-[10px] uppercase font-bold text-orange-600">Restock</div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-600">Inventory Health</span>
                                    <span class="font-bold"><?= $whHealth['health'] ?>%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="bg-red-600 h-2 rounded-full transition-all duration-500"
                                        style="width: <?= $whHealth['health'] ?>%"></div>
                                </div>
                            </div>
                            <div class="pt-4 border-t border-gray-100 space-y-2">
                                <div class="flex justify-between text-xs"><span class="text-gray-500">Total
                                        Variants</span><span class="font-medium"><?= $whHealth['total'] ?></span></div>
                                <div class="flex justify-between text-xs"><span class="text-gray-500">Total
                                        Stock</span><span class="font-medium"><?= $whHealth['total_units'] ?>
                                        units</span></div>
                                <div class="flex justify-between text-xs"><span class="text-gray-500">Service
                                        Area</span><span class="font-medium">Warehouse</span></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>


    </div>

    <?php include '../include/logout-modal.php'; ?>
</body>

</html>