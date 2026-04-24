<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    $pdo  = getDb();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = 20;
    $off  = ($page - 1) * $per;

    $rows = $pdo->query("
        SELECT id, entry_date, memo, ref1,
               expense_account_code, expense_account_name,
               amount, vat_amount, total_amount,
               payment_account_code, payment_account_name, payment_type,
               receipt_path, sap_doc_entry, sap_doc_num,
               status, error_message, created_at
        FROM expense_entries
        ORDER BY created_at DESC
        LIMIT {$per} OFFSET {$off}
    ")->fetchAll();

    $total = (int)$pdo->query('SELECT COUNT(*) FROM expense_entries')->fetchColumn();

    echo json_encode([
        'data'  => $rows,
        'total' => $total,
        'page'  => $page,
        'pages' => (int)ceil($total / $per),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
