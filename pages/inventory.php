<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
$cfg          = getAppConfig();
$currency     = htmlspecialchars($cfg['app']['currency']);
$pageTitle    = 'Inventory';
$pageSubtitle = 'Stock levels & goods movements';
$activePage   = 'inventory';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<!-- Toolbar -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
  <div class="input-group input-group-sm" style="max-width:220px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="invSearch" class="form-control" placeholder="Item code or name…">
  </div>
  <button class="btn btn-sm btn-outline-primary" id="refreshInvBtn">
    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
  </button>
  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#receiptModal">
      <i class="bi bi-box-arrow-in-down me-1"></i>Goods Receipt
    </button>
    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#issueModal">
      <i class="bi bi-box-arrow-up me-1"></i>Goods Issue
    </button>
  </div>
</div>

<!-- KPI row -->
<div class="row g-3 mb-4" id="invKpi">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-primary"><i class="bi bi-boxes"></i></div>
      <div><div class="stat-value" id="kpiItems">—</div><div class="stat-label">Item Lines</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-green"><i class="bi bi-arrow-down-circle"></i></div>
      <div><div class="stat-value" id="kpiInStock">—</div><div class="stat-label">Total In-Stock</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-red"><i class="bi bi-exclamation-triangle"></i></div>
      <div><div class="stat-value" id="kpiLow">—</div><div class="stat-label">Low / Zero Stock</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-cyan"><i class="bi bi-building"></i></div>
      <div><div class="stat-value" id="kpiWarehouses">—</div><div class="stat-label">Warehouses</div></div>
    </div>
  </div>
</div>

<!-- Stock table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-table me-1" style="color:var(--primary)"></i>Stock Overview</span>
    <span style="font-size:.75rem;color:var(--text-3)" id="invTimestamp">—</span>
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Item Code</th><th>Item Name</th><th>Warehouse</th>
          <th class="text-end">In Stock</th>
          <th class="text-end">Committed</th>
          <th class="text-end">On Order</th>
          <th class="text-end">Available</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="invTableBody">
        <tr><td colspan="8" class="text-center py-5" style="color:var(--text-3)">
          <span class="spinner-border spinner-border-sm me-2"></span>Loading from SAP…
        </td></tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer" style="font-size:.8rem;color:var(--text-3)" id="invFooter">—</div>
</div>

</div></div></div>

<!-- ═══════════════════ Goods Receipt Modal ══════════════════════════════════ -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2" style="color:var(--green)"></i>Goods Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="text" id="recDate" class="form-control" placeholder="YYYY-MM-DD">
          </div>
          <div class="col-md-8">
            <label class="form-label">Remarks</label>
            <input type="text" id="recMemo" class="form-control" placeholder="Supplier invoice, delivery note…">
          </div>
        </div>
        <table class="table je-table mb-2" id="recTable">
          <thead><tr><th>Item</th><th style="width:100px">Qty</th><th style="width:130px">Unit Price</th><th style="width:120px">Warehouse</th><th style="width:36px"></th></tr></thead>
          <tbody id="recLines"></tbody>
        </table>
        <button class="btn btn-sm btn-outline-primary" onclick="addMoveLine('rec')"><i class="bi bi-plus-circle me-1"></i>Add Line</button>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success px-4" id="postReceiptBtn"><i class="bi bi-send me-1"></i>Post Receipt</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════ Goods Issue Modal ════════════════════════════════════ -->
<div class="modal fade" id="issueModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-box-arrow-up me-2" style="color:var(--red)"></i>Goods Issue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">Date</label>
            <input type="text" id="issDate" class="form-control" placeholder="YYYY-MM-DD">
          </div>
          <div class="col-md-8">
            <label class="form-label">Remarks</label>
            <input type="text" id="issMemo" class="form-control" placeholder="Reason for issue…">
          </div>
        </div>
        <table class="table je-table mb-2" id="issTable">
          <thead><tr><th>Item</th><th style="width:100px">Qty</th><th style="width:120px">Warehouse</th><th style="width:36px"></th></tr></thead>
          <tbody id="issLines"></tbody>
        </table>
        <button class="btn btn-sm btn-outline-danger" onclick="addMoveLine('iss')"><i class="bi bi-plus-circle me-1"></i>Add Line</button>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger px-4" id="postIssueBtn"><i class="bi bi-send me-1"></i>Post Issue</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
let moveIdx = 0;

