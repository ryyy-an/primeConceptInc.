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

    // Mark the current user as Online
    $stmt = $pdo->prepare("UPDATE users SET is_online = 1 WHERE id = ?");
    $stmt->execute([$userId]);


    // Fetch total cart items for Admin POS badge
    $cartItems = get_cart_items($pdo, $userId);
    $adminCartCount = count($cartItems);

    // Product search variable
    $prodSearch = $_GET['prod_search'] ?? '';
} else {
    // Not logged in → redirect
    header("Location: ../../public/index.php");
    exit;
}

// Fetch users for the table
$allUsers = get_all_users($pdo);

// Fetch stats for the cards
$stats = get_user_summary_stats($pdo);

// Helper function to get initials for the avatar circle
function getInitials($name)
{
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) {
        $initials .= mb_substr($w, 0, 1);
    }
    return strtoupper(substr($initials, 0, 2));
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
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js?v=1.4.0" defer></script>
    <script src="../../public/assets/js/order.js?v=1.4.0" defer></script>
    <script src="../../public/assets/js/settings.js?v=1.3.0" defer></script>
    <script src="../../public/assets/js/rep-generation.js?v=<?= time() ?>" defer></script>


    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #cbd5e1;
        }
    </style>

</head>

<body class="bg-white flex flex-col gap-6 text-gray-800 font-sans py-5 px-4 md:px-8">
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
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
            </a>

            <!-- Logout -->
            <a href="javascript:void(0)" data-open-modal="logout-modal" class="logout-trigger flex items-center gap-2 border border-gray-300 px-4 h-9 rounded-lg hover:bg-red-50 hover:border-red-200 transition group">
                <svg class="size-5 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                </svg>
                <span class="text-sm text-red-600 font-medium">Logout</span>
            </a>

            <?php include '../include/logout-modal.php'; ?>

        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex gap-10 mt-6 w-full max-w-7xl mx-auto px-6">

        <!-- Nav Bars -->
        <aside class="w-64 flex flex-col gap-6 shrink-0">
            <div>
                <h2 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4 px-2">Data Reports</h2>
                <nav class="flex flex-col gap-2">
                    
                    <!-- Filed Reports -->
                    <a href="javascript:void(0)" onclick="showReportSection('filedReportsSection', 'filedReportsLink')" id="filedReportsLink"
                        class="report-link flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group">
                        <div class="size-10 rounded-xl flex items-center justify-center transition duration-300 group-hover:scale-110 icon-box bg-gray-50 border border-gray-100 group-hover:bg-white group-hover:shadow-sm group-[.active-report-link]:bg-white/20 group-[.active-report-link]:border-transparent group-[.active-report-link]:shadow-inner">
                            <svg class="size-5 transition-colors text-gray-400 group-hover:text-gray-900 group-[.active-report-link]:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-widest block leading-tight">Filed Reports</span>
                            <span class="text-[9px] font-bold uppercase tracking-tighter opacity-80 subtitle text-gray-400 group-[.active-report-link]:text-red-100 transition-colors">Overview</span>
                        </div>
                    </a>

                    <!-- Product -->
                    <a href="javascript:void(0)" onclick="showReportSection('productSection', 'productLink')" id="productLink"
                        class="report-link flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group">
                        <div class="size-10 rounded-xl flex items-center justify-center transition duration-300 group-hover:scale-110 icon-box bg-gray-50 border border-gray-100 group-hover:bg-white group-hover:shadow-sm group-[.active-report-link]:bg-white/20 group-[.active-report-link]:border-transparent group-[.active-report-link]:shadow-inner">
                            <svg class="size-5 transition-colors text-gray-400 group-hover:text-gray-900 group-[.active-report-link]:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-widest block leading-tight">Product</span>
                            <span class="text-[9px] font-bold uppercase tracking-tighter opacity-80 subtitle text-gray-400 group-[.active-report-link]:text-red-100 transition-colors">Catalog</span>
                        </div>
                    </a>

                    <!-- Stocks Log -->
                    <a href="javascript:void(0)" onclick="showReportSection('stocksLogSection', 'stocksLogLink')" id="stocksLogLink"
                        class="report-link flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group">
                        <div class="size-10 rounded-xl flex items-center justify-center transition duration-300 group-hover:scale-110 icon-box bg-gray-50 border border-gray-100 group-hover:bg-white group-hover:shadow-sm group-[.active-report-link]:bg-white/20 group-[.active-report-link]:border-transparent group-[.active-report-link]:shadow-inner">
                            <svg class="size-5 transition-colors text-gray-400 group-hover:text-gray-900 group-[.active-report-link]:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-widest block leading-tight">Stocks Log</span>
                            <span class="text-[9px] font-bold uppercase tracking-tighter opacity-80 subtitle text-gray-400 group-[.active-report-link]:text-red-100 transition-colors">Movement History</span>
                        </div>
                    </a>

                    <!-- Transaction -->
                    <a href="javascript:void(0)" onclick="showReportSection('transactionSection', 'transactionLink')" id="transactionLink"
                        class="report-link flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group">
                        <div class="size-10 rounded-xl flex items-center justify-center transition duration-300 group-hover:scale-110 icon-box bg-gray-50 border border-gray-100 group-hover:bg-white group-hover:shadow-sm group-[.active-report-link]:bg-white/20 group-[.active-report-link]:border-transparent group-[.active-report-link]:shadow-inner">
                            <svg class="size-5 transition-colors text-gray-400 group-hover:text-gray-900 group-[.active-report-link]:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-widest block leading-tight">Transaction</span>
                            <span class="text-[9px] font-bold uppercase tracking-tighter opacity-80 subtitle text-gray-400 group-[.active-report-link]:text-red-100 transition-colors">Sales Records</span>
                        </div>
                    </a>

                    <!-- Customer / Client -->
                    <a href="javascript:void(0)" onclick="showReportSection('customerSection', 'customerLink')" id="customerLink"
                        class="report-link flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group">
                        <div class="size-10 rounded-xl flex items-center justify-center transition duration-300 group-hover:scale-110 icon-box bg-gray-50 border border-gray-100 group-hover:bg-white group-hover:shadow-sm group-[.active-report-link]:bg-white/20 group-[.active-report-link]:border-transparent group-[.active-report-link]:shadow-inner">
                            <svg class="size-5 transition-colors text-gray-400 group-hover:text-gray-900 group-[.active-report-link]:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-widest block leading-tight">Customer / Clients</span>
                            <span class="text-[9px] font-bold uppercase tracking-tighter opacity-80 subtitle text-gray-400 group-[.active-report-link]:text-red-100 transition-colors">Directory</span>
                        </div>
                    </a>

                </nav>
            </div>
        </aside>

        <!-- Right Content Areas -->
        <div class="flex-1 flex flex-col gap-6 w-full pb-20 relative">
            
            <section id="filedReportsSection" class="report-section hidden">
                <div class="bg-gray-50 border border-gray-100 rounded-3xl p-10 flex flex-col items-center justify-center text-center min-h-[400px]">
                    <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Filed Reports</h3>
                    <p class="text-sm font-medium text-gray-500">Analytics and summary overview</p>
                </div>
            </section>
            
            <section id="productSection" class="report-section hidden">
                <div class="bg-white border border-gray-100 rounded-[2rem] p-6 md:p-10 min-h-[400px] shadow-sm">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8 border-b border-gray-100 pb-6">
                        <div>
                            <h3 class="text-2xl font-black text-gray-900 tracking-tight leading-none mb-2">Product Catalog</h3>
                            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Complete Masterlist of Items</p>
                        </div>
                        
                        <div class="flex items-center gap-3 ml-auto">
                            <a href="../include/inc.admin/export-products.php" class="px-5 py-2.5 h-10 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex gap-2 items-center shadow-sm">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                Export to Excel
                            </a>
                        </div>
                    </div>

                    <!-- Relocated Search Form -->
                    <div class="mb-4 flex justify-start">
                        <form method="GET" action="" class="flex items-center gap-2">
                            <input type="hidden" name="section" value="productSection">
                            <div class="relative group">
                                <input type="text" name="prod_search" value="<?= htmlspecialchars($prodSearch) ?>" placeholder="Search product name or code..." class="h-10 pl-11 pr-4 bg-gray-50 border border-gray-100 rounded-xl text-xs font-bold text-gray-700 outline-none focus:border-red-500 focus:bg-white transition-all w-[280px] shadow-sm">
                                <svg class="size-4 absolute left-4 top-3 text-gray-400 group-focus-within:text-red-500 transition-colors pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <?php if(!empty($prodSearch)): ?>
                                <a href="rep-generation.php?section=productSection" class="h-10 px-4 flex items-center justify-center bg-gray-100 text-gray-500 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-200 transition-all border border-gray-200 shadow-sm">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900 mt-2">
                        <div class="overflow-x-auto overflow-y-scroll max-h-[500px] custom-scrollbar block">
                            <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                                <thead class="sticky top-0 bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100 z-10">
                                    <tr>
                                        <th class="px-6 py-4 font-black">Item</th>
                                        <th class="px-6 py-4 font-black">Name & Desc</th>
                                        <th class="px-6 py-4 font-black text-center">Variants</th>
                                        <th class="px-6 py-4 font-black text-blue-400 text-right">WH Stock</th>
                                        <th class="px-6 py-4 font-black text-orange-400 text-right">SR Stock</th>
                                        <th class="px-6 py-4 font-black text-green-500 text-right">Overall</th>
                                        <th class="px-6 py-4 font-black text-right">Price</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                <?php 
                                $products = get_inventory_cards($pdo, $prodSearch, 15); 
                                if(empty($products)):
                                ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">No products found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($products as $p): ?>
                                    <tr class="hover:bg-gray-50/40 transition-colors group">
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="size-10 rounded-xl bg-white border border-gray-100 flex items-center justify-center overflow-hidden shrink-0 shadow-sm p-1">
                                                    <img src="<?= $p['image'] ?>" class="w-full h-full object-contain" onerror="this.src='<?= $p['placeholder'] ?>';">
                                                </div>
                                                <span class="text-[10px] font-bold text-gray-400 font-mono tracking-tighter bg-gray-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($p['code']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <p class="text-[13px] font-black text-gray-900 leading-none mb-1 italic"><?= htmlspecialchars($p['name']) ?></p>
                                            <p class="text-[10px] font-bold text-gray-400 tracking-tighter truncate max-w-[200px]"><?= htmlspecialchars($p['desc']) ?></p>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <span class="inline-flex px-3 py-1 bg-gray-50 text-gray-600 border border-gray-100 rounded-full text-[9px] font-black uppercase tracking-widest"><?= count($p['variants']) ?></span>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <span class="text-[11px] font-black text-blue-600 bg-blue-50 px-2.5 py-1 rounded-md"><?= $p['total_wh'] ?></span>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <span class="text-[11px] font-black text-orange-600 bg-orange-50 px-2.5 py-1 rounded-md"><?= $p['total_sr'] ?></span>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <span class="text-[13px] font-black <?= $p['overall'] <= 5 ? 'text-red-500' : 'text-green-600' ?>"><?= $p['overall'] ?></span>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <?php if($p['is_on_sale']): ?>
                                                <div class="flex flex-col items-end">
                                                    <span class="text-[9px] font-bold text-gray-400 line-through tracking-tighter">₱<?= number_format($p['price'], 2) ?></span>
                                                    <span class="text-[13px] font-black text-gray-900 leading-none">₱<?= number_format($p['price'] - ($p['price'] * ($p['discount'] / 100)), 2) ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-[13px] font-black text-gray-900 leading-none">₱<?= number_format($p['price'], 2) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            
            <?php
                // Date filter variables
                $whFrom = $_GET['wh_from'] ?? null;
                $whTo = $_GET['wh_to'] ?? null;
                $srFrom = $_GET['sr_from'] ?? null;
                $srTo = $_GET['sr_to'] ?? null;
                $transFrom = $_GET['trans_from'] ?? null;
                $transTo = $_GET['trans_to'] ?? null;
                
                if(empty($whFrom)) $whFrom = null;
                if(empty($whTo)) $whTo = null;
                if(empty($srFrom)) $srFrom = null;
                if(empty($srTo)) $srTo = null;
                if(empty($transFrom)) $transFrom = null;
                if(empty($transTo)) $transTo = null;
            ?>

            <section id="stocksLogSection" class="report-section hidden">
                <div class="flex flex-col gap-6 w-full">
                    
                    <!-- Warehouse (WH) Logs -->
                    <div class="bg-white border border-gray-100 rounded-[2rem] p-8 shadow-sm w-full">
                        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                            <div>
                                <h3 class="text-xl font-black text-gray-700 tracking-[0.1em] uppercase mb-2">Warehouse Logs</h3>
                                <p class="text-gray-400 font-bold uppercase tracking-[0.2em] text-[10px]">Recent Activity Records</p>
                            </div>
                            
                            <!-- Filter Form WH -->
                            <form method="GET" action="" class="flex flex-wrap items-end gap-3 bg-gray-50 p-3.5 rounded-2xl border border-gray-100 ml-auto">
                                <input type="hidden" name="section" value="stocksLogSection" id="whFilterSectionData">
                                
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest pl-1">From</label>
                                    <input type="date" name="wh_from" value="<?= $whFrom ?>" onchange="this.form.submit()" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-red-500 transition-all font-bold text-gray-700 w-[140px] cursor-pointer">
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest pl-1">To</label>
                                    <input type="date" name="wh_to" value="<?= $whTo ?>" onchange="this.form.submit()" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-red-500 transition-all font-bold text-gray-700 w-[140px] cursor-pointer">
                                </div>

                                <div class="flex gap-2">
                                    <a href="../include/inc.admin/export-stock-logs.php?type=wh&from=<?= $whFrom ?>&to=<?= $whTo ?>" class="h-10 px-5 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex gap-2 items-center shadow-sm">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                        Export
                                    </a>
                                    <a href="rep-generation.php?section=stocksLogSection" class="h-10 px-6 bg-red-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-red-700 transition-all active:scale-95 flex items-center justify-center shadow-md shadow-red-100 shadow-sm">Reset</a>
                                </div>
                            </form>
                        </div>

                        <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900 mt-2">
                            <div class="overflow-x-auto overflow-y-scroll max-h-[500px] custom-scrollbar block">
                                <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                                    <thead class="sticky top-0 bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100 z-10">
                                        <tr>
                                            <th class="px-6 py-4">Product / Material</th>
                                            <th class="px-6 py-4 text-center">Prod Code</th>
                                            <th class="px-6 py-4 text-center">Adjustment</th>
                                            <th class="px-6 py-4 text-right">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <?php 
                                        $whLogs = get_wh_stock_logs($pdo, 50, $whFrom, $whTo); 
                                        if(empty($whLogs)):
                                        ?>
                                            <tr>
                                                <td colspan="4" class="px-6 py-16 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">No transaction records found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach($whLogs as $log): 
                                                // Determine formatting based on qty polarity
                                                $isAdd = $log['qty'] > 0;
                                                $sign = $isAdd ? '+' : '';
                                                $color = $isAdd ? 'text-green-600 bg-green-50 border border-green-100' : 'text-red-600 bg-red-50 border border-red-100';
                                            ?>
                                            <tr class="hover:bg-gray-50/50 transition duration-200">
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col">
                                                        <span class="text-sm font-bold text-gray-900 mb-0.5"><?= htmlspecialchars((string)$log['product_name']) ?></span>
                                                        <span class="text-[10px] text-blue-500 font-bold uppercase tracking-tight"><?= htmlspecialchars((string)$log['variant_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars((string)($log['prod_code'] ?? 'N/A')) ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="inline-flex px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest shadow-sm <?= $color ?>"><?= $sign . $log['qty'] ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-bold text-gray-500 uppercase mb-0.5"><?= date('M d, Y', strtotime($log['log_date'])) ?></span>
                                                        <span class="text-[10px] font-bold text-gray-400 uppercase"><?= date('h:i A', strtotime($log['log_date'])) ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Showroom (SR) Logs -->
                    <div class="bg-white border border-gray-100 rounded-[2rem] p-8 shadow-sm w-full mt-4">
                        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                            <div>
                                <h3 class="text-xl font-black text-gray-700 tracking-[0.1em] uppercase mb-2">Showroom Logs</h3>
                                <p class="text-gray-400 font-bold uppercase tracking-[0.2em] text-[10px]">Recent Activity Records</p>
                            </div>

                            <!-- Filter Form SR -->
                            <form method="GET" action="" class="flex flex-wrap items-end gap-3 bg-gray-50 p-3.5 rounded-2xl border border-gray-100 ml-auto">
                                <input type="hidden" name="section" value="stocksLogSection" id="srFilterSectionData">
                                
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest pl-1">From</label>
                                    <input type="date" name="sr_from" value="<?= $srFrom ?>" onchange="this.form.submit()" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-red-500 transition-all font-bold text-gray-700 w-[140px] cursor-pointer">
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest pl-1">To</label>
                                    <input type="date" name="sr_to" value="<?= $srTo ?>" onchange="this.form.submit()" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-red-500 transition-all font-bold text-gray-700 w-[140px] cursor-pointer">
                                </div>

                                <div class="flex gap-2">
                                    <a href="../include/inc.admin/export-stock-logs.php?type=sr&from=<?= $srFrom ?>&to=<?= $srTo ?>" class="h-10 px-5 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex gap-2 items-center shadow-sm">
                                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                        Export
                                    </a>
                                    <a href="rep-generation.php?section=stocksLogSection" class="h-10 px-6 bg-red-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-red-700 transition-all active:scale-95 flex items-center justify-center shadow-md shadow-red-100 shadow-sm">Reset</a>
                                </div>
                            </form>
                        </div>

                        <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900 mt-2">
                            <div class="overflow-x-auto overflow-y-scroll max-h-[500px] custom-scrollbar block">
                                <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                                    <thead class="sticky top-0 bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100 z-10">
                                        <tr>
                                            <th class="px-6 py-4">Product / Material</th>
                                            <th class="px-6 py-4 text-center">Prod Code</th>
                                            <th class="px-6 py-4 text-center">Adjustment</th>
                                            <th class="px-6 py-4 text-right">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <?php 
                                        $srLogs = get_sr_stock_logs($pdo, 50, $srFrom, $srTo); 
                                        if(empty($srLogs)):
                                        ?>
                                            <tr>
                                                <td colspan="4" class="px-6 py-16 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">No transaction records found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach($srLogs as $log): 
                                                // Determine formatting based on qty polarity
                                                $isAdd = $log['qty'] > 0;
                                                $sign = $isAdd ? '+' : '';
                                                $color = $isAdd ? 'text-green-600 bg-green-50 border border-green-100' : 'text-red-600 bg-red-50 border border-red-100';
                                            ?>
                                            <tr class="hover:bg-gray-50/50 transition duration-200">
                                                <td class="px-6 py-4">
                                                    <div class="flex flex-col">
                                                        <span class="text-sm font-bold text-gray-900 mb-0.5"><?= htmlspecialchars((string)$log['product_name']) ?></span>
                                                        <span class="text-[10px] text-blue-500 font-bold uppercase tracking-tight"><?= htmlspecialchars((string)$log['variant_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars((string)($log['prod_code'] ?? 'N/A')) ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="inline-flex px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest shadow-sm <?= $color ?>"><?= $sign . $log['qty'] ?></span>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-bold text-gray-500 uppercase mb-0.5"><?= date('M d, Y', strtotime($log['log_date'])) ?></span>
                                                        <span class="text-[10px] font-bold text-gray-400 uppercase"><?= date('h:i A', strtotime($log['log_date'])) ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <section id="transactionSection" class="report-section hidden">
                <div class="bg-white border border-gray-100 rounded-[2rem] p-8 shadow-sm w-full">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                        <div>
                            <h3 class="text-xl font-black text-gray-700 tracking-[0.1em] uppercase mb-2">Transactions</h3>
                            <p class="text-gray-400 font-bold uppercase tracking-[0.2em] text-[10px]">Financial Payment Records</p>
                        </div>

                        <!-- Filter Form Transactions -->
                        <form method="GET" action="" class="flex flex-wrap items-end gap-3 bg-gray-50 p-3.5 rounded-2xl border border-gray-100 ml-auto">
                            <input type="hidden" name="section" value="transactionSection" id="transFilterSectionData">
                            
                            <div class="flex flex-col gap-1">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest pl-1">From</label>
                                <input type="date" name="trans_from" value="<?= $transFrom ?>" onchange="this.form.submit()" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-red-500 transition-all font-bold text-gray-700 w-[140px] cursor-pointer">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest pl-1">To</label>
                                <input type="date" name="trans_to" value="<?= $transTo ?>" onchange="this.form.submit()" class="h-10 px-3 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-red-500 transition-all font-bold text-gray-700 w-[140px] cursor-pointer">
                            </div>

                            <div class="flex gap-2">
                                <a href="../include/inc.admin/export-transactions.php?from=<?= $transFrom ?>&to=<?= $transTo ?>" class="h-10 px-5 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex gap-2 items-center shadow-sm">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                    Export
                                </a>
                                <a href="rep-generation.php?section=transactionSection" class="h-10 px-6 bg-red-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-red-700 transition-all active:scale-95 flex items-center justify-center shadow-md shadow-red-100 shadow-sm">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900 mt-2">
                        <div class="overflow-x-auto overflow-y-scroll max-h-[500px] custom-scrollbar block">
                            <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                                <thead class="sticky top-0 bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100 z-10">
                                    <tr>
                                        <th class="px-6 py-4">Transaction ID</th>
                                        <th class="px-6 py-4">Customer / Client</th>
                                        <th class="px-6 py-4 text-center">Payment</th>
                                        <th class="px-6 py-4 text-center">Amount</th>
                                        <th class="px-6 py-4 text-center">Status</th>
                                        <th class="px-6 py-4 text-right">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php 
                                    $transactions = get_report_transactions($pdo, 50, $transFrom, $transTo); 
                                    if(empty($transactions)):
                                    ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-16 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">No transaction records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($transactions as $t): 
                                            $methodColor = $t['payment_type'] === 'Full' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-purple-50 text-purple-600 border-purple-100';
                                            $statusColor = $t['trans_status'] === 'Completed' ? 'bg-green-50 text-green-600 border-green-100' : 'bg-orange-50 text-orange-600 border-orange-100';
                                        ?>
                                        <tr class="hover:bg-gray-50/50 transition duration-200">
                                            <td class="px-6 py-4 font-black text-gray-900 text-sm">#<?= $t['trans_id'] ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold text-gray-900 mb-0.5"><?= htmlspecialchars($t['customer_name']) ?></span>
                                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-tight"><?= htmlspecialchars($t['client_type']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="inline-flex px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $methodColor ?>"><?= $t['payment_type'] ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-center font-black text-gray-900 text-sm">₱<?= number_format((float)$t['amount'], 2) ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="inline-flex px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?= $statusColor ?>"><?= $t['trans_status'] ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex flex-col">
                                                    <span class="text-xs font-bold text-gray-500 uppercase mb-0.5"><?= date('M d, Y', strtotime($t['transaction_date'])) ?></span>
                                                    <span class="text-[10px] font-bold text-gray-400 uppercase"><?= date('h:i A', strtotime($t['transaction_date'])) ?></span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="customerSection" class="report-section hidden">
                <div class="bg-white border border-gray-100 rounded-[2rem] p-8 shadow-sm w-full">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
                        <div>
                            <h3 class="text-xl font-black text-gray-700 tracking-[0.1em] uppercase mb-2">Customer Directory</h3>
                            <p class="text-gray-400 font-bold uppercase tracking-[0.2em] text-[10px]">Client Database & Engagement</p>
                        </div>

                        <div class="flex gap-2">
                            <a href="../include/inc.admin/export-customers.php" class="px-5 py-2.5 h-10 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors flex gap-2 items-center shadow-sm">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                Export to Excel
                            </a>
                        </div>
                    </div>

                    <div class="w-full overflow-hidden border border-gray-100 rounded-2xl shadow-sm bg-white font-sans text-gray-900 mt-2">
                        <div class="overflow-x-auto overflow-y-scroll max-h-[500px] custom-scrollbar block">
                            <table class="w-full text-md text-left text-gray-700 table-auto border-collapse">
                                <thead class="sticky top-0 bg-gray-50 text-gray-400 text-[9px] font-bold uppercase tracking-widest border-b border-gray-100 z-10">
                                    <tr>
                                        <th class="px-6 py-4">Client Detail</th>
                                        <th class="px-6 py-4">Contact</th>
                                        <th class="px-6 py-4 text-center">Gov Branch</th>
                                        <th class="px-6 py-4 text-center">Orders</th>
                                        <th class="px-6 py-4 text-right">Total Spend</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php 
                                    $customers = get_report_customers($pdo, 100); 
                                    if(empty($customers)):
                                    ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-16 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">No customer records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($customers as $c): ?>
                                        <tr class="hover:bg-gray-50/50 transition duration-200">
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-bold text-gray-900 mb-0.5"><?= htmlspecialchars($c['name']) ?></span>
                                                    <span class="text-[10px] text-blue-500 font-bold uppercase tracking-tight"><?= htmlspecialchars($c['client_type']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-medium text-gray-600"><?= htmlspecialchars($c['contact_no'] ?: 'N/A') ?></td>
                                            <td class="px-6 py-4 text-center font-bold text-gray-400 text-[10px] uppercase"><?= htmlspecialchars($c['gov_branch'] ?: 'Private') ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="px-2.5 py-1 rounded-md bg-gray-100 text-gray-700 font-black text-[10px]"><?= $c['total_orders'] ?></span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <span class="text-sm font-black text-red-600">₱<?= number_format((float)($c['total_spend'] ?? 0), 2) ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>



</body>

</html>