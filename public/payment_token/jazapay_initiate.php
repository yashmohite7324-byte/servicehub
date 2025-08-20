<!-- jazapay_initiate.php -->

<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Enable detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log');

// Validate input
if (!isset($_SESSION['user']['id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['amount'])) {
    $_SESSION['payment_error'] = ['message' => 'Invalid request'];
    header('Location: add_money.php');
    exit;
}

$amount = floatval($_POST['amount']);
if ($amount < 10 || $amount > 100000) {
    $_SESSION['payment_error'] = ['message' => 'Invalid amount'];
    header('Location: add_money.php');
    exit;
}

// Generate transaction ID
$transaction_id = 'TXN' . time() . mt_rand(1000, 9999);

try {
    $pdo->beginTransaction();

    // Create payment record
    $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, transaction_id, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$_SESSION['user']['id'], $amount, $transaction_id]);
    $payment_id = $pdo->lastInsertId();

    // Prepare API request
    $apiData = [
        'apikey' => '72ba3bd0183631753f45720bf6a2a5a5',
        'amt' => number_format($amount, 2, '.', ''),
        'callback' => 'https://yourdomain.com/jazapay_callback.php'
    ];

    // Call payment gateway
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://jazainc.com/api/v2/pg/orders/pg-create-order.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($apiData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL error: $error");
    }

    $responseData = json_decode($response, true);
    if (!$responseData || !isset($responseData['status'])) {
        throw new Exception("Invalid API response");
    }

    if ($responseData['status'] !== "200") {
        throw new Exception($responseData['message'] ?? 'Payment initiation failed');
    }

    // Update payment record with gateway details
    $stmt = $pdo->prepare("UPDATE payments SET 
        gateway_transaction_id = ?,
        payment_link = ?,
        status = 'initiated'
        WHERE id = ?");
    $stmt->execute([
        $responseData['txnid'],
        $responseData['payUrl'],
        $payment_id
    ]);

    // Store in session
    $_SESSION['current_payment'] = [
        'transaction_id' => $transaction_id,
        'amount' => $amount,
        'payment_url' => $responseData['payUrl'],
        'gateway_txnid' => $responseData['txnid'],
        'created_at' => date('Y-m-d H:i:s')
    ];

    $pdo->commit();

    // Redirect to payment page
    header('Location: jazapay_qr.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment initiation error: " . $e->getMessage());
    $_SESSION['payment_error'] = [
        'message' => 'Payment failed: ' . $e->getMessage(),
        'transaction_id' => $transaction_id ?? null
    ];
    header('Location: add_money.php');
    exit;
}