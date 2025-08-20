<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

$txn_id = $_GET['txn_id'] ?? null;
$success = isset($_GET['success']);

if (!$txn_id) {
    header('Location: add_money.php');
    exit;
}

// Get payment details
$stmt = $pdo->prepare("SELECT p.*, u.name, u.mobile 
    FROM payments p
    JOIN users u ON p.user_id = u.id
    WHERE p.transaction_id = ?");
$stmt->execute([$txn_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    $_SESSION['payment_error'] = [
        'message' => 'Transaction not found',
        'transaction_id' => $txn_id
    ];
    header('Location: add_money.php');
    exit;
}

// Get current wallet balance
$stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->execute([$payment['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$current_balance = $user['wallet_balance'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .status-card {
            max-width: 600px;
            margin: 2rem auto;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .status-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }
        .success { color: #28a745; }
        .pending { color: #ffc107; }
        .failed { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card status-card">
            <div class="card-body text-center p-5">
                <?php if ($payment['status'] === 'success' || $success): ?>
                    <i class="bi bi-check-circle-fill status-icon success"></i>
                    <h2 class="mb-3">Payment Successful!</h2>
                    <p class="lead">₹<?= number_format($payment['amount'], 2) ?> has been added to your wallet</p>
                <?php elseif ($payment['status'] === 'pending'): ?>
                    <i class="bi bi-hourglass-split status-icon pending"></i>
                    <h2 class="mb-3">Payment Pending</h2>
                    <p class="lead">Your payment of ₹<?= number_format($payment['amount'], 2) ?> is being processed</p>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill status-icon failed"></i>
                    <h2 class="mb-3">Payment Failed</h2>
                    <p class="lead">We couldn't process your payment of ₹<?= number_format($payment['amount'], 2) ?></p>
                <?php endif; ?>
                
                <div class="payment-details mt-4 p-3 bg-light rounded">
                    <p><strong>Transaction ID:</strong> <?= htmlspecialchars($payment['transaction_id']) ?></p>
                    <p><strong>Amount:</strong> ₹<?= number_format($payment['amount'], 2) ?></p>
                    <p><strong>Date:</strong> <?= date('d M Y h:i A', strtotime($payment['created_at'])) ?></p>
                    <p><strong>Current Balance:</strong> ₹<?= number_format($current_balance, 2) ?></p>
                </div>
                
                <div class="mt-4">
                    <?php if ($payment['status'] === 'pending'): ?>
                        <a href="jazapay_qr.php" class="btn btn-primary">
                            Back to Payment
                        </a>
                    <?php endif; ?>
                    <a href="wallet.php" class="btn btn-outline-primary ms-2">
                        View Wallet
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>