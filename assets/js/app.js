/* ── Helpers ─────────────────────────────────────────────────────────────── */
const fmt = n => Number(n).toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 });
const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString() : '—';
const escHtml = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

/* ── Flatpickr ───────────────────────────────────────────────────────────── */
flatpickr('#entry_date', { dateFormat:'Y-m-d', allowInput:true, maxDate:'today' });

/* ── Select2 account search ──────────────────────────────────────────────── */
function initAccountSelect(selector, placeholder) {
    $(selector).select2({
        placeholder,
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: '/api/get_accounts.php',
            dataType: 'json',
            delay: 250,
            data: p => ({ q: p.term ?? '' }),
            processResults: d => {
                if (d.error) { showToast(d.error, 'error'); return { results:[] }; }
                return { results: d.results };
            },
            cache: true,
        },
        templateResult: a => {
            if (a.loading) return $('<span>Searching…</span>');
            if (!a.code)   return $('<span>' + a.text + '</span>');
            const typeMap = { at_Assets:'Asset', at_Expenses:'Expense', at_Liabilities:'Liability', at_Revenues:'Revenue' };
            const type = typeMap[a.type] || '';
            return $(`<div class="d-flex justify-content-between align-items-center gap-2">
                <div><span class="account-chip">${escHtml(a.code)}</span>${escHtml(a.name)}</div>
                ${type ? `<small style="color:var(--text-3);white-space:nowrap">${type}</small>` : ''}
            </div>`);
        },
        templateSelection: a => a.code ? `${a.code} — ${a.name ?? a.text}` : (a.text || placeholder),
    });
}

initAccountSelect('#expense_account',  'Search expense account…');
initAccountSelect('#vat_account',      'Search VAT / tax account…');
initAccountSelect('#payment_account',  'Search bank / cash account…');

