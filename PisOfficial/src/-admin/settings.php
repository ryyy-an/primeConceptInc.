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
    <link rel="stylesheet" href="../output.css">
    <script src="../../public/assets/js/global.js" defer></script>
    <script src="../../public/assets/js/order.js" defer></script>
    <script src="../../public/assets/js/settings.js?v=1.3.0" defer></script>


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

    <!-- Main Content -->
    <main class="flex-1 flex gap-10 mt-6 w-full max-w-7xl mx-auto px-6">

        <!-- Nav Bars -->
        <aside class="w-64 flex flex-col gap-6">
            <div>
                <h2 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4 px-2">System Config</h2>
                <nav class="flex flex-col gap-2">
                    <!-- User Accounts -->
                    <a href="javascript:void(0)" onclick="showSettingSection('userManagement')" id="userManagementLink"
                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group active-setting-link bg-red-600 text-white shadow-lg shadow-red-100">
                        <div class="size-10 bg-gray-50 border border-gray-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition duration-300 group-hover:bg-white group-hover:shadow-sm group-[.active-setting-link]:bg-white/20 group-[.active-setting-link]:border-transparent group-[.active-setting-link]:shadow-inner">
                            <svg class="size-5 text-gray-400 group-hover:text-gray-900 group-[.active-setting-link]:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-widest block leading-tight">Users</span>
                            <span class="text-[9px] text-gray-400 group-[.active-setting-link]:text-red-100 font-bold uppercase tracking-tighter opacity-80 transition-colors">Access Control</span>
                        </div>
                    </a>

                    <!-- Reports & Backups -->
                    <a href="javascript:void(0)" onclick="showSettingSection('reportsBackup')" id="reportsBackupLink"
                        class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-300 group text-gray-400 hover:bg-gray-50 hover:text-gray-900 border border-transparent hover:border-gray-100">
                        <div class="size-10 bg-gray-50 border border-gray-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition duration-300 group-hover:bg-white group-hover:shadow-sm group-[.active-setting-link]:bg-white/20 group-[.active-setting-link]:border-transparent group-[.active-setting-link]:shadow-inner">
                            <svg class="size-5 text-gray-400 group-hover:text-gray-900 group-[.active-setting-link]:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-black uppercase tracking-widest block leading-tight">Data</span>
                            <span class="text-[9px] text-gray-400 group-[.active-setting-link]:text-red-100 font-bold uppercase tracking-tighter opacity-80 transition-colors">Reports & Backup</span>
                        </div>
                    </a>
                </nav>
            </div>
        </aside>

        <div class="flex-1 flex flex-col gap-6">

            <!-- Admin Stats Cards -->
            <div class="grid grid-cols-3 gap-6 mb-2">
                <!-- Total Staff -->
                <div class="bg-white border border-gray-100 p-6 rounded-[2rem] shadow-sm hover:shadow-md transition-all duration-300 group relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <svg class="size-12 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Registered</p>
                    <div class="flex items-baseline gap-2">
                        <h2 class="text-4xl font-black text-gray-900 tracking-tighter"><?= number_format((float)$stats['total_staff']) ?></h2>
                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-tighter">Users</span>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 mt-2 uppercase tracking-tighter italic">System-wide directory</p>
                </div>

                <!-- Active Now -->
                <div class="bg-white border border-gray-100 p-6 rounded-[2rem] shadow-sm hover:shadow-md transition-all duration-300 group relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <svg class="size-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <p class="text-[10px] font-black text-green-600 uppercase tracking-widest mb-1">Sessions Active</p>
                    <div class="flex items-center gap-3">
                        <h2 class="text-4xl font-black text-gray-900 tracking-tighter"><?= number_format((float)$stats['active_now']) ?></h2>
                        <div class="flex items-center gap-1.5 px-2 py-1 bg-green-50 rounded-full border border-green-100">
                            <span class="size-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            <span class="text-[10px] font-black text-green-600 uppercase">Live</span>
                        </div>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 mt-2 uppercase tracking-tighter italic">Currently logged in</p>
                </div>

                <!-- Admins -->
                <div class="bg-white border border-gray-100 p-6 rounded-[2rem] shadow-sm hover:shadow-md transition-all duration-300 group relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                        <svg class="size-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <p class="text-[10px] font-black text-red-600 uppercase tracking-widest mb-1">Administrative</p>
                    <div class="flex items-baseline gap-2">
                        <h2 class="text-4xl font-black text-red-600 tracking-tighter"><?= number_format((float)$stats['total_admins']) ?></h2>
                        <span class="text-[11px] font-bold text-red-400 uppercase tracking-tighter">Managers</span>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 mt-2 uppercase tracking-tighter italic">High-level access</p>
                </div>
            </div>

            <!-- User Managements Section -->
            <section id="userManagementSection" class="flex flex-1 bg-white border border-gray-100 rounded-[2rem] shadow-sm overflow-hidden flex flex-col">

                <div class="flex items-center justify-between p-8 border-b border-gray-100 bg-gray-50/30">
                    <div>
                        <h3 class="text-xl font-black text-gray-900 tracking-tight leading-none mb-1 uppercase">User Accounts</h3>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest italic font-medium">Manage system access and staff credentials</p>
                    </div>

                    <!-- System Toast Notification Triggers -->

                    <!-- System Toast Notification Triggers -->
                    <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
                        <script>
                            window.addEventListener('DOMContentLoaded', () => {
                                <?php if (isset($_GET['success'])): ?>
                                    <?php if ($_GET['success'] === 'password_reset'): ?>
                                        showToast("Password has been successfully updated!", "success");
                                    <?php elseif ($_GET['success'] === 'user_deleted'): ?>
                                        showToast("User has been successfully removed from the system.", "success");
                                    <?php elseif ($_GET['success'] === 'user_added'): ?>
                                        showToast("New staff member has been successfully registered!", "success");
                                    <?php elseif ($_GET['success'] === 'migration_complete'): ?>
                                        showToast("Database migration and reset completed successfully!", "success");
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (isset($_GET['error'])): ?>
                                    <?php if ($_GET['error'] === 'self_delete'): ?>
                                        showToast("You cannot delete your own account while logged in.", "error");
                                    <?php elseif ($_GET['error'] === 'numeric_name'): ?>
                                        showToast("Full name cannot contain numeric characters.", "error");
                                    <?php elseif ($_GET['error'] === 'migration_failed'): ?>
                                        showToast("Database migration failed. Please check log files.", "error");
                                    <?php else: ?>
                                        showToast("An error occurred processing your request.", "error");
                                    <?php endif; ?>
                                <?php endif; ?>

                                // Clean up the URL so the toast doesn't reappear on refresh
                                if (window.history.replaceState) {
                                    const url = new URL(window.location.href);
                                    url.searchParams.delete('success');
                                    url.searchParams.delete('error');
                                    window.history.replaceState({}, '', url.pathname);
                                }
                            });
                        </script>
                    <?php endif; ?>
                    <button onclick="window.formHasUnsavedChanges = false; openModal('registrationModal')"
                        class="flex items-center gap-2 bg-red-600 hover:bg-gray-900 text-white px-5 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg shadow-red-100 active:scale-95 group">
                        <svg class="size-4 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New Staff
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-0">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-[0.15em] text-gray-400 bg-gray-50/50">
                                <th class="px-8 py-4 font-black border-b border-gray-100">User Identity</th>
                                <th class="px-8 py-4 font-black border-b border-gray-100 text-center">System Role</th>
                                <th class="px-8 py-4 font-black border-b border-gray-100 text-center">Connection</th>
                                <th class="px-8 py-4 font-black border-b border-gray-100 text-right">Administrative</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if (empty($allUsers)): ?>
                                <tr>
                                    <td colspan="4" class="px-8 py-12 text-center">
                                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">No user accounts found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr class="hover:bg-gray-50/40 transition-colors group">
                                        <td class="px-8 py-3">
                                            <div class="flex items-center gap-4">
                                                <div class="size-10 rounded-2xl bg-gray-50 border border-gray-100 text-gray-900 flex items-center justify-center font-black text-[11px] shadow-sm transform group-hover:scale-105 transition-transform">
                                                    <?= getInitials($user['full_name']) ?>
                                                </div>
                                                <div>
                                                    <p class="text-[13px] font-black text-gray-900 leading-none mb-1 italic"><?= htmlspecialchars($user['full_name']) ?></p>
                                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">@<?= htmlspecialchars($user['username']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-8 py-3 text-center">
                                            <span class="inline-flex px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest
                                                <?= $user['role'] === 'admin' ? 'bg-red-50 text-red-600 border border-red-100 shadow-sm' : 'bg-gray-50 text-gray-600 border border-gray-100' ?>">
                                                <?= htmlspecialchars($user['role']) ?>
                                            </span>
                                        </td>
                                        <td class="px-8 py-3 text-center">
                                            <?php if (isset($user['is_online']) && $user['is_online'] == 1): ?>
                                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-green-50/50 border border-green-100">
                                                    <span id="status-dot-<?= $user['id'] ?>" class="size-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                                    <span id="status-text-<?= $user['id'] ?>" class="text-[9px] font-black text-green-600 uppercase">Live</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-gray-50/50 border border-gray-100">
                                                    <span id="status-dot-<?= $user['id'] ?>" class="size-1.5 rounded-full bg-gray-300"></span>
                                                    <span id="status-text-<?= $user['id'] ?>" class="text-[9px] font-black text-gray-400 uppercase">Off</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-8 py-3 text-right">
                                            <div class="flex justify-end gap-1 transition-opacity duration-300">
                                                <button onclick="confirmDelete(<?= $user['id'] ?>)"
                                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all"
                                                    title="Remove Staff">
                                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                                <button onclick="openResetModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>', '<?= htmlspecialchars($user['username']) ?>')"
                                                    class="p-2 text-gray-400 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-all"
                                                    title="Reset Pwd">
                                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
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

            </section>

            <!-- Reports & Backups Section -->
            <section id="reportsBackupSection" class="hidden flex-1 flex-col min-h-[500px] gap-6">

                <div class="bg-white border border-gray-100 rounded-[2rem] shadow-sm overflow-hidden flex flex-col h-full">
                    <div class="p-8 border-b border-gray-100 flex items-center justify-between bg-gray-50/30">
                        <div class="flex items-center gap-4">
                            <div class="size-12 bg-red-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-red-100">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-gray-900 tracking-tight leading-none mb-1 uppercase">System Config</h3>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest italic font-medium tracking-widest">Reports & database file management</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8 flex-1">
                        <!-- Left Column: Generate Reports -->
                        <div class="flex flex-col h-full">
                            <div class="border-b border-gray-100 pb-3 mb-6">
                                <h4 class="text-[11px] font-black text-red-600 uppercase tracking-widest">Excel Generation</h4>
                            </div>

                            <div class="p-8 pb-10 rounded-[2rem] border border-gray-100 flex flex-col items-center justify-center gap-6 text-center hover:border-red-100 hover:shadow-xl hover:shadow-red-50/40 transition-all duration-500 group cursor-pointer bg-white h-full">
                                <div class="size-20 bg-gray-50 text-gray-400 group-hover:text-red-600 group-hover:bg-red-50/50 group-hover:rotate-12 transition-all duration-500 rounded-3xl flex items-center justify-center border border-transparent group-hover:border-red-100">
                                    <svg class="size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2m-6-9a3 3 0 116 0 3 3 0 01-6 0zm11 2a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 012-2 2 2 0 012-2h10z" />
                                    </svg>
                                </div>
                                <div class="max-w-[240px]">
                                    <h5 class="font-black text-gray-900 tracking-tight text-lg mb-2 italic">Full System Audit</h5>
                                    <p class="text-[11px] font-medium text-gray-500 mb-8 leading-relaxed">Comprehensive inventory, staff performance, and complete sales data export.</p>
                                    <a href="rep-generation.php" id="generateFullReportBtn" class="block text-center w-full py-3.5 bg-red-600 hover:bg-gray-900 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl transition-all active:scale-95 shadow-lg shadow-red-100">Generate Audit</a>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Backups -->
                        <div class="flex flex-col gap-6">
                            <div class="border-b border-gray-100 pb-3">
                                <h4 class="text-[11px] font-black text-gray-900 uppercase tracking-widest tracking-widest">Database Backup</h4>
                            </div>
                            <div class="flex flex-col gap-4">
                                <!-- Export -->
                                <div class="p-6 border border-gray-100 rounded-[2rem] flex items-center justify-between hover:border-gray-200 transition-all bg-gray-50/30 group">
                                    <div class="flex items-center gap-5">
                                        <div class="size-14 bg-white shadow-sm border border-gray-100 rounded-2xl flex items-center justify-center text-gray-400 group-hover:text-gray-900 transition-colors">
                                            <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-black text-gray-900 leading-none mb-1 italic">Snapshot Export</h5>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">Download Current .SQL</p>
                                        </div>
                                    </div>
                                    <button class="shrink-0 px-5 py-2.5 bg-white border-2 border-gray-100 text-[10px] font-black uppercase tracking-widest text-gray-600 hover:text-black hover:border-gray-900 rounded-xl transition-all active:scale-95">Download</button>
                                </div>

                                <!-- Restore -->
                                <div class="p-6 border border-gray-100 rounded-[2rem] flex items-center justify-between hover:border-gray-200 transition-all bg-white group shadow-sm">
                                    <div class="flex items-center gap-5">
                                        <div class="size-14 bg-gray-50 flex items-center justify-center text-gray-400 rounded-2xl border border-gray-100 group-hover:text-red-600 group-hover:bg-red-50/50 transition-colors">
                                            <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-black text-gray-900 leading-none mb-1 italic">Rollback Mode</h5>
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter text-red-400">Restore from local file</p>
                                        </div>
                                    </div>
                                    <button class="shrink-0 px-5 py-2.5 bg-gray-900 border-2 border-gray-900 text-[10px] font-black uppercase tracking-widest text-white hover:bg-black rounded-xl transition-all shadow-lg active:scale-95">Upload File</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>


            <div id="resetModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 opacity-0 pointer-events-none transition-all duration-300 p-4">
                <div class="bg-white w-full max-w-2xl rounded-[2rem] shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 border border-gray-100">

                    <div class="bg-gray-50 border-b border-gray-100 px-8 py-6 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <div class="size-12 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-50">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 tracking-tight leading-none mb-1">Account Recovery</h3>
                                <p class="text-xs font-medium text-gray-500">Credential & Security Tool</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeModalWithCheck('resetModal', 'resetForm')" class="text-gray-400 hover:text-red-600 transition-colors">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M6 18L18 6M6 6l12 12" stroke-width="2.5" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-10">
                        <p class="text-sm text-gray-500 mb-8 px-2 font-medium">
                            Managing credentials for <span id="reset_user_display_name" class="font-bold text-gray-800 underline decoration-red-400 decoration-2 underline-offset-4"></span>.
                        </p>

                        <form id="resetForm" action="../include/inc.admin/admin.ctrl.php" method="POST" class="space-y-6" onsubmit="return validateResetForm()" oninput="window.formHasUnsavedChanges = true">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="user_id" id="reset_user_id_input">

                            <div class="space-y-2">
                                <label class="text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none">Access Username</label>
                                <div class="relative group">
                                    <input type="text" name="username" id="reset_username_input" readonly
                                        class="w-full pl-6 pr-14 py-4 bg-gray-50 border border-gray-100 rounded-2xl text-gray-500 outline-none font-medium cursor-not-allowed transition-all focus:ring-2 focus:ring-red-500/20 focus:border-red-400 focus:bg-white focus:text-gray-800">
                                    <button type="button" onclick="toggleUsernameEdit()" id="editUsernameBtn"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all"
                                        title="Edit Username">
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none">Security Key</label>
                                    <div class="relative">
                                        <input type="password" name="new_password" id="new_password" required placeholder="••••••••"
                                            class="w-full pl-6 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-red-500/20 focus:border-red-400 outline-none transition-all text-gray-800 font-medium tracking-widest placeholder:text-gray-300">
                                        <button type="button" onclick="toggleVisibility('new_password', 'newEyeIcon')"
                                            class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all focus:outline-none">
                                            <svg id="newEyeIcon" fill="none" class="size-5" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none">Verify State</label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="••••••••"
                                            class="w-full pl-6 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-2xl focus:ring-2 focus:ring-red-500/20 focus:border-red-400 outline-none transition-all text-gray-800 font-medium tracking-widest placeholder:text-gray-300">
                                        <button type="button" onclick="toggleVisibility('confirm_password', 'confirmEyeIcon')"
                                            class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all focus:outline-none">
                                            <svg id="confirmEyeIcon" fill="none" class="size-5" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-4 pt-6">
                                <button type="button" onclick="closeModalWithCheck('resetModal', 'resetForm')"
                                    class="flex-1 px-8 py-4 border-2 border-gray-100 text-gray-500 rounded-2xl hover:border-gray-200 hover:text-gray-900 transition-all font-black uppercase tracking-widest text-[10px] active:scale-95">
                                    Discard
                                </button>
                                <button type="submit"
                                    class="flex-1 px-8 py-4 bg-red-600 hover:bg-gray-900 text-white rounded-2xl transition-all font-black uppercase tracking-widest text-[10px] active:scale-95 shadow-lg shadow-red-100">
                                    Restore Access
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    window.toggleUsernameEdit = function() {
                        const input = document.getElementById('reset_username_input');
                        const btn = document.getElementById('editUsernameBtn');
                        
                        if (input.readOnly) {
                            input.readOnly = false;
                            input.classList.remove('cursor-not-allowed', 'text-gray-500', 'bg-gray-50');
                            input.classList.add('text-gray-900', 'bg-white');
                            input.focus();
                            btn.classList.add('bg-red-600', 'text-white', 'shadow-red-200');
                        } else {
                            input.readOnly = true;
                            input.classList.add('cursor-not-allowed', 'text-gray-500', 'bg-gray-50');
                            input.classList.remove('text-gray-900', 'bg-white');
                            btn.classList.remove('bg-red-600', 'text-white', 'shadow-red-200');
                        }
                    }

                    window.validateResetForm = function() {
                        const pass = document.getElementById('new_password').value;
                        const confirm = document.getElementById('confirm_password').value;
                        
                        if (pass !== confirm) {
                            alert("Security keys do not match! Please verify the state.");
                            return false;
                        }
                        return true;
                    }
                </script>
            </div>


            <div id="registrationModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 opacity-0 pointer-events-none transition-all duration-300 p-4">
                <div class="bg-white w-full max-w-2xl rounded-[2rem] shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 border border-gray-100">

                    <div class="bg-gray-50 border-b border-gray-100 px-8 py-6 flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <div class="size-12 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-50">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-800 tracking-tight leading-none mb-1">Staff Registry</h3>
                                <p class="text-xs font-medium text-gray-500">Create new administrative credentials</p>
                            </div>
                        </div>
                        <button onclick="closeModalWithCheck('registrationModal', 'regForm')" class="text-gray-400 hover:text-red-600 transition-colors">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M6 18L18 6M6 6l12 12" stroke-width="2.5" />
                            </svg>
                        </button>
                    </div>

                    <form id="regForm" action="../include/inc.admin/admin.ctrl.php" method="POST" class="p-10 space-y-6"
                        oninput="window.formHasUnsavedChanges = true">
                        <input type="hidden" name="action" value="add_user">

                        <div class="space-y-6">
                            <!-- Basic Info -->
                            <div>
                                <div class="flex justify-between items-center mb-2 px-1">
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none">Full Name</label>
                                    <span id="nameStatus" class="text-[10px] font-bold uppercase text-gray-400">Min 3 chars</span>
                                </div>
                                <input type="text" name="full_name" id="regFullName" required placeholder="e.g. Juan Dela Cruz"
                                    class="w-full px-6 py-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none font-medium transition-all focus:ring-2 focus:ring-red-500/20 focus:border-red-400 focus:bg-white text-gray-800 placeholder:text-gray-400 text-sm">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="flex justify-between items-center mb-2 px-1">
                                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none">Access Username</label>
                                        <span id="userStatus" class="text-[10px] font-bold uppercase text-gray-400">Unique Required</span>
                                    </div>
                                    <input type="text" name="username" id="regUsername" required placeholder="j.delacruz01"
                                        class="w-full px-6 py-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none font-medium transition-all focus:ring-2 focus:ring-red-500/20 focus:border-red-400 focus:bg-white text-gray-800 placeholder:text-gray-400 text-sm">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none mb-2">System Role</label>
                                    <select name="role"
                                        class="w-full px-6 py-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none font-medium transition-all focus:ring-2 focus:ring-red-500/20 focus:border-red-400 focus:bg-white text-gray-800 appearance-none cursor-pointer">
                                        <option value="showroom">Showroom</option>
                                        <option value="warehouse">Warehouse</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Security -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none mb-2">Temporary Password</label>
                                    <div class="relative">
                                        <input type="password" name="password" id="passwordInput" required placeholder="••••••••"
                                            class="w-full px-6 py-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none font-medium text-gray-800 tracking-widest transition-all focus:ring-2 focus:ring-red-500/20 focus:border-red-400 focus:bg-white pr-14 text-sm">
                                        <button type="button" onclick="toggleVisibility('passwordInput', 'eyeIcon1')"
                                            class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                                            <svg id="eyeIcon1" fill="none" class="size-5" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="mt-2 px-2">
                                        <div class="flex h-1.5 w-full bg-gray-100 rounded-full overflow-hidden mb-1.5">
                                            <div id="strengthBar" class="w-0 transition-all duration-500 ease-out"></div>
                                        </div>
                                        <p id="strengthText" class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Security Strength</p>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between items-center mb-2 px-1">
                                        <label class="text-xs font-bold text-gray-600 uppercase tracking-wider ml-1 leading-none">Verify State</label>
                                        <span id="matchStatus" class="text-[10px] font-bold uppercase text-gray-400">Not Matched</span>
                                    </div>
                                    <div class="relative">
                                        <input type="password" id="confirmPasswordInput" required placeholder="••••••••"
                                            class="w-full px-6 py-4 bg-gray-50 border border-gray-100 rounded-2xl outline-none font-medium text-gray-800 tracking-widest transition-all focus:ring-2 focus:ring-red-500/20 focus:border-red-400 focus:bg-white pr-14 text-sm">
                                        <button type="button" onclick="toggleVisibility('confirmPasswordInput', 'eyeIcon2')"
                                            class="absolute right-4 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all">
                                            <svg id="eyeIcon2" fill="none" class="size-5" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 flex gap-4">
                                <button type="button" onclick="closeModalWithCheck('registrationModal', 'regForm')"
                                    class="flex-1 px-8 py-4 border-2 border-gray-100 text-gray-500 rounded-2xl hover:border-gray-200 hover:text-gray-900 transition-all font-black uppercase tracking-widest text-[10px] active:scale-95">
                                    Discard
                                </button>
                                <button type="submit" id="submitBtn" disabled
                                    class="flex-1 px-8 py-4 bg-red-600 hover:bg-gray-900 text-white rounded-2xl cursor-not-allowed transition-all font-black uppercase tracking-widest text-[10px] active:scale-95 shadow-lg shadow-red-100">
                                    Finalize Entry
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>


            <div id="deleteModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 opacity-0 pointer-events-none transition-opacity duration-300">
                <div
                    class="bg-white w-[380px] rounded-2xl p-6 text-center shadow-2xl transform scale-95 transition-transform duration-300">
                    <div
                        class="size-16 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">Delete Account?</h3>
                    <p class="text-sm text-gray-500 mt-2 px-4">This action is permanent. The user will lose
                        all access
                        to the system immediately.</p>
                    <form action="../include/inc.admin/admin.ctrl.php" method="POST" class="flex flex-col gap-2 mt-6">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id_input">

                        <div class="flex gap-3">
                            <button type="button" onclick="toggleModal('deleteModal')"
                                class=" card-style flex-1 px-4 py-2 border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition font-medium">
                                Cancel
                            </button>
                            <button type="submit"
                                class=" card-style flex-1 px-4 py-2 bg-red-500 text-white rounded-xl transition font-medium">
                                Delete Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Global Toast System -->
            <?php include '../include/toast.php'; ?>

            <!-- Discard Changes Modal -->
            <div id="discardModal" style="z-index: 9999;"
                class="fixed inset-0 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
                <div class="absolute inset-0 bg-black/50"></div>

                <div class="relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden transform transition-all p-8 text-center border border-gray-100">
                    <div class="w-16 h-16 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-orange-50/50">
                        <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Unsaved Changes</h3>
                    <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">You have unsaved changes. Are you sure you want to discard them?</p>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('discardModal')"
                            class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Keep Editing</button>
                        <button type="button" id="confirmDiscardBtn" onclick="confirmDiscardAction()"
                            class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
                    </div>
                </div>
            </div>

            <script>
                document.getElementById('generateFullReportBtn')?.addEventListener('click', () => {
                    window.location.href = '../include/reports.ctrl.php?action=generate_full_report';
                });
            </script>
</body>

</html>