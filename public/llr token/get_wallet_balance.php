<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user = $_SESSION['user'];

try {
    // Get current balance and LLR price
    $stmt = $pdo->prepare("SELECT wallet_balance, llr_price FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        throw new Exception('User not found');
    }
    
    echo json_encode([
        'success' => true,
        'balance' => $userData['wallet_balance'],
        'llrPrice' => $userData['llr_price']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}