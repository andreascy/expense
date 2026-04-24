<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
$pageTitle    = 'SAP Setup';
$pageSubtitle = 'SAP Business One Service Layer connection';
$activePage   = 'sap';
include __DIR__ . '/../includes/head.php';
$settings = [];
try {
    $settings = getDb()->query("SELECT key, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {}
$cfg = getAppConfig();
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<div class="row g-4">
  <div class="col-xl-7">

    <!-- Connection -->
    <div class="card mb-4">
      <div class="card-header">
        <i class="bi bi-plug" style="color:var(--primary)"></i>
        Service Layer Connection
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Service Layer URL *</label>
          <input type="url" id="SAP_BASE_URL" class="form-control"
                 value="<?= htmlspecialchars($settings['SAP_BASE_URL'] ?? $cfg['sap']['base_url']) ?>"
                 placeholder="https://sap-server:50000/b1s/v1">
          <div class="form-text">Include /b1s/v1 at the end</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Company DB *</label>
          <input type="text" id="SAP_COMPANY_DB" class="form-control"
                 value="<?= htmlspecialchars($settings['SAP_COMPANY_DB'] ?? $cfg['sap']['company_db']) ?>"
                 placeholder="SBODEMOUS">
        </div>
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label">Username *</label>
            <input type="text" id="SAP_USERNAME" class="form-control"
                   value="<?= htmlspecialchars($settings['SAP_USERNAME'] ?? $cfg['sap']['username']) ?>"
                   placeholder="manager" autocomplete="off">
          </div>
          <div class="col-sm-6">
            <label class="form-label">Password *</label>
            <div class="input-group">
              <input type="password" id="SAP_PASSWORD" class="form-control"
                     placeholder="<?= empty($settings['SAP_PASSWORD']) ? 'Enter password' : '••••••••  (saved)' ?>"
                     autocomplete="new-password">
              <button type="button" class="input-group-text" id="toggleSapPwd" style="cursor:pointer">
                <i class="bi bi-eye" id="sapEye"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="mb-4">
          <div class="d-flex align-items-center gap-3">
            <input type="checkbox" class="form-check-input toggle" id="SAP_VERIFY_SSL"
                   <?= ($settings['SAP_VERIFY_SSL'] ?? 'false') === 'true' ? 'checked' : '' ?>>
            <div>
              <label class="form-label mb-0" for="SAP_VERIFY_SSL">Verify SSL Certificate</label>
              <div class="form-text">Disable for self-signed certificates (development)</div>
            </div>
          </div>
        </div>

        <div id="connStatus" class="connection-status idle mb-4">
          <i class="bi bi-circle" id="connIcon"></i>
          <span id="connText">Not tested yet</span>
        </div>

        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" id="testConnBtn">
            <i class="bi bi-lightning-charge me-1"></i>Test Connection
          </button>
          <button class="btn btn-primary" id="saveSapBtn">
            <i class="bi bi-floppy me-1"></i>Save Settings
          </button>
        </div>
      </div>
    </div>

    <!-- App settings -->
    <div class="card">
      <div class="card-header">
        <i class="bi bi-sliders" style="color:var(--cyan)"></i>
        Application Settings
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-sm-4">
            <label class="form-label">Currency Code</label>
            <input type="text" id="APP_CURRENCY" class="form-control"
                   value="<?= htmlspecialchars($settings['APP_CURRENCY'] ?? $cfg['app']['currency']) ?>"
                   placeholder="EUR" maxlength="3">
          </div>
          <div class="col-sm-4">
            <label class="form-label">Default VAT %</label>
            <div class="input-group">
              <input type="number" id="APP_VAT_RATE" class="form-control"
                     value="<?= htmlspecialchars($settings['APP_VAT_RATE'] ?? $cfg['app']['default_vat_rate']) ?>"
                     step="0.01" min="0" max="100" placeholder="19">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-sm-4">
            <label class="form-label">Company Name</label>
            <input type="text" id="APP_COMPANY" class="form-control"
                   value="<?= htmlspecialchars($settings['APP_COMPANY'] ?? $cfg['app']['company_name']) ?>"
                   placeholder="My Company Ltd">
          </div>
        </div>
        <button class="btn btn-primary" id="saveAppBtn">
          <i class="bi bi-floppy me-1"></i>Save App Settings
        </button>
      </div>
    </div>

  </div>

  <!-- Info panel -->
  <div class="col-xl-5">
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-info-circle" style="color:var(--cyan)"></i>Connection Info</div>
      <div class="card-body" style="font-size:.875rem">
        <div class="mb-3">
          <div style="color:var(--text-3);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem">Current URL</div>
          <code style="color:var(--primary-text);word-break:break-all"><?= htmlspecialchars($cfg['sap']['base_url']) ?></code>
        </div>
        <div class="mb-3">
          <div style="color:var(--text-3);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem">Company DB</div>
          <code style="color:var(--primary-text)"><?= htmlspecialchars($cfg['sap']['company_db']) ?></code>
        </div>
        <div>
          <div style="color:var(--text-3);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem">Username</div>
          <code style="color:var(--primary-text)"><?= htmlspecialchars($cfg['sap']['username']) ?></code>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-question-circle" style="color:var(--amber)"></i>Help</div>
      <div class="card-body" style="font-size:.85rem;color:var(--text-2)">
        <p class="mb-2"><strong style="color:var(--text-1)">Service Layer URL</strong><br>
          Format: <code>https://HOST:50000/b1s/v1</code></p>
        <p class="mb-2"><strong style="color:var(--text-1)">SSL Verify</strong><br>
          Disable if using self-signed certificates. Always enable in production.</p>
        <p class="mb-0"><strong style="color:var(--text-1)">Mock Server</strong><br>
          For testing without SAP, the mock runs at<br>
          <code>http://localhost:50001/b1s/v1</code></p>
      </div>
    </div>
  </div>
</div>

</div></div></div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
document.getElementById('toggleSapPwd').addEventListener('click', () => {
    const p = document.getElementById('SAP_PASSWORD');
    const i = document.getElementById('sapEye');
    p.type = p.type === 'password' ? 'text' : 'password';
    i.className = p.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

function getSapPayload() {
    return {
        SAP_BASE_URL:   document.getElementById('SAP_BASE_URL').value.trim(),
        SAP_COMPANY_DB: document.getElementById('SAP_COMPANY_DB').value.trim(),
        SAP_USERNAME:   document.getElementById('SAP_USERNAME').value.trim(),
        SAP_PASSWORD:   document.getElementById('SAP_PASSWORD').value,
        SAP_VERIFY_SSL: document.getElementById('SAP_VERIFY_SSL').checked ? 'true' : 'false',
    };
}

document.getElementById('testConnBtn').addEventListener('click', async () => {
    const s = document.getElementById('connStatus');
    const icon = document.getElementById('connIcon');
    const text = document.getElementById('connText');
    s.className = 'connection-status testing mb-4';
    icon.className = 'bi bi-arrow-repeat spin';
    text.textContent = 'Testing connection…';

    try {
        const res  = await fetch('/api/settings.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action:'test', ...getSapPayload() })
        });
        const data = await res.json();
        if (data.success) {
            s.className = 'connection-status success mb-4';
            icon.className = 'bi bi-check-circle-fill';
            text.textContent = 'Connection successful!';
            showToast('SAP B1 connection successful!');
        } else {
            s.className = 'connection-status error mb-4';
            icon.className = 'bi bi-x-circle-fill';
            text.textContent = data.error || 'Connection failed.';
            showToast(data.error || 'Connection failed.', 'error');
        }
    } catch(e) {
        s.className = 'connection-status error mb-4';
        icon.className = 'bi bi-x-circle-fill';
        text.textContent = 'Network error: ' + e.message;
    }
});

document.getElementById('saveSapBtn').addEventListener('click', async () => {
    const res  = await fetch('/api/settings.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'save', ...getSapPayload() })
    });
    const data = await res.json();
    data.success ? showToast('SAP settings saved.') : showToast(data.error||'Error.','error');
});

document.getElementById('saveAppBtn').addEventListener('click', async () => {
    const res = await fetch('/api/settings.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            action: 'save',
            APP_CURRENCY: document.getElementById('APP_CURRENCY').value.trim(),
            APP_VAT_RATE: document.getElementById('APP_VAT_RATE').value,
            APP_COMPANY:  document.getElementById('APP_COMPANY').value.trim(),
        })
    });
    const data = await res.json();
    data.success ? showToast('App settings saved.') : showToast(data.error||'Error.','error');
});
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
.spin { display:inline-block; animation: spin .8s linear infinite; }
</style>
</body></html>
