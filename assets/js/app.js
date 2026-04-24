/* ─── Utility helpers ────────────────────────────────────────────────────── */

const fmt = (n) =>
    Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const fmtDate = (d) => d ? new Date(d).toLocaleDateString() : '—';

function showError(msg) {
    document.getElementById('errorToastBody').innerHTML =
        '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + msg;
    bootstrap.Toast.getOrCreateInstance(document.getElementById('errorToast')).show();
}

/* ─── Flatpickr date picker ──────────────────────────────────────────────── */
flatpickr('#entry_date', {
    dateFormat: 'Y-m-d',
    allowInput: true,
    maxDate: 'today',
});

/* ─── Account Select2 factory ────────────────────────────────────────────── */
function initAccountSelect(selector, placeholder) {
    $(selector).select2({
        placeholder,
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/get_accounts.php',
            dataType: 'json',
            delay: 280,
            data: (params) => ({ q: params.term ?? '' }),
            processResults: (data) => {
                if (data.error) { showError(data.error); return { results: [] }; }
                return { results: data.results };
            },
            cache: true,
        },
        templateResult: (a) => {
            if (a.loading) return $('<span><i class="bi bi-hourglass-split me-1"></i>Searching…</span>');
            if (!a.code)   return $('<span>' + a.text + '</span>');

            const typeLabel = accountTypeLabel(a.type);
            return $(`
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="account-chip">${escHtml(a.code)}</span>
                        ${escHtml(a.name)}
                    </div>
                    ${typeLabel ? `<small class="text-muted ms-2">${typeLabel}</small>` : ''}
                </div>`);
        },
        templateSelection: (a) => a.code
            ? `${a.code} — ${a.name ?? a.text}`
            : (a.text || placeholder),
    });
}

function accountTypeLabel(t) {
    const map = {
        at_Assets:         'Asset',
        at_Liabilities:    'Liability',
        at_Revenues:       'Revenue',
        at_Expenses:       'Expense',
        at_Other:          'Other',
        ot_Assets:         'Asset',
        ot_Revenues:       'Revenue',
        ot_Expenses:       'Expense',
        ot_CostOfRevenues: 'CoGS',
    };
    return map[t] ?? '';
}

function escHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// Initialise all three account dropdowns
initAccountSelect('#expense_account', 'Search expense account…');
initAccountSelect('#vat_account',     'Search VAT / tax account…');
initAccountSelect('#payment_account', 'Search bank / cash account…');

// Capture name when an account is selected
function captureAccountName(selectId, nameId) {
    $(selectId).on('select2:select', (e) => {
        document.getElementById(nameId).value = e.params.data.name ?? '';
        updatePreview();
    });
    $(selectId).on('select2:clear', () => {
        document.getElementById(nameId).value = '';
        updatePreview();
    });
}

captureAccountName('#expense_account', 'expense_account_name');
captureAccountName('#vat_account',     'vat_account_name');
captureAccountName('#payment_account', 'payment_account_name');

/* ─── Payment type toggle label ──────────────────────────────────────────── */
document.querySelectorAll('input[name="payment_type"]').forEach((r) => {
    r.addEventListener('change', () => {
        document.getElementById('paymentAccountLabel').innerHTML =
            r.value === 'bank'
                ? 'Bank Account <span class="text-danger">*</span>'
                : 'Petty Cash Account <span class="text-danger">*</span>';
        updatePreview();
    });
});

/* ─── VAT calculation ────────────────────────────────────────────────────── */
let vatLock = false; // prevent circular update

document.getElementById('amount').addEventListener('input', recalcVat);
document.getElementById('vat_rate').addEventListener('input', recalcVat);
document.getElementById('vat_amount').addEventListener('input', () => {
    vatLock = true;
    updateTotals();
    checkVatAccountRow();
    updatePreview();
    vatLock = false;
});

function recalcVat() {
    if (vatLock) return;
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const rate   = parseFloat(document.getElementById('vat_rate').value) || 0;
    const vat    = Math.round(amount * rate) / 100;
    document.getElementById('vat_amount').value = vat > 0 ? vat.toFixed(2) : '0';
    updateTotals();
    checkVatAccountRow();
    updatePreview();
}

function updateTotals() {
    const amount = parseFloat(document.getElementById('amount').value)     || 0;
    const vat    = parseFloat(document.getElementById('vat_amount').value) || 0;
    const total  = amount + vat;

    document.getElementById('displayAmount').textContent = fmt(amount);
    document.getElementById('displayVat').textContent    = fmt(vat);
    document.getElementById('displayTotal').textContent  = fmt(total);
}

