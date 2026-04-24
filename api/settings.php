<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';

if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error' => 'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDb();

$ALLOWED_KEYS = ['SAP_BASE_URL','SAP_COMPANY_DB','SAP_USERNAME','SAP_PASSWORD','SAP_VERIFY_SSL','APP_CURRENCY','APP_VAT_RATE','APP_COMPANY'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT key, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    unset($rows['SAP_PASSWORD']);
    echo json_encode(['data' => $rows]);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? 'save';

    if ($action === 'test') {
        $cfg = getAppConfig();
        if (!empty($body['SAP_BASE_URL']))   $cfg['sap']['base_url']   = $body['SAP_BASE_URL'];
        if (!empty($body['SAP_COMPANY_DB'])) $cfg['sap']['company_db'] = $body['SAP_COMPANY_DB'];
        if (!empty($body['SAP_USERNAME']))   $cfg['sap']['username']   = $body['SAP_USERNAME'];
        if (!empty($body['SAP_PASSWORD']))   $cfg['sap']['password']   = $body['SAP_PASSWORD'];
        $cfg['sap']['verify_ssl'] = filter_var($body['SAP_VERIFY_SSL'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        try {
            $sap = new SapServiceLayer($cfg['sap']);
            $sap->login();
            echo json_encode(['success' => true, 'message' => 'Connection successful!']);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // Save settings
    $stmt = $pdo->prepare("INSERT INTO settings (key,value,updated_at) VALUES (?,?,datetime('now')) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at");
    foreach ($ALLOWED_KEYS as $key) {
        if (array_key_exists($key, $body)) {
            if ($key === 'SAP_PASSWORD' && $body[$key] === '') continue;
            $stmt->execute([$key, $body[$key]]);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
