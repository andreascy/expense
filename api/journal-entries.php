<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDb();

if ($method === 'GET') {
    $page = max(1,(int)($_GET['page']??1));
    $per  = 25;
    $off  = ($page-1)*$per;
    $rows = $pdo->query("SELECT * FROM journal_entries ORDER BY created_at DESC LIMIT $per OFFSET $off")->fetchAll();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn();
    foreach ($rows as &$r) $r['lines'] = json_decode($r['lines_json'] ?? '[]', true);
    echo json_encode(['data'=>$rows,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/$per)]);
    exit;
}

if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $date  = trim($body['entry_date'] ?? date('Y-m-d'));
    $memo  = trim($body['memo']  ?? '');
    $ref1  = trim($body['ref1']  ?? '');
    $ref2  = trim($body['ref2']  ?? '');
    $lines = $body['lines'] ?? [];

    if (!$memo)         { http_response_code(400); echo json_encode(['error'=>'Memo is required.']); exit; }
    if (count($lines)<2){ http_response_code(400); echo json_encode(['error'=>'At least 2 lines required.']); exit; }

    // Validate balance
    $totalDr = array_sum(array_column($lines,'debit'));
    $totalCr = array_sum(array_column($lines,'credit'));
    if (abs($totalDr - $totalCr) > 0.005) {
        http_response_code(400);
        echo json_encode(['error'=>sprintf('Entry is not balanced: DR %.2f ≠ CR %.2f',$totalDr,$totalCr)]);
        exit;
    }

    // Build SAP JE lines
    $jeLines = array_map(fn($l) => array_filter([
        'AccountCode' => $l['account_code'],
        'Debit'       => ($l['debit']  ?? 0) > 0 ? round((float)$l['debit'],  2) : null,
        'Credit'      => ($l['credit'] ?? 0) > 0 ? round((float)$l['credit'], 2) : null,
        'LineMemo'    => $l['line_memo'] ?? $memo,
        'ShortName'   => $l['bp_code'] ?? null,
    ], fn($v) => $v !== null && $v !== '' && $v !== 0.0), $lines);

    $cfg         = getAppConfig();
    $sap         = new SapServiceLayer($cfg['sap']);
    $sapDocEntry = null; $sapDocNum = null;
    $status = 'pending'; $errorMsg = null;

    try {
        $resp        = $sap->createJournalEntry($memo, $date, $lines, $ref1, $ref2);
        $sapDocEntry = $resp['JdtNum']    ?? null;
        $sapDocNum   = $resp['DocNumber'] ?? $resp['Number'] ?? null;
        $status      = 'posted';
    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
        $status   = 'failed';
    }

    $pdo->prepare("INSERT INTO journal_entries (entry_date,memo,ref1,ref2,sap_doc_entry,sap_doc_num,status,error_message,lines_json,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$date,$memo,$ref1,$ref2,$sapDocEntry,$sapDocNum,$status,$errorMsg,json_encode($lines),$_SESSION['user']['id']]);

    $id = (int)$pdo->lastInsertId();
    if ($status === 'posted') {
        echo json_encode(['success'=>true,'id'=>$id,'sap_doc_entry'=>$sapDocEntry,'sap_doc_num'=>$sapDocNum]);
    } else {
        http_response_code(422);
        echo json_encode(['success'=>false,'error'=>$errorMsg,'id'=>$id]);
    }
    exit;
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