function checkVatAccountRow() {
    const vat = parseFloat(document.getElementById('vat_amount').value) || 0;
    document.getElementById('vatAccountRow').classList.toggle('d-none', vat <= 0);
}

/* ─── Journal entry preview ──────────────────────────────────────────────── */
function updatePreview() {
    const amount   = parseFloat(document.getElementById('amount').value)     || 0;
    const vat      = parseFloat(document.getElementById('vat_amount').value) || 0;
    const total    = amount + vat;

    const expCode  = document.getElementById('expense_account').value;
    const expName  = document.getElementById('expense_account_name').value || expCode;
    const vatCode  = document.getElementById('vat_account').value;
    const vatName  = document.getElementById('vat_account_name').value || vatCode;
    const payCode  = document.getElementById('payment_account').value;
    const payName  = document.getElementById('payment_account_name').value || payCode;

    const lines = [];

    if (expCode || amount > 0) {
        lines.push({
            code:   expCode  || '—',
            name:   expName  || 'Expense Account',
            debit:  amount,
            credit: 0,
            cls:    'preview-line-debit',
        });
    }

    if (vat > 0) {
        lines.push({
            code:   vatCode || '—',
            name:   vatName || 'VAT Account',
            debit:  vat,
            credit: 0,
            cls:    'preview-line-debit',
        });
    }

    if (payCode || total > 0) {
        lines.push({
            code:   payCode || '—',
            name:   payName || 'Payment Account',
            debit:  0,
            credit: total,
            cls:    'preview-line-credit',
        });
    }

    const tbody = document.getElementById('previewLines');
    const tfoot = document.getElementById('previewFoot');

    if (!lines.length) {
        tbody.innerHTML = `
            <tr class="text-muted">
                <td colspan="3" class="text-center py-4">
                    <i class="bi bi-arrow-left-circle me-1"></i>Fill the form to preview
                </td>
            </tr>`;
        tfoot.classList.add('d-none');
        return;
    }

    tbody.innerHTML = lines.map((l) => `
        <tr class="${l.cls}">
            <td>
                <span class="account-chip">${escHtml(l.code)}</span>
                <small>${escHtml(l.name)}</small>
            </td>
            <td class="text-end">${l.debit  > 0 ? fmt(l.debit)  : ''}</td>
            <td class="text-end">${l.credit > 0 ? fmt(l.credit) : ''}</td>
        </tr>`).join('');

    document.getElementById('previewTotalDebit').textContent  = fmt(lines.reduce((s, l) => s + l.debit,  0));
    document.getElementById('previewTotalCredit').textContent = fmt(lines.reduce((s, l) => s + l.credit, 0));
    tfoot.classList.remove('d-none');
}

// Update preview when memo changes
document.getElementById('memo').addEventListener('input', updatePreview);

/* ─── Drop-zone / image upload ───────────────────────────────────────────── */
const dropZone    = document.getElementById('dropZone');
const fileInput   = document.getElementById('receipt');
const previewWrap = document.getElementById('imagePreviewWrapper');
const dropContent = document.getElementById('dropZoneContent');
const imgPreview  = document.getElementById('imagePreview');
const pdfPreview  = document.getElementById('pdfPreview');
const pdfName     = document.getElementById('pdfName');
const removeBtn   = document.getElementById('removeImage');

dropZone.addEventListener('dragover',  (e) => { e.preventDefault(); dropZone.classList.add('dragging'); });
dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('dragging'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragging');
    const file = e.dataTransfer.files[0];
    if (file) handleFile(file);
});

fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) handleFile(fileInput.files[0]);
});

removeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    clearFile();
});

function handleFile(file) {
    if (file.size > 5 * 1024 * 1024) {
        showError('File is too large. Maximum size is 5 MB.');
        return;
    }

    dropContent.classList.add('d-none');
    previewWrap.classList.remove('d-none');

    if (file.type === 'application/pdf') {
        imgPreview.classList.add('d-none');
        pdfPreview.classList.remove('d-none');
        pdfName.textContent = file.name;
    } else {
        pdfPreview.classList.add('d-none');
        imgPreview.classList.remove('d-none');
        const reader = new FileReader();
        reader.onload = (e) => { imgPreview.src = e.target.result; };
        reader.readAsDataURL(file);
    }

    // Transfer file to actual input if drag-dropped
    if (file !== fileInput.files[0]) {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
    }
}

function clearFile() {
    fileInput.value = '';
    imgPreview.src  = '';
    previewWrap.classList.add('d-none');
    pdfPreview.classList.add('d-none');
    imgPreview.classList.add('d-none');
    dropContent.classList.remove('d-none');
}

