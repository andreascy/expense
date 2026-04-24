<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    sidebar.classList.add('open');
    overlay.classList.add('show');
});
['sidebarClose','sidebarOverlay'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    });
});

// Global toast
function showToast(msg, type = 'success') {
    const wrap = document.getElementById('toastWrap') || (() => {
        const d = document.createElement('div');
        d.id = 'toastWrap';
        d.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(d);
        return d;
    })();
    const id = 'toast_' + Date.now();
    const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill';
    wrap.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast app-toast toast-${type}" role="alert">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi ${icon}"></i>
                <span>${msg}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    bootstrap.Toast.getOrCreateInstance(document.getElementById(id), { delay: 4000 }).show();
}
</script>
