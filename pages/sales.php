<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

$type = $_GET['type'] ?? 'quotation';
if (!in_array($type, ['quotation','order','invoice'])) $type = 'quotation';

$cfg      = getAppConfig();
$currency = htmlspecialchars($cfg['app']['currency']);

$meta = [
    'quotation' => ['label'=>'Quotations',   'singular'=>'Quotation', 'icon'=>'bi-file-earmark-text', 'color'=>'var(--amber)',   'convertTo'=>'order',   'convertLabel'=>'Convert to Order',   'sapLabel'=>'Sales Quotation'],
    'order'     => ['label'=>'Sales Orders', 'singular'=>'Order',     'icon'=>'bi-bag-check',         'color'=>'var(--primary)', 'convertTo'=>'invoice', 'convertLabel'=>'Convert to Invoice', 'sapLabel'=>'Sales Order'],
    'invoice'   => ['label'=>'AR Invoices',  'singular'=>'Invoice',   'icon'=>'bi-receipt',           'color'=>'var(--green)',   'convertTo'=>null,      'convertLabel'=>null,                 'sapLabel'=>'AR Invoice'],
][$type];

$pageTitle    = $meta['label'];
$pageSubtitle = match($type) {
    'quotation' => 'Sales quotations & price offers',
    'order'     => 'Confirmed customer orders',
    'invoice'   => 'Accounts receivable invoices',
};
$activePage = $type;
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<!-- Toolbar -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
  <div class="btn-group btn-group-sm" role="group" id="statusTabs">
    <input type="radio" class="btn-check" name="stFilter" id="stAll"    value=""        checked>
    <label class="btn btn-outline-secondary" for="stAll">All</label>
    <input type="radio" class="btn-check" name="stFilter" id="stDraft"  value="draft">
    <label class="btn btn-outline-secondary" for="stDraft">Draft</label>
    <input type="radio" class="btn-check" name="stFilter" id="stOpen"   value="open">
    <label class="btn btn-outline-secondary" for="stOpen">Open</label>
    <input type="radio" class="btn-check" name="stFilter" id="stClosed" value="closed">
    <label class="btn btn-outline-secondary" for="stClosed">Closed</label>
  </div>
  <div class="input-group input-group-sm" style="max-width:220px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="docSearch" class="form-control" placeholder="Search customer…">
  </div>
  <button class="btn btn-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#docModal" id="newBtn">
    <i class="bi bi-plus-lg me-1"></i>New <?= $meta['singular'] ?>
  </button>
</div>

<!-- List table -->
<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Doc #</th><th>Date</th><th>Customer</th><th class="text-end">Total (<?= $currency ?>)</th>
          <th>Status</th><th>SAP #</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="docTableBody">
        <tr><td colspan="7" class="text-center py-5" style="color:var(--text-3)">
          <span class="spinner-border spinner-border-sm me-2"></span>Loading…
        </td></tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex align-items-center justify-content-between" style="font-size:.8rem;color:var(--text-3)">
    <span id="docCount">—</span>
    <div id="docPager" class="d-flex gap-1"></div>
  </div>
</div>

</div></div></div>

