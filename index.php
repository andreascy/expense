<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/database.php';

$cfg            = getAppConfig();
$appCurrency    = htmlspecialchars($cfg['app']['currency']);
$defaultVatRate = (float)$cfg['app']['default_vat_rate'];
$companyName    = htmlspecialchars($cfg['app']['company_name']);
$pageTitle      = 'Expense Entry';
$pageSubtitle   = 'Post journal entries to SAP Business One';
$activePage     = 'expense';
include __DIR__ . '/includes/head.php';
?>
<div class="app-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="page-body">

<div class="row g-4">
  <!-- ── Form ── -->
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header">
        <i class="bi bi-plus-circle" style="color:var(--primary)"></i>
        New Expense Entry
      </div>
      <div class="card-body">
        <form id="expenseForm" novalidate>

          <div class="row g-3 mb-4">
            <div class="col-sm-6">
              <label class="form-label">Date *</label>
              <input type="text" id="entry_date" name="entry_date" class="form-control"
                     value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Reference / Doc No.</label>
              <input type="text" id="ref1" name="ref1" class="form-control"
                     placeholder="e.g. INV-2024-001" maxlength="100">
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">Description *</label>
            <textarea id="memo" name="memo" class="form-control" rows="2"
                      placeholder="e.g. Office supplies — January 2024" maxlength="255" required></textarea>
          </div>

          <div class="section-divider mb-3">
            <span style="color:var(--red)"><i class="bi bi-arrow-up-circle me-1"></i>Debit Side</span>
          </div>

          <div class="mb-3">
            <label class="form-label">Expense Account *</label>
            <select id="expense_account" name="expense_account" class="form-select account-select" required>
              <option value="">Search by code or name…</option>
            </select>
            <input type="hidden" id="expense_account_name" name="expense_account_name">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-sm-5">
              <label class="form-label">Net Amount (<?= $appCurrency ?>) *</label>
              <div class="input-group">
                <span class="input-group-text"><?= $appCurrency ?></span>
                <input type="number" id="amount" name="amount" class="form-control"
                       placeholder="0.00" step="0.01" min="0" required>
              </div>
            </div>
            <div class="col-sm-3">
              <label class="form-label">VAT %</label>
              <div class="input-group">
                <input type="number" id="vat_rate" class="form-control"
                       value="<?= $defaultVatRate ?>" step="0.01" min="0" max="100">
                <span class="input-group-text">%</span>
              </div>
            </div>
            <div class="col-sm-4">
              <label class="form-label">VAT Amount (<?= $appCurrency ?>)</label>
              <div class="input-group">
                <span class="input-group-text"><?= $appCurrency ?></span>
                <input type="number" id="vat_amount" name="vat_amount" class="form-control"
                       placeholder="0.00" step="0.01" min="0" value="0">
              </div>
            </div>
          </div>

          <div id="vatAccountRow" class="mb-4 d-none">
            <label class="form-label">VAT Account *</label>
            <select id="vat_account" name="vat_account" class="form-select account-select">
              <option value="">Search VAT / tax account…</option>
            </select>
            <input type="hidden" id="vat_account_name" name="vat_account_name">
          </div>

          <div class="section-divider mb-3">
            <span style="color:var(--green)"><i class="bi bi-arrow-down-circle me-1"></i>Credit Side (Payment)</span>
          </div>

          <div class="mb-3">
            <label class="form-label">Payment Method</label>
            <div class="payment-toggle">
              <input type="radio" class="btn-check" name="payment_type" id="pt_bank" value="bank" checked>
              <label class="btn btn-outline-primary" for="pt_bank">
                <i class="bi bi-bank2"></i> Bank
              </label>
              <input type="radio" class="btn-check" name="payment_type" id="pt_petty" value="petty_cash">
              <label class="btn btn-outline-warning" for="pt_petty">
                <i class="bi bi-cash-coin"></i> Petty Cash
              </label>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label" id="paymentAccountLabel">Bank Account *</label>
            <select id="payment_account" name="payment_account" class="form-select account-select" required>
              <option value="">Search bank / cash account…</option>
            </select>
            <input type="hidden" id="payment_account_name" name="payment_account_name">
          </div>

          <div class="section-divider mb-3">
            <span><i class="bi bi-paperclip me-1"></i>Receipt</span>
          </div>

          <div class="mb-4">
            <div id="dropZone" class="drop-zone">
              <div class="drop-zone-content" id="dropZoneContent">
                <i class="bi bi-cloud-upload fs-2 mb-2 d-block"></i>
                <p class="mb-1" style="font-size:.875rem">Drag &amp; drop receipt here</p>
                <small>JPG, PNG, PDF · max 5 MB</small>
              </div>
              <div id="imagePreviewWrapper" class="d-none w-100">
                <img id="imagePreview" src="" alt="" class="receipt-preview">
                <div id="pdfPreview" class="pdf-preview d-none">
                  <i class="bi bi-file-earmark-pdf" style="font-size:2.5rem"></i>
                  <p id="pdfName" class="mt-2 mb-0" style="font-size:.8rem;color:var(--text-2)"></p>
                </div>
                <button type="button" id="removeImage" class="btn btn-danger btn-sm remove-image-btn">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
              <input type="file" id="receipt" name="receipt"
                     accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"
                     class="drop-zone-input">
            </div>
          </div>

          <div class="totals-card mb-4">
            <div class="row g-0">
              <div class="col-4 totals-item">
                <div class="totals-label">Net Amount</div>
                <div class="totals-value" id="displayAmount">0.00</div>
              </div>
              <div class="col-4 totals-item totals-item-mid">
                <div class="totals-label">VAT</div>
                <div class="totals-value" id="displayVat">0.00</div>
              </div>
              <div class="col-4 totals-item totals-item-total">
                <div class="totals-label">Total</div>
                <div class="totals-value fw-bold" id="displayTotal">0.00</div>
              </div>
            </div>
          </div>

          <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100">
            <span id="submitBtnText"><i class="bi bi-send-fill me-1"></i>Post Journal Entry to SAP B1</span>
            <span id="submitSpinner" class="d-none">
              <span class="spinner-border spinner-border-sm me-1"></span>Posting…
            </span>
          </button>

        </form>
      </div>
    </div>
  </div>

  <!-- ── Right panel ── -->
  <div class="col-xl-5">
    <div class="card mb-4">
      <div class="card-header">
        <i class="bi bi-journal-text" style="color:var(--cyan)"></i>
        Journal Entry Preview
      </div>
      <div class="card-body p-0">
        <table class="table preview-table mb-0">
          <thead>
            <tr><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr>
          </thead>
          <tbody id="previewLines">
            <tr><td colspan="3" class="text-center py-4" style="color:var(--text-3)">
              <i class="bi bi-arrow-left-circle me-1"></i>Fill the form to preview
            </td></tr>
          </tbody>
          <tfoot id="previewFoot" class="d-none">
            <tr>
              <td>Total</td>
              <td class="text-end" id="previewTotalDebit">0.00</td>
              <td class="text-end" id="previewTotalCredit">0.00</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1" style="color:var(--text-3)"></i>Recent Entries</span>
        <button class="btn btn-sm btn-outline-secondary" id="refreshEntries">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
      </div>
      <div class="card-body p-0">
        <div id="entriesList" class="entries-list">
          <div class="text-center py-4" style="color:var(--text-3)">
            <span class="spinner-border spinner-border-sm me-1"></span>Loading…
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</div><!-- /page-body -->
</div><!-- /main-content -->
</div><!-- /app-layout -->

<!-- Success modal -->
<div class="modal fade" id="successModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center p-5">
        <div class="success-icon mb-3"><i class="bi bi-check-circle-fill"></i></div>
        <h4 class="mb-1">Entry Posted!</h4>
        <p style="color:var(--text-3)" class="mb-3">Journal entry created in SAP Business One.</p>
        <div class="row g-2 mb-4">
          <div class="col-6"><div class="doc-badge"><small style="color:var(--text-3);display:block">Doc Entry</small><strong id="modalDocEntry">—</strong></div></div>
          <div class="col-6"><div class="doc-badge"><small style="color:var(--text-3);display:block">Doc Number</small><strong id="modalDocNum">—</strong></div></div>
        </div>
        <button class="btn btn-primary px-4" data-bs-dismiss="modal" id="newEntryBtn">
          <i class="bi bi-plus-circle me-1"></i>New Entry
        </button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/scripts.php'; ?>
<script>
const APP_CURRENCY    = '<?= $appCurrency ?>';
const DEFAULT_VAT_RATE = <?= $defaultVatRate ?>;
</script>
<script src="/assets/js/app.js"></script>
</body>
</html>
