<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';

if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error' => 'Unauthenticated']); exit; }
if ($_SESSION['user']['role'] !== 'admin') { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = (int)($_GET['id'] ?? $body['id'] ?? 0);
$pdo    = getDb();

switch ($method) {
    case 'GET':
        $rows = $pdo->query("SELECT id,name,email,role,active,created_at FROM users ORDER BY id")->fetchAll();
        echo json_encode(['data' => $rows]);
        break;

    case 'POST':
        $name  = trim($body['name']  ?? '');
        $email = trim($body['email'] ?? '');
        $pass  = trim($body['password'] ?? '');
        $role  = in_array($body['role'] ?? '', ['admin','user']) ? $body['role'] : 'user';

        if (!$name || !$email || !$pass) { http_response_code(400); echo json_encode(['error' => 'Name, email and password are required.']); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['error' => 'Invalid email address.']); exit; }

        try {
            $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)")
                ->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $role]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Throwable $e) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists.']);
        }
        break;

    case 'PATCH':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit; }
        $fields = []; $params = [];
        if (isset($body['name']))   { $fields[] = 'name = ?';   $params[] = trim($body['name']); }
        if (isset($body['email']))  { $fields[] = 'email = ?';  $params[] = trim($body['email']); }
        if (isset($body['role']) && in_array($body['role'], ['admin','user'])) { $fields[] = 'role = ?'; $params[] = $body['role']; }
        if (isset($body['active'])) { $fields[] = 'active = ?'; $params[] = (int)(bool)$body['active']; }
        if (!empty($body['password'])) { $fields[] = 'password_hash = ?'; $params[] = password_hash($body['password'], PASSWORD_BCRYPT); }

        if (!$fields) { echo json_encode(['success' => true]); exit; }
        $params[] = $id;
        $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit; }
        if ($id === (int)$_SESSION['user']['id']) { http_response_code(400); echo json_encode(['error' => 'Cannot delete yourself.']); exit; }
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
