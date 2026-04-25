<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'stock';
$cfg    = getAppConfig();

try {
    $sap = new SapServiceLayer($cfg['sap']);

    // ── GET: stock levels ────────────────────────────────────────────────────────
    if ($method === 'GET' && $action === 'stock') {
        $q   = trim($_GET['q'] ?? '');
        $top = min((int)($_GET['top'] ?? 50), 200);

        $filters = [];
        if ($q) {
            $s = str_replace("'", "''", $q);
            $filters[] = "(contains(tolower(ItemCode),tolower('{$s}')) or contains(tolower(ItemName),tolower('{$s}')))";
        }
        $qs = http_build_query(array_filter([
            '$select'  => 'ItemCode,ItemName,WarehouseCode,InStock,Committed,OnOrder',
            '$filter'  => $filters ? implode(' and ', $filters) : null,
            '$orderby' => 'ItemCode asc',
            '$top'     => $top,
        ]));

        $result = $sap->callRaw('GET', '/ItemWarehouseInfoCollection?' . $qs);
        echo json_encode(['data' => $result['value'] ?? []]);
        exit;
    }

    // ── POST: goods receipt ──────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'receipt') {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $lines = $body['lines'] ?? [];
        $date  = trim($body['doc_date'] ?? date('Y-m-d'));
        $memo  = trim($body['memo'] ?? '');

        if (!$lines) { http_response_code(400); echo json_encode(['error'=>'Lines required']); exit; }

        $payload = array_filter([
            'DocDate'      => $date,
            'Comments'     => $memo ?: null,
            'DocumentLines'=> array_map(fn($l) => array_filter([
                'ItemCode'     => $l['item_code'],
                'Quantity'     => (float)($l['quantity'] ?? 1),
                'WarehouseCode'=> $l['warehouse'] ?? null,
                'UnitPrice'    => (float)($l['unit_price'] ?? 0) ?: null,
            ], fn($v) => $v !== null && $v !== ''), $lines),
        ], fn($v) => $v !== null);

        $resp = $sap->callRaw('POST', '/InventoryGenEntries', $payload);
        echo json_encode(['success'=>true,'doc_entry'=>$resp['DocEntry']??null,'doc_num'=>$resp['DocNum']??null]);
        exit;
    }

    // ── POST: goods issue ────────────────────────────────────────────────────────
    if ($method === 'POST' && $action === 'issue') {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $lines = $body['lines'] ?? [];
        $date  = trim($body['doc_date'] ?? date('Y-m-d'));
        $memo  = trim($body['memo'] ?? '');

        if (!$lines) { http_response_code(400); echo json_encode(['error'=>'Lines required']); exit; }

        $payload = array_filter([
            'DocDate'      => $date,
            'Comments'     => $memo ?: null,
            'DocumentLines'=> array_map(fn($l) => array_filter([
                'ItemCode'     => $l['item_code'],
                'Quantity'     => (float)($l['quantity'] ?? 1),
                'WarehouseCode'=> $l['warehouse'] ?? null,
            ], fn($v) => $v !== null && $v !== ''), $lines),
        ], fn($v) => $v !== null);

        $resp = $sap->callRaw('POST', '/InventoryGenExits', $payload);
        echo json_encode(['success'=>true,'doc_entry'=>$resp['DocEntry']??null,'doc_num'=>$resp['DocNum']??null]);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
