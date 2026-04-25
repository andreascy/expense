<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDb();
$action = $_GET['action'] ?? '';

// ── Sync from SAP ───────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'sync') {
    try {
        $cfg   = getAppConfig();
        $sap   = new SapServiceLayer($cfg['sap']);
        $items = $sap->getItems('', 200);
        $upsert = $pdo->prepare("INSERT INTO items (item_code,item_name,item_type,price,stock_qty,sap_synced,updated_at) VALUES (?,?,?,?,?,1,datetime('now')) ON CONFLICT(item_code) DO UPDATE SET item_name=excluded.item_name,item_type=excluded.item_type,price=excluded.price,stock_qty=excluded.stock_qty,sap_synced=1,updated_at=excluded.updated_at");
        $synced = 0;
        foreach ($items as $i) {
            $price = (float)($i['ItemPrices'][0]['Price'] ?? 0);
            $upsert->execute([$i['ItemCode'], $i['ItemName'], $i['ItemType'] ?? 'itItems', $price, (float)($i['OnHand'] ?? 0)]);
            $synced++;
        }
        echo json_encode(['success'=>true,'synced'=>$synced]); exit;
    } catch (Throwable $e) {
        http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); exit;
    }
}

// ── GET: list ───────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $per   = 25;
    $off   = ($page - 1) * $per;
    $q     = trim($_GET['q'] ?? '');
    $itype = trim($_GET['item_type'] ?? '');

    $where = ['active = 1'];
    $params = [];
    if ($q)     { $where[] = '(item_code LIKE ? OR item_name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($itype) { $where[] = 'item_type = ?'; $params[] = $itype; }
    $sql = 'WHERE ' . implode(' AND ', $where);

    $rows = $pdo->prepare("SELECT * FROM items $sql ORDER BY item_code LIMIT $per OFFSET $off");
    $rows->execute($params);
    $cnt  = $pdo->prepare("SELECT COUNT(*) FROM items $sql");
    $cnt->execute($params);

    echo json_encode(['data'=>$rows->fetchAll(),'total'=>(int)$cnt->fetchColumn(),'page'=>$page,'pages'=>(int)ceil((int)$cnt->fetchColumn()/$per)]);
    exit;
}

// ── POST: create ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $code     = trim($body['item_code']  ?? '');
    $name     = trim($body['item_name']  ?? '');
    $itype    = trim($body['item_type']  ?? 'itItems');
    $price    = (float)($body['price']   ?? 0);
    $unit     = trim($body['unit']       ?? 'EA');
    $currency = trim($body['currency']   ?? 'EUR');

    if (!$code || !$name) { http_response_code(400); echo json_encode(['error'=>'Item code and name are required.']); exit; }

    $postToSap = !empty($body['post_to_sap']);
    if ($postToSap) {
        try {
            $cfg = getAppConfig();
            $sap = new SapServiceLayer($cfg['sap']);
            $sap->callRaw('POST', '/Items', array_filter([
                'ItemCode' => $code,
                'ItemName' => $name,
                'ItemType' => $itype,
                'PurchaseUnit' => $unit,
                'SalesUnit'    => $unit,
            ], fn($v) => $v !== ''));
        } catch (Throwable $e) {
            // non-fatal — save locally anyway
        }
    }

    try {
        $pdo->prepare("INSERT INTO items (item_code,item_name,item_type,price,currency,unit,sap_synced) VALUES (?,?,?,?,?,?,?)")
            ->execute([$code,$name,$itype,$price,$currency,$unit,$postToSap?1:0]);
        $id = (int)$pdo->lastInsertId();
        echo json_encode(['success'=>true,'id'=>$id]);
    } catch (Throwable $e) {
        http_response_code(409); echo json_encode(['error'=>'Item code already exists.']);
    }
    exit;
}

// ── PATCH: update ───────────────────────────────────────────────────────────────
if ($method === 'PATCH') {
    $code = $_GET['code'] ?? '';
    if (!$code) { http_response_code(400); echo json_encode(['error'=>'Code required']); exit; }
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $fields = []; $params = [];
    foreach (['item_name','item_type','price','currency','unit','active'] as $f) {
        if (array_key_exists($f, $body)) { $fields[] = "$f=?"; $params[] = $body[$f]; }
    }
    if (!$fields) { echo json_encode(['success'=>true]); exit; }
    $params[] = $code;
    $pdo->prepare("UPDATE items SET " . implode(',', $fields) . ",updated_at=datetime('now') WHERE item_code=?")->execute($params);
    echo json_encode(['success'=>true]); exit;
}

// ── DELETE: deactivate ───────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $code = $_GET['code'] ?? '';
    if (!$code) { http_response_code(400); echo json_encode(['error'=>'Code required']); exit; }
    $pdo->prepare("UPDATE items SET active=0 WHERE item_code=?")->execute([$code]);
    echo json_encode(['success'=>true]); exit;
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
