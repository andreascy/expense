<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDb();
$type   = $_GET['type'] ?? 'quotation';
if (!in_array($type, ['quotation','order','invoice'])) {
    http_response_code(400); echo json_encode(['error'=>'Invalid type']); exit;
}
$sapEndpoint = [
    'quotation' => '/Quotations',
    'order'     => '/Orders',
    'invoice'   => '/Invoices',
][$type];

// ── GET: list or single ────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM sales_documents WHERE id=? AND type=?");
        $stmt->execute([$id, $type]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }
        $row['lines'] = json_decode($row['lines_json'] ?? '[]', true);
        echo json_encode(['data' => $row]); exit;
    }

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $per    = 20;
    $off    = ($page - 1) * $per;
    $status = $_GET['status'] ?? '';
    $q      = trim($_GET['q'] ?? '');

    $where = ['type = ?'];
    $params = [$type];
    if ($status && $status !== 'all') { $where[] = 'status = ?'; $params[] = $status; }
    if ($q) { $where[] = '(card_code LIKE ? OR card_name LIKE ? OR memo LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
    $sql = 'WHERE ' . implode(' AND ', $where);

    $rows = $pdo->prepare("SELECT id,type,doc_date,due_date,card_code,card_name,memo,total,tax_total,sap_doc_num,status,linked_to,created_at FROM sales_documents $sql ORDER BY created_at DESC LIMIT $per OFFSET $off");
    $rows->execute($params);
    $rows = $rows->fetchAll();

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM sales_documents $sql");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    echo json_encode(['data'=>$rows,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/$per)]);
    exit;
}

// ── POST: create ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $cardCode  = trim($body['card_code'] ?? '');
    $cardName  = trim($body['card_name'] ?? '');
    $docDate   = trim($body['doc_date']  ?? date('Y-m-d'));
    $dueDate   = trim($body['due_date']  ?? '');
    $memo      = trim($body['memo']      ?? '');
    $lines     = $body['lines']          ?? [];
    $saveDraft = !empty($body['save_draft']);
    $linkedTo  = $body['linked_to']      ?? null; // source doc local id

    if (!$cardCode) { http_response_code(400); echo json_encode(['error'=>'Customer is required.']); exit; }
    if (!$lines)    { http_response_code(400); echo json_encode(['error'=>'At least one line is required.']); exit; }

    // Calculate totals
    $subtotal = 0; $taxTotal = 0;
    foreach ($lines as &$line) {
        $qty      = (float)($line['quantity']   ?? 1);
        $price    = (float)($line['unit_price'] ?? 0);
        $disc     = (float)($line['discount']   ?? 0);
        $taxRate  = (float)($line['tax_rate']   ?? 0);
        $lineNet  = round($qty * $price * (1 - $disc / 100), 4);
        $lineTax  = round($lineNet * $taxRate / 100, 4);
        $line['line_total'] = round($lineNet + $lineTax, 2);
        $subtotal += $lineNet;
        $taxTotal += $lineTax;
    }
    unset($line);
    $total = round($subtotal + $taxTotal, 2);

    $sapDocEntry = null; $sapDocNum = null;
    $status = $saveDraft ? 'draft' : 'pending';
    $errMsg = null;

    if (!$saveDraft) {
        try {
            $cfg = getAppConfig();
            $sap = new SapServiceLayer($cfg['sap']);
            $sapLines = array_map(function ($l) {
                return array_filter([
                    'ItemCode'        => $l['item_code']   ?? null,
                    'ItemDescription' => $l['description'] ?? null,
                    'Quantity'        => (float)($l['quantity']   ?? 1),
                    'UnitPrice'       => (float)($l['unit_price'] ?? 0),
                    'DiscountPercent' => ($d = (float)($l['discount'] ?? 0)) > 0 ? $d : null,
                    'TaxCode'         => $l['tax_code'] ?? null,
                ], fn($v) => $v !== null && $v !== '');
            }, $lines);

            $payload = array_filter([
                'CardCode'       => $cardCode,
                'DocDate'        => $docDate,
                'DocDueDate'     => $dueDate ?: null,
                'Comments'       => $memo    ?: null,
                'DocumentLines'  => $sapLines,
            ], fn($v) => $v !== null);

            $resp        = $sap->callRaw('POST', $sapEndpoint, $payload);
            $sapDocEntry = $resp['DocEntry'] ?? null;
            $sapDocNum   = $resp['DocNum']   ?? null;
            $status      = 'open';
        } catch (Throwable $e) {
            $errMsg = $e->getMessage();
            $status = 'failed';
        }
    }

    $pdo->prepare("INSERT INTO sales_documents (type,doc_date,due_date,card_code,card_name,memo,total,tax_total,sap_doc_entry,sap_doc_num,status,error_message,linked_to,lines_json,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$type,$docDate,$dueDate,$cardCode,$cardName,$memo,$total,$taxTotal,$sapDocEntry,$sapDocNum,$status,$errMsg,$linkedTo,json_encode($lines),$_SESSION['user']['id']]);

    $id = (int)$pdo->lastInsertId();

    if (in_array($status, ['draft','open'])) {
        echo json_encode(['success'=>true,'id'=>$id,'sap_doc_entry'=>$sapDocEntry,'sap_doc_num'=>$sapDocNum,'status'=>$status]);
    } else {
        http_response_code(422);
        echo json_encode(['success'=>false,'error'=>$errMsg,'id'=>$id,'status'=>$status]);
    }
    exit;
}

// ── PATCH: update status ────────────────────────────────────────────────────────
if ($method === 'PATCH') {
    $id   = (int)($_GET['id'] ?? 0);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'ID required']); exit; }
    if (isset($body['status'])) {
        $pdo->prepare("UPDATE sales_documents SET status=? WHERE id=? AND type=?")->execute([$body['status'],$id,$type]);
    }
    echo json_encode(['success'=>true]); exit;
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
