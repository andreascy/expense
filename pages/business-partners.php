<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
$cfg          = getAppConfig();
$currency     = htmlspecialchars($cfg['app']['currency']);
$crystalBase  = htmlspecialchars($cfg['app']['crystal_url'] ?? '');
$pageTitle    = 'Business Partners';
$pageSubtitle = 'Customers & suppliers';
$activePage   = 'business-partners';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<!-- Toolbar -->
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
  <div class="input-group" style="max-width:260px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="bpSearch" class="form-control" placeholder="Search code or name…">
  </div>
  <div class="btn-group btn-group-sm" role="group" id="typeFilter">
    <input type="radio" class="btn-check" name="bpType" id="typeAll" value="" checked>
    <label class="btn btn-outline-secondary" for="typeAll">All</label>
    <input type="radio" class="btn-check" name="bpType" id="typeCust" value="C">
    <label class="btn btn-outline-secondary" for="typeCust"><i class="bi bi-person me-1"></i>Customers</label>
    <input type="radio" class="btn-check" name="bpType" id="typeSupp" value="S">
    <label class="btn btn-outline-secondary" for="typeSupp"><i class="bi bi-building me-1"></i>Suppliers</label>
  </div>
  <button class="btn btn-sm btn-outline-primary ms-auto" id="refreshBtn">
    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
  </button>
</div>

<!-- Main grid: partner list + statement panel -->
<div class="row g-3" id="bpLayout">

  <!-- Partner cards -->
  <div class="col-xl-5" id="bpListCol">
    <div id="bpGrid" class="row g-2">
      <div class="col-12 text-center py-5" style="color:var(--text-3)">
        <span class="spinner-border me-2"></span>Loading partners…
      </div>
    </div>
    <div id="bpPager" class="d-flex justify-content-center mt-3 gap-2 d-none"></div>
  </div>

  <!-- Statement panel -->
  <div class="col-xl-7" id="stmtCol">
    <div class="card h-100" style="min-height:300px">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span id="stmtTitle"><i class="bi bi-receipt me-1" style="color:var(--cyan)"></i>Account Statement</span>
        <div class="d-flex gap-2" id="stmtActions" style="display:none!important">
          <button class="btn btn-sm btn-outline-secondary" id="exportCsvBtn" title="Export CSV">
            <i class="bi bi-filetype-csv"></i>
          </button>
          <?php if ($crystalBase): ?>
          <button class="btn btn-sm btn-outline-info" id="crystalBtn" title="Crystal Report">
            <i class="bi bi-file-earmark-pdf me-1"></i>Report
          </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body p-0" id="stmtBody">
        <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5" style="color:var(--text-3)">
          <i class="bi bi-person-lines-fill" style="font-size:2.5rem;opacity:.3"></i>
          <p class="mt-2 mb-0">Select a partner to view statement</p>
        </div>
      </div>
    </div>
  </div>

</div><!-- /row -->

</div></div></div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
const CURRENCY   = '<?= $currency ?>';
const CRYSTAL_BASE = '<?= $crystalBase ?>';
let allPartners  = [];
let filtered     = [];
let currentCode  = null;
let currentStmt  = [];
const PAGE_SIZE  = 24;
let currentPage  = 1;

// ── load partners ─────────────────────────────────────────────────────────────
async function loadPartners() {
    const q    = document.getElementById('bpSearch').value.trim();
    const type = document.querySelector('input[name="bpType"]:checked').value;
    const url  = '/api/business-partners.php?action=list&q=' + encodeURIComponent(q) + '&type=' + type;

    document.getElementById('bpGrid').innerHTML =
        '<div class="col-12 text-center py-5" style="color:var(--text-3)"><span class="spinner-border me-2"></span>Loading…</div>';

    try {
        const res  = await fetch(url);
        const data = await res.json();
        allPartners = data.data || [];
        filtered    = allPartners;
        currentPage = 1;
        renderPage();
    } catch (e) {
        document.getElementById('bpGrid').innerHTML =
            '<div class="col-12 text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>' + escHtml(e.message) + '</div>';
    }
}

