<?php
declare(strict_types=1);

require_once "../include/config.php";
require_once "../include/dbh.inc.php";
require_once "../include/inc.showroom/sr.model.php";

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

$transactions = [];
try {
    $sql = "SELECT 
                o.id as order_id, 
                o.status, 
                o.created_at, 
                COALESCE(c.name, o.temp_customer_name) AS customer_name,
                c.client_type,
                c.gov_branch,
                u.full_name as processor,
                p.name as product_name, 
                pv.variant, 
                COALESCE(pv.variant_image, p.default_image) as img,
                oi.qty
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN users u ON o.created_by = u.id
            JOIN product_variant pv ON oi.variant_id = pv.id
            JOIN products p ON pv.prod_id = p.id
            WHERE oi.get_from = 'WH' OR oi.get_from = 'Warehouse'
            ORDER BY o.created_at DESC
            LIMIT 20";
    $stmt = $pdo->query($sql);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dashboard Stats for Product Status Page
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_deleted = 0")->fetchColumn();
    
    $stmt_user = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_by = ?");
    $stmt_user->execute([$userId]);
    $userTransactions = $stmt_user->fetchColumn();

    $pendingWH = $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                             JOIN order_items oi ON o.id = oi.order_id 
                             WHERE o.status = 'pending' AND (oi.get_from = 'WH' OR oi.get_from = 'Warehouse')")->fetchColumn();

    $pendingSR = $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                             JOIN order_items oi ON o.id = oi.order_id 
                             WHERE o.status = 'pending' AND (oi.get_from = 'SR' OR oi.get_from = 'Showroom')")->fetchColumn();
} catch (PDOException $e) {
    error_log("Warehouse Transaction Error: " . $e->getMessage());
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

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-25">
    <header
        class="sticky top-0 z-40 flex h-25 items-center justify-between border-b border-gray-200 px-6 bg-white container">

        <div class="flex container">
            <a href="../-warehouse/dashboard-page.php" class="flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/primeLogo.ico" alt="Prime Concept Logo"
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
            <div class="rounded-md bg-red-100 px-3 py-1 text-sm text-red-600 font-medium"><?= htmlspecialchars(
                                                                                                ucfirst($role),
                                                                                            ) ?> User</div>

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

    <section class="px-6 py-4">
        <div class="grid grid-cols-[repeat(4,300px)] justify-center gap-5">
            <!-- Card 1 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Available Products</div>
                <div class="text-4xl font-bold text-gray-800"><?= (int)$totalProducts ?></div>
                <div class="text-sm text-gray-600">Total base furniture items.</div>
            </div>

            <!-- Card 2 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Your Transactions</div>
                <div class="text-4xl font-bold text-gray-800"><?= (int)$userTransactions ?></div>
                <div class="text-sm text-gray-600">Total orders you processed.</div>
            </div>

            <!-- Card 3 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Pending Warehouse</div>
                <div class="text-4xl font-bold text-red-600"><?= (int)$pendingWH ?></div>
                <div class="text-sm text-gray-600">Current pending WH requests.</div>
            </div>

            <!-- Card 4 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Pending Showroom</div>
                <div class="text-4xl font-bold text-red-600"><?= (int)$pendingSR ?></div>
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
                        <svg class="w-5 h-5 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
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
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
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
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor">
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


    <section class="px-25 py-4 ">
        <div class="card-look p-12">
            <h2 class="text-2xl font-semibold mb-2">Transaction Timeline</h2>
            <p>Recent warehouse product transactions and movements</p>
            <div class="bg-gray-50 flex flex-col items-center gap-5 mt-6 border-t border-gray-200 pt-8" style="min-height: 50vh;">
                <?php if (empty($transactions)): ?>
                    <p class="text-gray-500 text-lg py-12">No recent warehouse transactions found.</p>
                <?php else: ?>
                    <?php foreach ($transactions as $txn): 
                        $statusClass = 'bg-gray-100 text-gray-800 border-gray-200';
                        $status = strtolower($txn['status']);
                        if (in_array($status, ['pending', 'processing'])) {
                            $statusClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                        } elseif (in_array($status, ['approved', 'completed', 'delivered'])) {
                            $statusClass = 'bg-green-100 text-green-800 border-green-200';
                        } elseif (in_array($status, ['declined', 'cancelled'])) {
                            $statusClass = 'bg-red-100 text-red-800 border-red-200';
                        }
                        
                        $imgFile = rawurlencode(trim($txn['img'] ?? 'default-placeholder.png'));
                        $imgPath = "../../public/assets/img/furnitures/" . $imgFile;
                    ?>
                    <div class="bg-white rounded-2xl shadow-sm p-6 w-full max-w-3xl flex justify-between font-sans border border-gray-200 hover:shadow-md transition">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center gap-3">
                                <span class="<?= $statusClass ?> px-3 py-1 rounded-full text-xs font-bold border uppercase tracking-wider">
                                    <?= htmlspecialchars($txn['status']) ?>
                                </span>
                                <span class="text-gray-400 text-sm font-semibold tracking-wider">ORDER #<?= htmlspecialchars((string)$txn['order_id']) ?></span>
                            </div>

                            <div class="flex gap-4 mt-2">
                                <div class="size-16 rounded-xl bg-gray-50 p-2 flex items-center justify-center border border-gray-100">
                                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($txn['product_name']) ?>" class="object-contain h-full w-full mix-blend-multiply" />
                                </div>

                                <div class="flex flex-col justify-center">
                                    <h3 class="font-bold text-gray-900 text-lg leading-tight">
                                        <?= htmlspecialchars($txn['product_name']) ?> <span class="font-normal text-sm text-gray-500 ml-1">(<?= htmlspecialchars($txn['variant']) ?>)</span>
                                    </h3>
                                    <p class="text-gray-500 text-sm mt-1 font-medium">Quantity: <?= (int)$txn['qty'] ?> unit<?= (int)$txn['qty'] > 1 ? 's' : '' ?></p>
                                </div>
                            </div>

                            <div class="text-gray-600 text-sm mt-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
                                <p>Customer: <span class="font-semibold text-gray-800"><?= htmlspecialchars($txn['customer_name'] ?? 'Unknown') ?></span></p>
                                <p>Processed by: <span class="font-semibold text-gray-800"><?= htmlspecialchars($txn['processor'] ?? 'System') ?></span></p>
                            </div>

                            <?php if (!empty($txn['client_type'])): ?>
                            <div class="mt-3 flex items-center gap-2">
                                <span class="border border-blue-200 bg-blue-50 text-blue-700 px-3 py-1 rounded-lg text-xs font-bold tracking-wide uppercase shadow-sm">
                                    <?= htmlspecialchars($txn['client_type']) ?>
                                </span>
                                <?php if (!empty($txn['gov_branch'])): ?>
                                <span class="text-gray-500 text-xs font-medium bg-gray-100 px-2 py-1 rounded border border-gray-200">
                                    <?= htmlspecialchars($txn['gov_branch']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col justify-between items-end">
                            <div class="flex items-center gap-2 text-gray-500 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" />
                                    <polyline points="12 6 12 12 16 14" />
                                </svg>
                                <span class="text-xs font-bold tracking-wide"><?= date('m/d/Y h:i A', strtotime($txn['created_at'])) ?></span>
                            </div>

                            <div class="text-right">
                                <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest">Units Moved</p>
                                <p class="text-3xl font-extrabold text-gray-900 leading-none mt-1"><?= (int)$txn['qty'] ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
    </section>
</body>

</html>