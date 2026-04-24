<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
$pageTitle    = 'Account Setup';
$pageSubtitle = 'Assign SAP accounts to expense, bank, and petty cash categories';
$activePage   = 'accounts';
include __DIR__ . '/../includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php
$topbarActions = '
<button class="btn btn-outline-secondary btn-sm" id="syncSapBtn">
    <i class="bi bi-arrow-repeat me-1"></i>Sync from SAP
</button>
<button class="btn btn-primary btn-sm" id="saveAllBtn">
    <i class="bi bi-floppy me-1"></i>Save Changes
</button>';
include __DIR__ . '/../includes/topbar.php';
?>
<div class="page-body">

<!-- Filter + Search bar -->
<div class="card mb-4">
  <div class="card-body" style="padding:.875rem 1.25rem">
    <div class="row g-3 align-items-center">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="accountSearch" class="form-control" placeholder="Search by code or name…">
        </div>
      </div>
      <div class="col-md-7">
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-sm filter-btn active" data-filter="">All Accounts</button>
          <button class="btn btn-sm filter-btn" data-filter="expense" style="background:var(--red-bg);color:var(--red);border-color:var(--red)">
            <i class="bi bi-arrow-up-circle me-1"></i>Expenses
          </button>
          <button class="btn btn-sm filter-btn" data-filter="bank" style="background:var(--cyan-bg);color:var(--cyan);border-color:var(--cyan)">
            <i class="bi bi-bank2 me-1"></i>Banks
          </button>
          <button class="btn btn-sm filter-btn" data-filter="petty_cash" style="background:var(--amber-bg);color:var(--amber);border-color:var(--amber)">
            <i class="bi bi-cash-coin me-1"></i>Petty Cash
          </button>
          <button class="btn btn-sm filter-btn" data-filter="none" style="background:var(--bg-hover);color:var(--text-3);border-color:var(--border)">
            <i class="bi bi-dash-circle me-1"></i>Unassigned
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4" id="statsRow">
  <div class="col-6 col-md-3">
    <div class="card text-center" style="padding:.875rem">
      <div style="font-size:1.5rem;font-weight:700;color:var(--text-1)" id="statTotal">—</div>
      <div style="font-size:.75rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px">Total</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center" style="padding:.875rem">
      <div style="font-size:1.5rem;font-weight:700;color:var(--red)" id="statExpense">—</div>
      <div style="font-size:.75rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px">Expenses</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center" style="padding:.875rem">
      <div style="font-size:1.5rem;font-weight:700;color:var(--cyan)" id="statBank">—</div>
      <div style="font-size:.75rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px">Banks</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center" style="padding:.875rem">
      <div style="font-size:1.5rem;font-weight:700;color:var(--amber)" id="statPetty">—</div>
      <div style="font-size:.75rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px">Petty Cash</div>
    </div>
  </div>
</div>

<!-- Accounts table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-diagram-3" style="color:var(--primary)"></i> Chart of Accounts</span>
    <span id="accountCount" class="badge badge-secondary">—</span>
  </div>
  <div id="tableWrap" class="table-responsive">
    <table class="table mb-0" id="accountsTable">
      <thead>
        <tr>
          <th>Code</th>
          <th>Account Name</th>
          <th>SAP Type</th>
          <th>Category</th>
        </tr>
      </thead>
      <tbody id="accountsBody">
        <tr><td colspan="4" class="text-center py-5" style="color:var(--text-3)">
          Click <strong>Sync from SAP</strong> to load accounts, or saved accounts appear here.
        </td></tr>
      </tbody>
    </table>
  </div>
</div>

</div></div></div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
let allAccounts = [];   // merged list: SAP accounts + saved categories
let savedCategories = {};
let activeFilter = '';
let pendingChanges = {};

const catLabel = {
    expense:    '<span class="category-pill category-expense"><i class="bi bi-arrow-up-circle me-1"></i>Expense</span>',
    bank:       '<span class="category-pill category-bank"><i class="bi bi-bank2 me-1"></i>Bank</span>',
    petty_cash: '<span class="category-pill category-petty_cash"><i class="bi bi-cash-coin me-1"></i>Petty Cash</span>',
    '':         '<span class="category-pill category-none">—</span>',
};

const sapTypeLabel = t => {
    const m = { at_Expenses:'Expense', at_Assets:'Asset', at_Liabilities:'Liability', at_Revenues:'Revenue' };
    return m[t] ? `<span class="badge badge-secondary">${m[t]}</span>` : '';
};

