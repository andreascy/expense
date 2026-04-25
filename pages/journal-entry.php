<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
$cfg       = getAppConfig();
$currency  = htmlspecialchars($cfg['app']['currency']);
$pageTitle = 'Journal Entry';
$pageSubtitle = 'Manual multi-line journal entry';
$activePage   = 'je';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php
$topbarActions = '<button class="btn btn-outline-secondary btn-sm" id="clearBtn"><i class="bi bi-trash3 me-1"></i>Clear</button>';
include __DIR__ . '/../includes/topbar.php';
?>
<div class="page-body">

<div class="row g-4">
  <!-- Entry form -->
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-journal-text" style="color:var(--primary)"></i>
        New Journal Entry
      </div>
      <div class="card-body">

        <!-- Header fields -->
        <div class="row g-3 mb-4">
          <div class="col-sm-3">
            <label class="form-label">Date *</label>
            <input type="text" id="je_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-sm-3">
            <label class="form-label">Reference 1</label>
            <input type="text" id="je_ref1" class="form-control" placeholder="e.g. INV-001" maxlength="50">
          </div>
          <div class="col-sm-3">
            <label class="form-label">Reference 2</label>
            <input type="text" id="je_ref2" class="form-control" placeholder="e.g. PO-2024" maxlength="50">
          </div>
          <div class="col-sm-3">
            <label class="form-label">Memo *</label>
            <input type="text" id="je_memo" class="form-control" placeholder="Journal entry description" maxlength="255" required>
          </div>
        </div>

        <!-- Lines table -->
        <div class="table-responsive mb-3">
          <table class="je-table" id="jeTable">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <th style="min-width:220px">Account *</th>
                <th style="min-width:130px">Business Partner</th>
                <th style="min-width:180px">Line Memo</th>
                <th style="width:140px">Debit (<?= $currency ?>)</th>
                <th style="width:140px">Credit (<?= $currency ?>)</th>
                <th style="width:40px"></th>
              </tr>
            </thead>
            <tbody id="jeLines"></tbody>
            <tfoot>
              <tr>
                <td colspan="4" class="text-end" style="font-size:.8rem;color:var(--text-3)">Totals</td>
                <td id="totalDebit" style="font-variant-numeric:tabular-nums">0.00</td>
                <td id="totalCredit" style="font-variant-numeric:tabular-nums">0.00</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Add line + balance -->
        <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
          <button class="btn btn-sm btn-outline-secondary" id="addLineBtn">
            <i class="bi bi-plus-lg me-1"></i>Add Line
          </button>
          <div id="balanceIndicator" class="je-balance-row je-balance-zero">
            <i class="bi bi-dash-circle me-1"></i>Enter amounts
          </div>
          <span style="font-size:.8rem;color:var(--text-3)" id="diffLabel"></span>
        </div>

        <!-- Post button -->
        <div class="d-flex gap-2">
          <button class="btn btn-primary" id="postJeBtn">
            <span id="postText"><i class="bi bi-send-fill me-1"></i>Post to SAP B1</span>
            <span id="postSpinner" class="d-none"><span class="spinner-border spinner-border-sm me-1"></span>Posting…</span>
          </button>
          <button class="btn btn-outline-secondary" id="balanceBtn" title="Auto-fill last line to balance">
            <i class="bi bi-lightning-charge me-1"></i>Auto Balance
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- Recent journal entries -->
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1" style="color:var(--text-3)"></i>Recent Manual Entries</span>
        <button class="btn btn-sm btn-outline-secondary" id="refreshJe"><i class="bi bi-arrow-clockwise"></i></button>
      </div>
      <div class="table-responsive">
        <table class="table mb-0" id="recentJeTable">
          <thead>
            <tr><th>Date</th><th>Memo</th><th>Ref 1</th><th class="text-end">Lines</th><th>SAP Doc</th><th>Status</th><th>By</th></tr>
          </thead>
          <tbody id="recentJeBody">
            <tr><td colspan="7" class="text-center py-4" style="color:var(--text-3)">
              <span class="spinner-border spinner-border-sm me-1"></span>Loading…
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div></div></div>

<!-- Success modal -->
<div class="modal fade" id="jeSuccessModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center p-5">
        <div class="success-icon mb-3"><i class="bi bi-check-circle-fill"></i></div>
        <h4 class="mb-1">Posted to SAP B1!</h4>
        <p style="color:var(--text-3)" class="mb-3">Journal entry created successfully.</p>
        <div class="row g-2 mb-4">
          <div class="col-6"><div class="doc-badge"><small style="color:var(--text-3);display:block">Doc Entry</small><strong id="jeDocEntry">—</strong></div></div>
          <div class="col-6"><div class="doc-badge"><small style="color:var(--text-3);display:block">Doc Number</small><strong id="jeDocNum">—</strong></div></div>
        </div>
        <button class="btn btn-primary px-4" data-bs-dismiss="modal">
          <i class="bi bi-plus-circle me-1"></i>New Entry
        </button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr('#je_date', { dateFormat:'Y-m-d', allowInput:true });

