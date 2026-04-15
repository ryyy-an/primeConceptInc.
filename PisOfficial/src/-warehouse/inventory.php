<?php

declare(strict_types=1);

require_once "../include/config.php";
require_once "../include/dbh.inc.php";
require_once "../include/global.model.php";
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

// Fetch Aggregated Inventory
$inventory = get_inventory_cards($pdo);

// Dashboard Stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_deleted = 0")->fetchColumn();

$stmt_user = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_by = ?");
$stmt_user->execute([$userId]);
$userTransactions = $stmt_user->fetchColumn();

$pendingWH = (int) $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                         JOIN order_items oi ON o.id = oi.order_id 
                         WHERE o.status = 'pending' AND (oi.get_from = 'WH' OR oi.get_from = 'Warehouse')")->fetchColumn();

$pendingSR = (int) $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                         JOIN order_items oi ON o.id = oi.order_id 
                         WHERE o.status = 'pending' AND (oi.get_from = 'SR' OR oi.get_from = 'Showroom')")->fetchColumn();
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
                    <h4 class="text-base text-gray-500">Welcome, <?= htmlspecialchars(
                        $username,
                    ) ?></h4>
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

    <section class="px-6 py-4">
        <div class="grid grid-cols-[repeat(4,300px)] justify-center gap-5">
            <!-- Card 1 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Available Products</div>
                <div class="text-4xl font-bold text-gray-800"><?= (int) $totalProducts ?></div>
                <div class="text-sm text-gray-600">Total base furniture items.</div>
            </div>

            <!-- Card 2 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Your Transactions</div>
                <div class="text-4xl font-bold text-gray-800"><?= (int) $userTransactions ?></div>
                <div class="text-sm text-gray-600">Total orders you processed.</div>
            </div>

            <!-- Card 3 -->
            <div class="flex flex-col justify-between bg-white border border-gray-300 rounded-lg shadow h-[150px] p-6">
                <div class="text-sm uppercase tracking-wide text-gray-500">Pending Warehouse</div>
                <div class="text-4xl font-bold text-red-600"><?= (int) $pendingWH ?></div>
                <div class="text-sm text-gray-600">Current pending WH requests.</div>
            </div>

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
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
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
                        class="flex items-center justify-center gap-2 h-10 px-4 text-red-600 font-semibold border-b-2 border-red-600">
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
                        <span>Inventory</span>
                    </a>
                </li>

                <!-- Product Status -->
                <li>
                    <a href="product-status.php"
                        class="flex items-center justify-center gap-2 h-10 px-4 text-gray-700 font-medium hover:text-red-600 transition">
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


    <section class="px-4 md:px-[100px] py-4">
        <div class="card-look p-12 bg-white border border-gray-300 rounded-2xl shadow-sm">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-2xl font-semibold mb-2">Warehouse Inventory Overview</h2>
                    <p class="text-gray-600">Detailed view of all products stored in the warehouse with current stock
                        levels and status.</p>
                </div>
            </div>

            <!-- Search -->
            <div class="relative w-full flex flex-row items-center gap-4 mt-8 mb-8">
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
                        class="w-full h-full bg-gray-100 border border-gray-300 rounded-xl pl-11 pr-4 outline-none text-md text-black placeholder-gray-500 focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all shadow-sm"
                        type="text" placeholder="Search product name or code..." />
                </div>
            </div>

            <div id="inventoryGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-5">
                <?php if (empty($inventory)): ?>
                    <div class="col-span-full py-12 text-center text-gray-500 text-lg">No inventory data found.</div>
                <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                        <div class="card-style p-6 border border-gray-200 rounded-2xl bg-white shadow-sm relative h-full flex flex-col hover:shadow-md transition-all duration-300 product-card"
                            data-name="<?= htmlspecialchars($item['name']) ?>"
                            data-code="<?= htmlspecialchars($item['code']) ?>" data-wh="<?= $item['total_wh'] ?>"
                            data-sr="<?= $item['total_sr'] ?>">

                            <div class="relative">
                                <div class="absolute inset-x-0 top-0 flex justify-between items-start z-10 pointer-events-none">
                                    <?php if ((int) $item['total_wh'] <= 5): ?>
                                        <span
                                            class="bg-red-600 text-white text-[10px] px-3 py-1 rounded-full font-bold uppercase tracking-wider shadow-sm pointer-events-auto">
                                            Low Stock
                                        </span>
                                    <?php else: ?>
                                        <div></div>
                                    <?php endif; ?>
                                </div>

                                <div
                                    class="w-full h-48 bg-gray-50 rounded-xl overflow-hidden flex items-center justify-center p-4">
                                    <img src="<?= htmlspecialchars($item['image']) ?>"
                                        alt="<?= htmlspecialchars($item['name']) ?>"
                                        class="max-w-full max-h-full object-contain hover:scale-105 transition-transform duration-500"
                                        onerror="this.onerror=null; this.src='../../public/assets/img/primeLogo.ico';">
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
                                            <?= $item['overall'] ?> <span
                                                class="text-[10px] font-bold text-gray-400 uppercase">Qty</span>
                                        </p>
                                    </div>
                                </div>

                                <p class="text-sm text-gray-500 line-clamp-2 italic leading-relaxed h-10 mt-3">
                                    <?= htmlspecialchars($item['desc'] ?? 'No description available.') ?>
                                </p>

                                <div class="pt-3 border-t border-gray-100 mt-4">
                                    <h3
                                        class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2 border-b border-gray-50 pb-1">
                                        Inventory Per Variant</h3>
                                    <div class="flex gap-2 overflow-x-auto pb-2 no-scrollbar">
                                        <?php foreach ($item['variants'] as $v): ?>
                                            <div
                                                class="shrink-0 w-15 bg-gray-50 p-2 rounded-lg border border-gray-100 hover:bg-white transition-colors">
                                                <p class="text-[9px] font-bold text-gray-600 truncate">
                                                    <?= htmlspecialchars($v['name']) ?>: <?= $v['wh'] ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="noResults" class="hidden col-span-full py-20 text-center">
                <div class="bg-gray-100 p-8 rounded-full inline-block mb-6 shadow-sm">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.0"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-800">No matching products found</h3>
                <p class="text-md text-gray-500 mt-2">
                    Try adjusting your keywords or <span onclick="resetSearch()"
                        class="text-red-600 font-bold cursor-pointer hover:underline active:text-red-800 active:scale-95 inline-block transition-all">clear
                        searching filter</span>
                </p>
            </div>

            <script>
                function resetSearch() {
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput) {
                        searchInput.value = '';
                        if (typeof applyPosFilters === 'function') {
                            applyPosFilters();
                        }
                    }
                }
                function openViewModal(code) {
                    const formData = new FormData();
                    formData.append('action', 'get_product_details');
                    formData.append('code', code);

                    fetch('../include/inc.admin/admin.ctrl.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const p = data.product;
                                document.getElementById('viewCodeHeader').innerText = p.code;
                                document.getElementById('viewName').innerText = p.name;
                                document.getElementById('viewCategory').innerText = p.category || 'N/A';
                                document.getElementById('viewPrice').innerText = '₱' + parseFloat(p.price).toLocaleString(undefined, {
                                    minimumFractionDigits: 2
                                });
                                document.getElementById('viewDescription').innerText = p.description || 'No description available.';

                                const img = p.default_image || 'default.png';
                                document.getElementById('viewImagePreview').src = `../../public/assets/img/furnitures/${img}`;

                                // Variants
                                const container = document.getElementById('viewVariantsContainer');
                                container.innerHTML = '';
                                if (p.variants && p.variants.length > 0) {
                                    p.variants.forEach(v => {
                                        const div = document.createElement('div');
                                        div.className = 'flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-sm';
                                        div.innerHTML = `
                                            <div class="w-12 h-12 bg-white rounded-xl border border-gray-200 flex items-center justify-center shrink-0">
                                                <img src="../../public/assets/img/furnitures/${v.variant_image || 'default.png'}" class="object-cover w-full h-full rounded-xl" onerror="this.src='../../public/assets/img/primeLogo.ico'">
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5 leading-none">Variant Name</p>
                                                <p class="font-bold text-gray-800 text-sm">${v.variant}</p>
                                            </div>
                                            <div class="text-right flex gap-3 border-l border-gray-100 pl-3">
                                                <div>
                                                    <p class="text-[9px] font-black text-blue-500 uppercase leading-none mb-1">WH</p>
                                                    <p class="font-black text-blue-600 text-sm tracking-tighter">${v.v_wh || 0}</p>
                                                </div>
                                            </div>
                                        `;
                                        container.appendChild(div);
                                    });
                                } else {
                                    container.innerHTML = '<p class="text-sm text-gray-400 italic">No variants available.</p>';
                                }

                                // Components
                                const compContainer = document.getElementById('viewComponentsContainer');
                                compContainer.innerHTML = '';
                                if (p.components && p.components.length > 0) {
                                    p.components.forEach(c => {
                                        const div = document.createElement('div');
                                        div.className = 'flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100 shadow-sm transition-all hover:bg-white';
                                        div.innerHTML = `
                                            <div>
                                                <p class="text-xs font-bold text-gray-800">${c.component_name}</p>
                                                <p class="text-[9px] text-gray-400 font-bold uppercase tracking-tighter mt-0.5">Loc: ${c.location || 'N/A'}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-0.5">Need</p>
                                                <p class="text-sm font-black text-gray-900">${c.qty_needed}</p>
                                            </div>
                                        `;
                                        compContainer.appendChild(div);
                                    });
                                } else {
                                    compContainer.innerHTML = '<p class="text-sm text-gray-400 italic">No component recipe established.</p>';
                                }

                                openModal('viewModal');
                            }
                        })
                        .catch(err => console.error(err));
                }

                function closeViewModal() {
                    closeModal('viewModal');
                }
            </script>
        </div>
    </section>

    <!-- View Product Details Modal (Read Only) -->
    <div id="viewModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeViewModal()"></div>
        <div
            class="relative bg-white w-full max-w-4xl rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col h-[90vh] transition-all duration-300">
            <!-- Header -->
            <div class="p-8 border-b border-gray-50 flex justify-between items-center bg-white shrink-0">
                <div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Product Details</h3>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mt-1">
                        Warehouse View • Code: <span class="text-blue-600" id="viewCodeHeader">...</span>
                    </p>
                </div>
                <button onclick="closeViewModal()"
                    class="p-3 hover:bg-gray-100 rounded-2xl text-gray-400 hover:text-gray-900 transition-all group">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-10 space-y-12 custom-scrollbar">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                    <!-- Image -->
                    <div class="md:col-span-1">
                        <label class="block text-[11px] font-black text-gray-400 uppercase mb-4 tracking-widest">Product
                            Preview</label>
                        <div
                            class="aspect-square bg-gray-50 border border-gray-100 rounded-[2.5rem] overflow-hidden flex items-center justify-center p-8">
                            <img id="viewImagePreview" src="" class="object-contain w-full h-full mix-blend-multiply">
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="md:col-span-2 space-y-8">
                        <div>
                            <label
                                class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Name</label>
                            <h2 id="viewName" class="text-3xl font-black text-gray-900 tracking-tight">...</h2>
                        </div>

                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <label
                                    class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Category</label>
                                <p id="viewCategory" class="text-lg font-bold text-gray-700">...</p>
                            </div>
                            <div>
                                <label
                                    class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Unit
                                    Price</label>
                                <p id="viewPrice" class="text-2xl font-black text-green-600">...</p>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-gray-400 uppercase mb-2 tracking-widest">Description</label>
                            <p id="viewDescription" class="text-sm text-gray-500 leading-relaxed italic">...</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    <!-- Variants Section -->
                    <div>
                        <h4
                            class="text-[11px] font-black text-gray-900 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2">
                            Variants (Warehouse Stock)</h4>
                        <div id="viewVariantsContainer" class="space-y-3">
                            <!-- Dynamic Content -->
                        </div>
                    </div>

                    <!-- Components Section -->
                    <div>
                        <h4
                            class="text-[11px] font-black text-gray-900 uppercase tracking-widest mb-4 border-b border-gray-100 pb-2">
                            Component Recipe</h4>
                        <div id="viewComponentsContainer" class="space-y-2">
                            <!-- Dynamic Content -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-8 bg-gray-50 border-t border-gray-100 flex justify-end shrink-0">
                <button onclick="closeViewModal()"
                    class="px-10 py-4 bg-gray-900 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-black transition-all active:scale-95 shadow-lg shadow-gray-200">
                    Close Window
                </button>
            </div>
        </div>
    </div>

</body>

</html>