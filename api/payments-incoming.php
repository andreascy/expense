<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDb();

// ── GET ────────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // List open invoices for a customer (used for payment apply-to dropdown)
    if (($_GET['action'] ?? '') === 'open-invoices') {
        $cc   = trim($_GET['card_code'] ?? '');
        $stmt = $pdo->prepare("SELECT id,doc_date,sap_doc_num,total,card_name FROM sales_documents WHERE type='invoice' AND status='open' AND card_code=? ORDER BY doc_date DESC");
        $stmt->execute([$cc]);
        echo json_encode(['data' => $stmt->fetchAll()]); exit;
    }

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $per    = 20;
    $off    = ($page - 1) * $per;
    $q      = trim($_GET['q'] ?? '');
    $where  = []; $params = [];
    if ($q) { $where[] = '(card_code LIKE ? OR card_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $rows = $pdo->prepare("SELECT * FROM incoming_payments $sql ORDER BY created_at DESC LIMIT $per OFFSET $off");
    $rows->execute($params);
    $cnt  = $pdo->prepare("SELECT COUNT(*) FROM incoming_payments $sql");
    $cnt->execute($params);

    echo json_encode(['data'=>$rows->fetchAll(),'total'=>(int)$cnt->fetchColumn(),'page'=>$page,'pages'=>(int)ceil((int)$cnt->fetchColumn()/$per)]);
    exit;
}

// ── POST ────────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $cardCode  = trim($body['card_code']      ?? '');
    $cardName  = trim($body['card_name']      ?? '');
    $payDate   = trim($body['payment_date']   ?? date('Y-m-d'));
    $amount    = (float)($body['amount']      ?? 0);
    $method2   = trim($body['payment_method'] ?? 'transfer');
    $bankAcc   = trim($body['bank_account']   ?? '');
    $ref       = trim($body['transfer_ref']   ?? '');
    $memo      = trim($body['memo']           ?? '');
    $invoiceId = $body['invoice_id']          ?? null;
    $sapInvDe  = $body['sap_invoice_doc_entry'] ?? null;
    $saveDraft = !empty($body['save_draft']);

    if (!$cardCode) { http_response_code(400); echo json_encode(['error'=>'Customer is required.']); exit; }
    if ($amount <= 0){ http_response_code(400); echo json_encode(['error'=>'Amount must be greater than zero.']); exit; }

    $sapDocEntry = null; $sapDocNum = null;
    $status = $saveDraft ? 'draft' : 'pending';
    $errMsg = null;

    if (!$saveDraft) {
        try {
            $cfg = getAppConfig();
            $sap = new SapServiceLayer($cfg['sap']);

            $payload = array_filter([
                'CardCode' => $cardCode,
                'DocDate'  => $payDate,
                'Remarks'  => $memo ?: null,
                'CashSum'      => $method2 === 'cash'     ? $amount : null,
                'TransferSum'  => $method2 === 'transfer' ? $amount : null,
                'TransferDate' => $method2 === 'transfer' ? $payDate : null,
                'TransferReference' => $ref ?: null,
                'PaymentInvoices'   => $sapInvDe ? [[
                    'DocEntry'    => (int)$sapInvDe,
                    'SumApplied'  => $amount,
                    'InvoiceType' => 'it_ARInvoice',
                ]] : null,
            ], fn($v) => $v !== null);

            $resp        = $sap->callRaw('POST', '/IncomingPayments', $payload);
            $sapDocEntry = $resp['DocEntry'] ?? null;
            $sapDocNum   = $resp['DocNum']   ?? null;
            $status      = 'posted';

            // Mark invoice as closed if fully applied
            if ($invoiceId) {
                $pdo->prepare("UPDATE sales_documents SET status='closed' WHERE id=?")->execute([$invoiceId]);
            }
        } catch (Throwable $e) {
            $errMsg = $e->getMessage();
            $status = 'failed';
        }
    }

    $pdo->prepare("INSERT INTO incoming_payments (payment_date,card_code,card_name,amount,payment_method,bank_account,transfer_ref,memo,invoice_id,sap_invoice_doc_entry,sap_doc_entry,sap_doc_num,status,error_message,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$payDate,$cardCode,$cardName,$amount,$method2,$bankAcc,$ref,$memo,$invoiceId,$sapInvDe,$sapDocEntry,$sapDocNum,$status,$errMsg,$_SESSION['user']['id']]);

    $id = (int)$pdo->lastInsertId();

    if (in_array($status, ['draft','posted'])) {
        echo json_encode(['success'=>true,'id'=>$id,'sap_doc_num'=>$sapDocNum]);
    } else {
        http_response_code(422);
        echo json_encode(['success'=>false,'error'=>$errMsg,'id'=>$id]);
    }
    exit;
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
