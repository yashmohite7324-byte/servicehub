<?php
session_start();
if (!isset($_SESSION['user']) || !isset($_GET['txnid'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$transaction_id = $_GET['txnid'];
$user_id = $_SESSION['user']['id'];

try {
    // Get payment details
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE transaction_id = ? AND user_id = ?");
    $stmt->execute([$transaction_id, $user_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        $_SESSION['error'] = "Invalid transaction";
        header('Location: add_money.php');
        exit;
    }
    
    // Generate UPI payment link and QR code
    $upi_id = "yourbusiness@upi"; // Replace with your UPI ID
    $business_name = "Your Business Name";
    $amount = $payment['amount'];
    $upi_link = "upi://pay?pa=$upi_id&pn=$business_name&am=$amount&tn=$transaction_id";
    $qr_code_url = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($upi_link);
    
    // Get current wallet balance
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error";
    header('Location: add_money.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Instructions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #224abe;
            --success: #1cc88a;
            --danger: #e74a3b;
            --warning: #f6c23e;
        }
        body { background-color: #f8f9fc; }
        .payment-card {
            border-left: 4px solid var(--primary);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .step-number {
            width: 30px;
            height: 30px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        .qr-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            display: inline-block;
            margin: 0 auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-pending { background-color: rgba(var(--warning), 0.1); color: var(--warning); }
        .status-success { background-color: rgba(var(--success), 0.1); color: var(--success); }
        .status-failed { background-color: rgba(var(--danger), 0.1); color: var(--danger); }
        .copy-btn {
            cursor: pointer;
            transition: all 0.2s;
        }
        .copy-btn:hover {
            transform: scale(1.05);
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card payment-card mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Complete Your Payment</h4>
                            <span class="status-badge status-pending">
                                <i class="bi bi-hourglass-split me-1"></i>Pending
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-primary">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">Transaction ID: <?= $transaction_id ?></h5>
                                    <h3 class="mb-0">Amount: ₹<?= number_format($payment['amount'], 2) ?></h3>
                                </div>
                                <div class="text-end">
                                    <small class="d-block">Current Balance</small>
                                    <h4 class="mb-0">₹<?= number_format($user['wallet_balance'], 2) ?></h4>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center my-4">
                            <div class="qr-container mb-3">
                                <img src="<?= $qr_code_url ?>" alt="Scan to Pay" width="220">
                            </div>
                            <p class="text-muted mb-4">Scan this QR code with any UPI app to pay</p>
                            
                            <div class="d-flex justify-content-center mb-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="d-flex align-items-center mb-2">
                                        <small class="text-muted me-2">UPI ID:</small>
                                        <strong class="me-2"><?= $upi_id ?></strong>
                                        <i class="bi bi-clipboard copy-btn" onclick="copyToClipboard('<?= $upi_id ?>')"></i>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <small class="text-muted me-2">Reference:</small>
                                        <strong class="me-2"><?= $transaction_id ?></strong>
                                        <i class="bi bi-clipboard copy-btn" onclick="copyToClipboard('<?= $transaction_id ?>')"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Payment Instructions</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <span class="step-number">1</span>
                                    <span>Open any UPI app (Google Pay, PhonePe, PayTM, BHIM, etc.)</span>
                                </div>
                                <div class="d-flex align-items-start mb-3">
                                    <span class="step-number">2</span>
                                    <span>Choose <strong>"Scan QR Code"</strong> and scan the code above</span>
                                </div>
                                <div class="d-flex align-items-start mb-3">
                                    <span class="step-number">3</span>
                                    <span>Verify the amount: <strong>₹<?= number_format($payment['amount'], 2) ?></strong></span>
                                </div>
                                <div class="d-flex align-items-start">
                                    <span class="step-number">4</span>
                                    <span>Complete the payment (Reference will auto-fill)</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <p class="mb-0"><i class="bi bi-exclamation-circle me-2"></i><strong>Important:</strong> 
                            Payment verification may take 2-5 minutes. Do not refresh this page.</p>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="add_money.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="bi bi-house me-1"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card payment-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Payment Status</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="spinner-border text-primary mb-3" role="status" id="statusSpinner">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mb-3" id="statusText">Checking payment status...</p>
                        <div id="paymentStatus"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
        
        // Check payment status
        function checkPaymentStatus() {
            fetch(`api/check_payment.php?txnid=<?= $transaction_id ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Update UI
                        document.getElementById('statusSpinner').className = 'bi bi-check-circle-fill text-success mb-3';
                        document.getElementById('statusSpinner').style.fontSize = '2.5rem';
                        document.getElementById('statusText').innerHTML = 'Payment Successful!';
                        
                        document.getElementById('paymentStatus').innerHTML = `
                            <div class="alert alert-success">
                                <p class="mb-0">₹${data.amount} has been added to your wallet balance.</p>
                            </div>
                            <a href="dashboard.php" class="btn btn-success mt-2">
                                <i class="bi bi-check-circle me-1"></i> Continue to Dashboard
                            </a>
                        `;
                        
                        // Update status badge
                        const badge = document.querySelector('.status-badge');
                        badge.className = 'status-badge status-success';
                        badge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Completed';
                        
                        // Stop checking
                        return;
                        
                    } else if (data.status === 'failed') {
                        // Update UI
                        document.getElementById('statusSpinner').className = 'bi bi-x-circle-fill text-danger mb-3';
                        document.getElementById('statusSpinner').style.fontSize = '2.5rem';
                        document.getElementById('statusText').innerHTML = 'Payment Failed';
                        
                        document.getElementById('paymentStatus').innerHTML = `
                            <div class="alert alert-danger">
                                <p class="mb-0">${data.message || 'Payment failed or was declined.'}</p>
                            </div>
                            <a href="add_money.php" class="btn btn-danger mt-2">
                                <i class="bi bi-arrow-repeat me-1"></i> Try Again
                            </a>
                        `;
                        
                        // Update status badge
                        const badge = document.querySelector('.status-badge');
                        badge.className = 'status-badge status-failed';
                        badge.innerHTML = '<i class="bi bi-x-circle me-1"></i>Failed';
                        
                        // Stop checking
                        return;
                    }
                    
                    // If still pending, check again after 5 seconds
                    setTimeout(checkPaymentStatus, 5000);
                })
                .catch(error => {
                    console.error('Error checking payment:', error);
                    // Try again after 10 seconds on error
                    setTimeout(checkPaymentStatus, 10000);
                });
        }
        
        // Start checking payment status after 3 seconds
        setTimeout(checkPaymentStatus, 3000);
    </script>
</body>
</html>