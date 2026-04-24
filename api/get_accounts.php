<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/SapServiceLayer.php';

try {
    $cfg    = require __DIR__ . '/../config/config.php';
    $search = trim($_GET['q'] ?? '');
    $sap    = new SapServiceLayer($cfg['sap']);
    $accounts = $sap->getAccounts($search, 80);

    // Format for Select2 { id, text, type }
    $results = array_map(fn($a) => [
        'id'   => $a['Code'],
        'text' => $a['Code'] . ' — ' . $a['Name'],
        'code' => $a['Code'],
        'name' => $a['Name'],
        'type' => $a['AccountType'] ?? '',
    ], $accounts);

    echo json_encode(['results' => $results]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
