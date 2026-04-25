<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$pdo = getDb();

// KPI totals
$kpi = $pdo->query("
    SELECT
        COUNT(*)                              AS total_entries,
        SUM(CASE WHEN status='posted' THEN 1 ELSE 0 END)  AS posted,
        SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END)  AS failed,
        COALESCE(SUM(CASE WHEN status='posted' THEN total_amount ELSE 0 END),0) AS total_amount
    FROM expense_entries
")->fetch();

// Monthly totals — last 13 months
$monthly = $pdo->query("
    SELECT
        strftime('%Y-%m', entry_date) AS month,
        COUNT(*)                       AS entries,
        COALESCE(SUM(total_amount),0)  AS total,
        SUM(CASE WHEN status='posted' THEN 1 ELSE 0 END) AS posted,
        SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed
    FROM expense_entries
    GROUP BY strftime('%Y-%m', entry_date)
    ORDER BY month DESC
    LIMIT 13
")->fetchAll();

// Drill-down for a specific month
$drillMonth = $_GET['month'] ?? null;
$drillRows  = [];
if ($drillMonth && preg_match('/^\d{4}-\d{2}$/', $drillMonth)) {
    $drillRows = $pdo->prepare("
        SELECT id, entry_date, memo, ref1,
               expense_account_code, expense_account_name,
               amount, vat_amount, total_amount, payment_type,
               payment_account_code, status, sap_doc_num
        FROM expense_entries
        WHERE strftime('%Y-%m', entry_date) = ?
        ORDER BY entry_date DESC, id DESC
    ");
    $drillRows->execute([$drillMonth]);
    $drillRows = $drillRows->fetchAll();
}

echo json_encode([
    'kpi'     => $kpi,
    'monthly' => array_reverse($monthly),
    'drill'   => $drillRows,
]);
