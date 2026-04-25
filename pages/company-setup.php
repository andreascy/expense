<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: /index.php'); exit;
}
$pageTitle    = 'Company Setup';
$pageSubtitle = 'Brand & contact information';
$activePage   = 'company-setup';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<div class="row g-4">

  <!-- Logo card -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-image me-1" style="color:var(--primary)"></i>Company Logo</div>
      <div class="card-body d-flex flex-column align-items-center gap-3">
        <div id="logoPreviewWrap" style="width:120px;height:120px;border-radius:16px;overflow:hidden;background:var(--bg-surface);display:flex;align-items:center;justify-content:center;border:2px dashed var(--border)">
          <img id="logoPreview" src="" alt="" style="width:100%;height:100%;object-fit:contain;display:none">
          <i id="logoPlaceholder" class="bi bi-building" style="font-size:2.5rem;color:var(--text-3)"></i>
        </div>
        <label class="btn btn-sm btn-outline-primary w-100" for="logoFile">
          <i class="bi bi-upload me-1"></i>Upload Logo
          <input type="file" id="logoFile" accept="image/*" class="d-none">
        </label>
        <button class="btn btn-sm btn-outline-danger w-100 d-none" id="removeLogoBtn">
          <i class="bi bi-trash me-1"></i>Remove Logo
        </button>
        <p class="mb-0" style="font-size:.75rem;color:var(--text-3);text-align:center">
          Recommended: 200×200px, PNG/SVG with transparent background
        </p>
      </div>
    </div>
  </div>

  <!-- Company details -->
  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-building me-1" style="color:var(--cyan)"></i>Company Information</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Company Name</label>
            <input type="text" id="companyName" class="form-control" placeholder="My Company Ltd.">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tax / VAT Number</label>
            <input type="text" id="companyTax" class="form-control" placeholder="DE123456789">
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" id="companyPhone" class="form-control" placeholder="+49 30 1234567">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" id="companyEmail" class="form-control" placeholder="info@company.com">
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea id="companyAddress" class="form-control" rows="2" placeholder="Street, City, Country"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Country</label>
            <input type="text" id="companyCountry" class="form-control" placeholder="Germany">
          </div>
          <div class="col-md-6">
            <label class="form-label">Website</label>
            <input type="text" id="companyWebsite" class="form-control" placeholder="https://company.com">
          </div>
        </div>
      </div>
    </div>

    <!-- Crystal / Reports -->
    <div class="card mt-3">
      <div class="card-header"><i class="bi bi-file-earmark-pdf me-1" style="color:var(--amber)"></i>Reports Configuration</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Crystal Report Base URL <span style="color:var(--text-3);font-size:.8rem">(SAP gateway report URL)</span></label>
            <input type="url" id="crystalUrl" class="form-control font-monospace" placeholder="http://sap-server:8080/reports/BPStatement">
            <div class="form-text" style="color:var(--text-3)">Used on the Business Partners page to open Crystal Reports for a selected partner. The partner code is appended as <code>?CardCode=…</code></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Default Currency</label>
            <input type="text" id="appCurrency" class="form-control" maxlength="8" placeholder="EUR">
          </div>
          <div class="col-md-6">
            <label class="form-label">Default VAT %</label>
            <input type="number" id="appVat" class="form-control" step="0.01" min="0" max="100" placeholder="19">
          </div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /row -->

<!-- Save bar -->
<div class="d-flex justify-content-end gap-2 mt-4">
  <button class="btn btn-outline-secondary" id="resetBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
  <button class="btn btn-primary px-4" id="saveBtn"><i class="bi bi-floppy me-1"></i>Save Changes</button>
</div>

</div></div></div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
let removeLogo = false;
let originalData = {};

