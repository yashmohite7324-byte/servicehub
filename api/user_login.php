<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$mobile = $input['mobile'] ?? null;
$password = $input['password'] ?? null;

if (!$mobile || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Mobile number and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, mobile, wallet_balance, is_blocked FROM users WHERE mobile = ? AND password = ? LIMIT 1');
    $stmt->execute([$mobile, $password]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }
    if ($user['is_blocked']) {
        http_response_code(403);
        echo json_encode(['error' => 'Your account has been blocked. Please contact administrator.']);
        exit;
    }
    // Set session
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'mobile' => $user['mobile']
    ];
    echo json_encode(['success' => true, 'user' => $user]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage()]);
} 