<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
$cfg          = getAppConfig();
$currency     = htmlspecialchars($cfg['app']['currency']);
$pageTitle    = 'Dashboard';
$pageSubtitle = 'Journal entries overview';
$activePage   = 'dashboard';
include __DIR__ . '/../includes/head.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<div class="app-layout">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="page-body">

<!-- KPI cards -->
<div class="row g-3 mb-4" id="kpiRow">
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-primary"><i class="bi bi-journal-check"></i></div>
      <div><div class="stat-value" id="kpiTotal">—</div><div class="stat-label">Total Entries</div></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-green"><i class="bi bi-check-circle"></i></div>
      <div><div class="stat-value" id="kpiPosted">—</div><div class="stat-label">Posted</div></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-red"><i class="bi bi-x-circle"></i></div>
      <div><div class="stat-value" id="kpiFailed">—</div><div class="stat-label">Failed</div></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon stat-icon-cyan"><i class="bi bi-currency-exchange"></i></div>
      <div><div class="stat-value" id="kpiAmount">—</div><div class="stat-label">Total Amount (<?= $currency ?>)</div></div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Chart -->
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart-line me-1" style="color:var(--primary)"></i>Monthly Expenses</span>
        <span style="font-size:.75rem;color:var(--text-3)">Last 12 months</span>
      </div>
      <div class="card-body">
        <div class="chart-wrap"><canvas id="monthChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Month breakdown -->
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-table me-1" style="color:var(--cyan)"></i>Breakdown by Month
      </div>
      <div class="table-responsive" style="max-height:340px;overflow-y:auto">
        <table class="table mb-0">
          <thead>
            <tr><th>Month</th><th class="text-end">Entries</th><th class="text-end">Total (<?= $currency ?>)</th><th></th></tr>
          </thead>
          <tbody id="monthTable">
            <tr><td colspan="4" class="text-center py-4" style="color:var(--text-3)">
              <span class="spinner-border spinner-border-sm me-1"></span>Loading…
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Drill-down panel -->
<div id="drillPanel" class="card mt-4 d-none">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span id="drillTitle"><i class="bi bi-list-ul me-1" style="color:var(--amber)"></i>Entries</span>
    <button class="btn btn-sm btn-outline-secondary" id="closeDrill"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr><th>Date</th><th>Description</th><th>Account</th><th>Payment</th><th class="text-end">Amount (<?= $currency ?>)</th><th>SAP #</th><th>Status</th></tr>
      </thead>
      <tbody id="drillBody"></tbody>
    </table>
  </div>
</div>

</div></div></div>

<?php include __DIR__ . '/../includes/scripts.php'; ?>
<script>
const CURRENCY = '<?= $currency ?>';
let chartInstance = null;

async function loadDashboard() {
    const res  = await fetch('/api/journal-stats.php');
    const data = await res.json();

    // KPIs
    document.getElementById('kpiTotal').textContent  = data.kpi?.total_entries ?? 0;
    document.getElementById('kpiPosted').textContent = data.kpi?.posted ?? 0;
    document.getElementById('kpiFailed').textContent = data.kpi?.failed ?? 0;
    document.getElementById('kpiAmount').textContent = fmt(data.kpi?.total_amount ?? 0);

    // Chart
    const months = (data.monthly || []).map(r => {
        const [y,m] = r.month.split('-');
        return new Date(y,m-1).toLocaleString('default',{month:'short',year:'2-digit'});
    });
    const totals = (data.monthly || []).map(r => parseFloat(r.total));

    const isDark  = document.documentElement.getAttribute('data-theme') !== 'light';
    const gridCol = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.07)';
    const textCol = isDark ? '#64748b' : '#94a3b8';

    if (chartInstance) chartInstance.destroy();
    chartInstance = new Chart(document.getElementById('monthChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Total (' + CURRENCY + ')',
                data: totals,
                backgroundColor: 'rgba(99,102,241,.65)',
                borderColor: '#6366f1',
                borderWidth: 2,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridCol }, ticks: { color: textCol, font: { size: 11 } } },
                y: { grid: { color: gridCol }, ticks: { color: textCol, font: { size: 11 },
                     callback: v => fmt(v) } }
            }
        }
    });

    // Month table
    const tbody = document.getElementById('monthTable');
    if (!data.monthly?.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4" style="color:var(--text-3)">No data yet.</td></tr>';
        return;
    }
    tbody.innerHTML = [...data.monthly].reverse().map(r => {
        const [y,m] = r.month.split('-');
        const label = new Date(y,m-1).toLocaleString('default',{month:'long',year:'numeric'});
        return `<tr class="month-row" data-month="${r.month}">
            <td><strong>${label}</strong></td>
            <td class="text-end">${r.entries}</td>
            <td class="text-end"><strong>${fmt(r.total)}</strong></td>
            <td class="text-end"><i class="bi bi-chevron-down" style="color:var(--text-3)"></i></td>
        </tr>`;
    }).join('');

    document.querySelectorAll('.month-row').forEach(row => {
        row.addEventListener('click', () => drillDown(row.dataset.month));
    });
}

async function drillDown(month) {
    const panel = document.getElementById('drillPanel');
    const title = document.getElementById('drillTitle');
    const body  = document.getElementById('drillBody');
    const [y,m] = month.split('-');
    const label = new Date(y,m-1).toLocaleString('default',{month:'long',year:'numeric'});
    title.innerHTML = `<i class="bi bi-list-ul me-1" style="color:var(--amber)"></i>Entries — ${label}`;
    body.innerHTML  = '<tr><td colspan="7" class="text-center py-3" style="color:var(--text-3)"><span class="spinner-border spinner-border-sm me-1"></span></td></tr>';
    panel.classList.remove('d-none');
    panel.scrollIntoView({behavior:'smooth', block:'start'});

    const res  = await fetch('/api/journal-stats.php?month=' + month);
    const data = await res.json();
    const rows = data.drill || [];

    if (!rows.length) { body.innerHTML = '<tr><td colspan="7" class="text-center py-4" style="color:var(--text-3)">No entries.</td></tr>'; return; }

    const statusCls = {posted:'badge-success',failed:'badge-danger',pending:'badge-warning'};
    body.innerHTML = rows.map(r => `
        <tr>
            <td style="color:var(--text-3);font-size:.8rem">${r.entry_date}</td>
            <td style="font-weight:500">${escHtml(r.memo)}</td>
            <td><code style="color:var(--primary-text);font-size:.8rem">${escHtml(r.expense_account_code)}</code></td>
            <td style="font-size:.8rem;color:var(--text-3)">${r.payment_type === 'bank' ? '🏦' : '💵'} ${escHtml(r.payment_account_code)}</td>
            <td class="text-end"><strong>${fmt(r.total_amount)}</strong></td>
            <td>${r.sap_doc_num ? `<span class="badge badge-primary">#${r.sap_doc_num}</span>` : '—'}</td>
            <td><span class="badge ${statusCls[r.status]||'badge-secondary'}">${r.status}</span></td>
        </tr>`).join('');
}

document.getElementById('closeDrill').addEventListener('click', () => {
    document.getElementById('drillPanel').classList.add('d-none');
});

// Reload chart on theme change
document.getElementById('themeToggle')?.addEventListener('click', () => {
    setTimeout(loadDashboard, 200);
});

loadDashboard();
</script>
</body></html>