<!-- ═══════════════════ Create / Convert Modal ═══════════════════════════════ -->
<div class="modal fade" id="docModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docModalTitle"><i class="bi <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>"></i>New <?= $meta['singular'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="fLinkedTo">
        <!-- Header fields -->
        <div class="row g-3 mb-3">
          <div class="col-md-5">
            <label class="form-label">Customer <span class="text-danger">*</span></label>
            <select id="fCustomer" class="form-select" style="width:100%"></select>
          </div>
          <div class="col-md-3 col-6">
            <label class="form-label">Date <span class="text-danger">*</span></label>
            <input type="text" id="fDate" class="form-control" placeholder="YYYY-MM-DD">
          </div>
          <div class="col-md-2 col-6">
            <label class="form-label">Due Date</label>
            <input type="text" id="fDueDate" class="form-control" placeholder="YYYY-MM-DD">
          </div>
          <div class="col-md-2">
            <label class="form-label">Reference</label>
            <input type="text" id="fRef" class="form-control" placeholder="PO-001">
          </div>
        </div>

        <!-- Line items -->
        <div class="table-responsive mb-2">
          <table class="table je-table mb-0" id="lineTable">
            <thead>
              <tr>
                <th style="width:3rem">#</th>
                <th style="min-width:200px">Item / Service</th>
                <th style="min-width:160px">Description</th>
                <th style="width:80px">Qty</th>
                <th style="width:110px">Unit Price</th>
                <th style="width:70px">Disc %</th>
                <th style="width:70px">Tax %</th>
                <th style="width:100px" class="text-end">Total</th>
                <th style="width:36px"></th>
              </tr>
            </thead>
            <tbody id="lineBody"></tbody>
          </table>
        </div>
        <button class="btn btn-sm btn-outline-primary" id="addLineBtn">
          <i class="bi bi-plus-circle me-1"></i>Add Line
        </button>

        <!-- Totals -->
        <div class="row mt-3 justify-content-end">
          <div class="col-md-4">
            <table class="table table-sm mb-0" style="font-size:.85rem">
              <tr><td style="color:var(--text-3)">Subtotal</td><td class="text-end fw-600" id="fSubtotal">0.00</td></tr>
              <tr><td style="color:var(--text-3)">Tax</td><td class="text-end" id="fTaxTotal">0.00</td></tr>
              <tr style="border-top:2px solid var(--border)"><td class="fw-700">TOTAL (<?= $currency ?>)</td><td class="text-end fw-700 fs-6" id="fTotal">0.00</td></tr>
            </table>
          </div>
        </div>

        <!-- Notes -->
        <div class="mt-3">
          <label class="form-label">Notes / Remarks</label>
          <textarea id="fMemo" class="form-control" rows="2" placeholder="Internal notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" id="saveDraftBtn">
            <i class="bi bi-floppy me-1"></i>Save Draft
          </button>
          <button class="btn btn-primary px-4" id="postDocBtn">
            <i class="bi bi-send me-1"></i>Post to SAP
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═════════════════ View Detail Modal ═════════════════════════════════════ -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewTitle">Document Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewBody">Loading…</div>
      <div class="modal-footer justify-content-between" id="viewFooter"></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
const DOC_TYPE    = '<?= $type ?>';
const CURRENCY    = '<?= $currency ?>';
const CONVERT_TO  = '<?= $meta['convertTo'] ?? '' ?>';
const CONVERT_LBL = '<?= addslashes($meta['convertLabel'] ?? '') ?>';
const DEFAULT_VAT = <?= (float)($cfg['app']['default_vat_rate'] ?? 0) ?>;

let docPage = 1;
let lineIdx = 0;

// ── Status badges ──────────────────────────────────────────────────────────────
const statusCls = {draft:'badge-secondary',open:'badge-primary',closed:'badge-success',failed:'badge-danger',pending:'badge-warning'};

// ── Load document list ────────────────────────────────────────────────────────
async function loadDocs() {
    const status = document.querySelector('input[name="stFilter"]:checked').value;
    const q      = document.getElementById('docSearch').value.trim();
    const url    = `/api/sales-documents.php?type=${DOC_TYPE}&page=${docPage}&status=${status}&q=${encodeURIComponent(q)}`;
    const tbody  = document.getElementById('docTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4" style="color:var(--text-3)"><span class="spinner-border spinner-border-sm me-2"></span></td></tr>';
    try {
        const res  = await fetch(url);
        const data = await res.json();
        renderDocs(data);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${escHtml(e.message)}</td></tr>`;
    }
}

function renderDocs(data) {
    const rows = data.data || [];
    const tbody = document.getElementById('docTableBody');
    document.getElementById('docCount').textContent = `${data.total ?? 0} document(s)`;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5" style="color:var(--text-3)">No documents found.</td></tr>';
        document.getElementById('docPager').innerHTML = '';
        return;
    }

    tbody.innerHTML = rows.map(r => `
        <tr>
          <td><strong style="color:var(--primary-text)">#${r.id}</strong></td>
          <td style="color:var(--text-3);font-size:.82rem">${r.doc_date}</td>
          <td>
            <div class="fw-600" style="font-size:.88rem">${escHtml(r.card_name || r.card_code)}</div>
            <div style="font-size:.73rem;color:var(--text-3)">${escHtml(r.card_code)}</div>
          </td>
          <td class="text-end fw-600">${fmt(r.total)}</td>
          <td><span class="badge ${statusCls[r.status]||'badge-secondary'}">${r.status}</span></td>
          <td>${r.sap_doc_num ? `<span class="badge badge-primary">#${r.sap_doc_num}</span>` : '—'}</td>
          <td class="text-end">
            <button class="btn btn-xs btn-outline-secondary me-1" onclick="viewDoc(${r.id})"><i class="bi bi-eye"></i></button>
            ${CONVERT_TO && r.status === 'open' ? `<button class="btn btn-xs btn-outline-primary me-1" onclick="convertDoc(${r.id})"><i class="bi bi-arrow-right-circle me-1"></i>${escHtml(CONVERT_LBL)}</button>` : ''}
            ${r.status === 'open' ? `<button class="btn btn-xs btn-outline-danger" onclick="cancelDoc(${r.id})"><i class="bi bi-x"></i></button>` : ''}
          </td>
        </tr>`).join('');

    // Pager
    const pager = document.getElementById('docPager');
    if (data.pages <= 1) { pager.innerHTML = ''; return; }
    let html = '';
    for (let i = 1; i <= data.pages; i++) {
        html += `<button class="btn btn-xs ${i === docPage ? 'btn-primary' : 'btn-outline-secondary'}" onclick="docPage=${i};loadDocs()">${i}</button>`;
    }
    pager.innerHTML = html;
}

// ── View detail ───────────────────────────────────────────────────────────────
async function viewDoc(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    document.getElementById('viewBody').innerHTML = '<div class="text-center py-4"><span class="spinner-border"></span></div>';
    document.getElementById('viewFooter').innerHTML = '';
    modal.show();
    const res  = await fetch(`/api/sales-documents.php?type=${DOC_TYPE}&id=${id}`);
    const data = await res.json();
    const r    = data.data;
    if (!r) { document.getElementById('viewBody').innerHTML = '<p class="text-danger">Not found.</p>'; return; }

    document.getElementById('viewTitle').innerHTML = `<i class="bi bi-receipt me-2"></i>${escHtml(r.card_name)} &mdash; #${r.id}`;
    const lines = r.lines || [];
    document.getElementById('viewBody').innerHTML = `
      <div class="row g-2 mb-3" style="font-size:.85rem">
        <div class="col-6"><span style="color:var(--text-3)">Customer</span><div class="fw-600">${escHtml(r.card_name)} (${escHtml(r.card_code)})</div></div>
        <div class="col-3"><span style="color:var(--text-3)">Date</span><div>${r.doc_date}</div></div>
        <div class="col-3"><span style="color:var(--text-3)">Status</span><div><span class="badge ${statusCls[r.status]||''}">${r.status}</span></div></div>
        ${r.memo ? `<div class="col-12"><span style="color:var(--text-3)">Notes</span><div>${escHtml(r.memo)}</div></div>` : ''}
      </div>
      <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Item</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
        <tbody>${lines.map(l=>`<tr>
          <td><code style="color:var(--primary-text)">${escHtml(l.item_code||'')}</code></td>
          <td style="font-size:.82rem">${escHtml(l.description||'')}</td>
          <td class="text-end">${l.quantity}</td>
          <td class="text-end">${fmt(l.unit_price)}</td>
          <td class="text-end fw-600">${fmt(l.line_total)}</td>
        </tr>`).join('')}</tbody>
        <tfoot style="border-top:2px solid var(--border)">
          <tr><td colspan="4" class="text-end" style="color:var(--text-3)">Subtotal</td><td class="text-end">${fmt(r.total - r.tax_total)}</td></tr>
          <tr><td colspan="4" class="text-end" style="color:var(--text-3)">Tax</td><td class="text-end">${fmt(r.tax_total)}</td></tr>
          <tr><td colspan="4" class="text-end fw-700">TOTAL</td><td class="text-end fw-700 text-primary">${fmt(r.total)}</td></tr>
        </tfoot>
      </table></div>`;

    const footer = document.getElementById('viewFooter');
    let btns = '<button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><div class="d-flex gap-2">';
    if (CONVERT_TO && r.status === 'open') btns += `<button class="btn btn-primary" onclick="convertDoc(${r.id});bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide()">${escHtml(CONVERT_LBL)}</button>`;
    btns += '</div>';
    footer.innerHTML = btns;
}

// ── Convert doc (prefill form with source data) ───────────────────────────────
async function convertDoc(id) {
    const res  = await fetch(`/api/sales-documents.php?type=${DOC_TYPE}&id=${id}`);
    const data = await res.json();
    const r    = data.data;
    if (!r) return;

    resetForm();
    document.getElementById('fLinkedTo').value = id;
    setCustomer(r.card_code, r.card_name);
    document.getElementById('fMemo').value = r.memo || '';
    document.getElementById('docModalTitle').innerHTML = `<i class="bi bi-arrow-right-circle me-2"></i>${escHtml(CONVERT_LBL)} from #${id}`;

    (r.lines || []).forEach(l => addLine(l));
    recalc();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('docModal')).show();
}

// ── Cancel doc ────────────────────────────────────────────────────────────────
async function cancelDoc(id) {
    if (!confirm('Cancel this document?')) return;
    await fetch(`/api/sales-documents.php?type=${DOC_TYPE}&id=${id}`, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:'cancelled'})});
    loadDocs();
}

