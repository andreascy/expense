<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// ── Sidebar ───────────────────────────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    sidebar.classList.add('open'); overlay.classList.add('show');
});
['sidebarClose','sidebarOverlay'].forEach(id =>
    document.getElementById(id)?.addEventListener('click', () => {
        sidebar.classList.remove('open'); overlay.classList.remove('show');
    })
);

// ── Theme toggle ──────────────────────────────────────────────────────────
(function() {
    const saved = localStorage.getItem('lumenTheme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.className = saved === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
})();

document.getElementById('themeToggle')?.addEventListener('click', () => {
    const curr = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = curr === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('lumenTheme', next);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
});

// ── Global toast ──────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    let wrap = document.getElementById('toastWrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'toastWrap';
        wrap.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        wrap.style.zIndex = 9999;
        document.body.appendChild(wrap);
    }
    const id   = 'toast_' + Date.now();
    const icon = type === 'success' ? 'bi-check-circle-fill'
               : type === 'warning' ? 'bi-exclamation-triangle-fill'
               : 'bi-x-circle-fill';
    wrap.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast app-toast toast-${type}" role="alert">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi ${icon}"></i><span>${msg}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    bootstrap.Toast.getOrCreateInstance(document.getElementById(id), {delay:4000}).show();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
const fmt = n => Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
</script>
