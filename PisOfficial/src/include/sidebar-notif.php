<!-- Sidebar Overlay -->
<div id="sidebarOverlay"
    class="fixed inset-0 bg-black/40 z-[60] hidden opacity-0 transition-opacity duration-300">
</div>

<!-- Notification Sidebar -->
<div id="notificationSidebar"
    style="transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);"
    class="fixed top-0 right-0 h-full w-80 md:w-[450px] bg-white z-[70] shadow-2xl border-l border-gray-100 flex flex-col rounded-l-[2.5rem] overflow-hidden">

    <div class="h-[100px] px-8 border-b border-gray-100 flex justify-between items-center bg-white">
        <div>
            <h3 class="font-black text-gray-900 uppercase text-xs tracking-[0.2em]">Notifications</h3>
            <div class="flex items-center gap-4 mt-1">
                <div class="flex items-center gap-2">
                    <span class="size-1.5 bg-red-500 rounded-full animate-pulse"></span>
                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">System Updates</p>
                </div>
                <button onclick="clearAllNotifs()" class="text-[9px] font-black text-red-500 uppercase tracking-widest hover:underline active:scale-95 transition-transform">
                    Clear All
                </button>
            </div>
        </div>
        <button id="closeSidebar" class="p-2 rounded-xl hover:bg-red-50 text-gray-400 hover:text-red-500 transition-all active:scale-95">
            <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Notification List (Dynamic) -->
    <div id="notifList" class="flex-1 overflow-y-auto p-8 space-y-6">
        <div class="flex flex-col items-center justify-center h-full text-gray-400 space-y-4">
            <div class="size-12 border-4 border-gray-100 border-t-red-600 rounded-full animate-spin"></div>
            <p class="text-[10px] font-black uppercase tracking-widest">Checking alerts...</p>
        </div>
    </div>

    <div class="p-8 border-t border-gray-50 bg-white">
        <button onclick="markReadAndClose()" class="w-full py-4 bg-gray-50 hover:bg-gray-100 text-gray-500 hover:text-red-500 text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl transition-all active:scale-[0.98]">
            Mark as Read & Close
        </button>
    </div>
</div>

