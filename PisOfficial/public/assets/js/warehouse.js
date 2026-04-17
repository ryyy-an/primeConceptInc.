/**
 * Warehouse Module - General Utilities
 * Shared JS logic for the warehouse dashboard, inventory, and reports.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Standardize initialization if needed
    console.log('[Warehouse] Module Initialized');
});

/**
 * Common logout modal toggle (if not handled by global.js)
 */
function toggleLogoutModal(show) {
    const modal = document.getElementById('logoutModal');
    if (!modal) return;
    
    if (show) {
        modal.classList.remove('hidden');
        setTimeout(() => modal.classList.add('opacity-100'), 10);
    } else {
        modal.classList.remove('opacity-100');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }
}
