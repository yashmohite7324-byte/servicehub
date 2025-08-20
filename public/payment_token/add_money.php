<!-- add_money.php -->

<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log');

// Get current wallet balance
$current_balance = 0.00;
if (isset($_SESSION['user']['id'])) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_balance = $user['wallet_balance'] ?? 0.00;
}

// Handle manual verification
if (isset($_POST['verify_payment'])) {
    if (!isset($_SESSION['current_payment'])) {
        $_SESSION['payment_error'] = [
            'message' => 'No active payment to verify',
            'transaction_id' => null
        ];
        header('Location: add_money.php');
        exit;
    }

    $txn_id = $_SESSION['current_payment']['transaction_id'];
    
    // Call the check_payment_status endpoint
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://jazainc.com/api/v2/pg/orders/pg-order-status.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['txnid' => $_SESSION['current_payment']['gateway_txnid']]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        
        if ($responseData['status'] === '200') {
            // Payment successful
            $amount = $_SESSION['current_payment']['amount'];
            
            // Update payment status in database
            $stmt = $pdo->prepare("UPDATE payments SET status = 'success' WHERE transaction_id = ?");
            $stmt->execute([$txn_id]);
            
            // Update user wallet
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$amount, $_SESSION['user']['id']]);
            
            // Add to payment history
            $stmt = $pdo->prepare("INSERT INTO payment_history (user_id, transaction_type, amount, description, reference_id, balance_after) 
                                 VALUES (?, 'credit', ?, 'Wallet top-up via payment gateway', ?, ?)");
            $stmt->execute([
                $_SESSION['user']['id'],
                $amount,
                $txn_id,
                $current_balance + $amount
            ]);
            
            $_SESSION['payment_success'] = [
                'amount' => $amount,
                'txn_id' => $txn_id
            ];
            
            unset($_SESSION['current_payment']);
            header('Location: add_money.php');
            exit;
        } else {
            $_SESSION['payment_error'] = [
                'message' => 'Payment not completed yet: ' . ($responseData['message'] ?? ''),
                'transaction_id' => $txn_id
            ];
        }
    } else {
        $_SESSION['payment_error'] = [
            'message' => 'Failed to verify payment status',
            'transaction_id' => $txn_id
        ];
    }
    
    header('Location: add_money.php');
    exit;
}

// Handle successful payment redirect
if (isset($_GET['success']) && isset($_GET['txn_id']) && isset($_GET['amount'])) {
    $txn_id = $_GET['txn_id'];
    $amount = floatval($_GET['amount']);
    
    // Verify the transaction is actually successful
    $stmt = $pdo->prepare("SELECT status FROM payments WHERE transaction_id = ?");
    $stmt->execute([$txn_id]);
    $payment = $stmt->fetch();
    
    if ($payment && $payment['status'] === 'success') {
        $_SESSION['payment_success'] = [
            'amount' => $amount,
            'txn_id' => $txn_id
        ];
        
        // Clear current payment session
        if (isset($_SESSION['current_payment'])) {
            unset($_SESSION['current_payment']);
        }
        
        // Redirect to avoid refresh issues
        header('Location: add_money.php');
        exit;
    }
}

