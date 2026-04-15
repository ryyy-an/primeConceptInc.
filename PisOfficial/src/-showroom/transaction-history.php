<?php

declare(strict_types=1);

require_once '../include/config.php';
require_once '../include/dbh.inc.php';
require_once '../include/global.model.php';

/** @var PDO $pdo */
if (!isset($pdo)) {
    die('Database connection not established.');
}

if (isset($_SESSION['user_id'])) {
    $userId   = (int)$_SESSION['user_id'];
    $username = htmlspecialchars($_SESSION['username']);
    $role     = htmlspecialchars($_SESSION['role']);

    // Fetch total cart items for notification badge
    require_once '../include/global.model.php';
    require_once '../include/inc.showroom/sr.model.php';
    
    $cartItemsCount = count(get_cart_items($pdo, $userId));
    $totalCartItems = $cartItemsCount;

    // Fetch transactions
    $transactions = fetch_sr_transaction_history($pdo, $userId);

    // Fetch counts for the stats cards
    $totalProducts = (int)$pdo->query("SELECT COUNT(DISTINCT p.id) FROM products p JOIN product_variant pv ON p.id = pv.prod_id WHERE p.is_deleted = 0")->fetchColumn();
    
    $totalTransactions = $pdo->prepare("SELECT COUNT(*) FROM transactions t JOIN orders o ON t.order_id = o.id WHERE o.created_by = ?");
    $totalTransactions->execute([$userId]);
    $totalTransactionsCount = (int)$totalTransactions->fetchColumn();

    $pendingRequests = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_by = ? AND status IN ('For Review', 'Pending', 'Approved')");
    $pendingRequests->execute([$userId]);
    $pendingRequestsCount = (int)$pendingRequests->fetchColumn();
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
    <title>Prime-In-Sync | Transaction History</title>
    <link rel="icon" type="image/x-icon" href="../../public/assets/img/primeLogo.ico">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js?v=1.2" defer></script>
    <script src="../../public/assets/js/order.js" defer></script>
    <style>
        html { zoom: 90%; }
    </style>
</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-[100px]">
    <header class="sticky top-0 z-40 flex h-[100px] items-center justify-between border-b border-gray-200 px-6 bg-white container">
        <div class="flex container">
            <a href="#" class="flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/primeLogo.ico" alt="Prime Concept Logo" class="h-full object-contain" />
                </div>
                <div>
                    <h1 class="text-2xl font-semibold text-red-600">Prime-In-Sync</h1>
                    <h4 class="text-base text-gray-500">Welcome, <?= $username ?></h4>
                </div>
            </a>
        </div>

        <div class="flex items-center gap-4 justify-end w-1/2">
            <div class="rounded-md bg-red-100 px-3 py-1 text-sm text-red-600 font-medium">
                <?= ucfirst($role) ?> User
            </div>

            <div class="relative inline-block">
                <button id="notifButton" class="flex items-center justify-center border border-gray-300 size-9 rounded-lg hover:bg-red-100 transition active:scale-95">
                    <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5" />
                    </svg>
                </button>

                <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-2xl z-50 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
                        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-tight">Recent Activity</h3>
                    </div>
                    <div id="notifList" class="overflow-y-auto" style="max-height: 200px;">
                        <div class="px-4 py-6 text-center text-gray-400 text-xs italic">
                            No recent activities found.
                        </div>
                    </div>
                </div>
            </div>

            <a href="javascript:void(0)" onclick="toggleLogoutModal(true)" class="flex items-center gap-2 border border-gray-300 px-4 h-9 rounded-lg hover:bg-red-50 hover:border-red-200 transition group">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                </svg>
                <span class="text-sm text-red-600 font-medium">Logout</span>
            </a>

            <?php include '../include/logout-modal.php'; ?>
        </div>
    </header>

    <section class="px-6 py-4">
        <div class="grid grid-cols-[repeat(3,400px)] justify-center gap-5">
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[180px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Available Products</div>
                <div class="text-4xl font-bold text-gray-800"><?= number_format((float)$totalProducts) ?></div>
                <div class="text-sm text-gray-600">Total products in the catalog.</div>
            </div>

            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[180px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Total Transactions</div>
                <div class="text-4xl font-bold text-gray-800"><?= number_format((float)$totalTransactionsCount) ?></div>
                <div class="text-sm text-gray-600">Total completed transactions recorded.</div>
            </div>

            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[180px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Pending Request</div>
                <div class="text-4xl font-bold text-red-600"><?= number_format((float)$pendingRequestsCount) ?></div>
                <div class="text-sm text-gray-600">Current pending order requests.</div>
            </div>
        </div>
    </section>

    <nav class="px-5 flex justify-center">
        <div class="max-w-7xl w-full">
            <ul class="grid grid-cols-3 bg-gray-100 rounded-3xl h-12 shadow-sm px-5 items-center gap-2">
                <li>
                    <a href="home-page.php" class="relative flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span>Order Products</span>
                        <span id="cart-badge-showroom" class="cart-badge <?= $totalCartItems > 0 ? 'flex' : 'hidden' ?> absolute top-0 right-2 bg-red-600 text-white text-[10px] font-black w-5 h-5 items-center justify-center rounded-full shadow-md border-2 border-white transform translate-x-1/2 -translate-y-1/2 transition-all duration-300">
                            <?= $totalCartItems ?>
                        </span>
                    </a>
                </li>

                <li>
                    <a href="transaction-history.php" class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                        </svg>
                        <span>Transaction History</span>
                    </a>
                </li>

                <li>
                    <a href="order-req-page.php" class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>My Order Requests</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="flex flex-center w-full">
        <div class="border border-gray-300 rounded-2xl p-12 w-[1250px]">
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h2 class="text-2xl font-semibold mb-2">Transaction History</h2>
                    <p class="text-gray-600 mb-6">Browse and manage your transaction records.</p>
                </div>
            </div>

            <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900">
                <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                    <thead class="bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 w-[15%]">Transaction ID</th>
                            <th class="px-4 py-4 w-[18%]">Customer / Type</th>
                            <th class="px-4 py-4 w-[15%]">Branch</th>
                            <th class="px-4 py-4 w-[15%]">Method</th>
                            <th class="px-4 py-4 text-center w-[12%]">Date</th>
                            <th class="px-4 py-4 text-center w-[10%]">Status</th>
                            <th class="px-4 py-4 text-right w-[15%]">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-15 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="size-16 bg-gray-50 rounded-full flex items-center justify-center border border-gray-100">
                                            <svg class="size-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-gray-400 font-medium italic mb-10">No transaction records found.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $trans): ?>
                                <tr class="hover:bg-gray-50/50 transition duration-200">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-black text-gray-900">#<?= htmlspecialchars((string)$trans['trans_id']) ?></span>
                                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-tight">System Record</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($trans['customer_name']) ?></span>
                                            <span class="text-[10px] font-black text-red-500 uppercase"><?= htmlspecialchars($trans['client_type']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="text-xs font-bold text-gray-600"><?= htmlspecialchars($trans['gov_branch'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="size-6 bg-gray-100 rounded flex items-center justify-center">
                                                <svg class="size-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                            </div>
                                            <span class="text-xs font-black text-gray-900 uppercase"><?= htmlspecialchars($trans['method']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="text-xs font-bold text-gray-600"><?= date('M d, Y', strtotime($trans['date'])) ?></span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-tighter border <?= $trans['status'] === 'Success' ? 'bg-green-50 text-green-600 border-green-100' : 'bg-red-50 text-red-600 border-red-100' ?>">
                                            <?= htmlspecialchars($trans['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <span class="text-sm font-black text-gray-900 underline decoration-red-200 underline-offset-4">₱<?= number_format((float)$trans['amount'], 2) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                <div class="text-xs font-black text-gray-400 uppercase tracking-widest">
                    Showing <?= count($transactions) ?> of <?= count($transactions) ?> Results
                </div>
                <div class="flex gap-3">
                    <div class="px-5 py-2 border border-gray-300 rounded-lg text-xs font-black text-gray-400 uppercase tracking-tight opacity-50 cursor-not-allowed">
                        Previous
                    </div>
                    <div class="px-5 py-2 bg-red-600 border border-red-600 rounded-lg text-xs font-black text-white uppercase tracking-tight shadow-md opacity-50 cursor-not-allowed">
                        Next Page
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

</body>

</html>