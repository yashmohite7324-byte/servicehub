<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['txnid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Transaction ID missing']);
    exit;
}

$transaction_id = $_GET['txnid'];

try {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_id = ?");
    $stmt->execute([$transaction_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }
    
    echo json_encode([
        'status' => $payment['status'],
        'amount' => $payment['amount'],
        'message' => $payment['status'] === 'failed' ? 'Payment failed or was declined' : ''
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}