<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/SapServiceLayer.php';
require_once __DIR__ . '/../config/database.php';

// ─── helpers ─────────────────────────────────────────────────────────────────

function jsonError(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function validateAmount(mixed $v, string $field): float
{
    $f = filter_var($v, FILTER_VALIDATE_FLOAT);
    if ($f === false || $f < 0) {
        jsonError("Invalid value for {$field}");
    }
    return round($f, 2);
}

// ─── parse input ─────────────────────────────────────────────────────────────

$raw = file_get_contents('php://input');

// Determine if multipart (image upload) or JSON
if (!empty($_POST)) {
    $input = $_POST;
} else {
    $input = json_decode($raw, true) ?? [];
}

$expenseCode    = trim($input['expense_account']     ?? '');
$expenseName    = trim($input['expense_account_name']   ?? '');
$vatCode        = trim($input['vat_account']         ?? '');
$vatName        = trim($input['vat_account_name']    ?? '');
$paymentCode    = trim($input['payment_account']     ?? '');
$paymentName    = trim($input['payment_account_name']   ?? '');
$paymentType    = trim($input['payment_type']        ?? 'bank');
$memo           = trim($input['memo']                ?? '');
$ref1           = trim($input['ref1']                ?? '');
$entryDate      = trim($input['entry_date']          ?? date('Y-m-d'));

if (!$expenseCode)  jsonError('Expense account is required.');
if (!$paymentCode)  jsonError('Payment account (bank/cash) is required.');
if (!$memo)         jsonError('Description / memo is required.');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
    jsonError('Invalid date format.');
}

$amount    = validateAmount($input['amount']     ?? 0, 'Net Amount');
$vatAmount = validateAmount($input['vat_amount'] ?? 0, 'VAT Amount');
$total     = round($amount + $vatAmount, 2);

if ($amount <= 0) jsonError('Net amount must be greater than 0.');
if ($vatAmount > 0 && !$vatCode) jsonError('VAT account is required when VAT amount is entered.');

// ─── handle receipt image ─────────────────────────────────────────────────────

$cfg        = require __DIR__ . '/../config/config.php';
$uploadDir  = $cfg['upload']['dir'];
$receiptPath = null;

if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['receipt'];

    if ($file['size'] > $cfg['upload']['max_size']) {
        jsonError('Receipt file is too large (max 5 MB).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    if (!in_array($mime, $cfg['upload']['allowed'], true)) {
        jsonError('Unsupported file type: ' . $mime);
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext         = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename    = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        jsonError('Failed to save receipt image.', 500);
    }

    $receiptPath = 'uploads/' . $filename;
}

// ─── build SAP journal entry lines ───────────────────────────────────────────

$jeLines = [];

// Debit: expense account (net amount)
$jeLines[] = [
    'account' => $expenseCode,
    'debit'   => $amount,
    'credit'  => 0,
    'memo'    => $memo,
];

// Debit: VAT account (if applicable)
if ($vatAmount > 0) {
    $jeLines[] = [
        'account' => $vatCode,
        'debit'   => $vatAmount,
        'credit'  => 0,
        'memo'    => 'VAT — ' . $memo,
    ];
}

// Credit: bank / petty cash account (total)
$jeLines[] = [
    'account' => $paymentCode,
    'debit'   => 0,
    'credit'  => $total,
    'memo'    => $memo,
];

// ─── post to SAP B1 ──────────────────────────────────────────────────────────

$sap         = new SapServiceLayer($cfg['sap']);
$sapDocEntry = null;
$sapDocNum   = null;
$status      = 'pending';
$errorMsg    = null;

try {
    $jeResponse  = $sap->createJournalEntry($memo, $entryDate, $jeLines, $ref1);
    $sapDocEntry = $jeResponse['JdtNum']    ?? null;
    $sapDocNum   = $jeResponse['DocNumber'] ?? $jeResponse['Number'] ?? null;
    $status      = 'posted';
} catch (Throwable $e) {
    $errorMsg = $e->getMessage();
    $status   = 'failed';
}

// ─── save to local DB ─────────────────────────────────────────────────────────

$entryId = null;
try {
    $pdo = getDb();
    $stmt = $pdo->prepare('
        INSERT INTO expense_entries
            (entry_date, memo, ref1,
             expense_account_code, expense_account_name,
             amount,
             vat_account_code, vat_account_name, vat_amount,
             payment_account_code, payment_account_name, payment_type,
             total_amount, receipt_path,
             sap_doc_entry, sap_doc_num,
             status, error_message)
        VALUES
            (:entry_date, :memo, :ref1,
             :expense_code, :expense_name,
             :amount,
             :vat_code, :vat_name, :vat_amount,
             :payment_code, :payment_name, :payment_type,
             :total, :receipt,
             :sap_doc_entry, :sap_doc_num,
             :status, :error)
    ');

    $stmt->execute([
        ':entry_date'    => $entryDate,
        ':memo'          => $memo,
        ':ref1'          => $ref1,
        ':expense_code'  => $expenseCode,
        ':expense_name'  => $expenseName,
        ':amount'        => $amount,
        ':vat_code'      => $vatCode,
        ':vat_name'      => $vatName,
        ':vat_amount'    => $vatAmount,
        ':payment_code'  => $paymentCode,
        ':payment_name'  => $paymentName,
        ':payment_type'  => $paymentType,
        ':total'         => $total,
        ':receipt'       => $receiptPath,
        ':sap_doc_entry' => $sapDocEntry,
        ':sap_doc_num'   => $sapDocNum,
        ':status'        => $status,
        ':error'         => $errorMsg,
    ]);

    $entryId = (int)$pdo->lastInsertId();
} catch (Throwable $dbErr) {
    // DB failure is not fatal if SAP succeeded — log and continue
    error_log('DB insert failed: ' . $dbErr->getMessage());
}

// ─── respond ─────────────────────────────────────────────────────────────────

if ($status === 'posted') {
    echo json_encode([
        'success'      => true,
        'message'      => 'Journal entry posted successfully.',
        'sap_doc_entry'=> $sapDocEntry,
        'sap_doc_num'  => $sapDocNum,
        'entry_id'     => $entryId,
        'total'        => $total,
    ]);
} else {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error'   => $errorMsg,
        'entry_id'=> $entryId,
    ]);
}