// Load saved categories on page load
async function loadSaved() {
    const res  = await fetch('/api/account-categories.php');
    const data = await res.json();
    savedCategories = {};
    (data.data || []).forEach(r => { savedCategories[r.account_code] = r; });
    // If no SAP accounts loaded yet, show saved ones in table
    if (allAccounts.length === 0) {
        allAccounts = Object.values(savedCategories).map(r => ({
            Code: r.account_code, Name: r.account_name, AccountType: ''
        }));
    }
    renderTable();
}

document.getElementById('syncSapBtn').addEventListener('click', async () => {
    const btn = document.getElementById('syncSapBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing…';
    try {
        const res  = await fetch('/api/account-categories.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'sync' })
        });
        const data = await res.json();
        if (data.success) {
            allAccounts = data.accounts || [];
            renderTable();
            showToast(`Loaded ${allAccounts.length} accounts from SAP.`);
        } else {
            showToast(data.error || 'Sync failed.', 'error');
        }
    } catch(e) { showToast('Network error.', 'error'); }
    finally { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync from SAP'; }
});

document.getElementById('saveAllBtn').addEventListener('click', async () => {
    const entries = allAccounts.map(a => ({
        code:     a.Code,
        name:     a.Name,
        category: pendingChanges[a.Code] !== undefined ? pendingChanges[a.Code] : (savedCategories[a.Code]?.category || ''),
    }));

    const res  = await fetch('/api/account-categories.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'save', entries })
    });
    const data = await res.json();
    if (data.success) {
        pendingChanges = {};
        await loadSaved();
        showToast('Account categories saved.');
    } else showToast(data.error || 'Error saving.', 'error');
});

function getCategory(code) {
    if (pendingChanges[code] !== undefined) return pendingChanges[code];
    return savedCategories[code]?.category || '';
}

function renderTable() {
    const search = document.getElementById('accountSearch').value.toLowerCase();
    let rows = allAccounts;

    if (search) {
        rows = rows.filter(a =>
            a.Code.toLowerCase().includes(search) ||
            a.Name.toLowerCase().includes(search)
        );
    }
    if (activeFilter === 'none') {
        rows = rows.filter(a => !getCategory(a.Code));
    } else if (activeFilter) {
        rows = rows.filter(a => getCategory(a.Code) === activeFilter);
    }

    document.getElementById('accountCount').textContent = rows.length;

    // Update stats
    document.getElementById('statTotal').textContent   = allAccounts.length;
    document.getElementById('statExpense').textContent = allAccounts.filter(a => getCategory(a.Code) === 'expense').length;
    document.getElementById('statBank').textContent    = allAccounts.filter(a => getCategory(a.Code) === 'bank').length;
    document.getElementById('statPetty').textContent   = allAccounts.filter(a => getCategory(a.Code) === 'petty_cash').length;

    if (!rows.length) {
        document.getElementById('accountsBody').innerHTML =
            '<tr><td colspan="4" class="text-center py-5" style="color:var(--text-3)">No accounts match filter.</td></tr>';
        return;
    }

    document.getElementById('accountsBody').innerHTML = rows.map(a => {
        const cat = getCategory(a.Code);
        return `<tr>
            <td><code style="color:var(--primary-text);font-size:.85rem">${escHtml(a.Code)}</code></td>
            <td style="font-weight:500">${escHtml(a.Name)}</td>
            <td>${sapTypeLabel(a.AccountType)}</td>
            <td>
                <select class="cat-select" data-code="${escHtml(a.Code)}" onchange="setCategory('${escHtml(a.Code)}',this.value)">
                    <option value=""    ${cat===''?'selected':''}>— None —</option>
                    <option value="expense"    ${cat==='expense'?'selected':''}>💸 Expense</option>
                    <option value="bank"       ${cat==='bank'?'selected':''}>🏦 Bank</option>
                    <option value="petty_cash" ${cat==='petty_cash'?'selected':''}>💵 Petty Cash</option>
                </select>
            </td>
        </tr>`;
    }).join('');
}

function setCategory(code, val) {
    pendingChanges[code] = val;
    // re-render stats only (no full re-render to preserve dropdown focus)
    document.getElementById('statTotal').textContent   = allAccounts.length;
    document.getElementById('statExpense').textContent = allAccounts.filter(a => getCategory(a.Code) === 'expense').length;
    document.getElementById('statBank').textContent    = allAccounts.filter(a => getCategory(a.Code) === 'bank').length;
    document.getElementById('statPetty').textContent   = allAccounts.filter(a => getCategory(a.Code) === 'petty_cash').length;
}

// Filters
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeFilter = btn.dataset.filter;
        renderTable();
    });
});

document.getElementById('accountSearch').addEventListener('input', renderTable);

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Active filter button style
const styleTag = document.createElement('style');
styleTag.textContent = '.filter-btn.active{outline:2px solid var(--primary);outline-offset:2px}';
document.head.appendChild(styleTag);

loadSaved();
</script>
</body></html>
