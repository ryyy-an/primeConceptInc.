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
            </a>

            <?php include '../include/logout-modal.php'; ?>

        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex gap-10 mt-6 container mx-auto">

        <!-- Nav Bars -->
        <aside class="w-64 flex flex-col gap-2">
            <h2 class="text-xl font-bold mb-4 px-4 text-gray-900">Settings</h2>

            <nav class="w-64 flex flex-col gap-2">
                <!-- User Accounts -->
                <a href="javascript:void(0)" onclick="showSettingSection('userManagement')" id="userManagementLink"
                    class="flex items-center gap-3 px-6 py-4 rounded-2xl bg-red-600 text-white shadow-lg shadow-red-100 transition duration-300 group active-setting-link">
                    <div class="size-10 bg-white/20 rounded-xl flex items-center justify-center group-hover:scale-110 transition duration-300">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-sm font-black uppercase tracking-widest block">User Accounts</span>
                        <span class="text-[10px] text-red-100 font-medium">Manage System Access</span>
                    </div>
                </a>

                <!-- Placeholder for future module -->
                <!-- Reports & Backups -->
                <a href="javascript:void(0)" onclick="showSettingSection('reportsBackup')" id="reportsBackupLink"
                    class="flex items-center gap-3 px-6 py-4 rounded-2xl text-gray-400 hover:bg-gray-50 transition duration-300 group">
                    <div class="size-10 bg-gray-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition duration-300">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-sm font-black uppercase tracking-widest block">System Config</span>
                        <span class="text-[10px] text-gray-400 font-medium">Reports & Backups</span>
                    </div>
                </a>
            </nav>
        </aside>

        <div class="flex-1 flex flex-col gap-6">

            <!-- Admin Stats Cards -->
            <div class="grid grid-cols-3 gap-6 mb-4">
                <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Total Staff</p>
                    <h2 class="text-3xl font-bold text-gray-900 mt-1"><?= number_format((float)$stats['total_staff']) ?></h2>
                    <p class="text-[10px] text-gray-400 mt-1 italic">Registered system users.</p>
                </div>
                <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Active Now</p>
                    <div class="flex items-center gap-2 mt-1">
                        <h2 class="text-3xl font-bold text-gray-900"><?= number_format((float)$stats['active_now']) ?></h2>
                        <span class="size-2.5 rounded-full bg-green-500 animate-pulse"></span>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1 italic">Users currently logged in.</p>
                </div>
                <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Admins</p>
                    <h2 class="text-3xl font-bold text-red-600 mt-1"><?= number_format((float)$stats['total_admins']) ?></h2>
                    <p class="text-[10px] text-red-400 mt-1 italic">System administrators.</p>
                </div>
            </div>

            <!-- User Managements Section -->
            <section id="userManagementSection" class="flex flex-1 bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">

                <div class="flex items-center justify-between p-6 border-b border-gray-100 bg-gray-50/50">

                    <div>
                        <h3 class="text-lg font-bold text-gray-900">User Accounts</h3>
                        <p class="text-sm text-gray-500">Create, update, or remove staff access to the system.</p>
                    </div>

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
                        class="card-style flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition shadow-md shadow-red-100">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New User
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase tracking-wider text-gray-400 bg-gray-100">
                                <th class="px-6 py-4 font-semibold">User Info</th>
                                <th class="px-6 py-4 font-semibold">Role</th>
                                <th class="px-6 py-4 font-semibold">Status</th>
                                <th class="px-6 py-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($allUsers)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-gray-400">No user accounts found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr class="hover:bg-gray-50/80 transition">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="size-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center font-bold text-sm">
                                                    <?= getInitials($user['full_name']) ?>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900">
                                                        <?= htmlspecialchars($user['full_name']) ?></p>
                                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($user['username']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center"> <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider
                            <?= $user['role'] === 'admin' ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-amber-50 text-amber-600 border border-amber-100' ?>">
                                                    <?= htmlspecialchars($user['role']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if (isset($user['is_online']) && $user['is_online'] == 1): ?>
                                                <div class="flex items-center gap-1.5 text-xs text-green-600 font-medium">
                                                    <span class="size-1.5 rounded-full bg-green-600 animate-pulse"></span>
                                                    Online
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center gap-1.5 text-xs text-gray-400 font-medium">
                                                    <span class="size-1.5 rounded-full bg-gray-300"></span>
                                                    Offline
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button onclick="confirmDelete(<?= $user['id'] ?>)"
                                                    class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                                    title="Delete User">
                                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">

                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />

                                                    </svg>
                                                </button>
                                                <form action="../include/inc.admin/admin.ctrl.php" method="POST">
                                                    <button type="button"
                                                        onclick="openResetModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')"
                                                        class="p-2 text-gray-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition"
                                                        title="Reset Password">
                                                        <svg class="size-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                        </svg>
                                                    </button>
                                                </form>
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

                <div class="bg-white border border-gray-200 rounded-3xl shadow-sm overflow-hidden flex flex-col h-full">
                    <div class="p-8 border-b border-gray-100 flex items-center justify-between bg-gray-50/30">
                        <div class="flex items-center gap-4">
                            <div class="size-12 bg-red-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-red-100">
                                <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-gray-900 tracking-tight leading-none mb-1 uppercase">Reports & Backups</h3>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest italic">Generate system reports and manage database files</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8 flex-1">
                        <!-- Left Column: Generate Reports -->
                        <div>
                            <div class="border-b border-gray-50 pb-2 mb-4">
                                <h4 class="text-xs font-black text-red-600 uppercase tracking-tighter">System Reports</h4>
                            </div>
                            
                            <div class="p-6 rounded-2xl border border-gray-100 flex flex-col items-center justify-center gap-4 text-center hover:border-red-200 hover:bg-red-50/20 transition-all group cursor-pointer h-[250px]">
                                <div class="size-16 bg-gray-50 text-gray-400 group-hover:text-red-500 group-hover:bg-red-100 transition-colors rounded-xl flex items-center justify-center">
                                    <svg class="size-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a2 2 0 012-2h2a2 2 0 012 2v2m-6-9a3 3 0 116 0 3 3 0 01-6 0zm11 2a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 012-2 2 2 0 012-2h10z" /></svg>
                                </div>
                                <div>
                                    <h5 class="font-bold text-gray-800 text-base mb-1">Full System Report</h5>
                                    <p class="text-xs text-gray-500 mb-5 px-6 leading-relaxed">Generate a comprehensive summary of inventory, staff performance, and complete sales data across all system modules.</p>
                                    <button class="px-6 py-3 bg-gray-900 hover:bg-black text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition active:scale-95 shadow-lg">Generate Report</button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Backups -->
                        <div>
                            <div class="border-b border-gray-50 pb-2 mb-4">
                                <h4 class="text-xs font-black text-red-600 uppercase tracking-tighter">Data Backup & Recovery</h4>
                            </div>
                            <div class="grid gap-4">
                                <div class="p-5 border border-gray-100 rounded-2xl flex items-center justify-between hover:border-gray-200 transition-all bg-gray-50/50 group h-[117px]">
                                    <div class="flex items-center gap-4">
                                        <div class="size-12 bg-white shadow-sm border border-gray-200 rounded-xl flex items-center justify-center text-gray-500 group-hover:text-gray-900 transition-colors">
                                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-bold text-gray-800 leading-tight mb-0.5">Save New Backup</h5>
                                            <p class="text-[10px] text-gray-500 font-medium tracking-tight">Export database state</p>
                                        </div>
                                    </div>
                                    <button class="shrink-0 px-4 py-2 bg-white border border-gray-200 text-gray-700 hover:text-black hover:border-gray-300 rounded-xl text-xs font-bold transition shadow-sm active:scale-95">Download .sql</button>
                                </div>

                                <div class="p-5 border border-gray-100 rounded-2xl flex items-center justify-between hover:border-gray-200 hover:bg-gray-50/50 transition-all bg-white group h-[117px]">
                                    <div class="flex items-center gap-4">
                                        <div class="size-12 bg-white flex items-center justify-center text-gray-500 rounded-xl border border-gray-200 group-hover:text-gray-900 transition-colors">
                                            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-bold text-gray-800 leading-tight mb-0.5">Restore Backup</h5>
                                            <p class="text-[10px] text-gray-500 font-medium tracking-tight">Upload previous version</p>
                                        </div>
                                    </div>
                                    <button class="shrink-0 px-4 py-2 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-bold transition shadow-md active:scale-95 border border-transparent">Select File</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>


            <div id="resetModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 opacity-0 pointer-events-none transition-all duration-300">
                <div
                    class="bg-white w-full max-w-[400px] rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 border border-slate-100">

                    <div class="bg-amber-50 border-b border-amber-100 px-6 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-amber-900 flex items-center gap-2">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            Manual Password Reset
                        </h3>
                        <button onclick="toggleModal('resetModal')" class="text-amber-400 hover:text-amber-600">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path d="M6 18L18 6M6 6l12 12" stroke-width="2" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6">
                        <p class="text-sm text-slate-600 mb-4">
                            Set a new password for <span id="reset_user_display_name"
                                class="font-bold text-slate-900 underline decoration-amber-300"></span>.
                        </p>

                        <form action="../include/inc.admin/admin.ctrl.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="user_id" id="reset_user_id_input">

                            <div class="space-y-1">
                                <label class="text-[10px] uppercase font-bold text-slate-400 tracking-widest ml-1">New Password</label>
                                <div class="relative">
                                    <input type="password" name="new_password" id="new_password" required placeholder="••••••••"
                                        class="w-full pl-4 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition font-mono">

                                    <button type="button" onclick="toggleNewPassword()"
                                        class="cursor-pointer absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-amber-600 transition-colors focus:outline-none">
                                        <svg id="newEyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.644C3.67 8.5 7.652 6 12 6c4.348 0 8.332 2.5 9.964 5.678.15.287.15.629 0 .916C20.332 15.5 16.348 18 12 18c-4.348 0-8.332-2.5-9.964-5.678Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>


                            <div class="flex gap-3">
                                <button type="button" onclick="toggleModal('resetModal')"
                                    class=" card-style flex-1 px-4 py-2 border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition font-medium">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class=" card-style flex-1 px-4 py-2 border bg-slate-900 text-white rounded-xl transition font-medium">
                                    Update Password
                                </button>


                            </div>
                        </form>
                    </div>
                </div>
            </div>


            <div id="registrationModal"
                class="fixed inset-0 z-50 opacity-0 pointer-events-none flex items-center justify-center bg-black/40 p-4 transition-all duration-300">
                <div
                    class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden transform transition-all border border-slate-100">

                    <div class="bg-slate-50 border-b border-slate-100 px-6 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-slate-800">Register New Staff</h3>
                        <button type="button" onclick="closeModalWithCheck('registrationModal', 'regForm')"
                            class="text-slate-400 hover:text-slate-600 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form id="regForm" action="../include/inc.admin/admin.ctrl.php" method="POST" class="p-8 space-y-6"
                        oninput="window.formHasUnsavedChanges = true">
                        <input type="hidden" name="action" value="add_user">

                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="block text-sm font-bold text-slate-700 ml-1">Full Name</label>
                                <span id="nameStatus" class="text-[10px] font-bold uppercase text-slate-400">Min 3
                                    chars</span>
                            </div>
                            <input type="text" name="full_name" id="regFullName" required
                                placeholder="e.g. Juan Dela Cruz"
                                class="w-full px-5 py-3.5 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-black outline-none transition-all bg-slate-50 focus:bg-white text-base font-medium">
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-bold text-slate-700 ml-1">Username</label>
                                    <span id="userStatus"
                                        class="text-[10px] font-bold uppercase text-slate-400">Required</span>
                                </div>
                                <input type="text" name="username" id="regUsername" required placeholder="j.delacruz01"
                                    class="w-full px-5 py-3.5 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-black outline-none transition-all bg-slate-50 focus:bg-white text-base font-medium">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2 ml-1">Role</label>
                                <select name="role"
                                    class="w-full px-5 py-3.5 border border-slate-200 rounded-2xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-black outline-none transition-all cursor-pointer text-base font-medium">
                                    <option value="showroom">Showroom</option>
                                    <option value="warehouse">Warehouse</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="block text-sm font-bold text-slate-700 mb-2 ml-1">Temporary Password</label>
                                <div class="relative">
                                    <input type="password" name="password" id="passwordInput" required placeholder="••••••••"
                                        class="w-full px-5 py-3.5 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-black outline-none transition-all bg-slate-50 focus:bg-white text-base font-medium pr-12">
                                    <button type="button" onclick="toggleVisibility('passwordInput', 'eyeIcon1')"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-black transition-colors focus:outline-none">
                                        <svg id="eyeIcon1" fill="none" class="w-5 h-5" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="mt-2 space-y-1">
                                    <div class="flex h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div id="strengthBar" class="w-0 transition-all duration-500 ease-out"></div>
                                    </div>
                                    <p id="strengthText" class="text-[10px] font-medium uppercase tracking-wider text-slate-400">Enter a password</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-bold text-slate-700 ml-1">Confirm Password</label>
                                    <span id="matchStatus" class="text-[10px] font-bold uppercase text-slate-400">Not Matched</span>
                                </div>
                                <div class="relative">
                                    <input type="password" id="confirmPasswordInput" required placeholder="••••••••"
                                        class="w-full px-5 py-3.5 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-black outline-none transition-all bg-slate-50 focus:bg-white text-base font-medium pr-12">
                                    <button type="button" onclick="toggleVisibility('confirmPasswordInput', 'eyeIcon2')"
                                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-black transition-colors focus:outline-none">
                                        <svg id="eyeIcon2" fill="none" class="w-5 h-5" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-[11px] text-slate-500 italic mt-2">Notice: Match the password above.</p>
                            </div>
                        </div>

                        <div class="pt-6 flex gap-4">
                            <button type="button"
                                onclick="closeModalWithCheck('registrationModal', 'regForm')"
                                class=" card-style flex-1 px-6 py-4 border border-slate-200 text-slate-600 rounded-2xl hover:bg-slate-100 transition font-bold uppercase tracking-widest text-xs">
                                Cancel
                            </button>
                            <button type="submit" id="submitBtn" disabled
                                class=" card-style flex-1 px-6 py-4 bg-red-500 hover:bg-red-700 text-white rounded-2xl cursor-not-allowed transition font-bold uppercase tracking-widest text-xs">
                                Create Account
                            </button>
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
            <div id="discardModal"
                class="fixed inset-0 z-[1000] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
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
                    <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">Mayroon kang mga sinimulang ilagay. Sigurado ka ba na gusto mong i-discard ang mga changes na ito?</p>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('discardModal')"
                            class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Keep Editing</button>
                        <button type="button" id="confirmDiscardBtn"
                            class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Discard</button>
                    </div>
                </div>
            </div>

</body>

</html>