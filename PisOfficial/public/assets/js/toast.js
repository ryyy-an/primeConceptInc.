/**
 * Global Toast & Notification System
 * Handles session-based notifications and URL parameters
 */

window.showToast = function(message, type = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'bottom-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: '#ffffff',
        color: '#1f2937', 
        iconColor: type === 'success' ? '#16a34a' : '#dc2626',
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({
        icon: type === 'error' ? 'error' : 'success',
        title: message
    });
};

document.addEventListener("DOMContentLoaded", function() {
    // Check for explicit config div
    const toastConfig = document.getElementById('toast-config');
    const urlParams = new URLSearchParams(window.location.search);
    
    // 1. Check URL Parameters for Success
    if (urlParams.has('success')) {
        let msg = urlParams.get('success');
        let displayMsg = "Operation successful";
        if (msg === 'user_added') displayMsg = "Admin Account Generated Effectively!";
        if (msg === 'user_deleted') displayMsg = "Account Successfully Revoked!";
        if (msg === 'password_reset') displayMsg = "Security Key Successfully Reverified!";
        if (msg === 'migration_complete') displayMsg = "Database Safely Reseeded!";
        
        window.showToast(displayMsg, 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // 2. Check URL Parameters for Error
    if (urlParams.has('error')) {
        let err = urlParams.get('error');
        let displayErr = "An error occurred";
        if (err === 'exists') displayErr = "Conflict: Record already exists!";
        if (err === 'self_delete') displayErr = "Cannot delete your own active session!";
        if (err === 'numeric_name') displayErr = "Failure: Full Name cannot contain numbers!";
        if (err === 'invalid_input') displayErr = "Invalid parameters provided.";
        if (err === 'migration_failed') displayErr = "Database migration failed. Please check log files.";
        
        window.showToast(displayErr, 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // 3. Check for server-side triggered toasts (from data attributes)
    if (toastConfig) {
        const serverSuccess = toastConfig.getAttribute('data-success');
        const serverError = toastConfig.getAttribute('data-error');
        
        if (serverSuccess) window.showToast(serverSuccess, 'success');
        if (serverError) window.showToast(serverError, 'error');
    }
});
