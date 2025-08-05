<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// PhonePe Configuration - Updated for local testing
define('PHONEPE_MERCHANT_ID', 'TEST-M23KNTTTK5Z8N_25072'); // Test merchant ID
define('PHONEPE_SALT_KEY', 'M2EzOGE4NmItYzY3OC00ODI2LTk0NDQtZDY0Mjc4OGU3MWUx'); // Test salt key
define('PHONEPE_SALT_INDEX', 1);
define('PHONEPE_BASE_URL', 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1'); // Test endpoint
define('PHONEPE_CALLBACK_URL', 'https://webhook.site/397a12dc-68fd-49ca-89ea-efb12891694d'); // Temporary test URL
define('PHONEPE_REDIRECT_URL', 'http://localhost/php/public/dashboard.php'); // Local URL

// Enable detailed logging
ini_set('display_errors', 1); // Enable during debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    try {
        $amount = floatval($_POST['amount']);
        
        // Validate amount
        if ($amount < 10) {
            throw new Exception("Minimum amount is ₹10");
        }
        
        $user_id = $_SESSION['user']['id'];
        $transaction_id = 'TXN' . time() . $user_id;
        $amount_in_paise = $amount * 100;

        // Create payment record
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, status, transaction_id) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$user_id, $amount, $transaction_id]);

        // Prepare PhonePe request
        $data = [
    "merchantId" => PHONEPE_MERCHANT_ID,
    "merchantTransactionId" => $transaction_id,
    "merchantUserId" => $user_id,
    "amount" => $amount_in_paise,
    "redirectUrl" => PHONEPE_REDIRECT_URL,
    "redirectMode" => "POST",
    "callbackUrl" => PHONEPE_CALLBACK_URL,
    "paymentInstrument" => ["type" => "PAY_PAGE"]
];

// Generate checksum - MUST use this exact format
$base64Payload = base64_encode(json_encode($data));
$string = $base64Payload . '/pg/v1/pay' . PHONEPE_SALT_KEY;
$sha256 = hash('sha256', $string);
$checksum = $sha256 . '###' . PHONEPE_SALT_INDEX;

        // Debug logs - Enhanced
        error_log("Attempting payment with transaction ID: $transaction_id");
        error_log("Request Data: " . print_r($data, true));
        error_log("Base64 Payload: $base64Payload");
        error_log("Checksum String: $string");
        error_log("Final Checksum: $checksum");

        $headers = [
            "Content-Type: application/json",
            "X-VERIFY: $checksum",
            "accept: application/json"
        ];

        // Make API request
        $apiUrl = PHONEPE_BASE_URL . '/pay';
        error_log("Making request to: $apiUrl");
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['request' => $base64Payload]),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_VERBOSE => true, // Enable verbose output
        ]);

        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("cURL Verbose Log: " . $verboseLog);
        fclose($verbose);

        curl_close($ch);

        error_log("Response Code: $httpCode");
        error_log("Response: " . print_r($response, true));
        error_log("cURL Error: " . ($curlError ?: 'None'));

        if ($httpCode !== 200) {
            throw new Exception("API request failed with status: $httpCode. Response: " . $response);
        }

        $responseData = json_decode($response, true);
        if (!$responseData) {
            throw new Exception("Invalid JSON response: " . $response);
        }

        if ($responseData['success'] && $responseData['code'] === 'PAYMENT_INITIATED') {
            header('Location: ' . $responseData['data']['instrumentResponse']['redirectInfo']['url']);
            exit;
        } else {
            $errorMsg = $responseData['message'] ?? 'Unknown error';
            throw new Exception("Payment failed: " . $errorMsg);
        }
        
    } catch (Exception $e) {
        error_log("Payment Error: " . $e->getMessage());
        $_SESSION['payment_error'] = [
            'message' => $e->getMessage(),
            'transaction_id' => $transaction_id ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        header('Location: add_money.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Money to Wallet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border-radius: 0.5rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
        .payment-card { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; }
        .btn-pay { background-color: #1cc88a; color: white; font-weight: 600; }
        .btn-pay:hover { background-color: #13855c; color: white; }
        .error-details { font-size: 0.9rem; color: #666; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">Add Money to Wallet</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['payment_error'])): ?>
                            <div class="alert alert-danger">
                                <h5><i class="bi bi-exclamation-triangle-fill me-2"></i>Payment Failed</h5>
                                <p class="mb-1"><?= htmlspecialchars($_SESSION['payment_error']['message']) ?></p>
                                <?php if ($_SESSION['payment_error']['transaction_id']): ?>
                                    <p class="error-details mb-1">
                                        Transaction ID: <?= htmlspecialchars($_SESSION['payment_error']['transaction_id']) ?>
                                    </p>
                                <?php endif; ?>
                                <p class="error-details">
                                    <?= htmlspecialchars($_SESSION['payment_error']['timestamp']) ?>
                                </p>
                            </div>
                            <?php unset($_SESSION['payment_error']); ?>
                        <?php endif; ?>
                        
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-4 mb-md-0">
                                <div class="card payment-card">
                                    <div class="card-body text-center py-4">
                                        <h3 class="mb-3">Current Balance</h3>
                                        <h2 class="fw-bold mb-4">₹<?= number_format($_SESSION['user']['wallet_balance'], 2) ?></h2>
                                        <i class="bi bi-wallet2" style="font-size: 2.5rem; opacity: 0.8;"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" action="add_money.php" id="paymentForm">
                                    <div class="mb-3">
                                        <label for="amount" class="form-label fw-bold">Amount to Add (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" class="form-control form-control-lg" 
                                                   id="amount" name="amount" min="10" step="1" 
                                                   placeholder="100" required>
                                        </div>
                                        <div class="form-text">Minimum amount: ₹10</div>
                                    </div>
                                    <button type="submit" class="btn btn-pay btn-lg w-100 py-2">
                                        <i class="bi bi-credit-card me-2"></i> Proceed to Payment
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Add client-side validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            if (amount < 10) {
                e.preventDefault();
                alert('Minimum amount is ₹10');
            }
        });
    </script>
</body>
</html>