// ── Load stock ─────────────────────────────────────────────────────────────────
async function loadInventory() {
    const q     = document.getElementById('invSearch').value.trim();
    const tbody = document.getElementById('invTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4" style="color:var(--text-3)"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr>';

    try {
        const res  = await fetch(`/api/inventory.php?action=stock&top=100&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        const rows = data.data || [];

        document.getElementById('invTimestamp').textContent = 'Updated ' + new Date().toLocaleTimeString();
        document.getElementById('invFooter').textContent    = `${rows.length} line(s) loaded from SAP`;

        // KPIs
        const warehouses = [...new Set(rows.map(r => r.WarehouseCode))];
        const totalStock = rows.reduce((s, r) => s + (parseFloat(r.InStock) || 0), 0);
        const lowCount   = rows.filter(r => (parseFloat(r.InStock) || 0) <= 0).length;
        document.getElementById('kpiItems').textContent      = rows.length;
        document.getElementById('kpiInStock').textContent    = Math.round(totalStock);
        document.getElementById('kpiLow').textContent        = lowCount;
        document.getElementById('kpiWarehouses').textContent = warehouses.length;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5" style="color:var(--text-3)">No stock data.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const inStock  = parseFloat(r.InStock  ?? 0);
            const committed= parseFloat(r.Committed ?? 0);
            const onOrder  = parseFloat(r.OnOrder   ?? 0);
            const avail    = inStock - committed;
            const stockCls = inStock <= 0 ? 'text-danger' : (inStock < 5 ? 'text-warning' : 'text-success');
            const availCls = avail   <= 0 ? 'text-danger' : '';
            const status   = inStock <= 0 ? '<span class="badge badge-danger">Out of Stock</span>'
                           : (inStock < 5 ? '<span class="badge badge-warning">Low Stock</span>'
                           : '<span class="badge badge-success">In Stock</span>');
            return `<tr>
              <td><code style="color:var(--primary-text)">${escHtml(r.ItemCode)}</code></td>
              <td style="font-size:.85rem" class="fw-600">${escHtml(r.ItemName||'')}</td>
              <td><span class="badge badge-secondary" style="font-size:.7rem">${escHtml(r.WarehouseCode||'')}</span></td>
              <td class="text-end fw-600 ${stockCls}">${inStock}</td>
              <td class="text-end" style="color:var(--text-3)">${committed}</td>
              <td class="text-end" style="color:var(--text-3)">${onOrder}</td>
              <td class="text-end fw-600 ${availCls}">${avail}</td>
              <td>${status}</td>
            </tr>`;
        }).join('');

    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${escHtml(e.message)}</td></tr>`;
    }
}

// ── Movement line helpers ──────────────────────────────────────────────────────
function addMoveLine(prefix) {
    const idx  = moveIdx++;
    const tbody = document.getElementById(`${prefix}Lines`);
    const modal = prefix === 'rec' ? '#receiptModal' : '#issueModal';
    const isRec = prefix === 'rec';
    const tr   = document.createElement('tr');
    tr.id = `${prefix}line_${idx}`;
    tr.innerHTML = `
      <td><select id="${prefix}item_${idx}" class="form-select form-select-sm" style="width:100%"></select></td>
      <td><input type="number" id="${prefix}qty_${idx}" class="je-input form-control-sm w-100 text-end" value="1" min="0.001" step="any"></td>
      ${isRec ? `<td><input type="number" id="${prefix}price_${idx}" class="je-input form-control-sm w-100 text-end" value="0" min="0" step="any"></td>` : ''}
      <td><input type="text" id="${prefix}wh_${idx}" class="je-input form-control-sm w-100" value="01" placeholder="WH01"></td>
      <td><button class="btn btn-xs btn-link text-danger p-0" onclick="document.getElementById('${prefix}line_${idx}').remove()"><i class="bi bi-x-lg"></i></button></td>`;
    tbody.appendChild(tr);

    $(`#${prefix}item_${idx}`).select2({
        dropdownParent: $(modal),
        placeholder: 'Search item…',
        allowClear: true,
        ajax: {
            url: '/api/get-items.php',
            dataType: 'json', delay: 250,
            data: p => ({q: p.term}),
            processResults: d => d,
        },
    });
}

// ── Post movement ─────────────────────────────────────────────────────────────
async function postMovement(action) {
    const prefix = action === 'receipt' ? 'rec' : 'iss';
    const isRec  = action === 'receipt';
    const date   = document.getElementById(`${prefix}Date`).value;
    const memo   = document.getElementById(`${prefix}Memo`).value;
    const lines  = [];
    document.querySelectorAll(`[id^="${prefix}line_"]`).forEach(tr => {
        const i    = tr.id.replace(`${prefix}line_`, '');
        const item = $(`#${prefix}item_${i}`).select2('data')[0];
        if (!item) return;
        const l = {
            item_code: item.id,
            quantity:  parseFloat(document.getElementById(`${prefix}qty_${i}`)?.value || 1),
            warehouse: document.getElementById(`${prefix}wh_${i}`)?.value || '01',
        };
        if (isRec) l.unit_price = parseFloat(document.getElementById(`${prefix}price_${i}`)?.value || 0);
        lines.push(l);
    });

    if (!lines.length) { showToast('Add at least one line.', 'warning'); return; }

    const btn = document.getElementById(isRec ? 'postReceiptBtn' : 'postIssueBtn');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    try {
        const res  = await fetch(`/api/inventory.php?action=${action}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({doc_date: date, memo, lines}),
        });
        const data = await res.json();
        if (data.success) {
            showToast(`${isRec ? 'Goods Receipt' : 'Goods Issue'} posted #${data.doc_num || data.doc_entry}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById(isRec ? 'receiptModal' : 'issueModal')).hide();
            loadInventory();
        } else {
            showToast(data.error || 'Failed.', 'danger');
        }
    } catch (e) {
        showToast(e.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

document.getElementById('postReceiptBtn').addEventListener('click', () => postMovement('receipt'));
document.getElementById('postIssueBtn').addEventListener('click',   () => postMovement('issue'));
document.getElementById('refreshInvBtn').addEventListener('click', loadInventory);

// Init datepickers on modal show
document.getElementById('receiptModal').addEventListener('show.bs.modal', () => {
    flatpickr('#recDate', {dateFormat:'Y-m-d', defaultDate:'today'});
    document.getElementById('recLines').innerHTML = '';
    addMoveLine('rec');
});
document.getElementById('issueModal').addEventListener('show.bs.modal', () => {
    flatpickr('#issDate', {dateFormat:'Y-m-d', defaultDate:'today'});
    document.getElementById('issLines').innerHTML = '';
    addMoveLine('iss');
});

let searchTimer;
document.getElementById('invSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadInventory, 400); });

loadInventory();
</script>
</body></html>