function renderPage() {
    const start = (currentPage - 1) * PAGE_SIZE;
    const slice = filtered.slice(start, start + PAGE_SIZE);
    const grid  = document.getElementById('bpGrid');

    if (!filtered.length) {
        grid.innerHTML = '<div class="col-12 text-center py-5" style="color:var(--text-3)">No partners found.</div>';
        document.getElementById('bpPager').classList.add('d-none');
        return;
    }

    grid.innerHTML = slice.map(bp => bpCard(bp)).join('');
    grid.querySelectorAll('.bp-card').forEach(card => {
        card.addEventListener('click', () => selectPartner(card.dataset.code));
    });

    renderPager();
}

function bpCard(bp) {
    const isCustomer = bp.CardType === 'C';
    const typeColor  = isCustomer ? 'var(--primary)' : 'var(--cyan)';
    const typeLabel  = isCustomer ? 'Customer' : 'Supplier';
    const initials   = (bp.CardName || '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();
    const bal        = parseFloat(bp.CurrentAccountBalance ?? 0);
    const balCls     = bal > 0 ? 'text-success' : bal < 0 ? 'text-danger' : '';
    const active     = currentCode === bp.CardCode ? 'bp-card-active' : '';

    return `<div class="col-12 col-sm-6">
      <div class="bp-card ${active}" data-code="${escHtml(bp.CardCode)}" style="cursor:pointer">
        <div class="d-flex align-items-center gap-2">
          <div class="bp-avatar" style="background:${typeColor}20;color:${typeColor}">${escHtml(initials)}</div>
          <div class="flex-fill overflow-hidden">
            <div class="fw-600 text-truncate" style="font-size:.9rem">${escHtml(bp.CardName)}</div>
            <div style="font-size:.75rem;color:var(--text-3)">${escHtml(bp.CardCode)}</div>
          </div>
          <div class="text-end">
            <div class="badge" style="background:${typeColor}20;color:${typeColor};font-size:.68rem">${typeLabel}</div>
            <div class="mt-1 ${balCls}" style="font-size:.78rem;font-weight:600">${fmt(bal)}</div>
          </div>
        </div>
        ${bp.Phone1 ? `<div class="mt-1" style="font-size:.72rem;color:var(--text-3)"><i class="bi bi-telephone me-1"></i>${escHtml(bp.Phone1)}</div>` : ''}
      </div>
    </div>`;
}

function renderPager() {
    const pages = Math.ceil(filtered.length / PAGE_SIZE);
    const pager = document.getElementById('bpPager');
    if (pages <= 1) { pager.classList.add('d-none'); return; }
    pager.classList.remove('d-none');
    let html = '';
    for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'}" data-page="${i}">${i}</button>`;
    }
    pager.innerHTML = html;
    pager.querySelectorAll('button').forEach(b => {
        b.addEventListener('click', () => { currentPage = +b.dataset.page; renderPage(); });
    });
}

// ── select & load statement ───────────────────────────────────────────────────
async function selectPartner(code) {
    currentCode = code;
    // Re-render cards to show active state
    renderPage();

    const bp    = allPartners.find(p => p.CardCode === code);
    const title = document.getElementById('stmtTitle');
    const body  = document.getElementById('stmtBody');
    const acts  = document.getElementById('stmtActions');

    title.innerHTML = `<i class="bi bi-receipt me-1" style="color:var(--cyan)"></i>${escHtml(bp?.CardName ?? code)}`;
    acts.style.removeProperty('display');
    body.innerHTML  = '<div class="text-center py-5" style="color:var(--text-3)"><span class="spinner-border me-2"></span>Loading statement…</div>';

    try {
        const res  = await fetch('/api/business-partners.php?action=statement&code=' + encodeURIComponent(code));
        const data = await res.json();
        currentStmt = data.data || [];
        renderStatement(currentStmt, bp);
    } catch (e) {
        body.innerHTML = '<div class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>' + escHtml(e.message) + '</div>';
    }
}

