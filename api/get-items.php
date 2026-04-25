<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['results'=>[]]); exit; }

$q   = trim($_GET['q'] ?? '');
$cfg = getAppConfig();

try {
    // Try SAP first, fall back to local DB
    $items = [];
    try {
        $sap   = new SapServiceLayer($cfg['sap']);
        $items = $sap->getItems($q, 40);
        $results = array_map(fn($i) => [
            'id'        => $i['ItemCode'],
            'text'      => $i['ItemCode'] . ' — ' . $i['ItemName'],
            'item_code' => $i['ItemCode'],
            'item_name' => $i['ItemName'],
            'price'     => (float)($i['ItemPrices'][0]['Price'] ?? 0),
            'on_hand'   => (float)($i['OnHand'] ?? 0),
        ], $items);
    } catch (Throwable $e) {
        // Fall back to local items table
        $pdo  = getDb();
        $like = '%' . $q . '%';
        $rows = $pdo->prepare("SELECT * FROM items WHERE active=1 AND (item_code LIKE ? OR item_name LIKE ?) ORDER BY item_code LIMIT 40");
        $rows->execute([$like, $like]);
        $results = array_map(fn($i) => [
            'id'        => $i['item_code'],
            'text'      => $i['item_code'] . ' — ' . $i['item_name'],
            'item_code' => $i['item_code'],
            'item_name' => $i['item_name'],
            'price'     => (float)$i['price'],
            'on_hand'   => (float)$i['stock_qty'],
        ], $rows->fetchAll());
    }
    echo json_encode(['results' => $results]);
} catch (Throwable $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()]);
}
