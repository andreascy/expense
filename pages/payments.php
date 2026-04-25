<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
$cfg          = getAppConfig();
$currency     = htmlspecialchars($cfg['app']['currency']);
$pageTitle    = 'Incoming Payments';
$pageSubtitle = 'Customer receipts & payment matching';
$activePage   = 'payments';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<!-- Toolbar -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
  <div class="input-group input-group-sm" style="max-width:240px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="pmtSearch" class="form-control" placeholder="Search customer…">
  </div>
  <button class="btn btn-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#pmtModal">
    <i class="bi bi-plus-lg me-1"></i>New Payment
  </button>
</div>

<!-- Payments table -->
<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>#</th><th>Date</th><th>Customer</th><th>Method</th>
          <th class="text-end">Amount (<?= $currency ?>)</th>
          <th>Invoice</th><th>SAP #</th><th>Status</th>
        </tr>
      </thead>
      <tbody id="pmtTableBody">
        <tr><td colspan="8" class="text-center py-5" style="color:var(--text-3)"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr>
      </tbody>
    </table>
  </div>
  <div class="card-footer d-flex justify-content-between" style="font-size:.8rem;color:var(--text-3)">
    <span id="pmtCount">—</span>
    <div id="pmtPager" class="d-flex gap-1"></div>
  </div>
</div>

</div></div></div>

<!-- ═══════════════════ Payment Modal ════════════════════════════════════════ -->
<div class="modal fade" id="pmtModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-stack me-2" style="color:var(--green)"></i>New Incoming Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Customer <span class="text-danger">*</span></label>
            <select id="pmtCustomer" class="form-select" style="width:100%"></select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
            <input type="text" id="pmtDate" class="form-control" placeholder="YYYY-MM-DD">
          </div>
          <div class="col-md-3">
            <label class="form-label">Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><?= $currency ?></span>
              <input type="number" id="pmtAmount" class="form-control text-end" step="0.01" min="0" placeholder="0.00">
            </div>
          </div>

          <!-- Payment method -->
          <div class="col-12">
            <label class="form-label">Payment Method</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input type="radio" class="form-check-input" name="pmtMethod" id="mTransfer" value="transfer" checked>
                <label class="form-check-label" for="mTransfer"><i class="bi bi-bank me-1"></i>Bank Transfer</label>
              </div>
              <div class="form-check">
                <input type="radio" class="form-check-input" name="pmtMethod" id="mCash" value="cash">
                <label class="form-check-label" for="mCash"><i class="bi bi-cash me-1"></i>Cash</label>
              </div>
              <div class="form-check">
                <input type="radio" class="form-check-input" name="pmtMethod" id="mCheck" value="check">
                <label class="form-check-label" for="mCheck"><i class="bi bi-file-earmark-text me-1"></i>Check</label>
              </div>
            </div>
          </div>

          <div class="col-md-6" id="bankAccRow">
            <label class="form-label">Bank Account Code</label>
            <input type="text" id="pmtBankAcc" class="form-control" placeholder="1200">
          </div>
          <div class="col-md-6" id="transferRefRow">
            <label class="form-label">Transfer Reference</label>
            <input type="text" id="pmtRef" class="form-control" placeholder="TRF-001">
          </div>

          <div class="col-12">
            <label class="form-label">Apply to Invoice <span style="color:var(--text-3);font-size:.8rem">(optional)</span></label>
            <select id="pmtInvoice" class="form-select">
              <option value="">— Select customer first —</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Memo</label>
            <input type="text" id="pmtMemo" class="form-control" placeholder="Payment reference, notes…">
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" id="pmtDraftBtn"><i class="bi bi-floppy me-1"></i>Save Draft</button>
          <button class="btn btn-success px-4" id="pmtPostBtn"><i class="bi bi-send me-1"></i>Post Payment</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
const CURRENCY = '<?= $currency ?>';
let pmtPage = 1;
const statusCls = {draft:'badge-secondary',posted:'badge-success',failed:'badge-danger',pending:'badge-warning'};
const methodIcon = {transfer:'bi-bank',cash:'bi-cash',check:'bi-file-earmark-text'};

