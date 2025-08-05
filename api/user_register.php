<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$mobile = trim($input['mobile'] ?? '');
$password = $input['password'] ?? '';

if (!$name || !$mobile || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Name, mobile number, and password are required']);
    exit;
}
if (!preg_match('/^\d{10}$/', $mobile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mobile number must be exactly 10 digits']);
    exit;
}
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile = ?');
    $stmt->execute([$mobile]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'User with this mobile number already exists']);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO users (name, mobile, password, wallet_balance, is_blocked, created_at) VALUES (?, ?, ?, 0.0, 0, NOW())');
    $stmt->execute([$name, $mobile, $password]);
    $user_id = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'user' => [
        'id' => $user_id,
        'name' => $name,
        'mobile' => $mobile,
        'wallet_balance' => 0.0,
        'is_blocked' => false
    ]]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'details' => $e->getMessage()]);
} 