/* ─── Form submission ────────────────────────────────────────────────────── */
document.getElementById('expenseForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    // Basic client-side validation
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const vat    = parseFloat(document.getElementById('vat_amount').value) || 0;

    if (!document.getElementById('expense_account').value) {
        showError('Please select an expense account.'); return;
    }
    if (!document.getElementById('payment_account').value) {
        showError('Please select a payment account (bank or petty cash).'); return;
    }
    if (amount <= 0) {
        showError('Net amount must be greater than 0.'); return;
    }
    if (vat > 0 && !document.getElementById('vat_account').value) {
        showError('Please select a VAT account when VAT amount is entered.'); return;
    }
    if (!document.getElementById('memo').value.trim()) {
        showError('Please enter a description.'); return;
    }

    // Build multipart form data
    const fd = new FormData(document.getElementById('expenseForm'));

    // Ensure hidden name fields are in FormData
    fd.set('expense_account_name', document.getElementById('expense_account_name').value);
    fd.set('vat_account_name',     document.getElementById('vat_account_name').value);
    fd.set('payment_account_name', document.getElementById('payment_account_name').value);

    setSubmitting(true);

    try {
        const res  = await fetch('api/submit_entry.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            document.getElementById('modalDocEntry').textContent = data.sap_doc_entry ?? '—';
            document.getElementById('modalDocNum').textContent   = data.sap_doc_num   ?? '—';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('successModal')).show();
            loadEntries();
        } else {
            showError(data.error ?? 'Unknown error posting to SAP B1.');
        }
    } catch (err) {
        showError('Network error: ' + err.message);
    } finally {
        setSubmitting(false);
    }
});

function setSubmitting(busy) {
    const btn    = document.getElementById('submitBtn');
    const text   = document.getElementById('submitBtnText');
    const spinner = document.getElementById('submitSpinner');
    btn.disabled = busy;
    text.classList.toggle('d-none', busy);
    spinner.classList.toggle('d-none', !busy);
}

// Reset form after success modal is closed
document.getElementById('newEntryBtn').addEventListener('click', resetForm);
document.getElementById('successModal').addEventListener('hidden.bs.modal', resetForm);

function resetForm() {
    document.getElementById('expenseForm').reset();
    document.getElementById('entry_date')._flatpickr?.setDate(new Date(), false, 'Y-m-d');
    $('#expense_account, #vat_account, #payment_account').val(null).trigger('change');
    ['expense_account_name', 'vat_account_name', 'payment_account_name']
        .forEach((id) => { document.getElementById(id).value = ''; });
    clearFile();
    updateTotals();
    checkVatAccountRow();
    updatePreview();
}

/* ─── Load recent entries ────────────────────────────────────────────────── */
async function loadEntries() {
    const list = document.getElementById('entriesList');
    try {
        const res  = await fetch('api/get_entries.php');
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        if (!data.data.length) {
            list.innerHTML = '<p class="text-center text-muted py-4 mb-0">No entries yet.</p>';
            return;
        }

        list.innerHTML = data.data.map(renderEntry).join('');
    } catch (err) {
        list.innerHTML = `<p class="text-center text-danger py-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>${err.message}</p>`;
    }
}

function renderEntry(e) {
    const sapRef = e.sap_doc_num
        ? `<span class="status-badge" style="background:#eff6ff;color:#2563eb">SAP #${e.sap_doc_num}</span>`
        : '';

    const receiptBadge = e.receipt_path
        ? `<i class="bi bi-paperclip text-muted" title="Receipt attached"></i>`
        : '';

    return `
    <div class="entry-item">
        <div class="entry-memo">${escHtml(e.memo)}</div>
        <div class="entry-amount">${fmt(e.total_amount)}</div>
        <div class="entry-sub">
            ${escHtml(e.expense_account_code)} &middot; ${fmtDate(e.entry_date)}
        </div>
        <div class="entry-sub text-end">
            <small class="text-muted">${e.payment_type === 'bank' ? '🏦' : '💵'} ${escHtml(e.payment_account_code)}</small>
            ${receiptBadge}
        </div>
        <div class="entry-badges">
            <span class="status-badge status-${e.status}">${e.status}</span>
            ${sapRef}
            ${e.ref1 ? `<span class="status-badge" style="background:#f1f5f9;color:#64748b">${escHtml(e.ref1)}</span>` : ''}
        </div>
    </div>`;
}

document.getElementById('refreshEntries').addEventListener('click', loadEntries);

/* ─── Init ───────────────────────────────────────────────────────────────── */
updateTotals();
updatePreview();
loadEntries();
