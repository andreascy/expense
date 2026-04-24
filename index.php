<?php
$cfg = require __DIR__ . '/config/config.php';
$appCurrency    = htmlspecialchars($cfg['app']['currency']);
$defaultVatRate = (float)$cfg['app']['default_vat_rate'];
$companyName    = htmlspecialchars($cfg['app']['company_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Entry — <?= $companyName ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <!-- Flatpickr -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <!-- App styles -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ═══════════════════════════════ HEADER ═══════════════════════════════════ -->
<header class="app-header">
    <div class="container-xl d-flex align-items-center justify-content-between py-3">
        <div class="d-flex align-items-center gap-3">
            <div class="app-logo">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
            <div>
                <h1 class="app-title mb-0">Expense Entry</h1>
                <span class="app-subtitle">SAP Business One · Journal Entries</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-company">
                <i class="bi bi-building me-1"></i><?= $companyName ?>
            </span>
        </div>
    </div>
</header>

<!-- ═══════════════════════════════ MAIN ═════════════════════════════════════ -->
<main class="container-xl py-4">
    <div class="row g-4">

        <!-- ── LEFT: Entry Form ─────────────────────────────────────────── -->
        <div class="col-xl-7">
            <div class="card form-card">
                <div class="card-header">
                    <i class="bi bi-plus-circle-fill me-2 text-primary"></i>
                    New Expense Entry
                </div>
                <div class="card-body p-4">
                    <form id="expenseForm" novalidate>

                        <!-- Date + Reference -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-calendar3 me-1"></i>Date <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="entry_date" name="entry_date"
                                       class="form-control flatpickr"
                                       placeholder="Select date"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">
                                    <i class="bi bi-hash me-1"></i>Reference / Doc No.
                                </label>
                                <input type="text" id="ref1" name="ref1" class="form-control"
                                       placeholder="e.g. INV-2024-001" maxlength="100">
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-card-text me-1"></i>Description <span class="text-danger">*</span>
                            </label>
                            <textarea id="memo" name="memo" class="form-control" rows="2"
                                      placeholder="e.g. Office supplies — January 2024"
                                      maxlength="255" required></textarea>
                        </div>

                        <!-- ── Section: Expense Account ──────────────────── -->
                        <div class="section-divider mb-3">
                            <span><i class="bi bi-arrow-up-circle text-danger me-1"></i>Debit Side</span>
                        </div>

                        <!-- Expense Account -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Expense Account <span class="text-danger">*</span>
                            </label>
                            <select id="expense_account" name="expense_account" class="form-select account-select" required>
                                <option value="">Search by code or name…</option>
                            </select>
                            <input type="hidden" id="expense_account_name" name="expense_account_name">
                        </div>

                        <!-- Amount -->
                        <div class="row g-3 mb-3">
                            <div class="col-sm-5">
                                <label class="form-label fw-semibold">
                                    Net Amount (<?= $appCurrency ?>) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text currency-symbol"><?= $appCurrency ?></span>
                                    <input type="number" id="amount" name="amount"
                                           class="form-control text-end amount-input"
                                           placeholder="0.00" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">VAT %</label>
                                <div class="input-group">
                                    <input type="number" id="vat_rate" class="form-control text-end"
                                           placeholder="<?= $defaultVatRate ?>"
                                           step="0.01" min="0" max="100"
                                           value="<?= $defaultVatRate ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <label class="form-label fw-semibold">
                                    VAT Amount (<?= $appCurrency ?>)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text currency-symbol"><?= $appCurrency ?></span>
                                    <input type="number" id="vat_amount" name="vat_amount"
                                           class="form-control text-end amount-input"
                                           placeholder="0.00" step="0.01" min="0" value="0">
                                </div>
                            </div>
                        </div>

                        <!-- VAT Account (shown when VAT > 0) -->
                        <div id="vatAccountRow" class="mb-4 d-none">
                            <label class="form-label fw-semibold">
                                VAT Account <span class="text-danger">*</span>
                            </label>
                            <select id="vat_account" name="vat_account" class="form-select account-select">
                                <option value="">Search VAT / Tax account…</option>
                            </select>
                            <input type="hidden" id="vat_account_name" name="vat_account_name">
                        </div>

                        <!-- ── Section: Credit Side ──────────────────────── -->
                        <div class="section-divider mb-3">
                            <span><i class="bi bi-arrow-down-circle text-success me-1"></i>Credit Side (Payment)</span>
                        </div>

                        <!-- Payment type toggle -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <div class="payment-toggle">
                                <input type="radio" class="btn-check" name="payment_type"
                                       id="pt_bank" value="bank" checked>
                                <label class="btn btn-outline-primary" for="pt_bank">
                                    <i class="bi bi-bank2 me-1"></i>Bank
                                </label>

                                <input type="radio" class="btn-check" name="payment_type"
                                       id="pt_petty" value="petty_cash">
                                <label class="btn btn-outline-warning" for="pt_petty">
                                    <i class="bi bi-cash-coin me-1"></i>Petty Cash
                                </label>
                            </div>
                        </div>

                        <!-- Payment Account -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold" id="paymentAccountLabel">
                                Bank Account <span class="text-danger">*</span>
                            </label>
                            <select id="payment_account" name="payment_account"
                                    class="form-select account-select" required>
                                <option value="">Search bank / cash account…</option>
                            </select>
                            <input type="hidden" id="payment_account_name" name="payment_account_name">
                        </div>

                        <!-- ── Section: Receipt ──────────────────────────── -->
                        <div class="section-divider mb-3">
                            <span><i class="bi bi-paperclip me-1"></i>Receipt / Attachment</span>
                        </div>

                        <div class="mb-4">
                            <div id="dropZone" class="drop-zone">
                                <div class="drop-zone-content" id="dropZoneContent">
                                    <i class="bi bi-cloud-upload fs-2 mb-2"></i>
                                    <p class="mb-1">Drag &amp; drop receipt here</p>
                                    <small class="text-muted">or click to browse — JPG, PNG, PDF · max 5 MB</small>
                                </div>
                                <div id="imagePreviewWrapper" class="d-none">
                                    <img id="imagePreview" src="" alt="Receipt preview" class="receipt-preview">
                                    <div id="pdfPreview" class="pdf-preview d-none">
                                        <i class="bi bi-file-earmark-pdf fs-1 text-danger"></i>
                                        <p id="pdfName" class="mb-0 mt-2 text-truncate"></p>
                                    </div>
                                    <button type="button" id="removeImage" class="btn btn-sm btn-danger remove-image-btn">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <input type="file" id="receipt" name="receipt"
                                       accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"
                                       class="drop-zone-input">
                            </div>
                        </div>

                        <!-- ── Totals summary ────────────────────────────── -->
                        <div class="totals-card mb-4">
                            <div class="row g-0 text-center">
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

                        <!-- Submit -->
                        <div class="d-grid">
                            <button type="submit" id="submitBtn" class="btn btn-primary btn-lg submit-btn">
                                <span id="submitBtnText">
                                    <i class="bi bi-send-fill me-2"></i>Post Journal Entry to SAP B1
                                </span>
                                <span id="submitSpinner" class="d-none">
                                    <span class="spinner-border spinner-border-sm me-2"></span>
                                    Posting to SAP B1…
                                </span>
                            </button>
                        </div>

                    </form>
                </div><!-- /card-body -->
            </div><!-- /form-card -->
        </div><!-- /col -->

        <!-- ── RIGHT: Entry Preview + Summary ──────────────────────────── -->
        <div class="col-xl-5">

            <!-- Journal Entry Preview -->
            <div class="card preview-card mb-4">
                <div class="card-header">
                    <i class="bi bi-journal-text me-2 text-info"></i>
                    Journal Entry Preview
                </div>
                <div class="card-body p-0">
                    <table class="table table-borderless mb-0 preview-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody id="previewLines">
                            <tr class="text-muted">
                                <td colspan="3" class="text-center py-4">
                                    <i class="bi bi-arrow-left-circle me-1"></i>
                                    Fill the form to preview
                                </td>
                            </tr>
                        </tbody>
                        <tfoot id="previewFoot" class="d-none">
                            <tr>
                                <td class="fw-semibold">Total</td>
                                <td class="text-end fw-semibold" id="previewTotalDebit">0.00</td>
                                <td class="text-end fw-semibold" id="previewTotalCredit">0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Recent Entries -->
            <div class="card entries-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2 text-secondary"></i>Recent Entries</span>
                    <button class="btn btn-sm btn-outline-secondary" id="refreshEntries">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="entriesList" class="entries-list">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm me-2"></div>Loading…
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /col right -->
    </div><!-- /row -->
</main>

<!-- ═══════════════════════════ SUCCESS MODAL ════════════════════════════════ -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-5">
                <div class="success-icon mb-3">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h4 class="mb-1">Entry Posted!</h4>
                <p class="text-muted mb-3">Journal entry created in SAP Business One.</p>
                <div class="doc-info mb-4">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="doc-badge">
                                <small class="text-muted d-block">Doc Entry</small>
                                <strong id="modalDocEntry">—</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="doc-badge">
                                <small class="text-muted d-block">Doc Number</small>
                                <strong id="modalDocNum">—</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal" id="newEntryBtn">
                    <i class="bi bi-plus-circle me-1"></i>New Entry
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ ERROR TOAST ══════════════════════════════════ -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="errorToast" class="toast align-items-center text-bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="errorToastBody">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                Error message here.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ SCRIPTS ══════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const APP_CURRENCY    = '<?= $appCurrency ?>';
    const DEFAULT_VAT_RATE = <?= $defaultVatRate ?>;
</script>
<script src="assets/js/app.js"></script>
</body>
</html>
