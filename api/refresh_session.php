<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT wallet_balance, llr_price, dl_price, rc_price FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentData) {
        $_SESSION['user']['wallet_balance'] = $currentData['wallet_balance'];
        $_SESSION['user']['llr_price'] = $currentData['llr_price'];
        $_SESSION['user']['dl_price'] = $currentData['dl_price'];
        $_SESSION['user']['rc_price'] = $currentData['rc_price'];
    }
    
    echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
}