<!-- SweetAlert2 Plugin -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Invisible Action Blocking Overlay -->
<div id="actionOverlay" class="fixed inset-0 z-[99999] hidden cursor-wait bg-slate-900/0"></div>

<script>
window.showToast = function(message, type = 'success') {
    const overlay = document.getElementById('actionOverlay');
    if (overlay) overlay.classList.remove('hidden');

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
    }).then(() => {
        if (overlay) overlay.classList.add('hidden');
    });
};
</script>
