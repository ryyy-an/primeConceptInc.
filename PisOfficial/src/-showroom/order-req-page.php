<?php

declare(strict_types=1);

require_once '../include/config.php';
require_once '../include/dbh.inc.php';
require_once '../include/inc.showroom/sr.model.php';

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection not established.');
}

if (isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $username = htmlspecialchars($_SESSION['username']);
    $role = htmlspecialchars($_SESSION['role']);

    // Fetch total cart items for notification badge
    require_once '../include/global.model.php';
    $cartItemsCount = count(get_cart_items($pdo, $userId));
    $totalCartItems = $cartItemsCount;

    // --- FETCH ORDER REQUESTS ---
    // User requested to remove filtering and pagination. Showing all active (non-Success) orders.
    $requests = fetch_requests($pdo, $userId, [], 500, 0);


    // --- OPTIMIZED STATS FETCHING (Consolidated) ---
    // Fetch all needed counts in fewer round-trips to the DB
    $statsQuery = $pdo->prepare("
        SELECT 
            (SELECT COUNT(DISTINCT p.id) FROM products p JOIN product_variant pv ON p.id = pv.prod_id WHERE p.is_deleted = 0) as total_products,
            (SELECT COUNT(*) FROM transactions t JOIN orders o ON t.order_id = o.id WHERE o.created_by = :uid1) as total_transactions,
            (SELECT COUNT(*) FROM orders WHERE created_by = :uid2 AND status IN ('For Review', 'Approved')) as pending_requests
    ");
    $statsQuery->execute([':uid1' => $userId, ':uid2' => $userId]);
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

    $totalProducts = $stats['total_products'] ?? 0;
    $totalTransactionsCount = $stats['total_transactions'] ?? 0;
    $pendingRequestsCount = $stats['pending_requests'] ?? 0;
    // --- END OPTIMIZATION ---
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
    <link rel="icon" type="image/png" href="../../public/assets/img/favIcon.png">
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js?v=1.2" defer></script>
    <script src="../../public/assets/js/order.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include '../include/toast.php'; ?>

    <style>
        /* Shrink entire UI by 10% - Removed for native zoom support */
        /* html {
            zoom: 90%;
        } */
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-4 md:px-8">
    <header
        class="sticky top-0 z-40 flex h-[100px] items-center justify-between border-b border-gray-200 px-6 bg-white w-full max-w-7xl mx-auto px-4 md:px-8">

        <div class="flex flex-1">
            <a href="#" class="flex items-center gap-4">
                <div class="h-full w-20">
                    <img src="../../public/assets/img/favIcon.png" alt="Prime Concept Logo"
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

    <section class="max-w-7xl w-full mx-auto px-6 py-4 px-4 md:px-8">
        <div class="grid grid-cols-[repeat(3,400px)] justify-center gap-5">
            <!-- Card 1 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[180px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Available Products</div>
                <div class="text-4xl font-bold text-gray-800"><?= number_format((float) $totalProducts) ?></div>
                <div class="text-sm text-gray-600">Total products in the catalog.</div>
            </div>

            <!-- Card 2 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[180px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Total Transactions</div>
                <div class="text-4xl font-bold text-gray-800"><?= number_format((float) $totalTransactionsCount) ?>
                </div>
                <div class="text-sm text-gray-600">Your completed transactions.</div>
            </div>

            <!-- Card 3 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[180px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Active Request</div>
                <div class="text-4xl font-bold text-red-600"><?= number_format((float) $pendingRequestsCount) ?></div>
                <div class="text-sm text-gray-600">Your active order requests.</div>
            </div>

        </div>
    </section>

    <nav class="px-5 flex justify-center w-full max-w-7xl mx-auto">
        <div class="max-w-7xl w-full">
            <ul class="grid grid-cols-3 bg-gray-100 rounded-3xl h-12 shadow-sm px-5 items-center gap-2">

                <!-- Order Products -->
                <li>
                    <a href="home-page.php"
                        class="relative flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 
                     1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 
                     1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 
                     1 5.513 7.5h12.974c.576 0 1.059.435 1.119 
                     1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 
                     .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 
                     1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <span>Order Products</span>
                        <span id="cart-badge-showroom"
                            class="cart-badge <?= $totalCartItems > 0 ? 'flex' : 'hidden' ?> absolute top-0 right-2 bg-red-600 text-white text-[10px] font-black w-5 h-5 items-center justify-center rounded-full shadow-md border-2 border-white transform translate-x-1/2 -translate-y-1/2 transition-all duration-300">
                            <?= $totalCartItems ?>
                        </span>
                    </a>
                </li>

                <!-- Transaction History -->
                <li>
                    <a href="transaction-history.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3M3.22302 14C4.13247 18.008 7.71683 21 
                     12 21c4.9706 0 9-4.0294 9-9 0-4.97056-4.0294-9-9-9-3.72916 
                     0-6.92858 2.26806-8.29409 5.5M7 9H3V5" />
                        </svg>
                        <span>Transaction History</span>
                    </a>
                </li>

                <!-- My Order Requests -->
                <li>
                    <a href="order-req-page.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
                        <svg class="w-5 h-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 
                     4.242 0 1.172 1.025 1.172 2.687 0 
                     3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 
                     1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 
                     1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>My Order Requests</span>
                    </a>
                </li>

            </ul>
        </div>
    </nav>

    <!-- Product Request -->
    <div class="flex flex-col items-center w-full max-w-7xl mx-auto px-6 pb-12">
        <div class="border border-gray-300 rounded-2xl p-6 md:p-12 w-full max-w-7xl bg-white">

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-2xl font-semibold mb-2">Product Requests</h2>
                    <p class="text-gray-600 font-medium">Review and manage your product requests below.</p>
                </div>


            </div>

            <div
                class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900">
                <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                    <thead
                        class="bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-4 w-[15%]">Request ID</th>
                            <th class="px-4 py-4 w-[20%]">Requested By</th>
                            <th class="px-4 py-4 w-[25%]">Customer Name</th>
                            <th class="px-4 py-4 w-[15%]">Date Requested</th>
                            <th class="px-4 py-4 text-center w-[15%]">Current Status</th>
                            <th class="px-6 py-4 text-center w-[10%]">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-15 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div
                                            class="size-16 bg-gray-50 rounded-full flex items-center justify-center border border-gray-100">
                                            <svg class="size-8 text-gray-300" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </div>
                                        <p class="text-sm text-gray-400 font-medium italic mb-10">No pending requests at the
                                            moment.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $row):
                                // Match Admin Status Logic
                                $status = strtolower($row['status'] ?? 'for review');

                                // Dynamic Status Coloring
                                if ($status === 'approved') {
                                    $statusClass = 'bg-green-50 text-green-600 border border-green-100';
                                } else if ($status === 'rejected') {
                                    $statusClass = 'bg-red-50 text-red-600 border border-red-100';
                                } else if ($status === 'cancelled') {
                                    $statusClass = 'bg-orange-50 text-orange-600 border border-orange-100';
                                } else if ($status === 'success') {
                                    $statusClass = 'bg-blue-50 text-blue-600 border border-blue-100';
                                } else {
                                    $statusClass = 'bg-yellow-50 text-yellow-600 border border-yellow-100';
                                }

                                // Gray out logic for older requests (anything not today)
                                $isToday = date('Y-m-d', strtotime($row['date'])) === date('Y-m-d');
                                $rowClass = !$isToday ? 'opacity-60 grayscale-[0.2]' : '';
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors group <?= $rowClass ?>" data-pr-no="<?= h($row['pr_no']) ?>">
                                    <td class="px-6 py-5 font-bold text-gray-900">
                                        <?= h($row['pr_no']) ?>
                                    </td>
                                    <td class="px-4 py-5 text-gray-800 font-medium tracking-tight">
                                        <?= h($row['full_name'] ?? 'N/A') ?>
                                    </td>
                                    <td class="px-4 py-5 text-gray-800 font-medium tracking-tight">
                                        <?= h($row['customer_name'] ?: ($row['temp_customer_name'] ?? 'N/A')) ?>
                                    </td>
                                    <td class="px-4 py-5 font-mono text-sm text-gray-600">
                                        <?= date('m/d/Y', strtotime($row['date'])) ?>
                                    </td>
                                    <td class="px-4 py-5 text-center">
                                        <span
                                            class="inline-block px-2 py-1 text-[10px] font-bold rounded <?= $statusClass ?> uppercase">
                                            <?= h($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick="openRequestInfoModal(<?= h(json_encode($row)) ?>)"
                                                class="p-2 rounded-lg hover:bg-gray-200 transition-colors cursor-pointer text-gray-400 hover:text-gray-900"
                                                title="View Details">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                    stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>


            </div>

            <!-- View Modal -->
            <div id="requestInfoModal"
                class="fixed inset-0 z-50 hidden items-center justify-center p-4 transition-all duration-300">
                <div class="absolute inset-0 bg-slate-900/60"></div>

                <div class="relative bg-white rounded-2xl shadow-2xl h-[85vh] max-w-6xl w-full overflow-hidden transform transition-all scale-95 opacity-0 duration-300 flex flex-col border border-gray-200"
                    id="requestInfoBox">

                    <div
                        class="flex-none px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-white z-20">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-8 bg-red-600 rounded-full"></div>
                            <h3 class="text-xl font-black text-gray-900 tracking-tight uppercase">Request Details</h3>
                        </div>
                        <button onclick="closeRequestInfoModal()"
                            class="p-2 hover:bg-gray-100 rounded-xl transition-all group">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6 text-gray-400 group-hover:text-gray-600" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 flex flex-row overflow-hidden">

                        <!-- Left Column: Order Stats & Base Info -->
                        <div
                            class="flex-1 border-r border-gray-100 overflow-y-auto p-8 space-y-8 bg-gray-50/10 custom-scrollbar">

                            <div class="max-w-xl py-4 px-2 space-y-5 bg-white">

                                <div class="border-b border-gray-100 pb-3 mb-6">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Ref:</span>
                                        <span id="modal-id" class="text-sm font-mono font-bold text-gray-900">--</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-x-16">
                                    <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-tight mb-0.5">Requested By</label>
                                        <p id="modal-by" class="text-sm font-bold text-gray-800">--</p>
                                    </div>
                                    <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-tight mb-0.5">Date Filed</label>
                                        <p id="modal-date" class="text-sm font-medium text-gray-600">--</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-x-16 pt-2">
                                    <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-tight mb-0.5">Customer Name</label>
                                        <p id="modal-customer" class="text-sm font-black text-gray-900 tracking-tight">--</p>
                                    </div>
                                    <div class="flex flex-col border-l-2 border-gray-100 pl-3">
                                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-tight mb-0.5">Status</label>
                                        <div id="modal-status">
                                            <span id="modal-status-badge" class="text-xs font-black text-yellow-600 tracking-tighter uppercase">
                                                ● Pending
                                            </span>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="pt-8 border-t border-gray-100 space-y-6">
                                <div id="modal-discount-section"
                                    class="p-4 rounded-xl border border-green-100 bg-green-50/30 flex justify-between items-center">
                                    <span class="text-[11px] font-bold text-green-700 uppercase tracking-widest">Applied
                                        Discount:</span>
                                    <span id="modal-discount-amount" class="text-xl font-bold text-green-800">₱
                                        0.00</span>
                                </div>

                                <div id="modal-remarks-section" class="space-y-3">
                                    <label
                                        class="text-[11px] font-bold text-gray-400 uppercase tracking-widest ml-1">Admin
                                        Comment/Remarks</label>
                                    <div id="remarks-bubble"
                                        class="p-5 rounded-xl bg-blue-50/30 border border-blue-100 min-h-[100px]">
                                        <p id="modal-remarks-text"
                                            class="text-sm italic text-blue-900/70 leading-relaxed font-medium">
                                            No remarks provided yet.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-8 border-t border-gray-100">
                                <div id="modal-cancel-container" class="w-full"></div>
                            </div>
                        </div>

                        <!-- Middle Column: Finalize Transaction -->
                        <div id="modal-right-column" class="flex-[1.5] flex flex-col bg-white overflow-hidden">
                            <div
                                class="px-8 py-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/20 shrink-0">
                                <div id="modal-right-header-container">
                                    <h2 id="modal-right-header-text"
                                        class="text-2xl font-black text-gray-900 tracking-tight uppercase">Finalize
                                        Transaction</h2>
                                    <p id="modal-right-sub-header"
                                        class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mt-1">
                                        Order
                                        Processing & Billing</p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span id="customerBadge"
                                        class="px-3 py-1 text-[10px] font-black bg-blue-50 text-blue-600 rounded-full border border-blue-100 uppercase italic transition-all">Linked
                                        Account</span>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto p-8 space-y-10 custom-scrollbar">

                                <section id="modal-order-items-section" class="space-y-4">
                                    <div class="flex items-center justify-between px-1">
                                        <h3 class="text-[11px] font-black text-gray-400 uppercase tracking-[0.3em]">
                                            Current Order</h3>
                                        <span id="summaryItemCount"
                                            class="bg-black text-white text-[10px] px-3 py-1 rounded-full font-black italic">0
                                            Items</span>
                                    </div>

                                    <div class="bg-white border border-gray-200 rounded-3xl overflow-hidden shadow-sm">
                                        <table class="w-full text-left border-collapse">
                                            <thead>
                                                <tr class="bg-gray-50/50 border-b border-gray-100">
                                                    <th
                                                        class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase">
                                                        Source</th>
                                                    <th
                                                        class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase">
                                                        Product Details</th>
                                                    <th
                                                        class="px-6 py-4 text-center text-[10px] font-black text-gray-400 uppercase">
                                                        Qty</th>
                                                    <th
                                                        class="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase">
                                                        Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody id="summaryTableBody" class="divide-y divide-gray-100">
                                                <!-- Dynamic Content -->
                                            </tbody>
                                        </table>
                                    </div>
                                </section>

                                <section id="modal-client-section" class="space-y-4 border-t border-gray-100 pt-6">
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
                                                <input type="number" id="adminDiscount" placeholder="0" readonly
                                                    class="w-full bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none pr-8 font-bold cursor-not-allowed">
                                                <span class="absolute right-4 top-3 text-gray-400 font-bold">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section id="modal-shipping-section" class="space-y-4 border-t border-gray-100">
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
                                            <textarea id="deliveryAddress" rows="2"
                                                placeholder="House/Bldg No., Street, Brgy, City..."
                                                class="w-full mt-1 bg-orange-50/30 border border-orange-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none resize-none font-medium"></textarea>
                                        </div>
                                    </div>
                                </section>

                                <section id="modal-payment-section" class="space-y-4 border-t border-gray-100">
                                    <div class="flex items-center justify-between px-1">
                                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Payment
                                            Configuration</h3>
                                        <span id="typeBadge"
                                            class="text-[9px] font-black px-2 py-0.5 rounded bg-blue-100 text-blue-600 uppercase">Standard
                                            Sale</span>
                                    </div>

                                    <!-- Simplified: Only Full Payment allowed in Showroom -->
                                    <input type="hidden" id="transactionType" value="full">
                                    <input type="hidden" id="interestRate" value="0">
                                    <input type="hidden" id="installmentTerm" value="1">

                                    <div class="grid grid-cols-2 gap-x-8 gap-y-6 items-start">
                                        <div class="col-span-2 md:col-span-1 space-y-1.5">
                                            <label class="text-xs font-semibold text-gray-700 ml-1">Payment
                                                Method</label>
                                            <select id="paymentMethod"
                                                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-semibold text-gray-900">
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
                                                class="text-xs font-semibold text-gray-700 ml-1">Final Amount
                                                Paid</label>
                                            <div class="relative">
                                                <span
                                                    class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-blue-600">₱</span>
                                                <input type="number" id="amountPaid" readonly
                                                    class="w-full bg-gray-100 border border-gray-200 rounded-xl pl-8 pr-4 py-4 text-lg outline-none font-black text-gray-700 cursor-not-allowed">
                                            </div>
                                        </div>

                                        <div class="col-span-2 space-y-1.5">
                                            <label class="text-xs font-semibold text-gray-700 ml-1">Payment Remarks /
                                                Bank Details</label>

                                            <textarea id="paymentRemarks" rows="2"
                                                placeholder="e.g. Paid via BDO Transfer, Check No. XXXX"
                                                class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-medium resize-none transition-all"></textarea>
                                            <br>
                                            <br>
                                            <div id="modal-grand-total-section"
                                                class="p-8 bg-gray-50 border-t border-gray-100 flex justify-between items-center rounded-2xl">
                                                <div>
                                                    <p
                                                        class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 leading-none">
                                                        Grand Total</p>
                                                    <h1 id="summaryGrandTotal"
                                                        class="text-3xl font-black text-gray-900 tracking-tighter leading-none">
                                                        ₱0.00</h1>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" id="calcBalance" value="0">
                                </section>

                            </div>


                        </div>
                    </div>

                    <div class="p-8 flex justify-between items-center gap-4 bg-white border-t border-gray-100 z-20">
                        <button onclick="closeRequestInfoModal()"
                            class="flex-1 justify-center bg-black hover:bg-zinc-800 text-white rounded-xl py-4 px-6 font-black text-[11px] uppercase tracking-[0.2em] transition-all shadow-xl shadow-gray-200 active:scale-95 flex items-center gap-2">
                            <span>Close Window</span>
                        </button>

                        <div id="hidden-order-id" class="hidden"></div>

                        <button id="showroomCompleteSaleBtn" onclick="handleShowroomCompleteSale()"
                            class="flex-1 justify-center bg-red-600 hover:bg-red-700 text-white rounded-xl py-4 px-6 font-black text-[11px] uppercase tracking-[0.2em] transition-all shadow-xl shadow-blue-100 active:scale-95 flex items-center gap-2">
                            <span>Complete Sale</span>
                        </button>
                    </div>

                </div>
            </div>


            <div id="cancelConfirmModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 transition-all duration-300">
                <div class="absolute inset-0 bg-slate-900/60" onclick="closeCancelConfirmModal()"></div>
                <div class="modal-box relative bg-white rounded-3xl shadow-2xl overflow-hidden text-center p-8 transform scale-95 opacity-0 transition-all duration-300 max-w-sm" id="cancelConfirmBox">
                    <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-2 text-red-600 uppercase tracking-tighter">Cancel Request?</h3>
                    <p class="text-gray-500 mb-8 text-sm leading-relaxed">
                        Are you sure you want to cancel Request <span class="font-black text-gray-900" id="cancel-pr-no-display">#--</span>?
                        <br><br>
                        <span class="text-red-500 font-black uppercase tracking-widest text-[10px] block mb-1">Warning: Irreversible Action</span>
                        This will notify the administration and stop further processing for this specific order.
                    </p>
                    <input type="hidden" id="cancel-pr-no-input">
                    <div class="flex gap-3">
                        <button type="button" onclick="closeCancelConfirmModal()"
                            class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-black text-[11px] uppercase tracking-widest text-gray-400 hover:bg-gray-50 transition-all">No, Keep It</button>
                        <button type="button" id="confirmCancelBtn" onclick="confirmCancelExecution()"
                            class="flex-1 py-4 bg-red-600 text-white rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-red-700 transition-all shadow-xl shadow-red-200 active:scale-95">Yes, Cancel Order</button>
                    </div>
                </div>
            </div>


            <!-- Validation Error Modal -->
            <div id="validationErrorModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 transition-all duration-300">
                <div class="absolute inset-0 bg-slate-900/40" onclick="closeValidationErrorModal()"></div>
                <div class="modal-box relative bg-white rounded-3xl shadow-2xl overflow-hidden text-center p-8 transform scale-95 opacity-0 transition-all duration-300 max-w-sm" id="validationErrorBox">
                    <div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-amber-50/50">
                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 mb-2 uppercase tracking-tight" id="validation-title">Invalid Input</h3>
                    <p class="text-gray-500 mb-8 text-sm leading-relaxed" id="validation-message">
                        Please check your information and try again.
                    </p>
                    <button type="button" onclick="closeValidationErrorModal()"
                        class="w-full py-4 bg-red-600 hover:bg-red-700 text-white rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] shadow-xl shadow-gray-200 active:scale-95 transition-all">Understood</button>
                </div>
            </div>

            <!-- Finalize Confirmation Modal -->
            <div id="finalizeConfirmModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 transition-all duration-300">
                <div class="absolute inset-0 bg-slate-900/60" onclick="closeFinalizeConfirmModal()"></div>
                <div class="modal-box relative bg-white rounded-3xl shadow-2xl overflow-hidden text-center p-8 transform scale-95 opacity-0 transition-all duration-300 max-w-sm" id="finalizeConfirmBox">
                    <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-2 uppercase tracking-tighter">Finalize Sale?</h3>
                    <p class="text-gray-500 mb-8 text-sm leading-relaxed">
                        This will record the transaction, update the inventory, and generate a receipt. Are you sure?
                    </p>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeFinalizeConfirmModal()"
                            class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-black text-[11px] uppercase tracking-widest text-gray-400 hover:bg-gray-50 transition-all">Review Again</button>
                        <button type="button" id="confirmFinalizeBtn" onclick="executeFinalizeTransaction()"
                            class="flex-1 py-4 bg-red-600 text-white rounded-2xl font-black text-[11px] uppercase tracking-widest hover:bg-red-700 transition-all shadow-xl shadow-blue-100 active:scale-95">Yes, Process Sale</button>
                    </div>
                </div>
            </div>

            <script src="../../public/assets/js/sr-order-req.js?v=1.4.0" defer></script>

            <style>
                .custom-scrollbar::-webkit-scrollbar {
                    width: 6px;
                }

                .custom-scrollbar::-webkit-scrollbar-track {
                    background: transparent;
                }

                .custom-scrollbar::-webkit-scrollbar-thumb {
                    background-color: #d1d5db;
                    border-radius: 10px;
                    border: 2px solid transparent;
                }

                .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                    background-color: #9ca3af;
                }

                label:has(input:checked) {
                    background-color: white !important;
                    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
                    color: #dc2626 !important;
                    border-color: #e5e7eb !important;
                }
            </style>

</body>

</html>