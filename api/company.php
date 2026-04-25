<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'Unauthenticated']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDb();

if ($method === 'GET') {
    $rows = $pdo->query("SELECT key, value FROM settings WHERE key LIKE 'COMPANY_%' OR key IN ('APP_COMPANY','APP_CURRENCY','APP_VAT_RATE')")->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['settings' => $rows]);
    exit;
}

if ($method === 'POST') {
    $cfg    = require __DIR__ . '/../config/config.php';
    $upload = $cfg['upload'];

    // Handle logo upload
    $logoPath = null;
    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file  = $_FILES['logo'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        if (!in_array($mime, $allowed)) { http_response_code(400); echo json_encode(['error'=>'Invalid image type.']); exit; }
        if ($file['size'] > 2*1024*1024) { http_response_code(400); echo json_encode(['error'=>'Logo max 2 MB.']); exit; }

        $dir = __DIR__ . '/../uploads/company/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        move_uploaded_file($file['tmp_name'], $dir . $filename);
        $logoPath = 'uploads/company/' . $filename;
    }

    $stmt = $pdo->prepare("INSERT INTO settings (key,value,updated_at) VALUES (?,?,datetime('now')) ON CONFLICT(key) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at");

    $fields = ['APP_COMPANY','APP_CURRENCY','APP_VAT_RATE','COMPANY_ADDRESS','COMPANY_PHONE','COMPANY_EMAIL','COMPANY_TAX_NO','COMPANY_COUNTRY','COMPANY_CRYSTAL_URL','COMPANY_WEBSITE'];
    foreach ($fields as $k) {
        if (isset($_POST[$k])) $stmt->execute([$k, $_POST[$k]]);
    }
    if ($logoPath) $stmt->execute(['COMPANY_LOGO', $logoPath]);

    if (!empty($_POST['remove_logo'])) {
        $old = $pdo->prepare('SELECT value FROM settings WHERE key=?');
        $old->execute(['COMPANY_LOGO']);
        $oldPath = $old->fetchColumn();
        if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
            @unlink(__DIR__ . '/../' . $oldPath);
        }
        $stmt->execute(['COMPANY_LOGO', '']);
    }

    $logoUrl = null;
    if ($logoPath) {
        $logoUrl = '/' . $logoPath;
    }

    echo json_encode(['success' => true, 'logo_url' => $logoUrl]);
    exit;
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