let rowCount = 0;

function addLine(data = {}) {
    rowCount++;
    const idx = rowCount;
    const row = document.createElement('tr');
    row.id    = 'jerow_' + idx;
    row.innerHTML = `
        <td style="color:var(--text-3);font-size:.8rem;text-align:center">${idx}</td>
        <td><select class="form-select je-account-select" id="acc_${idx}" style="font-size:.85rem"></select>
            <input type="hidden" id="accname_${idx}"></td>
        <td><input type="text" class="je-input" id="bp_${idx}" placeholder="BP Code" maxlength="20"></td>
        <td><input type="text" class="je-input" id="lmemo_${idx}" placeholder="Line description" maxlength="100"></td>
        <td><input type="number" class="je-input text-end" id="dr_${idx}" placeholder="0.00" step="0.01" min="0" oninput="syncLine(${idx},'dr')"></td>
        <td><input type="number" class="je-input text-end" id="cr_${idx}" placeholder="0.00" step="0.01" min="0" oninput="syncLine(${idx},'cr')"></td>
        <td><button class="row-del-btn" onclick="delLine(${idx})"><i class="bi bi-trash3"></i></button></td>`;

    if (data.account_code) {
        // Pre-set account (used when loading saved)
        const opt = new Option(data.account_code + ' — ' + (data.account_name||''), data.account_code, true, true);
        opt.dataset.name = data.account_name || '';
        $(row.querySelector('.je-account-select')).append(opt).trigger('change');
        document.getElementById('accname_' + idx) && (document.getElementById('accname_' + idx).value = data.account_name || '');
    }
    if (data.debit  > 0) row.querySelector('#dr_'+idx).value = data.debit;
    if (data.credit > 0) row.querySelector('#cr_'+idx).value = data.credit;
    if (data.bp_code)    row.querySelector('#bp_'+idx).value = data.bp_code;
    if (data.line_memo)  row.querySelector('#lmemo_'+idx).value = data.line_memo;

    document.getElementById('jeLines').appendChild(row);

    // Init Select2 for this row
    $('#acc_' + idx).select2({
        placeholder: 'Search account…',
        allowClear: true,
        minimumInputLength: 0,
        dropdownParent: $('body'),
        ajax: {
            url: '/api/get_accounts.php',
            dataType: 'json', delay: 250,
            data: p => ({ q: p.term ?? '' }),
            processResults: d => ({ results: d.results || [] }),
            cache: true,
        },
        templateResult: a => {
            if (a.loading) return $('<span>Searching…</span>');
            if (!a.code)   return $('<span>' + a.text + '</span>');
            return $(`<div><span class="account-chip">${escHtml(a.code)}</span>${escHtml(a.name)}</div>`);
        },
        templateSelection: a => a.code ? a.code + ' — ' + (a.name??a.text) : (a.text||'Search account…'),
    }).on('select2:select', e => {
        document.getElementById('accname_'+idx).value = e.params.data.name ?? '';
    }).on('select2:clear', () => {
        document.getElementById('accname_'+idx).value = '';
    });

    updateTotals();
}

function delLine(idx) {
    document.getElementById('jerow_'+idx)?.remove();
    updateTotals();
}

function syncLine(idx, changed) {
    // Clear opposite if this has value
    const dr = parseFloat(document.getElementById('dr_'+idx)?.value) || 0;
    const cr = parseFloat(document.getElementById('cr_'+idx)?.value) || 0;
    if (changed === 'dr' && dr > 0 && cr > 0) document.getElementById('cr_'+idx).value = '';
    if (changed === 'cr' && cr > 0 && dr > 0) document.getElementById('dr_'+idx).value = '';
    updateTotals();
}

function updateTotals() {
    let dr = 0, cr = 0;
    document.querySelectorAll('[id^="dr_"]').forEach(i => dr += parseFloat(i.value)||0);
    document.querySelectorAll('[id^="cr_"]').forEach(i => cr += parseFloat(i.value)||0);
    document.getElementById('totalDebit').textContent  = fmt(dr);
    document.getElementById('totalCredit').textContent = fmt(cr);
    const diff = Math.abs(dr - cr);
    const ind  = document.getElementById('balanceIndicator');
    const lbl  = document.getElementById('diffLabel');
    if (dr === 0 && cr === 0) {
        ind.className = 'je-balance-row je-balance-zero';
        ind.innerHTML = '<i class="bi bi-dash-circle me-1"></i>Enter amounts';
        lbl.textContent = '';
    } else if (diff < 0.005) {
        ind.className = 'je-balance-row je-balance-ok';
        ind.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Balanced';
        lbl.textContent = '';
    } else {
        ind.className = 'je-balance-row je-balance-off';
        ind.innerHTML = '<i class="bi bi-exclamation-circle-fill me-1"></i>Not balanced';
        lbl.textContent = 'Difference: ' + fmt(diff);
    }
}

