<?php

declare(strict_types=1);

require_once '../include/config.php';
require_once '../include/dbh.inc.php';
require_once '../include/inc.showroom/sr.model.php';
require_once '../include/inc.warehouse/wh.model.php';

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
    <link rel="icon" type="image/x-icon" href="../../public/assets/img/primeLogo.ico">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js" defer></script>

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
            <a href="../-warehouse/dashboard-page.php" class="flex items-center gap-4">
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

            <button
                class="flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                </svg>
            </button>

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

    <!-- Smart Stock Alerts -->
    <?php
    $whAlerts = [];
    $srAlerts = [];
    try {
        $wh_sql = "SELECT p.name as prod_name, pv.variant as variant_name, COALESCE(pv.variant_image, p.default_image) as img, ws.qty_on_hand, pv.min_buildable_qty
                   FROM warehouse_stocks ws
                   JOIN product_variant pv ON ws.variant_id = pv.id
                   JOIN products p ON pv.prod_id = p.id
                   WHERE ws.qty_on_hand <= pv.min_buildable_qty
                   ORDER BY ws.last_update DESC";
        $whStmt = $pdo->query($wh_sql);
        $whAlerts = $whStmt->fetchAll(PDO::FETCH_ASSOC);

        $sr_sql = "SELECT p.name as prod_name, pv.variant as variant_name, COALESCE(pv.variant_image, p.default_image) as img, ss.qty_on_hand, ss.min_display_qty
                   FROM showroom_stocks ss
                   JOIN product_variant pv ON ss.variant_id = pv.id
                   JOIN products p ON pv.prod_id = p.id
                   WHERE ss.qty_on_hand <= ss.min_display_qty
                   ORDER BY ss.last_update DESC";
        $srStmt = $pdo->query($sr_sql);
        $srAlerts = $srStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Alert Query Error: " . $e->getMessage());
    }
    $hasAlerts = count($whAlerts) > 0 || count($srAlerts) > 0;

    // Fetch Dashboard Stats
    $totalProducts = 0;
    $userTransactions = 0;
    $pendingWH = 0;
    $pendingSR = 0;

    try {
        // 1. Available Products (Count of unique base products with warehouse stock > 0)
        $totalProducts = $pdo->query("SELECT COUNT(DISTINCT p.id) 
                                    FROM products p
                                    JOIN product_variant pv ON p.id = pv.prod_id
                                    JOIN warehouse_stocks ws ON pv.id = ws.variant_id
                                    WHERE ws.qty_on_hand > 0 AND p.is_deleted = 0")->fetchColumn();

        // 2. Your Transactions (Orders created by current user)
        $stmt_user = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_by = ?");
        $stmt_user->execute([$userId]);
        $userTransactions = $stmt_user->fetchColumn();

        $pendingWH = $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                                JOIN order_items oi ON o.id = oi.order_id 
                                WHERE o.status = 'approved' AND (oi.get_from = 'WH' OR oi.get_from = 'Warehouse')")->fetchColumn();

        $pendingSR = $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                                JOIN order_items oi ON o.id = oi.order_id 
                                WHERE o.status = 'approved' AND (oi.get_from = 'SR' OR oi.get_from = 'Showroom')")->fetchColumn();
    } catch (PDOException $e) {
        error_log("Stats Fetch Error: " . $e->getMessage());
    }

    // New Warehouse Specific Data
    $whRequests = get_pending_warehouse_requests($pdo);
    $whStockOverview = get_warehouse_stock_overview($pdo);
    $whHealth = get_warehouse_health_stats($pdo);
    ?>

    <?php if ($hasAlerts): ?>
        <div class="flex flex-col gap-4 px-6 bg-white container">
            <?php if (count($whAlerts) > 0): ?>
                <!-- Warehouse Alert -->
                <div class="w-full bg-yellow-50 border border-red-300 rounded-xl shadow-md p-6 flex flex-col gap-4">
                    <div class="flex flex-row gap-3">
                        <div
                            class="flex items-center justify-center bg-yellow-100 text-yellow-700 rounded-full w-12 h-12 text-xl font-bold">
                            ⚠️
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-yellow-800">Smart Stock Alert - Warehouse</h2>
                            <p class="text-sm text-yellow-700"><?= count($whAlerts) ?> warehouse
                                product<?= count($whAlerts) > 1 ? 's need' : ' needs' ?> immediate restocking (on/below
                                buildable limit)</p>
                        </div>
                    </div>

                    <div class="flex p-4 flex-wrap gap-4">
                        <?php foreach ($whAlerts as $alert):
                            $imgFile = rawurlencode(trim($alert['img'] ?? 'default-placeholder.png'));
                            $imgPath = "../../public/assets/img/furnitures/" . $imgFile;
                            ?>
                            <div class="flex items-center gap-4 border p-2 card-style bg-white w-fit pr-4 h-[84px]">
                                <!-- Product Image -->
                                <div
                                    class="w-16 h-16 bg-white border border-gray-300 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($alert['prod_name']) ?>"
                                        class="object-contain h-full w-full" />
                                </div>
                                <!-- Product Info -->
                                <div class="flex flex-col justify-center">
                                    <h3 class="text-md font-semibold text-gray-800 line-clamp-1"
                                        title="<?= htmlspecialchars($alert['prod_name'] . ' - ' . $alert['variant_name']) ?>">
                                        <?= htmlspecialchars($alert['prod_name']) ?> <span
                                            class="font-normal text-sm text-gray-500">(<?= htmlspecialchars($alert['variant_name']) ?>)</span>
                                    </h3>
                                    <p class="text-sm text-red-600 font-semibold mt-1">Only <?= (int) $alert['qty_on_hand'] ?>
                                        unit<?= $alert['qty_on_hand'] == 1 ? '' : 's' ?> left</p>
                                    <p class="text-xs text-gray-500">Min Buildable: <?= (int) $alert['min_buildable_qty'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($srAlerts) > 0): ?>
                <!-- Showroom Alert -->
                <div class="w-full bg-yellow-50 border border-red-300 rounded-xl shadow-md p-6 flex flex-col gap-4">
                    <div class="flex flex-row gap-3">
                        <div
                            class="flex items-center justify-center bg-yellow-100 text-yellow-700 rounded-full w-12 h-12 text-xl font-bold">
                            ⚠️
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-yellow-800">Smart Stock Alert - Showroom</h2>
                            <p class="text-sm text-yellow-700"><?= count($srAlerts) ?> showroom
                                product<?= count($srAlerts) > 1 ? 's need' : ' needs' ?> immediate restocking (on/below display
                                limit)</p>
                        </div>
                    </div>

                    <div class="flex p-4 flex-wrap gap-4">
                        <?php foreach ($srAlerts as $alert):
                            $imgFile = rawurlencode(trim($alert['img'] ?? 'default-placeholder.png'));
                            $imgPath = "../../public/assets/img/furnitures/" . $imgFile;
                            ?>
                            <div class="flex items-center gap-4 border p-2 card-style bg-white w-fit pr-4 h-[84px]">
                                <!-- Product Image -->
                                <div
                                    class="w-16 h-16 bg-white border border-gray-300 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($alert['prod_name']) ?>"
                                        class="object-contain h-full w-full" />
                                </div>
                                <!-- Product Info -->
                                <div class="flex flex-col justify-center">
                                    <h3 class="text-md font-semibold text-gray-800 line-clamp-1"
                                        title="<?= htmlspecialchars($alert['prod_name'] . ' - ' . $alert['variant_name']) ?>">
                                        <?= htmlspecialchars($alert['prod_name']) ?> <span
                                            class="font-normal text-sm text-gray-500">(<?= htmlspecialchars($alert['variant_name']) ?>)</span>
                                    </h3>
                                    <p class="text-sm text-red-600 font-semibold mt-1">Only <?= (int) $alert['qty_on_hand'] ?>
                                        unit<?= $alert['qty_on_hand'] == 1 ? '' : 's' ?> left</p>
                                    <p class="text-xs text-gray-500">Min Display: <?= (int) $alert['min_display_qty'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="px-6 py-4">
        <div class="grid grid-cols-[repeat(4,300px)] justify-center gap-5">
            <!-- Card 1 -->
            <a href="inventory.php"
                class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6 hover:border-red-500 hover:shadow-lg transition-all group">
                <div class="text-sm uppercase tracking-wide text-gray-500 group-hover:text-red-500 transition-colors">
                    Available Products</div>
                <div class="text-4xl font-bold text-gray-800"><?= (int) $totalProducts ?></div>
                <div class="text-sm text-gray-600">Total base furniture items.</div>
            </a>

            <!-- Card 2 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Your Transactions</div>
                <div class="text-4xl font-bold text-gray-800"><?= (int) $userTransactions ?></div>
                <div class="text-sm text-gray-600">Total orders you processed.</div>
            </div>

            <!-- Card 3 -->
            <a href="order-fulfilment-page.php"
                class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6 hover:border-red-500 hover:shadow-lg transition-all group">
                <div class="text-sm uppercase tracking-wide text-gray-500 group-hover:text-red-500 transition-colors">
                    Pending Warehouse</div>
                <div class="text-4xl font-bold text-red-600"><?= (int) $pendingWH ?></div>
                <div class="text-sm text-gray-600">Current pending WH requests.</div>
            </a>

            <!-- Card 4 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Pending Showroom</div>
                <div class="text-4xl font-bold text-red-600"><?= (int) $pendingSR ?></div>
                <div class="text-sm text-gray-600">Current pending SR requests.</div>
            </div>
        </div>
    </section>

    <nav class="px-5 flex justify-center">
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
                    <a href="product-status.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 
                     4.242 0 1.172 1.025 1.172 2.687 0 
                     3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 
                     1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 
                     1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>Product Status</span>
                    </a>
                </li>

            </ul>
        </div>
    </nav>

    <section class="px-[100px] py-4">
        <div class="border border-gray-300 rounded-2xl p-8 w-full bg-white">
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
                    class="card-style flex flex-col rounded-xl border border-gray-200 h-[400px] bg-white overflow-hidden">
                    <div class="px-6 pt-6 pb-4">
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
                                    $isLow = (int) $stock['total_qty'] <= 5;
                                    ?>
                                    <div
                                        class="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg hover:border-red-300 transition-all">
                                        <div class="w-10 h-10 rounded-lg overflow-hidden border border-gray-200 shrink-0">
                                            <img src="<?= htmlspecialchars($imgPath) ?>" alt="Product"
                                                class="w-full h-full object-cover"
                                                onerror="this.src='../../public/assets/img/primeLogo.ico'">
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-sm truncate"><?= htmlspecialchars($stock['name']) ?></p>
                                            <p class="text-[10px] text-gray-500"><?= htmlspecialchars($stock['variant']) ?> •
                                                Loc: <?= htmlspecialchars($stock['location'] ?? 'N/A') ?></p>
                                        </div>
                                        <span
                                            class="px-2 py-0.5 text-xs font-semibold border rounded <?= $isLow ? 'bg-red-50 text-red-600 border-red-200' : 'bg-white border-gray-300' ?>">
                                            <?= (int) $stock['total_qty'] ?>
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

</body>

</html>