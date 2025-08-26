<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

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
$stmt = $pdo->prepare("SELECT status FROM payments WHERE transaction_id = ? AND user_id = ?");
$stmt->execute([$payment['transaction_id'], $_SESSION['user']['id']]);
$payment_record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($payment_record && $payment_record['status'] === 'success') {
    $_SESSION['payment_success'] = [
        'amount' => $payment['amount'],
        'txn_id' => $payment['transaction_id']
    ];
    unset($_SESSION['current_payment']);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
            /* Remove scrollbars from iframe */
            overflow: hidden;
        }

        .payment-iframe iframe {
            /* Ensure iframe doesn't show scrollbars */
            overflow: hidden;
            border: none;
            width: 100%;
            height: 100%;
        }

        .status-container {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 8px;
        }

        .refresh-btn {
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .refresh-btn:hover {
            transform: scale(1.05);
        }

        .refresh-btn:active {
            transform: scale(0.95);
        }

        .refresh-btn.active {
            position: relative;
        }

        .refresh-btn.active::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: ripple 1s linear infinite;
        }

        @keyframes ripple {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(2);
                opacity: 0;
            }
        }

        .refresh-spinner {
            display: inline-block;
            transition: transform 0.6s ease;
        }

        .refresh-btn.active .refresh-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

        .debug-info {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #6c757d;
            font-size: 0.8rem;
            max-height: 200px;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-alert {
            border-left: 4px solid #28a745;
        }
        
        .error-alert {
            border-left: 4px solid #dc3545;
        }
        
        .pending-alert {
            border-left: 4px solid #17a2b8;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        
        .debug-toggle {
            cursor: pointer;
            user-select: none;
        }
        
        /* Custom scrollbar for debug info */
        .debug-info::-webkit-scrollbar {
            width: 6px;
        }
        
        .debug-info::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .debug-info::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .debug-info::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="payment-container">
            <h4 class="mb-4 text-center"><i class="bi bi-credit-card me-2"></i> Complete Payment</h4>

            <div class="mb-3 payment-iframe">
                <iframe src="<?= htmlspecialchars($payment['payment_url']) ?>"
                    id="paymentFrame"
                    scrolling="no"></iframe>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6>Amount: ₹<?= number_format($payment['amount'], 2) ?></h6>
                    <p class="mb-0 small text-muted">TXN ID: <?= htmlspecialchars($payment['transaction_id']) ?></p>
                    <p class="mb-0 small text-muted">Gateway TXN: <?= htmlspecialchars($payment['gateway_txnid']) ?></p>
                </div>
                <div>
                    <button id="refreshStatusBtn" class="btn btn-sm btn-outline-primary refresh-btn pulse">
                        <span class="refresh-spinner"><i class="bi bi-arrow-repeat"></i></span> Refresh
                    </button>
                </div>
            </div>

            <div id="statusContainer" class="alert alert-info status-container pending-alert">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    <span>Waiting for payment...</span>
                </div>
            </div>

            <!-- Debug information (hidden by default) -->
            <div class="mt-3">
                <div class="debug-toggle" onclick="toggleDebug()">
                    <i class="bi bi-bug"></i> Debug Information
                </div>
                <div id="debugInfo" class="debug-info" style="display: none;">
                    <pre id="debugContent">Loading debug information...</pre>
                </div>
            </div>

            <div class="text-center mt-3">
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
            const debugContent = document.getElementById('debugContent');
            const gatewayTxnId = '<?= $payment["gateway_txnid"] ?>';
            const ourTxnId = '<?= $payment["transaction_id"] ?>';
            const paymentAmount = <?= $payment['amount'] ?>;
            
            let checkInterval;
            let checkCount = 0;
            const maxChecks = 300; // 5 minutes (300 checks * 1 second)
            let isChecking = false;
            let debugData = [];

            // Status templates
            const statusTemplates = {
                pending: (data) => `
                    <div class="d-flex align-items-center">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        <div>
                            <div class="fw-bold">${data.message}</div>
                            ${data.queue ? `<div class="small">Position in queue: ${data.queue}</div>` : ''}
                            ${data.remarks ? `<div class="small">Remarks: ${data.remarks}</div>` : ''}
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
                    <div class="mt-2">
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-info-circle me-1"></i> 
                            Your wallet will be updated shortly. Redirecting...
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
                    <div class="mt-3 text-center">
                        <button onclick="window.location.reload()" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-repeat me-1"></i> Try Again
                        </button>
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
                    <div class="mt-3 text-center">
                        <button onclick="retryPayment()" class="btn btn-sm btn-warning me-2">
                            <i class="bi bi-arrow-repeat me-1"></i> Retry
                        </button>
                        <button onclick="cancelPayment()" class="btn btn-sm btn-danger">
                            <i class="bi bi-x-circle me-1"></i> Cancel
                        </button>
                    </div>
                `
            };

            function updateStatus(type, data) {
                statusContainer.className = `alert alert-${
                    type === 'success' ? 'success success-alert' : 
                    type === 'failed' ? 'danger error-alert' : 
                    type === 'error' ? 'warning error-alert' : 'info pending-alert'
                } status-container status-update`;
                
                statusContainer.innerHTML = statusTemplates[type](data);
            }

            function addDebugEntry(message, data) {
                debugData.push({
                    timestamp: new Date().toLocaleTimeString(),
                    message: message,
                    data: data
                });
                
                // Update debug content
                debugContent.textContent = JSON.stringify(debugData, null, 2);
            }

            async function checkPaymentStatus() {
                if (isChecking || checkCount >= maxChecks) {
                    if (checkCount >= maxChecks) {
                        updateStatus('error', {
                            message: 'Status check timeout. Please refresh the page.',
                            timestamp: new Date().toLocaleTimeString()
                        });
                        addDebugEntry('Status check timeout reached');
                    }
                    return;
                }
                
                checkCount++;
                isChecking = true;
                refreshBtn.classList.add('active');
                
                try {
                    const formData = new FormData();
                    formData.append('txn_id', gatewayTxnId);
                    
                    addDebugEntry('Sending status check request', { txn_id: gatewayTxnId });
                    
                    const response = await fetch('check_payment_status.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    addDebugEntry('Received status response', data);
                    
                    switch (data.status) {
                        case 'success':
                            clearInterval(checkInterval);
                            updateStatus('success', data);
                            refreshBtn.classList.remove('pulse');
                            
                            // Clear session and redirect to success page after 3 seconds
                            setTimeout(() => {
                                window.location.href = `add_money.php?success=1&txn_id=${ourTxnId}&amount=${paymentAmount}`;
                            }, 3000);
                            break;
                            
                        case 'pending':
                            updateStatus('pending', data);
                            break;
                            
                        case 'failed':
                            clearInterval(checkInterval);
                            updateStatus('failed', data);
                            refreshBtn.classList.remove('pulse');
                            break;
                            
                        case 'error':
                            updateStatus('error', data);
                            break;
                            
                        default:
                            updateStatus('error', {
                                message: 'Unknown status received: ' + (data.status || 'undefined'),
                                timestamp: new Date().toLocaleTimeString()
                            });
                            break;
                    }
                } catch (error) {
                    console.error('Status check error:', error);
                    addDebugEntry('Status check error', { error: error.message });
                    
                    updateStatus('error', {
                        message: 'Connection error: ' + error.message,
                        timestamp: new Date().toLocaleTimeString()
                    });
                } finally {
                    isChecking = false;
                    refreshBtn.classList.remove('active');
                }
            }

            function toggleDebug() {
                const debugInfo = document.getElementById('debugInfo');
                debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
            }

            function retryPayment() {
                checkCount = 0;
                checkPaymentStatus();
            }

            function cancelPayment() {
                window.location.href = 'add_money.php?cancel=1';
            }

            // Start checking every 3 seconds
            updateStatus('pending', {
                message: 'Checking payment status...',
                timestamp: new Date().toLocaleTimeString()
            });
            
            addDebugEntry('Starting payment status checks', { 
                gatewayTxnId: gatewayTxnId,
                ourTxnId: ourTxnId,
                amount: paymentAmount
            });
            
            checkInterval = setInterval(checkPaymentStatus, 3000);
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