document.getElementById('addLineBtn').addEventListener('click', () => addLine());

document.getElementById('balanceBtn').addEventListener('click', () => {
    let dr = 0, cr = 0;
    document.querySelectorAll('[id^="dr_"]').forEach(i => dr += parseFloat(i.value)||0);
    document.querySelectorAll('[id^="cr_"]').forEach(i => cr += parseFloat(i.value)||0);
    const diff = dr - cr;
    if (Math.abs(diff) < 0.005) return;
    // Add balancing line
    addLine();
    const last = rowCount;
    if (diff > 0) {
        document.getElementById('cr_'+last).value = diff.toFixed(2);
    } else {
        document.getElementById('dr_'+last).value = Math.abs(diff).toFixed(2);
    }
    updateTotals();
});

document.getElementById('clearBtn').addEventListener('click', () => {
    document.getElementById('jeLines').innerHTML = '';
    document.getElementById('je_memo').value = '';
    document.getElementById('je_ref1').value = '';
    document.getElementById('je_ref2').value = '';
    rowCount = 0;
    addLine(); addLine();
    updateTotals();
});

document.getElementById('postJeBtn').addEventListener('click', async () => {
    const date = document.getElementById('je_date').value;
    const memo = document.getElementById('je_memo').value.trim();
    const ref1 = document.getElementById('je_ref1').value.trim();
    const ref2 = document.getElementById('je_ref2').value.trim();

    if (!memo) return showToast('Memo is required.', 'error');

    const lines = [];
    let hasRows = false;
    document.querySelectorAll('#jeLines tr').forEach(row => {
        const idx  = row.id.replace('jerow_','');
        const acc  = document.getElementById('acc_'+idx)?.value;
        const name = document.getElementById('accname_'+idx)?.value || '';
        const dr   = parseFloat(document.getElementById('dr_'+idx)?.value) || 0;
        const cr   = parseFloat(document.getElementById('cr_'+idx)?.value) || 0;
        const bp   = document.getElementById('bp_'+idx)?.value || '';
        const lm   = document.getElementById('lmemo_'+idx)?.value || '';
        if (!acc && dr === 0 && cr === 0) return;
        if (!acc) return showToast('Row '+ idx +': select an account.', 'error');
        hasRows = true;
        lines.push({ account_code:acc, account_name:name, debit:dr, credit:cr, bp_code:bp, line_memo:lm });
    });
    if (!hasRows || lines.length < 2) return showToast('At least 2 lines are required.', 'error');

    document.getElementById('postJeBtn').disabled = true;
    document.getElementById('postText').classList.add('d-none');
    document.getElementById('postSpinner').classList.remove('d-none');

    try {
        const res  = await fetch('/api/journal-entries.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ entry_date:date, memo, ref1, ref2, lines })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('jeDocEntry').textContent = data.sap_doc_entry ?? '—';
            document.getElementById('jeDocNum').textContent   = data.sap_doc_num   ?? '—';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('jeSuccessModal')).show();
            loadRecentJe();
        } else {
            showToast(data.error || 'Error posting entry.', 'error');
        }
    } catch(e) { showToast('Network error.', 'error'); }
    finally {
        document.getElementById('postJeBtn').disabled = false;
        document.getElementById('postText').classList.remove('d-none');
        document.getElementById('postSpinner').classList.add('d-none');
    }
});

async function loadRecentJe() {
    const res  = await fetch('/api/journal-entries.php');
    const data = await res.json();
    const tbody = document.getElementById('recentJeBody');
    const rows  = data.data || [];
    const statusCls = {posted:'badge-success',failed:'badge-danger',pending:'badge-warning'};
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4" style="color:var(--text-3)">No entries yet.</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td style="color:var(--text-3);font-size:.8rem">${r.entry_date}</td>
            <td style="font-weight:500">${escHtml(r.memo)}</td>
            <td style="color:var(--text-3)">${escHtml(r.ref1||'—')}</td>
            <td class="text-end">${(r.lines||[]).length}</td>
            <td>${r.sap_doc_num ? `<span class="badge badge-primary">#${r.sap_doc_num}</span>` : '—'}</td>
            <td><span class="badge ${statusCls[r.status]||'badge-secondary'}">${r.status}</span></td>
            <td style="color:var(--text-3);font-size:.8rem">${r.created_at?.split(' ')[0]||'—'}</td>
        </tr>`).join('');
}

document.getElementById('refreshJe').addEventListener('click', loadRecentJe);

// Init with 2 blank lines
addLine(); addLine();
loadRecentJe();
</script>
</body></html>