<div id="clearNotifModal" style="z-index: 9999;" class="fixed inset-0 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div class="relative bg-white w-full max-w-sm rounded-[2rem] shadow-2xl overflow-hidden transform transition-all p-8 text-center border border-gray-100">
        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-red-50/50">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </div>
        <h3 class="text-xl font-black text-gray-900 tracking-tight mb-2">Clear Alerts?</h3>
        <p class="text-sm font-medium text-gray-500 mb-8 leading-relaxed">This will permanently remove all notifications from your history. This action <span class="text-red-600 font-bold">cannot be undone</span>.</p>
        <div class="flex gap-3">
            <button type="button" onclick="toggleClearModal(false)" class="flex-1 py-4 border-2 border-gray-100 rounded-2xl font-bold text-gray-500 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-800 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Keep Alerts</button>
            <button type="button" onclick="executeClearNotifs()" class="flex-1 py-4 bg-red-500 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg shadow-red-100 active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em]">Clear All</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('notifButton');
        const sidebar = document.getElementById('notificationSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const closeBtn = document.getElementById('closeSidebar');
        const list = document.getElementById('notifList');

        if (!sidebar || !overlay || !closeBtn) return;

        // Unified centralized controller path
        const ctrlPath = '../include/global.ctrl.php';

        function showSidebar() {
            overlay.classList.remove('hidden');
            setTimeout(() => overlay.classList.add('opacity-100'), 10);
            sidebar.style.transform = 'translateX(0)';
            document.body.style.overflow = 'hidden';

            // Just refresh list, mark as read happens on CLOSE
            fetchNotifications();
        }

        function hideSidebar() {
            sidebar.style.transform = 'translateX(100%)';
            overlay.classList.remove('opacity-100');
            setTimeout(() => overlay.classList.add('hidden'), 300);
            document.body.style.overflow = '';

            // Mark all as read when closed
            fetch(`${ctrlPath}?action=mark_notifs_read`)
                .then(() => fetchNotifications());
        }

        window.markReadAndClose = function() {
            fetch(`${ctrlPath}?action=mark_notifs_read`)
                .then(() => {
                    fetchNotifications();
                    hideSidebar();
                });
        };

        window.toggleClearModal = function(show) {
            const modal = document.getElementById('clearNotifModal');
            const container = modal.querySelector('div');
            if (show) {
                modal.classList.remove('pointer-events-none', 'opacity-0');
                if (container) container.classList.remove('scale-95');
                if (container) container.classList.add('scale-100');
            } else {
                modal.classList.add('pointer-events-none', 'opacity-0');
                if (container) container.classList.remove('scale-100');
                if (container) container.classList.add('scale-95');
            }
        };

        window.clearAllNotifs = function() {
            toggleClearModal(true);
        };

        window.executeClearNotifs = function() {
            fetch(`${ctrlPath}?action=clear_all_notifs`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        renderNotifications([]); // Instant UI feedback
                        updateBadge(0);
                        toggleClearModal(false);
                    }
                });
        };

        let lastSeenNotifId = 0;

        function fetchNotifications(isPolling = false) {
            fetch(`${ctrlPath}?action=get_notifications`)
                .then(res => {
                    if (!res.ok) throw new Error("HTTP " + res.status);
                    return res.json();
                })
                .then(res => {
                    if (res && res.success) {
                        const notifs = res.notifications || [];

                        // Check for new notifications to show toast
                        if (isPolling && notifs.length > 0) {
                            const newNotifs = notifs.filter(n => parseInt(n.id) > lastSeenNotifId && n.is_read == '0');
                            newNotifs.forEach(n => {
                                if (window.showToast) {
                                    // Use the message content for the toast
                                    const shortMsg = n.message.split('\n')[1] || n.message.split('\n')[0];
                                    window.showToast(shortMsg, 'success');
                                }
                            });
                        }

                        // Update lastSeenNotifId
                        if (notifs.length > 0) {
                            const maxId = Math.max(...notifs.map(n => parseInt(n.id)));
                            if (maxId > lastSeenNotifId) lastSeenNotifId = maxId;
                        }

                        renderNotifications(notifs);
                        updateBadge(res.unread);
                    }
                })
                .catch(err => {
                    // Silently fail during polling to avoid console spam
                    if (!isPolling) console.error("Notif Error:", err);
                });
        }

        function renderNotifications(notifs) {
            if (!notifs || notifs.length === 0) {
                list.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-full text-gray-300 space-y-2 py-20">
                        <svg class="size-12 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <p class="text-[10px] font-black uppercase tracking-widest">No active alerts</p>
                    </div>`;
                return;
            }

            list.innerHTML = notifs.map(n => {
                let colorClass = 'bg-blue-500';
                let tagClass = 'bg-blue-100 text-blue-600';

                if (n.type === 'low_stock') {
                    colorClass = 'bg-red-500';
                    tagClass = 'bg-red-100 text-red-600';
                }
                if (n.type === 'result') {
                    colorClass = 'bg-green-500';
                    tagClass = 'bg-green-100 text-green-600';
                }
                if (n.type === 'fulfillment') {
                    colorClass = 'bg-orange-500';
                    tagClass = 'bg-orange-100 text-orange-600';
                }

                const time = timeAgo(new Date(n.created_at));

                return `
                    <div class="p-5 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-md transition-all group cursor-pointer relative overflow-hidden">
                        <div class="absolute left-0 top-0 h-full w-1 ${colorClass}"></div>
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 ${tagClass} text-[9px] font-black rounded-md uppercase tracking-tighter">${n.type.replace('_',' ')}</span>
                                ${n.is_read == '0' ? '<span class="px-1.5 py-0.5 bg-red-600 text-white text-[7px] font-black rounded flex items-center justify-center uppercase tracking-tighter animate-pulse">NEW</span>' : ''}
                            </div>
                            <span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">${time}</span>
                        </div>
                        <p class="text-[13px] text-gray-700 font-bold leading-relaxed whitespace-pre-line">
                            ${n.message}
                        </p>
                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-widest mt-2 italic">From: ${n.sender_name || 'System'}</p>
                    </div>
                `;
            }).join('');
        }

        function updateBadge(count) {
            const btn = document.getElementById('notifButton');
            if (!btn) return;

            let badge = document.getElementById('notifBadge');
            if (!badge) {
                // Inject badge container if not exists
                btn.classList.add('relative');
                badge = document.createElement('span');
                badge.id = 'notifBadge';
                badge.className = 'absolute top-0 right-0 transform translate-x-[90%] -translate-y-1/2 size-4 bg-red-600 text-white text-[8px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-sm transition-all animate-in zoom-in z-[100]';
                btn.appendChild(badge);
            }

            if (count > 0) {
                badge.textContent = count > 9 ? '9+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        function timeAgo(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            let interval = seconds / 31536000;
            if (interval > 1) return Math.floor(interval) + "y ago";
            interval = seconds / 2592000;
            if (interval > 1) return Math.floor(interval) + "mo ago";
            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + "d ago";
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + "h ago";
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + "m ago";
            return "Just now";
        }

        if (btn) btn.addEventListener('click', (e) => {
            e.preventDefault();
            showSidebar();
        });
        closeBtn.addEventListener('click', hideSidebar);
        overlay.addEventListener('click', hideSidebar);
        document.addEventListener('keydown', (e) => {
            if (e.key === "Escape") hideSidebar();
        });

        // Initial full fetch on load
        fetchNotifications(false);

        // Responsive 3s poll for real-time responsiveness
        setInterval(() => {
            fetchNotifications(true);
        }, 3000);
    });
</script>