// ── Customer Select2 ──────────────────────────────────────────────────────────
function initCustomerSelect() {
    $('#fCustomer').select2({
        dropdownParent: $('#docModal'),
        placeholder: 'Search customer…',
        allowClear: true,
        ajax: {
            url: '/api/business-partners.php?action=select2&type=C',
            dataType: 'json',
            delay: 250,
            data: params => ({q: params.term}),
            processResults: d => d,
        },
        templateResult: r => r.loading ? r.text : $(`<div><strong>${escHtml(r.card_code||r.id)}</strong> <span style="color:var(--text-3)">${escHtml(r.card_name||r.text)}</span></div>`)[0],
    });
    $('#fCustomer').on('select2:select', e => {
        window._bpName = e.params.data.card_name || e.params.data.text;
    });
}

function setCustomer(code, name) {
    const opt = new Option(code + ' — ' + name, code, true, true);
    $('#fCustomer').append(opt).trigger('change');
    window._bpName = name;
}

// ── Line management ──────────────────────────────────────────────────────────
function addLine(prefill = null) {
    const idx = lineIdx++;
    const p   = prefill || {};
    const tr  = document.createElement('tr');
    tr.id = `line_${idx}`;
    tr.innerHTML = `
      <td style="color:var(--text-3);font-size:.82rem">${idx + 1}</td>
      <td><select id="item_${idx}" class="form-select form-select-sm" style="width:100%"></select></td>
      <td><input type="text"   class="je-input form-control-sm w-100" id="desc_${idx}"  value="${escHtml(p.description||'')}" placeholder="Description"></td>
      <td><input type="number" class="je-input form-control-sm w-100 text-end" id="qty_${idx}"   value="${p.quantity||1}"   min="0" step="any"></td>
      <td><input type="number" class="je-input form-control-sm w-100 text-end" id="price_${idx}" value="${p.unit_price||0}" min="0" step="any"></td>
      <td><input type="number" class="je-input form-control-sm w-100 text-end" id="disc_${idx}"  value="${p.discount||0}"   min="0" max="100" step="any"></td>
      <td><input type="number" class="je-input form-control-sm w-100 text-end" id="tax_${idx}"   value="${p.tax_rate ?? DEFAULT_VAT}" min="0" max="100" step="any"></td>
      <td class="text-end fw-600" id="ltotal_${idx}" style="font-size:.85rem">0.00</td>
      <td><button class="btn btn-xs btn-link text-danger p-0" onclick="removeLine(${idx})"><i class="bi bi-x-lg"></i></button></td>`;
    document.getElementById('lineBody').appendChild(tr);

    // Init item Select2
    $(`#item_${idx}`).select2({
        dropdownParent: $('#docModal'),
        placeholder: 'Search item…',
        allowClear: true,
        ajax: {
            url: '/api/get-items.php',
            dataType: 'json',
            delay: 250,
            data: params => ({q: params.term}),
            processResults: d => d,
        },
        templateResult: r => r.loading ? r.text : $(`<div><strong>${escHtml(r.item_code||r.id)}</strong> <span style="color:var(--text-3)">${escHtml(r.item_name||r.text)}</span></div>`)[0],
    }).on('select2:select', e => {
        document.getElementById(`desc_${idx}`).value  = e.params.data.item_name  || '';
        document.getElementById(`price_${idx}`).value = e.params.data.price      || 0;
        recalc();
    });

    if (p.item_code) {
        const opt = new Option(p.item_code + ' — ' + (p.description || p.item_code), p.item_code, true, true);
        $(`#item_${idx}`).append(opt).trigger('change');
    }

    // Live recalc
    ['qty','price','disc','tax'].forEach(f => {
        document.getElementById(`${f}_${idx}`)?.addEventListener('input', recalc);
    });
    recalc();
}

