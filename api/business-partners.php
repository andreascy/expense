<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SapServiceLayer.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$cfg    = getAppConfig();
$sap    = new SapServiceLayer($cfg['sap']);
$action = $_GET['action'] ?? 'list';

try {
    if ($action === 'list') {
        $search = trim($_GET['q'] ?? '');
        $type   = $_GET['type'] ?? ''; // C=customer, S=supplier

        $filters = ["Active eq 'tYES'"];
        if ($search) {
            $s = str_replace("'","''",$search);
            $filters[] = "(contains(tolower(CardCode),tolower('{$s}')) or contains(tolower(CardName),tolower('{$s}')))";
        }
        if ($type) $filters[] = "CardType eq '{$type}'";

        $qs = http_build_query([
            '$select'  => 'CardCode,CardName,CardType,Phone1,EmailAddress,CurrentAccountBalance,CreditLimit',
            '$filter'  => implode(' and ', $filters),
            '$orderby' => 'CardName asc',
            '$top'     => 80,
        ]);

        $result = $sap->callRaw('GET', '/BusinessPartners?'.$qs);
        echo json_encode(['data' => $result['value'] ?? []]);
        exit;
    }

    if ($action === 'statement') {
        $code = $_GET['code'] ?? '';
        if (!$code) { echo json_encode(['data'=>[]]); exit; }

        $qs = http_build_query([
            '$select'  => 'JdtNum,RefDate,Memo,Ref1,Debit,Credit,CumulativeBalance,TransactionCode',
            '$filter'  => "ShortName eq '".str_replace("'","''",$code)."'",
            '$orderby' => 'RefDate desc',
            '$top'     => 100,
        ]);

        $result = $sap->callRaw('GET', '/JournalEntryLines?'.$qs);
        echo json_encode(['data' => $result['value'] ?? []]);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
