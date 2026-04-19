<!-- logout-modal.php -->
<div id="logout-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-all duration-300">
    <div class="absolute inset-0 bg-black/40"></div>

    <div class="modal-box relative bg-white w-full max-w-sm rounded-2xl shadow-2xl overflow-hidden transform transition-all scale-95 opacity-0 duration-200" id="logout-content">
        <div class="p-6 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4 ring-8 ring-red-50/50">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </div>
            <h3 class="text-xl font-black text-gray-900 uppercase tracking-tight">Confirm Logout</h3>
            <p class="text-sm font-medium text-gray-500 mt-2 leading-relaxed px-4">Are you sure you want to end your current session?</p>
        </div>

        <div class="flex gap-3 p-4 bg-gray-50/50 border-t border-gray-100">
            <button data-close-modal="logout-modal" class="logout-close flex-1 px-4 py-3 bg-white border border-gray-200 text-gray-700 rounded-xl font-bold uppercase text-[10px] tracking-widest hover:bg-gray-100 transition-all active:scale-95">
                Stay
            </button>
            <button id="confirmLogoutBtn" data-action="confirm-logout"
                class="flex-1 px-4 py-3 bg-red-600 text-white rounded-xl font-bold uppercase text-[10px] tracking-widest hover:bg-red-700 shadow-lg shadow-red-200 transition-all active:scale-95 flex items-center justify-center gap-2">
                Logout
            </button>
        </div>
    </div>
</div>