function removeLine(idx) {
    document.getElementById(`line_${idx}`)?.remove();
    recalc();
}

function recalc() {
    let subtotal = 0, taxTotal = 0;
    document.querySelectorAll('#lineBody tr').forEach(tr => {
        const i = tr.id.replace('line_', '');
        const qty   = parseFloat(document.getElementById(`qty_${i}`)?.value   || 0);
        const price = parseFloat(document.getElementById(`price_${i}`)?.value || 0);
        const disc  = parseFloat(document.getElementById(`disc_${i}`)?.value  || 0);
        const tax   = parseFloat(document.getElementById(`tax_${i}`)?.value   || 0);
        const net   = qty * price * (1 - disc / 100);
        const taxAmt = net * tax / 100;
        const total  = net + taxAmt;
        const el = document.getElementById(`ltotal_${i}`);
        if (el) el.textContent = fmt(total);
        subtotal += net;
        taxTotal += taxAmt;
    });
    document.getElementById('fSubtotal').textContent = fmt(subtotal);
    document.getElementById('fTaxTotal').textContent = fmt(taxTotal);
    document.getElementById('fTotal').textContent    = fmt(subtotal + taxTotal);
}

function buildPayload(saveDraft) {
    const lines = [];
    document.querySelectorAll('#lineBody tr').forEach(tr => {
        const i = tr.id.replace('line_', '');
        const itemSel = $(`#item_${i}`).select2('data')[0];
        lines.push({
            item_code:   itemSel?.id || '',
            description: document.getElementById(`desc_${i}`)?.value  || '',
            quantity:    parseFloat(document.getElementById(`qty_${i}`)?.value   || 1),
            unit_price:  parseFloat(document.getElementById(`price_${i}`)?.value || 0),
            discount:    parseFloat(document.getElementById(`disc_${i}`)?.value  || 0),
            tax_rate:    parseFloat(document.getElementById(`tax_${i}`)?.value   || 0),
        });
    });
    const bpSel = $('#fCustomer').select2('data')[0];
    return {
        card_code:  bpSel?.id   || bpSel?.card_code || '',
        card_name:  bpSel?.card_name || window._bpName || '',
        doc_date:   document.getElementById('fDate').value,
        due_date:   document.getElementById('fDueDate').value,
        memo:       document.getElementById('fMemo').value,
        lines:      lines,
        linked_to:  document.getElementById('fLinkedTo').value || null,
        save_draft: saveDraft ? 1 : 0,
    };
}

