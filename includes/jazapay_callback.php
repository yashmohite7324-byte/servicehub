<?php
require_once __DIR__ . '/../includes/db.php';

// Log the callback for debugging
file_put_contents('callback.log', date('[Y-m-d H:i:s]') . " - " . json_encode($_GET) . "\n", FILE_APPEND);

if (!isset($_GET['txnid'])) {
    die("Invalid callback - missing transaction ID");
}

$gateway_txn_id = $_GET['txnid'];

try {
    // Get payment details
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE gateway_transaction_id = ? FOR UPDATE");
    $stmt->execute([$gateway_txn_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        die("Payment not found");
    }

    // Check if already processed
    if ($payment['status'] === 'success') {
        die("Payment already processed");
    }

    // Verify payment with gateway
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://jazainc.com/api/v2/pg/orders/pg-order-status.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['txnid' => $gateway_txn_id]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($responseData['status'] === '200') {
        // Payment successful
        $pdo->beginTransaction();
        
        // Update payment status
        $stmt = $pdo->prepare("UPDATE payments SET 
            status = 'success',
            updated_at = NOW()
            WHERE gateway_transaction_id = ?");
        $stmt->execute([$gateway_txn_id]);
        
        // Update user wallet
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->execute([$payment['amount'], $payment['user_id']]);
        
        // Add to payment history
        $stmt = $pdo->prepare("INSERT INTO payment_history 
            (user_id, transaction_type, amount, description, reference_id, balance_after)
            SELECT 
                user_id, 
                'credit', 
                amount, 
                'Wallet top-up via payment gateway', 
                transaction_id,
                (SELECT wallet_balance FROM users WHERE id = ?)
            FROM payments 
            WHERE gateway_transaction_id = ?");
        $stmt->execute([$payment['user_id'], $gateway_txn_id]);
        
        $pdo->commit();
        
        // Send success response to gateway
        die("SUCCESS");
    } else {
        die("Payment not completed");
    }
} catch (Exception $e) {
    error_log("Callback error: " . $e->getMessage());
    die("ERROR");
}