// Clear any previous payment session if not coming back from payment
if (isset($_SESSION['current_payment']) && !isset($_GET['txn_id'])) {
    // Check if payment is older than 30 minutes
    if (time() - strtotime($_SESSION['current_payment']['created_at']) > 1800) {
        unset($_SESSION['current_payment']);
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
        /* Your existing styles */
        .status-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }
        .refresh-btn {
            cursor: pointer;
            transition: transform 0.3s;
        }
        .refresh-btn:hover {
            transform: rotate(180deg);
        }
        .refresh-btn.active {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (isset($_SESSION['payment_success'])): ?>
                    <div class="alert alert-success success-alert alert-dismissible fade show">
                        <h5 class="alert-heading"><i class="bi bi-check-circle-fill me-2"></i>Payment Successful!</h5>
                        <p class="mb-2">₹<?= number_format($_SESSION['payment_success']['amount'], 2) ?> has been added to your wallet.</p>
                        <p class="error-details mb-1">
                            Transaction ID: <?= htmlspecialchars($_SESSION['payment_success']['txn_id']) ?>
                        </p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['payment_success']); ?>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-white py-4">
                        <h4 class="mb-0 text-center"><i class="bi bi-wallet2 me-2"></i> Add Money to Wallet</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($_SESSION['payment_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Payment Error</h5>
                                <p class="mb-2"><?= htmlspecialchars($_SESSION['payment_error']['message']) ?></p>
                                <?php if (!empty($_SESSION['payment_error']['transaction_id'])): ?>
                                    <p class="error-details mb-1">
                                        Transaction ID: <?= htmlspecialchars($_SESSION['payment_error']['transaction_id']) ?>
                                    </p>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['payment_error']); ?>
                        <?php endif; ?>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="balance-card h-100 p-4 text-center d-flex flex-column justify-content-center">
                                    <div class="mb-3">
                                        <i class="bi bi-wallet2 fs-1"></i>
                                    </div>
                                    <h3 class="mb-3">Current Balance</h3>
                                    <h2 class="fw-bold mb-0">₹<?= number_format($current_balance, 2) ?></h2>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" id="paymentForm" action="jazapay_initiate.php">
                                    <div class="mb-4">
                                        <label for="amount" class="form-label fw-bold">Amount to Add (₹)</label>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">₹</span>
                                            <input type="number" class="form-control form-control-lg" 
                                                   id="amount" name="amount" min="10" step="1" 
                                                   placeholder="100" required>
                                        </div>
                                        <div class="form-text text-muted">Minimum: ₹10 | Maximum: ₹1,00,000</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-pay btn-lg w-100 mt-3">
                                        <i class="bi bi-qr-code me-2"></i> Generate Payment QR
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['current_payment'])): ?>
                <div class="card mt-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Active Payment</h5>
                        <div>
                            <span class="badge bg-warning status-badge" id="paymentStatusBadge">
                                <i class="bi bi-hourglass-split me-1"></i> Pending
                            </span>
                            <i class="bi bi-arrow-repeat refresh-btn ms-2" id="refreshStatusBtn"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6>Transaction ID: <?= htmlspecialchars($_SESSION['current_payment']['transaction_id']) ?></h6>
                                <p class="mb-0">Amount: ₹<?= number_format($_SESSION['current_payment']['amount'], 2) ?></p>
                                <p class="small text-muted mb-0">Gateway Ref: <?= htmlspecialchars($_SESSION['current_payment']['gateway_txnid']) ?></p>
                            </div>
                            <a href="jazapay_qr.php" class="btn btn-outline-primary">
                                View QR Code
                            </a>
                        </div>
                        <div class="progress mb-3" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="paymentProgress" style="width: 0%"></div>
                        </div>
                        <form method="POST" id="verifyPaymentForm">
                            <button type="submit" name="verify_payment" class="btn btn-verify btn-lg w-100">
                                <i class="bi bi-check-circle me-2"></i> Verify Payment Status
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Payment History Section -->
                <div class="card mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM payments 
                                WHERE user_id = ? 
                                ORDER BY created_at DESC 
                                LIMIT 5");
                            $stmt->execute([$_SESSION['user']['id']]);
                            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($payments)): ?>
                                <div class="text-center py-4 text-muted">
                                    No transactions found
                                </div>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <div class="list-group-item history-item <?= $payment['status'] ?>">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="mb-1">₹<?= number_format($payment['amount'], 2) ?></h6>
                                                <small class="text-muted"><?= date('d M Y, h:i A', strtotime($payment['created_at'])) ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?= 
                                                    $payment['status'] === 'success' ? 'success' : 
                                                    ($payment['status'] === 'pending' ? 'warning' : 'danger') 
                                                ?>">
                                                    <?= ucfirst($payment['status']) ?>
                                                </span>
                                                <div class="small text-muted mt-1"><?= $payment['transaction_id'] ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
        
        // Client-side validation
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const btn = this.querySelector('button[type="submit"]');
            
            if (amount < 10 || amount > 100000) {
                e.preventDefault();
                alert('Amount must be between ₹10 and ₹1,00,000');
                return;
            }
            
            // Disable button to prevent multiple submissions
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
        });
        
        // Auto-focus amount field if empty
        window.addEventListener('load', function() {
            const amountField = document.getElementById('amount');
            if (amountField && amountField.value === '') {
                amountField.focus();
            }
        });

        // Auto-dismiss success alert after 5 seconds
        const successAlert = document.querySelector('.success-alert');
        if (successAlert) {
            setTimeout(() => {
                const alert = new bootstrap.Alert(successAlert);
                alert.close();
            }, 5000);
        }
        
        // Payment status checking functionality
        <?php if (isset($_SESSION['current_payment'])): ?>
        const checkPaymentStatus = async () => {
            const refreshBtn = document.getElementById('refreshStatusBtn');
            const statusBadge = document.getElementById('paymentStatusBadge');
            const progressBar = document.getElementById('paymentProgress');
            
            try {
                refreshBtn.classList.add('active');
                
                const response = await fetch('check_payment_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `txn_id=<?= $_SESSION['current_payment']['gateway_txnid'] ?>`
                });
                
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Payment successful
                    statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Completed';
                    statusBadge.className = 'badge bg-success status-badge';
                    progressBar.style.width = '100%';
                    progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
                    
                    // Update UI and redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = `add_money.php?success=1&txn_id=<?= $_SESSION['current_payment']['transaction_id'] ?>&amount=<?= $_SESSION['current_payment']['amount'] ?>`;
                    }, 2000);
                } else if (data.status === 'pending') {
                    // Still pending
                    statusBadge.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> ${data.message || 'Pending'}`;
                    statusBadge.className = 'badge bg-warning status-badge';
                    
                    // Update progress bar (random increment for demo)
                    const currentWidth = parseInt(progressBar.style.width) || 0;
                    const newWidth = Math.min(currentWidth + (10 + Math.random() * 20), 90);
                    progressBar.style.width = `${newWidth}%`;
                    
                    // Show queue position if available
                    if (data.queue) {
                        statusBadge.innerHTML += ` (${data.queue})`;
                    }
                } else {
                    // Payment failed
                    statusBadge.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Failed';
                    statusBadge.className = 'badge bg-danger status-badge';
                    progressBar.style.width = '100%';
                    progressBar.className = 'progress-bar bg-danger';
                    
                    // Show error message
                    if (data.message) {
                        alert(`Payment failed: ${data.message}`);
                    }
                }
            } catch (error) {
                console.error('Error checking payment status:', error);
                statusBadge.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Error';
                statusBadge.className = 'badge bg-danger status-badge';
            } finally {
                refreshBtn.classList.remove('active');
            }
        };
        
        // Set up periodic checking every 5 seconds
        let checkInterval = setInterval(checkPaymentStatus, 5000);
        
        // Initial check
        checkPaymentStatus();
        
        // Manual refresh button
        document.getElementById('refreshStatusBtn').addEventListener('click', checkPaymentStatus);
        
        // Clean up interval when leaving page
        window.addEventListener('beforeunload', () => {
            clearInterval(checkInterval);
        });
        <?php endif; ?>
    </script>
</body>
</html>