async function submitDoc(saveDraft) {
    const payload = buildPayload(saveDraft);
    if (!payload.card_code) { showToast('Please select a customer.', 'warning'); return; }
    if (!payload.lines.length) { showToast('Please add at least one line.', 'warning'); return; }

    const btn = document.getElementById(saveDraft ? 'saveDraftBtn' : 'postDocBtn');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    try {
        const res  = await fetch(`/api/sales-documents.php?type=${DOC_TYPE}`, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
            showToast(saveDraft ? 'Saved as draft.' : `Posted to SAP #${data.sap_doc_num || data.id}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('docModal')).hide();
            loadDocs();
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

function resetForm() {
    lineIdx = 0;
    document.getElementById('lineBody').innerHTML = '';
    document.getElementById('fLinkedTo').value = '';
    document.getElementById('fDate').value    = new Date().toISOString().slice(0,10);
    document.getElementById('fDueDate').value = '';
    document.getElementById('fRef').value     = '';
    document.getElementById('fMemo').value    = '';
    $('#fCustomer').val(null).trigger('change');
    window._bpName = '';
    document.getElementById('docModalTitle').innerHTML = `<i class="bi <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>"></i>New <?= $meta['singular'] ?>`;
    recalc();
    addLine();
}

// ── Events ────────────────────────────────────────────────────────────────────
document.getElementById('addLineBtn').addEventListener('click', () => addLine());
document.getElementById('saveDraftBtn').addEventListener('click', () => submitDoc(true));
document.getElementById('postDocBtn').addEventListener('click',  () => submitDoc(false));
document.getElementById('newBtn').addEventListener('click', resetForm);

document.querySelectorAll('input[name="stFilter"]').forEach(r => r.addEventListener('change', () => { docPage=1; loadDocs(); }));
let searchTimer;
document.getElementById('docSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { docPage=1; loadDocs(); }, 350);
});

// Init on modal show
document.getElementById('docModal').addEventListener('show.bs.modal', () => {
    initCustomerSelect();
    flatpickr('#fDate',    {dateFormat:'Y-m-d', defaultDate:'today'});
    flatpickr('#fDueDate', {dateFormat:'Y-m-d'});
});

loadDocs();
</script>
</body></html>