// ── load current settings ─────────────────────────────────────────────────────
async function loadSettings() {
    const res  = await fetch('/api/company.php');
    const data = await res.json();
    const s    = data.settings ?? {};
    originalData = s;

    set('companyName',    s.APP_COMPANY         ?? '');
    set('companyTax',     s.COMPANY_TAX_NO      ?? '');
    set('companyPhone',   s.COMPANY_PHONE       ?? '');
    set('companyEmail',   s.COMPANY_EMAIL       ?? '');
    set('companyAddress', s.COMPANY_ADDRESS     ?? '');
    set('companyCountry', s.COMPANY_COUNTRY     ?? '');
    set('companyWebsite', s.COMPANY_WEBSITE     ?? '');
    set('crystalUrl',     s.COMPANY_CRYSTAL_URL ?? '');
    set('appCurrency',    s.APP_CURRENCY        ?? 'EUR');
    set('appVat',         s.APP_VAT_RATE        ?? '19');

    if (s.COMPANY_LOGO) {
        showLogo(s.COMPANY_LOGO);
    }
}

function set(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

function showLogo(src) {
    const img = document.getElementById('logoPreview');
    const ph  = document.getElementById('logoPlaceholder');
    const rm  = document.getElementById('removeLogoBtn');
    img.src = src; img.style.display = 'block';
    ph.style.display = 'none';
    rm.classList.remove('d-none');
}

function clearLogo() {
    const img = document.getElementById('logoPreview');
    const ph  = document.getElementById('logoPlaceholder');
    const rm  = document.getElementById('removeLogoBtn');
    img.src = ''; img.style.display = 'none';
    ph.style.display = '';
    rm.classList.add('d-none');
}

// ── logo file preview ─────────────────────────────────────────────────────────
document.getElementById('logoFile').addEventListener('change', function () {
    if (!this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => { showLogo(e.target.result); removeLogo = false; };
    reader.readAsDataURL(this.files[0]);
});

document.getElementById('removeLogoBtn').addEventListener('click', () => {
    clearLogo();
    removeLogo = true;
    document.getElementById('logoFile').value = '';
});

// ── save ──────────────────────────────────────────────────────────────────────
document.getElementById('saveBtn').addEventListener('click', async () => {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    try {
        const fd = new FormData();
        fd.append('APP_COMPANY',         document.getElementById('companyName').value.trim());
        fd.append('COMPANY_TAX_NO',      document.getElementById('companyTax').value.trim());
        fd.append('COMPANY_PHONE',       document.getElementById('companyPhone').value.trim());
        fd.append('COMPANY_EMAIL',       document.getElementById('companyEmail').value.trim());
        fd.append('COMPANY_ADDRESS',     document.getElementById('companyAddress').value.trim());
        fd.append('COMPANY_COUNTRY',     document.getElementById('companyCountry').value.trim());
        fd.append('COMPANY_WEBSITE',     document.getElementById('companyWebsite').value.trim());
        fd.append('COMPANY_CRYSTAL_URL', document.getElementById('crystalUrl').value.trim());
        fd.append('APP_CURRENCY',        document.getElementById('appCurrency').value.trim() || 'EUR');
        fd.append('APP_VAT_RATE',        document.getElementById('appVat').value.trim() || '19');
        if (removeLogo) fd.append('remove_logo', '1');

        const logoFile = document.getElementById('logoFile').files[0];
        if (logoFile) fd.append('logo', logoFile);

        const res  = await fetch('/api/company.php', {method:'POST', body:fd});
        const data = await res.json();

        if (data.success) {
            showToast('Company settings saved.', 'success');
            if (data.logo_url) showLogo(data.logo_url);
            // Reload sidebar logo
            const sidebarLogo = document.getElementById('sidebarLogo');
            if (sidebarLogo && data.logo_url) sidebarLogo.src = data.logo_url;
        } else {
            showToast(data.error ?? 'Save failed.', 'danger');
        }
    } catch (e) {
        showToast(e.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-floppy me-1"></i>Save Changes';
    }
});

// ── reset ─────────────────────────────────────────────────────────────────────
document.getElementById('resetBtn').addEventListener('click', () => {
    loadSettings();
    removeLogo = false;
    document.getElementById('logoFile').value = '';
});

loadSettings();
</script>
</body></html>
