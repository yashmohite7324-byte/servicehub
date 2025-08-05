<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? null;
$password = $input['password'] ?? null;

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, username FROM admins WHERE username = ? AND password = ? LIMIT 1');
    $stmt->execute([$username, $password]);
    $admin = $stmt->fetch();
    if (!$admin) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid admin credentials']);
        exit;
    }
    // Set session
    $_SESSION['admin'] = [
        'id' => $admin['id'],
        'username' => $admin['username']
    ];
    echo json_encode(['success' => true, 'admin' => $admin]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage()]);
} 