<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
$cfg          = getAppConfig();
$currency     = htmlspecialchars($cfg['app']['currency']);
$pageTitle    = 'Items';
$pageSubtitle = 'Products & services catalogue';
$activePage   = 'items';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<!-- Toolbar -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
  <div class="btn-group btn-group-sm" id="typeTabs">
    <input type="radio" class="btn-check" name="iType" id="iAll"     value="" checked>
    <label class="btn btn-outline-secondary" for="iAll">All</label>
    <input type="radio" class="btn-check" name="iType" id="iItems"   value="itItems">
    <label class="btn btn-outline-secondary" for="iItems"><i class="bi bi-box-seam me-1"></i>Products</label>
    <input type="radio" class="btn-check" name="iType" id="iLabor"   value="itLabor">
    <label class="btn btn-outline-secondary" for="iLabor"><i class="bi bi-person-badge me-1"></i>Services</label>
  </div>
  <div class="input-group input-group-sm" style="max-width:220px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="itemSearch" class="form-control" placeholder="Code or name…">
  </div>
  <button class="btn btn-sm btn-outline-secondary ms-auto" id="syncBtn">
    <i class="bi bi-cloud-download me-1"></i>Sync SAP
  </button>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal" id="newItemBtn">
    <i class="bi bi-plus-lg me-1"></i>New Item
  </button>
</div>

<!-- Stats -->
<div class="row g-2 mb-3" id="itemStats">
  <div class="col-auto">
    <div class="badge badge-primary" id="statTotal" style="font-size:.8rem;padding:.4rem .8rem">0 items</div>
  </div>
</div>

<!-- Items table -->
<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Item Code</th><th>Name</th><th>Type</th>
          <th class="text-end">Price (<?= $currency ?>)</th>
          <th class="text-end">In Stock</th><th>Unit</th>
          <th>SAP</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="itemTableBody">
        <tr><td colspan="8" class="text-center py-5" style="color:var(--text-3)">
          <span class="spinner-border spinner-border-sm me-2"></span>Loading…
        </td></tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-between" style="font-size:.8rem;color:var(--text-3)">
    <span id="itemCount">—</span>
    <div id="itemPager" class="d-flex gap-1"></div>
  </div>
</div>

</div></div></div>

<!-- ═══════════════ Item Modal ═══════════════════════════════════════════════ -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="itemModalTitle"><i class="bi bi-box-seam me-2" style="color:var(--cyan)"></i>New Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="iEditCode">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Item Code <span class="text-danger">*</span></label>
            <input type="text" id="iCode" class="form-control font-monospace" placeholder="ITM001" style="text-transform:uppercase">
          </div>
          <div class="col-md-7">
            <label class="form-label">Item Name <span class="text-danger">*</span></label>
            <input type="text" id="iName" class="form-control" placeholder="Laptop Pro 15">
          </div>
          <div class="col-md-6">
            <label class="form-label">Type</label>
            <select id="iItemType" class="form-select">
              <option value="itItems">Product</option>
              <option value="itLabor">Service / Labor</option>
              <option value="itFixedAssets">Fixed Asset</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit</label>
            <input type="text" id="iUnit" class="form-control" value="EA" placeholder="EA">
          </div>
          <div class="col-md-3">
            <label class="form-label">Currency</label>
            <input type="text" id="iCurrency" class="form-control" value="<?= $currency ?>" maxlength="8">
          </div>
          <div class="col-md-6">
            <label class="form-label">Sales Price</label>
            <div class="input-group">
              <span class="input-group-text"><?= $currency ?></span>
              <input type="number" id="iPrice" class="form-control text-end" step="0.01" min="0" value="0">
            </div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="iPostSap" checked>
              <label class="form-check-label" for="iPostSap">Also create in SAP B1</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary px-4" id="saveItemBtn"><i class="bi bi-floppy me-1"></i>Save Item</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
const CURRENCY = '<?= $currency ?>';
let itemPage = 1;
const typeLabel = {itItems:'Product', itLabor:'Service', itFixedAssets:'Fixed Asset'};

