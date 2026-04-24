<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $body['action'] ?? 'login';

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
    exit;
}

try {
    $pdo  = getDb();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
        exit;
    }

    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];

    echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
