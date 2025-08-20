<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check if payment session exists
if (!isset($_SESSION['current_payment'])) {
    header('Location: add_money.php?error=no_active_payment');
    exit;
}

$payment = $_SESSION['current_payment'];

// Verify required data
if (empty($payment['gateway_txnid']) || empty($payment['transaction_id']) || empty($payment['amount'])) {
    header('Location: add_money.php?error=invalid_payment_data');
    exit;
}

// Check if payment is already completed
$stmt = $pdo->prepare("SELECT status FROM payments WHERE transaction_id = ?");
$stmt->execute([$payment['transaction_id']]);
$status = $stmt->fetchColumn();

if ($status === 'success') {
    $_SESSION['payment_success'] = [
        'amount' => $payment['amount'],
        'txn_id' => $payment['transaction_id']
    ];
    header('Location: add_money.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        .payment-iframe {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .status-container {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 8px;
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .status-container {
            transition: all 0.3s ease;
        }

        .status-container .small {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .status-update {
            animation: fadeIn 0.5s;
        }

.status-container pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    background: rgba(0,0,0,0.05);
    padding: 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}

.debug-info {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #6c757d;
}
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="payment-container">
            <h4 class="mb-4 text-center"><i class="bi bi-credit-card me-2"></i> Complete Payment</h4>

            <div class="mb-3">
                <iframe src="<?= htmlspecialchars($payment['payment_url']) ?>"
                    class="payment-iframe"
                    id="paymentFrame"></iframe>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6>Amount: ₹<?= number_format($payment['amount'], 2) ?></h6>
                    <p class="mb-0 small text-muted">TXN ID: <?= htmlspecialchars($payment['transaction_id']) ?></p>
                </div>
                <div>
                    <button id="refreshStatusBtn" class="btn btn-sm btn-outline-primary refresh-btn">
                        <i class="bi bi-arrow-repeat"></i> Refresh
                    </button>
                </div>
            </div>

            <div id="statusContainer" class="alert alert-info status-container">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    <span>Waiting for payment...</span>
                </div>
            </div>

            <div class="text-center">
                <a href="add_money.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Wallet
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    const statusContainer = document.getElementById('statusContainer');
    const refreshBtn = document.getElementById('refreshStatusBtn');
    const paymentFrame = document.getElementById('paymentFrame');
    const gatewayTxnId = '<?= $payment["gateway_txnid"] ?>';
    const ourTxnId = '<?= $payment["transaction_id"] ?>';
    const paymentAmount = <?= $payment['amount'] ?>;
    
    let checkInterval;
    let checkCount = 0;
    const maxChecks = 120; // 10 minutes (120 checks * 5 seconds)
    let isChecking = false;

    // Status templates
    const statusTemplates = {
        pending: (data) => `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2"></div>
                <div>
                    <div class="fw-bold">${data.message}</div>
                    ${data.queue ? `<div class="small">Position in queue: ${data.queue}</div>` : ''}
                    <div class="small text-muted">Last checked: ${data.timestamp}</div>
                </div>
            </div>
        `,
        success: (data) => `
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <div>
                    <div class="fw-bold text-success">${data.message}</div>
                    ${data.utr ? `<div class="small">UTR: ${data.utr}</div>` : ''}
                    <div class="small text-muted">Completed at: ${data.timestamp}</div>
                </div>
            </div>
        `,
        failed: (data) => `
            <div class="d-flex align-items-center">
                <i class="bi bi-x-circle-fill text-danger me-2"></i>
                <div>
                    <div class="fw-bold text-danger">${data.message}</div>
                    <div class="small text-muted">Failed at: ${data.timestamp}</div>
                </div>
            </div>
        `,
        error: (data) => `
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                <div>
                    <div class="fw-bold text-warning">${data.message}</div>
                    <div class="small text-muted">Error at: ${data.timestamp}</div>
                </div>
            </div>
        `
    };

    function updateStatus(type, data) {
        statusContainer.className = `alert alert-${
            type === 'success' ? 'success' : 
            type === 'failed' ? 'danger' : 
            type === 'error' ? 'warning' : 'info'
        } status-container status-update`;
        
        statusContainer.innerHTML = statusTemplates[type](data);
    }

    async function checkPaymentStatus() {
        if (isChecking || checkCount >= maxChecks) return;
        
        checkCount++;
        isChecking = true;
        refreshBtn.classList.add('active');
        
        try {
            const response = await fetch('check_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `txn_id=${encodeURIComponent(gatewayTxnId)}`
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            switch (data.status) {
                case 'success':
                    clearInterval(checkInterval);
                    updateStatus('success', data);
                    
                    // Redirect to success page after 2 seconds
                    setTimeout(() => {
                        window.location.href = `add_money.php?success=1&txn_id=${ourTxnId}&amount=${paymentAmount}`;
                    }, 2000);
                    break;
                    
                case 'pending':
                    updateStatus('pending', data);
                    break;
                    
                case 'failed':
                    clearInterval(checkInterval);
                    updateStatus('failed', data);
                    
                    // Show retry button
                    setTimeout(() => {
                        statusContainer.innerHTML += `
                            <div class="mt-3 text-center">
                                <button onclick="window.location.reload()" class="btn btn-sm btn-primary">
                                    <i class="bi bi-arrow-repeat me-1"></i> Try Again
                                </button>
                            </div>
                        `;
                    }, 1000);
                    break;
                    
                case 'error':
                    updateStatus('error', data);
                    break;
            }
        } catch (error) {
            console.error('Status check error:', error);
            updateStatus('error', {
                message: 'Connection error: ' + error.message,
                timestamp: new Date().toLocaleTimeString()
            });
        } finally {
            isChecking = false;
            refreshBtn.classList.remove('active');
        }
    }

    // Start checking every 5 seconds
    updateStatus('pending', {
        message: 'Checking payment status...',
        timestamp: new Date().toLocaleTimeString()
    });
    
    checkInterval = setInterval(checkPaymentStatus, 5000);
    checkPaymentStatus(); // Initial check

    // Manual refresh button
    refreshBtn.addEventListener('click', checkPaymentStatus);

    // Clean up on page leave
    window.addEventListener('beforeunload', () => {
        clearInterval(checkInterval);
    });
});
    </script>
</body>

</html>