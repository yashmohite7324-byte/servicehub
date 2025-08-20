<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Validate request
if (!isset($_SESSION['user']['id']) || !isset($_POST['gateway_txnid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get payment with row lock
    $stmt = $pdo->prepare("SELECT * FROM payments 
        WHERE gateway_transaction_id = ? 
        AND user_id = ? 
        AND status = 'pending'
        FOR UPDATE");
    $stmt->execute([$_POST['gateway_txnid'], $_SESSION['user']['id']]);
    $payment = $stmt->fetch();

    if (!$payment) {
        throw new Exception('Payment not found or cannot be cancelled');
    }

    // Mark as cancelled
    $stmt = $pdo->prepare("UPDATE payments SET 
        status = 'cancelled',
        updated_at = NOW()
        WHERE gateway_transaction_id = ?");
    $stmt->execute([$_POST['gateway_txnid']]);

    // Optionally notify gateway about cancellation
    $url = "https://jazainc.com/api/v2/pg/orders/pg-cancel-order.php";
    $data = ['txnid' => $_POST['gateway_txnid']];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    curl_exec($ch);
    curl_close($ch);

    $pdo->commit();
    
    // Clear session
    unset($_SESSION['current_payment']);

    echo json_encode([
        'status' => 'success',
        'message' => 'Payment cancelled successfully'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Cancel payment error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}