function captureAccountName(selectId, nameId) {
    $(selectId).on('select2:select', e => {
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

/* ── Payment type label ──────────────────────────────────────────────────── */
document.querySelectorAll('input[name="payment_type"]').forEach(r => {
    r.addEventListener('change', () => {
        document.getElementById('paymentAccountLabel').innerHTML =
            r.value === 'bank'
                ? 'Bank Account <span style="color:var(--red)">*</span>'
                : 'Petty Cash Account <span style="color:var(--red)">*</span>';
        updatePreview();
    });
});

/* ── VAT calculation ─────────────────────────────────────────────────────── */
let vatLock = false;

document.getElementById('amount').addEventListener('input', recalcVat);
document.getElementById('vat_rate').addEventListener('input', recalcVat);
document.getElementById('vat_amount').addEventListener('input', () => {
    vatLock = true;
    updateTotals(); checkVatRow(); updatePreview();
    vatLock = false;
});

function recalcVat() {
    if (vatLock) return;
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const rate   = parseFloat(document.getElementById('vat_rate').value) || 0;
    document.getElementById('vat_amount').value = amount > 0 ? (Math.round(amount * rate) / 100).toFixed(2) : '0';
    updateTotals(); checkVatRow(); updatePreview();
}

function updateTotals() {
    const amount = parseFloat(document.getElementById('amount').value)     || 0;
    const vat    = parseFloat(document.getElementById('vat_amount').value) || 0;
    document.getElementById('displayAmount').textContent = fmt(amount);
    document.getElementById('displayVat').textContent    = fmt(vat);
    document.getElementById('displayTotal').textContent  = fmt(amount + vat);
}

function checkVatRow() {
    const vat = parseFloat(document.getElementById('vat_amount').value) || 0;
    document.getElementById('vatAccountRow').classList.toggle('d-none', vat <= 0);
}

/* ── Preview ─────────────────────────────────────────────────────────────── */
function updatePreview() {
    const amount = parseFloat(document.getElementById('amount').value)     || 0;
    const vat    = parseFloat(document.getElementById('vat_amount').value) || 0;
    const total  = amount + vat;

    const expCode = document.getElementById('expense_account').value;
    const expName = document.getElementById('expense_account_name').value || expCode;
    const vatCode = document.getElementById('vat_account').value;
    const vatName = document.getElementById('vat_account_name').value || vatCode;
    const payCode = document.getElementById('payment_account').value;
    const payName = document.getElementById('payment_account_name').value || payCode;

    const lines = [];
    if (expCode || amount > 0) lines.push({ code:expCode||'—', name:expName||'Expense Account', debit:amount, credit:0, cls:'preview-line-debit' });
    if (vat > 0)               lines.push({ code:vatCode||'—', name:vatName||'VAT Account',     debit:vat,   credit:0, cls:'preview-line-debit' });
    if (payCode || total > 0)  lines.push({ code:payCode||'—', name:payName||'Payment Account', debit:0, credit:total, cls:'preview-line-credit' });

    const tbody = document.getElementById('previewLines');
    const tfoot = document.getElementById('previewFoot');

    if (!lines.length) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center py-4" style="color:var(--text-3)">
            <i class="bi bi-arrow-left-circle me-1"></i>Fill the form to preview</td></tr>`;
        tfoot.classList.add('d-none'); return;
    }

    tbody.innerHTML = lines.map(l => `
        <tr class="${l.cls}">
            <td><span class="account-chip">${escHtml(l.code)}</span><small>${escHtml(l.name)}</small></td>
            <td class="text-end">${l.debit  > 0 ? fmt(l.debit)  : ''}</td>
            <td class="text-end">${l.credit > 0 ? fmt(l.credit) : ''}</td>
        </tr>`).join('');

    document.getElementById('previewTotalDebit').textContent  = fmt(lines.reduce((s,l) => s+l.debit,  0));
    document.getElementById('previewTotalCredit').textContent = fmt(lines.reduce((s,l) => s+l.credit, 0));
    tfoot.classList.remove('d-none');
}

document.getElementById('memo').addEventListener('input', updatePreview);

/* ── Drop-zone ───────────────────────────────────────────────────────────── */
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('receipt');

dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('dragging'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragging'));
dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragging'); if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });
fileInput.addEventListener('change', () => { if (fileInput.files[0]) handleFile(fileInput.files[0]); });
document.getElementById('removeImage').addEventListener('click', e => { e.stopPropagation(); clearFile(); });

function handleFile(file) {
    if (file.size > 5 * 1024 * 1024) { showToast('File too large (max 5 MB).', 'error'); return; }
    document.getElementById('dropZoneContent').classList.add('d-none');
    document.getElementById('imagePreviewWrapper').classList.remove('d-none');
    if (file.type === 'application/pdf') {
        document.getElementById('imagePreview').classList.add('d-none');
        document.getElementById('pdfPreview').classList.remove('d-none');
        document.getElementById('pdfName').textContent = file.name;
    } else {
        document.getElementById('pdfPreview').classList.add('d-none');
        document.getElementById('imagePreview').classList.remove('d-none');
        const r = new FileReader();
        r.onload = e => { document.getElementById('imagePreview').src = e.target.result; };
        r.readAsDataURL(file);
    }
    if (file !== fileInput.files[0]) {
        const dt = new DataTransfer(); dt.items.add(file); fileInput.files = dt.files;
    }
}

function clearFile() {
    fileInput.value = '';
    document.getElementById('imagePreview').src = '';
    document.getElementById('imagePreviewWrapper').classList.add('d-none');
    document.getElementById('pdfPreview').classList.add('d-none');
    document.getElementById('imagePreview').classList.add('d-none');
    document.getElementById('dropZoneContent').classList.remove('d-none');
}

/* ── Form submit ─────────────────────────────────────────────────────────── */
document.getElementById('expenseForm').addEventListener('submit', async e => {
    e.preventDefault();
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const vat    = parseFloat(document.getElementById('vat_amount').value) || 0;

    if (!document.getElementById('expense_account').value) return showToast('Select an expense account.', 'error');
    if (!document.getElementById('payment_account').value) return showToast('Select a payment account.', 'error');
    if (amount <= 0) return showToast('Net amount must be greater than 0.', 'error');
    if (vat > 0 && !document.getElementById('vat_account').value) return showToast('Select a VAT account.', 'error');
    if (!document.getElementById('memo').value.trim()) return showToast('Enter a description.', 'error');

    const fd = new FormData(document.getElementById('expenseForm'));
    fd.set('expense_account_name', document.getElementById('expense_account_name').value);
    fd.set('vat_account_name',     document.getElementById('vat_account_name').value);
    fd.set('payment_account_name', document.getElementById('payment_account_name').value);

    setSubmitting(true);
    try {
        const res  = await fetch('/api/submit_entry.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('modalDocEntry').textContent = data.sap_doc_entry ?? '—';
            document.getElementById('modalDocNum').textContent   = data.sap_doc_num   ?? '—';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('successModal')).show();
            loadEntries();
        } else {
            showToast(data.error || 'Error posting to SAP B1.', 'error');
        }
    } catch(err) { showToast('Network error: ' + err.message, 'error'); }
    finally { setSubmitting(false); }
});

function setSubmitting(busy) {
    document.getElementById('submitBtn').disabled = busy;
    document.getElementById('submitBtnText').classList.toggle('d-none', busy);
    document.getElementById('submitSpinner').classList.toggle('d-none', !busy);
}

document.getElementById('newEntryBtn')?.addEventListener('click', resetForm);
document.getElementById('successModal')?.addEventListener('hidden.bs.modal', resetForm);

function resetForm() {
    document.getElementById('expenseForm').reset();
    document.getElementById('entry_date')._flatpickr?.setDate(new Date(), false, 'Y-m-d');
    $('#expense_account, #vat_account, #payment_account').val(null).trigger('change');
    ['expense_account_name','vat_account_name','payment_account_name'].forEach(id => {
        document.getElementById(id).value = '';
    });
    clearFile();
    updateTotals(); checkVatRow(); updatePreview();
}

/* ── Recent entries ──────────────────────────────────────────────────────── */
async function loadEntries() {
    const list = document.getElementById('entriesList');
    try {
        const data = await fetch('/api/get_entries.php').then(r => r.json());
        if (data.error) throw new Error(data.error);
        if (!data.data.length) {
            list.innerHTML = '<p class="text-center py-4 mb-0" style="color:var(--text-3)">No entries yet.</p>';
            return;
        }
        list.innerHTML = data.data.map(e => {
            const statusCls = { posted:'badge-success', failed:'badge-danger', pending:'badge-warning' }[e.status] || 'badge-secondary';
            const sapRef = e.sap_doc_num ? `<span class="badge badge-primary">SAP #${e.sap_doc_num}</span>` : '';
            return `<div class="entry-item">
                <div class="entry-memo">${escHtml(e.memo)}</div>
                <div class="entry-amount">${fmt(e.total_amount)}</div>
                <div class="entry-sub">${escHtml(e.expense_account_code)} · ${fmtDate(e.entry_date)}</div>
                <div class="entry-sub text-end" style="font-size:.75rem;color:var(--text-3)">
                    ${e.payment_type === 'bank' ? '🏦' : '💵'} ${escHtml(e.payment_account_code)}
                    ${e.receipt_path ? '<i class="bi bi-paperclip ms-1"></i>' : ''}
                </div>
                <div class="entry-badges">
                    <span class="badge ${statusCls}">${e.status}</span>
                    ${sapRef}
                    ${e.ref1 ? `<span class="badge badge-secondary">${escHtml(e.ref1)}</span>` : ''}
                </div>
            </div>`;
        }).join('');
    } catch(err) {
        list.innerHTML = `<p class="text-center py-3 mb-0" style="color:var(--red)"><i class="bi bi-exclamation-triangle me-1"></i>${err.message}</p>`;
    }
}

document.getElementById('refreshEntries')?.addEventListener('click', loadEntries);

/* ── Init ────────────────────────────────────────────────────────────────── */
updateTotals(); updatePreview(); loadEntries();
