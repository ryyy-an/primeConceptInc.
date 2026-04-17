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
            <div class="flex items-center gap-2 mt-1">
                <span class="size-1.5 bg-red-500 rounded-full animate-pulse"></span>
                <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">System Updates</p>
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
            Clear and Close
        </button>
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

        let lastSeenNotifId = 0;

        function fetchNotifications(isPolling = false) {
            fetch(`${ctrlPath}?action=get_notifications`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const notifs = res.notifications || [];
                        
                        // Check for new notifications to show toast
                        if (isPolling && notifs.length > 0) {
                            const newNotifs = notifs.filter(n => parseInt(n.id) > lastSeenNotifId && n.is_read == '0');
                            newNotifs.forEach(n => {
                                if (window.showToast) {
                                    window.showToast(`New ${n.title}`, 'success');
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
                .catch(err => console.error("Notif Error:", err));
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
                
                if (n.type === 'low_stock') { colorClass = 'bg-red-500'; tagClass = 'bg-red-100 text-red-600'; }
                if (n.type === 'result') { colorClass = 'bg-green-500'; tagClass = 'bg-green-100 text-green-600'; }
                if (n.type === 'fulfillment') { colorClass = 'bg-orange-500'; tagClass = 'bg-orange-100 text-orange-600'; }

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
                badge.className = 'absolute -top-1 -right-1 size-4 bg-red-600 text-white text-[8px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-sm transition-all animate-in zoom-in';
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

        if (btn) btn.addEventListener('click', (e) => { e.preventDefault(); showSidebar(); });
        closeBtn.addEventListener('click', hideSidebar);
        overlay.addEventListener('click', hideSidebar);
        document.addEventListener('keydown', (e) => { if (e.key === "Escape") hideSidebar(); });

        // Initial full fetch on load
        fetchNotifications(false);

        // Lightweight poll every 15s
        setInterval(() => {
            fetchNotifications(true);
        }, 15000); 
    });
</script>
