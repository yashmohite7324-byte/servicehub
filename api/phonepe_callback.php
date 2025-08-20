<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/phonepe_config.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/phonepe_callback_errors.log');

// Log raw callback data
file_put_contents(__DIR__ . '/phonepe_callbacks.log', 
    date('Y-m-d H:i:s') . "\n" . file_get_contents('php://input') . "\n\n", 
    FILE_APPEND
);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['response'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid data']));
}

// Verify checksum
$receivedChecksum = $data['response']['checksum'] ?? '';
$payload = $data['response'];
unset($payload['checksum']);

ksort($payload);
$string = json_encode($payload, JSON_UNESCAPED_SLASHES);
$sha256 = hash('sha256', $string . $config['salt_key']);
$generatedChecksum = $sha256 . '###' . $config['salt_index'];

if ($receivedChecksum !== $generatedChecksum) {
    error_log("Checksum verification failed");
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Checksum verification failed']));
}

try {
    $pdo->beginTransaction();
    
    $transaction_id = $payload['merchantTransactionId'];
    $gateway_transaction_id = $payload['transactionId'] ?? null;
    $status = (strtolower($payload['code']) === 'payment_success') ? 'success' : 'failed';
    $amount = $payload['amount'] / 100; // Convert to rupees

    // Update payment record
    $stmt = $pdo->prepare("UPDATE payments SET 
        status = :status,
        gateway_transaction_id = :gateway_txn_id,
        phonepe_response = :response,
        updated_at = NOW()
        WHERE transaction_id = :txn_id");
    
    $stmt->execute([
        ':status' => $status,
        ':gateway_txn_id' => $gateway_transaction_id,
        ':response' => json_encode($payload),
        ':txn_id' => $transaction_id
    ]);

    // If success, update wallet
    if ($status === 'success') {
        $stmt = $pdo->prepare("UPDATE users SET 
            wallet_balance = wallet_balance + :amount 
            WHERE id = (SELECT user_id FROM payments WHERE transaction_id = :txn_id)");
        $stmt->execute([':amount' => $amount, ':txn_id' => $transaction_id]);
    }

    $pdo->commit();
    
    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Callback Processing Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}