function renderStatement(rows, bp) {
    const body = document.getElementById('stmtBody');

    if (!rows.length) {
        body.innerHTML = '<div class="text-center py-5" style="color:var(--text-3)">No transactions found.</div>';
        return;
    }

    // Summary row
    const last     = rows[0];
    const balance  = parseFloat(last.CumulativeBalance ?? 0);
    const balCls   = balance > 0 ? 'text-success' : balance < 0 ? 'text-danger' : '';
    const totalDr  = rows.reduce((s,r) => s + parseFloat(r.Debit  ?? 0), 0);
    const totalCr  = rows.reduce((s,r) => s + parseFloat(r.Credit ?? 0), 0);

    let html = `
    <div class="d-flex gap-3 p-3 border-bottom flex-wrap" style="background:var(--bg-card);font-size:.8rem">
      <div><div style="color:var(--text-3)">Total Debit</div><div class="fw-600 text-danger">${fmt(totalDr)}</div></div>
      <div><div style="color:var(--text-3)">Total Credit</div><div class="fw-600 text-success">${fmt(totalCr)}</div></div>
      <div class="ms-auto"><div style="color:var(--text-3)">Balance</div><div class="fw-700 ${balCls}" style="font-size:1rem">${fmt(balance)}</div></div>
    </div>
    <div class="table-responsive" style="max-height:420px;overflow-y:auto">
    <table class="table statement-table mb-0">
      <thead>
        <tr>
          <th>Date</th><th>Doc #</th><th>Memo</th>
          <th class="text-end">Debit</th><th class="text-end">Credit</th>
          <th class="text-end">Balance</th>
        </tr>
      </thead>
      <tbody>`;

    rows.forEach(r => {
        const dr  = parseFloat(r.Debit  ?? 0);
        const cr  = parseFloat(r.Credit ?? 0);
        const bal = parseFloat(r.CumulativeBalance ?? 0);
        const balCls2 = bal > 0 ? 'text-success' : bal < 0 ? 'text-danger' : '';
        html += `<tr>
          <td style="font-size:.78rem;color:var(--text-3);white-space:nowrap">${r.RefDate?.split('T')[0] ?? ''}</td>
          <td><span class="badge badge-primary" style="font-size:.7rem">#${r.JdtNum ?? ''}</span></td>
          <td style="font-size:.8rem;max-width:200px" class="text-truncate">${escHtml(r.Memo ?? '')}</td>
          <td class="text-end text-danger" style="font-size:.8rem">${dr ? fmt(dr) : '—'}</td>
          <td class="text-end text-success" style="font-size:.8rem">${cr ? fmt(cr) : '—'}</td>
          <td class="text-end fw-600 ${balCls2}" style="font-size:.8rem">${fmt(bal)}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    body.innerHTML = html;
}

// ── CSV export ────────────────────────────────────────────────────────────────
document.getElementById('exportCsvBtn').addEventListener('click', () => {
    if (!currentStmt.length) return;
    const rows = [['Date','Doc#','Memo','Debit','Credit','Balance']];
    currentStmt.forEach(r => rows.push([
        r.RefDate?.split('T')[0] ?? '',
        r.JdtNum ?? '',
        r.Memo ?? '',
        r.Debit  ?? 0,
        r.Credit ?? 0,
        r.CumulativeBalance ?? 0,
    ]));
    const csv  = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], {type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), {href:url, download:`statement_${currentCode}.csv`});
    a.click(); URL.revokeObjectURL(url);
});

// ── Crystal Report ────────────────────────────────────────────────────────────
<?php if ($crystalBase): ?>
document.getElementById('crystalBtn')?.addEventListener('click', () => {
    if (!currentCode) return;
    window.open(CRYSTAL_BASE + '?CardCode=' + encodeURIComponent(currentCode), '_blank');
});
<?php endif; ?>

// ── Search & filter ───────────────────────────────────────────────────────────
let searchTimer;
document.getElementById('bpSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadPartners, 350);
});
document.querySelectorAll('input[name="bpType"]').forEach(r => {
    r.addEventListener('change', loadPartners);
});
document.getElementById('refreshBtn').addEventListener('click', loadPartners);

loadPartners();
</script>
</body></html>