async function loadPayments() {
    const q = document.getElementById('pmtSearch').value.trim();
    const res  = await fetch(`/api/payments-incoming.php?page=${pmtPage}&q=${encodeURIComponent(q)}`);
    const data = await res.json();
    const rows = data.data || [];
    const tbody = document.getElementById('pmtTableBody');
    document.getElementById('pmtCount').textContent = `${data.total ?? 0} payment(s)`;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5" style="color:var(--text-3)">No payments found.</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
      <td><strong style="color:var(--primary-text)">#${r.id}</strong></td>
      <td style="color:var(--text-3);font-size:.82rem">${r.payment_date}</td>
      <td>
        <div class="fw-600" style="font-size:.88rem">${escHtml(r.card_name || r.card_code)}</div>
        <div style="font-size:.73rem;color:var(--text-3)">${escHtml(r.card_code)}</div>
      </td>
      <td><i class="bi ${methodIcon[r.payment_method]||'bi-cash'} me-1"></i><span style="font-size:.82rem">${r.payment_method}</span></td>
      <td class="text-end fw-600 text-success">${fmt(r.amount)}</td>
      <td>${r.invoice_id ? `<span class="badge badge-secondary">INV #${r.invoice_id}</span>` : '—'}</td>
      <td>${r.sap_doc_num ? `<span class="badge badge-primary">#${r.sap_doc_num}</span>` : '—'}</td>
      <td><span class="badge ${statusCls[r.status]||'badge-secondary'}">${r.status}</span></td>
    </tr>`).join('');
}

// ── Customer Select2 ──────────────────────────────────────────────────────────
$('#pmtCustomer').select2({
    dropdownParent: $('#pmtModal'),
    placeholder: 'Search customer…',
    allowClear: true,
    ajax: {
        url: '/api/business-partners.php?action=select2&type=C',
        dataType: 'json', delay: 250,
        data: p => ({q: p.term}),
        processResults: d => d,
    },
}).on('select2:select', async e => {
    const code = e.params.data.card_code || e.params.data.id;
    window._pmtBpName = e.params.data.card_name || '';
    await loadOpenInvoices(code);
});

async function loadOpenInvoices(code) {
    const sel = document.getElementById('pmtInvoice');
    sel.innerHTML = '<option value="">Loading…</option>';
    const res  = await fetch(`/api/payments-incoming.php?action=open-invoices&card_code=${encodeURIComponent(code)}`);
    const data = await res.json();
    const rows = data.data || [];
    if (!rows.length) { sel.innerHTML = '<option value="">No open invoices</option>'; return; }
    sel.innerHTML = '<option value="">— No invoice link —</option>' +
        rows.map(r => `<option value="${r.id}" data-sap="${r.sap_doc_entry||''}">#${r.id} — ${escHtml(r.card_name)} (${fmt(r.total)})</option>`).join('');
}

// ── Method toggle ─────────────────────────────────────────────────────────────
document.querySelectorAll('input[name="pmtMethod"]').forEach(r => r.addEventListener('change', () => {
    const m = document.querySelector('input[name="pmtMethod"]:checked').value;
    document.getElementById('bankAccRow').style.display    = m !== 'cash' ? '' : 'none';
    document.getElementById('transferRefRow').style.display = m === 'transfer' ? '' : 'none';
}));

// ── Submit ────────────────────────────────────────────────────────────────────
async function submitPayment(saveDraft) {
    const bpSel   = $('#pmtCustomer').select2('data')[0];
    const invSel  = document.getElementById('pmtInvoice');
    const invOpt  = invSel.options[invSel.selectedIndex];
    const payload = {
        card_code:      bpSel?.id || bpSel?.card_code || '',
        card_name:      bpSel?.card_name || window._pmtBpName || '',
        payment_date:   document.getElementById('pmtDate').value,
        amount:         parseFloat(document.getElementById('pmtAmount').value || 0),
        payment_method: document.querySelector('input[name="pmtMethod"]:checked').value,
        bank_account:   document.getElementById('pmtBankAcc').value.trim(),
        transfer_ref:   document.getElementById('pmtRef').value.trim(),
        memo:           document.getElementById('pmtMemo').value.trim(),
        invoice_id:     invOpt?.value || null,
        sap_invoice_doc_entry: invOpt?.dataset?.sap || null,
        save_draft:     saveDraft ? 1 : 0,
    };

    if (!payload.card_code) { showToast('Please select a customer.', 'warning'); return; }
    if (!payload.amount)    { showToast('Amount is required.', 'warning'); return; }

    const btn = document.getElementById(saveDraft ? 'pmtDraftBtn' : 'pmtPostBtn');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

    try {
        const res  = await fetch('/api/payments-incoming.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
        const data = await res.json();
        if (data.success) {
            showToast(saveDraft ? 'Draft saved.' : `Payment posted #${data.sap_doc_num || data.id}`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('pmtModal')).hide();
            loadPayments();
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

document.getElementById('pmtDraftBtn').addEventListener('click', () => submitPayment(true));
document.getElementById('pmtPostBtn').addEventListener('click',  () => submitPayment(false));

document.getElementById('pmtModal').addEventListener('show.bs.modal', () => {
    flatpickr('#pmtDate', {dateFormat:'Y-m-d', defaultDate:'today'});
    document.getElementById('pmtAmount').value = '';
    document.getElementById('pmtMemo').value   = '';
    document.getElementById('pmtRef').value    = '';
    $('#pmtCustomer').val(null).trigger('change');
    document.getElementById('pmtInvoice').innerHTML = '<option value="">— Select customer first —</option>';
});

let searchTimer;
document.getElementById('pmtSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { pmtPage=1; loadPayments(); }, 350); });

loadPayments();
</script>
</body></html>
