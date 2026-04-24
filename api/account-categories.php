<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';

if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error' => 'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDb();

if ($method === 'GET') {
    $filter = $_GET['category'] ?? '';
    $sql    = "SELECT account_code, account_name, category FROM account_categories";
    if ($filter) $sql .= " WHERE category = " . $pdo->quote($filter);
    $sql .= " ORDER BY category, account_code";
    echo json_encode(['data' => $pdo->query($sql)->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? 'save';

    if ($action === 'sync') {
        $cfg = getAppConfig();
        try {
            $sap      = new SapServiceLayer($cfg['sap']);
            $accounts = $sap->getAccounts('', 200);
            echo json_encode(['success' => true, 'accounts' => $accounts]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save') {
        $entries = $body['entries'] ?? [];
        $valid   = ['expense', 'bank', 'petty_cash'];
        $ins     = $pdo->prepare("INSERT INTO account_categories (account_code,account_name,category) VALUES (?,?,?) ON CONFLICT(account_code) DO UPDATE SET account_name=excluded.account_name, category=excluded.category");
        $del     = $pdo->prepare("DELETE FROM account_categories WHERE account_code = ?");

        $pdo->beginTransaction();
        foreach ($entries as $e) {
            $code = trim($e['code'] ?? '');
            $name = trim($e['name'] ?? '');
            $cat  = $e['category'] ?? '';
            if (!$code) continue;
            if ($cat && in_array($cat, $valid)) {
                $ins->execute([$code, $name, $cat]);
            } else {
                $del->execute([$code]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }
}

if ($method === 'DELETE') {
    $code = $_GET['code'] ?? '';
    if ($code) $pdo->prepare("DELETE FROM account_categories WHERE account_code = ?")->execute([$code]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