async function loadItems() {
    const q     = document.getElementById('itemSearch').value.trim();
    const itype = document.querySelector('input[name="iType"]:checked').value;
    const res   = await fetch(`/api/items.php?page=${itemPage}&q=${encodeURIComponent(q)}&item_type=${itype}`);
    const data  = await res.json();
    const rows  = data.data || [];
    const tbody = document.getElementById('itemTableBody');
    document.getElementById('itemCount').textContent = `${data.total ?? 0} item(s)`;
    document.getElementById('statTotal').textContent = `${data.total ?? 0} items`;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5" style="color:var(--text-3)">No items. Use <strong>Sync SAP</strong> to import.</td></tr>';
        document.getElementById('itemPager').innerHTML = '';
        return;
    }

    tbody.innerHTML = rows.map(r => `<tr>
      <td><code style="color:var(--primary-text)">${escHtml(r.item_code)}</code></td>
      <td class="fw-600" style="font-size:.88rem">${escHtml(r.item_name)}</td>
      <td><span class="badge badge-secondary" style="font-size:.7rem">${typeLabel[r.item_type] || r.item_type}</span></td>
      <td class="text-end">${fmt(r.price)}</td>
      <td class="text-end ${r.item_type === 'itItems' ? (r.stock_qty > 0 ? 'text-success' : 'text-danger') : 'text-muted'}">${r.item_type === 'itItems' ? r.stock_qty : '—'}</td>
      <td style="color:var(--text-3);font-size:.8rem">${escHtml(r.unit)}</td>
      <td>${r.sap_synced ? '<span class="badge badge-success" style="font-size:.68rem"><i class="bi bi-check2"></i> SAP</span>' : '<span class="badge badge-secondary" style="font-size:.68rem">Local</span>'}</td>
      <td class="text-end">
        <button class="btn btn-xs btn-outline-secondary me-1" onclick="editItem('${escHtml(r.item_code)}')"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-xs btn-outline-danger" onclick="deleteItem('${escHtml(r.item_code)}')"><i class="bi bi-trash"></i></button>
      </td>
    </tr>`).join('');

    // Pager
    const pager = document.getElementById('itemPager');
    if ((data.pages || 1) <= 1) { pager.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= data.pages; i++) {
        html += `<button class="btn btn-xs ${i === itemPage ? 'btn-primary' : 'btn-outline-secondary'}" onclick="itemPage=${i};loadItems()">${i}</button>`;
    }
    pager.innerHTML = html;
}

async function editItem(code) {
    const res  = await fetch(`/api/items.php?q=${encodeURIComponent(code)}`);
    const data = await res.json();
    const item = (data.data || []).find(i => i.item_code === code);
    if (!item) return;

    document.getElementById('iEditCode').value   = item.item_code;
    document.getElementById('iCode').value        = item.item_code;
    document.getElementById('iCode').disabled     = true;
    document.getElementById('iName').value        = item.item_name;
    document.getElementById('iItemType').value    = item.item_type;
    document.getElementById('iPrice').value       = item.price;
    document.getElementById('iUnit').value        = item.unit;
    document.getElementById('iCurrency').value    = item.currency;
    document.getElementById('itemModalTitle').innerHTML = `<i class="bi bi-pencil-square me-2" style="color:var(--cyan)"></i>Edit Item`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).show();
}

async function deleteItem(code) {
    if (!confirm(`Delete item ${code}?`)) return;
    await fetch(`/api/items.php?code=${encodeURIComponent(code)}`, {method:'DELETE'});
    showToast('Item removed.', 'success');
    loadItems();
}

// ── Save item ─────────────────────────────────────────────────────────────────
document.getElementById('saveItemBtn').addEventListener('click', async () => {
    const editCode = document.getElementById('iEditCode').value;
    const payload = {
        item_code:   document.getElementById('iCode').value.trim().toUpperCase(),
        item_name:   document.getElementById('iName').value.trim(),
        item_type:   document.getElementById('iItemType').value,
        price:       parseFloat(document.getElementById('iPrice').value || 0),
        unit:        document.getElementById('iUnit').value.trim() || 'EA',
        currency:    document.getElementById('iCurrency').value.trim(),
        post_to_sap: document.getElementById('iPostSap').checked ? 1 : 0,
    };

    if (!payload.item_code || !payload.item_name) { showToast('Code and name are required.', 'warning'); return; }

    const btn = document.getElementById('saveItemBtn');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    try {
        let res;
        if (editCode) {
            res = await fetch(`/api/items.php?code=${encodeURIComponent(editCode)}`, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
        } else {
            res = await fetch('/api/items.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
        }
        const data = await res.json();
        if (data.success) {
            showToast('Item saved.', 'success');
            bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
            loadItems();
        } else {
            showToast(data.error || 'Failed.', 'danger');
        }
    } catch (e) {
        showToast(e.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});

// ── Sync SAP ──────────────────────────────────────────────────────────────────
document.getElementById('syncBtn').addEventListener('click', async () => {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing…';
    try {
        const res  = await fetch('/api/items.php?action=sync', {method:'POST'});
        const data = await res.json();
        showToast(data.success ? `Synced ${data.synced} items from SAP.` : (data.error || 'Sync failed.'), data.success ? 'success' : 'danger');
        if (data.success) loadItems();
    } catch (e) {
        showToast(e.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
});

// ── Reset modal on new ────────────────────────────────────────────────────────
document.getElementById('newItemBtn').addEventListener('click', () => {
    document.getElementById('iEditCode').value   = '';
    document.getElementById('iCode').value        = '';
    document.getElementById('iCode').disabled     = false;
    document.getElementById('iName').value        = '';
    document.getElementById('iItemType').value    = 'itItems';
    document.getElementById('iPrice').value       = '0';
    document.getElementById('iUnit').value        = 'EA';
    document.getElementById('itemModalTitle').innerHTML = '<i class="bi bi-box-seam me-2" style="color:var(--cyan)"></i>New Item';
});

let searchTimer;
document.getElementById('itemSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { itemPage=1; loadItems(); }, 350); });
document.querySelectorAll('input[name="iType"]').forEach(r => r.addEventListener('change', () => { itemPage=1; loadItems(); }));

loadItems();
</script>
</body></html>
