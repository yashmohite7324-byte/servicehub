<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user']['id'];
$error_message = '';
$success_message = '';

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $amount = isset($_GET['amount']) ? htmlspecialchars($_GET['amount']) : '';
    $txnId = isset($_GET['txn_id']) ? htmlspecialchars($_GET['txn_id']) : '';
    
    $success_message = "
        <div class='alert alert-success alert-dismissible fade show' role='alert'>
            <i class='bi bi-check-circle-fill me-2'></i>
            <strong>Payment Successful!</strong> ₹$amount has been added to your wallet.
            <div class='small'>Transaction ID: $txnId</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
    ";
    
    // Clear any session data
    unset($_SESSION['current_payment']);
    unset($_SESSION['payment_error']);
}

// Check for error message
if (isset($_SESSION['payment_error'])) {
    $error_message = "
        <div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <i class='bi bi-exclamation-triangle-fill me-2'></i>
            <strong>Error!</strong> {$_SESSION['payment_error']['message']}
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
    ";
    unset($_SESSION['payment_error']);
}

// Get current wallet balance
$stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$wallet_balance = $user['wallet_balance'] ?? 0;

// Get payment history
$stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet | Add Money</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gradient-start: #4361ee;
            --gradient-end: #3a0ca3;
            --card-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f9fafc;
            color: #333;
            padding-bottom: 2rem;
        }
        
        .back-button {
            position: absolute;
            top: 1.5rem;
            left: 1rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 100;
            transition: var(--transition);
        }
        
        .back-button:hover {
            transform: translateX(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        .wallet-card {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .wallet-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(15deg);
        }
        
        .wallet-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        
        .amount-option {
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.2rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            background: white;
            margin-bottom: 1rem;
            height: 100%;
        }
        
        .amount-option:hover {
            border-color: var(--primary);
            background-color: rgba(67, 97, 238, 0.05);
            transform: translateY(-5px);
            box-shadow: var(--card-shadow);
        }
        
        .amount-option.selected {
            border-color: var(--primary);
            background-color: rgba(67, 97, 238, 0.1);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
        }
        
        .amount-option .amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .history-item {
            border-left: 4px solid var(--primary);
            padding: 1rem 1.2rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        
        .history-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }
        
        .card-header {
            border-radius: 20px 20px 0 0 !important;
            padding: 1.2rem 1.5rem;
            font-weight: 600;
            background: white;
            border-bottom: 1px solid #edf2f7;
        }
        
        .form-control, .form-control:focus {
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            border: 2px solid #e2e8f0;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
            border: none;
            border-radius: 12px;
            padding: 0.9rem 2rem;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }
        
        .section-title {
            position: relative;
            padding-left: 1.2rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 24px;
            width: 6px;
            background: var(--primary);
            border-radius: 10px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            color: #718096;
            border-radius: 10px;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        @media (max-width: 768px) {
            .wallet-card {
                padding: 1.5rem;
            }
            
            .amount-option {
                padding: 1rem 0.5rem;
            }
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .back-button {
                top: 1rem;
                left: 0.5rem;
                width: 45px;
                height: 45px;
            }
        }
        
        .animate-pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
    </style>
</head>

<body>
    <!-- Back Button -->
    <a href="../dashboard.php" class="back-button">
        <i class="bi bi-arrow-left" style="font-size: 1.5rem;"></i>
    </a>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h2 class="section-title">My Wallet</h2>
                
                <!-- Display messages -->
                <?php if (!empty($success_message)) echo $success_message; ?>
                <?php if (!empty($error_message)) echo $error_message; ?>
                
                <!-- Wallet Balance Card -->
                <div class="wallet-card animate-pulse">
                    <div class="position-relative z-10">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="wallet-icon">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <h6 class="text-light opacity-75">Current Balance</h6>
                                <h2 class="mb-0">₹<?= number_format($wallet_balance, 2) ?></h2>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-primary py-2 px-3 rounded-pill">
                                    <i class="bi bi-arrow-up-circle me-1"></i> Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs for Wallet Operations -->
                <ul class="nav nav-tabs mb-4" id="walletTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="add-money-tab" data-bs-toggle="tab" data-bs-target="#add-money" type="button" role="tab">
                            <i class="bi bi-plus-circle me-1"></i> Add Money
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                            <i class="bi bi-clock-history me-1"></i> Transaction History
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="walletTabContent">
                    <!-- Add Money Tab -->
                    <div class="tab-pane fade show active" id="add-money" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i> Add Money to Wallet</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="jazapay_initiate.php">
                                    <div class="mb-4">
                                        <label for="amount" class="form-label fw-semibold">Amount (₹)</label>
                                        <input type="number" class="form-control form-control-lg" id="amount" name="amount" 
                                            min="10" max="100000" step="1" required placeholder="Enter amount">
                                        <div class="form-text">Minimum ₹10, Maximum ₹100,000</div>
                                    </div>
                                    
                                    <!-- Quick amount options -->
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Quick Select</label>
                                        <div class="row">
                                            <div class="col-6 col-md-3">
                                                <div class="amount-option" data-amount="100">
                                                    <div class="amount">₹100</div>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="amount-option" data-amount="500">
                                                    <div class="amount">₹500</div>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="amount-option" data-amount="1000">
                                                    <div class="amount">₹1000</div>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="amount-option" data-amount="2000">
                                                    <div class="amount">₹2000</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3">
                                        <i class="bi bi-lock-fill me-2"></i> Proceed to Payment
                                    </button>
                                    
                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-shield-check me-1"></i> Your payments are secure and encrypted
                                        </small>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- History Tab -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Recent Transactions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($payment_history) > 0): ?>
                                    <?php foreach ($payment_history as $payment): ?>
                                        <div class="history-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">₹<?= number_format($payment['amount'], 2) ?></h6>
                                                    <p class="mb-0 small text-muted">
                                                        <?= date('M j, Y g:i A', strtotime($payment['created_at'])) ?>
                                                    </p>
                                                    <p class="mb-0 small">TXN: <?= $payment['transaction_id'] ?></p>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge status-badge bg-<?= 
                                                        $payment['status'] === 'success' ? 'success' : 
                                                        ($payment['status'] === 'pending' || $payment['status'] === 'initiated' ? 'warning' : 'danger')
                                                    ?>">
                                                        <?= ucfirst($payment['status']) ?>
                                                    </span>
                                                    <?php if ($payment['status'] === 'success' && !empty($payment['utr_id'])): ?>
                                                        <p class="mb-0 small mt-1">UTR: <?= $payment['utr_id'] ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-receipt text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-3">No transactions yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Quick amount selection
            const amountOptions = document.querySelectorAll('.amount-option');
            const amountInput = document.getElementById('amount');
            
            amountOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const amount = this.getAttribute('data-amount');
                    amountInput.value = amount;
                    
                    // Update selection UI
                    amountOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });
            
            // Form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const amount = parseFloat(amountInput.value);
                if (amount < 10 || amount > 100000 || isNaN(amount)) {
                    e.preventDefault();
                    alert('Amount must be between ₹10 and ₹100,000');
                }
            });
        });
    </script>
</body>

</html>