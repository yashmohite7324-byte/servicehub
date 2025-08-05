<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    $stmt = $pdo->prepare("SELECT wallet_balance, llr_price, dl_price, rc_price FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $currentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $_SESSION['user'] = array_merge($_SESSION['user'], $currentData);
    $user = $_SESSION['user'];
    
} catch (PDOException $e) {
    $user = $_SESSION['user'];
    $user['wallet_balance'] = $user['wallet_balance'] ?? 0.00;
    $user['llr_price'] = $user['llr_price'] ?? 100.00;
    $user['dl_price'] = $user['dl_price'] ?? 150.00;
    $user['rc_price'] = $user['rc_price'] ?? 200.00;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Service Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #224abe;
            --success: #1cc88a;
            --success-dark: #13855c;
            --info: #36b9cc;
            --info-dark: #258391;
            --warning: #f6c23e;
            --warning-dark: #dda20a;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .main-content {
            flex: 1;
            padding-bottom: 2rem;
        }
        
        .card-service {
            transition: all 0.3s ease;
            border-radius: 0.35rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            cursor: pointer;
            overflow: hidden;
            height: 100%;
        }
        
        .card-service:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .service-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .wallet-card {
            border-radius: 0.35rem;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .service-card-llr {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            color: white;
        }
        
        .service-card-dl {
            background: linear-gradient(135deg, var(--info) 0%, var(--info-dark) 100%);
            color: white;
        }
        
        .service-card-rc {
            background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
            color: white;
        }
        
        .service-price {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .service-price small {
            font-size: 0.75rem;
            font-weight: 400;
            opacity: 0.9;
            display: block;
        }
        
        .btn-service {
            border: 2px solid rgba(255, 255, 255, 0.8);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-service:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: white;
            color: white;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 767.98px) {
            .navbar-brand {
                font-size: 1rem;
            }
            
            .welcome-text {
                font-size: 0.8rem;
                margin-right: 0.5rem;
            }
            
            .wallet-card .row {
                flex-direction: column;
                text-align: center;
            }
            
            .wallet-card .col-md-8, 
            .wallet-card .col-md-4 {
                width: 100%;
                text-align: center;
            }
            
            .wallet-card .col-md-4 {
                margin-top: 1rem;
            }
            
            .service-icon {
                font-size: 2rem;
            }
            
            .card-title {
                font-size: 1.1rem;
            }
            
            .card-text {
                font-size: 0.85rem;
            }
            
            .service-price {
                font-size: 1.1rem;
            }
        }
        
        /* Tablet styles */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .service-icon {
                font-size: 2.2rem;
            }
            
            .card-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-gear-fill me-2"></i>Service Hub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="d-flex align-items-center ms-auto">
                    <span class="text-white me-3 welcome-text">Welcome, <?= htmlspecialchars($user['name'] ?? 'User') ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content container py-3">
        <!-- Wallet Balance Card -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card wallet-card mb-3">
                    <div class="card-body py-3">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="card-title mb-1"><i class="bi bi-wallet2 me-2"></i>Wallet Balance</h4>
                                <h2 class="fw-bold mb-0">₹<?= number_format($user['wallet_balance'], 2) ?></h2>
                            </div>
                            <div class="col-md-4 text-md-end mt-2 mt-md-0">
                                <a href="add_money.php" class="btn btn-light btn-sm">
                                    <i class="bi bi-plus-circle me-1"></i> Add Money
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Section -->
        <h4 class="mb-3 fw-bold"><i class="bi bi-collection me-2"></i>Available Services</h4>
        <div class="row g-3">
            <!-- LLR Exam Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="llr_exam.php" class="text-decoration-none">
                    <div class="card card-service h-100 service-card-llr">
                        <div class="card-body py-3 text-center">
                            <div class="service-icon">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <h4 class="card-title">LLR Exam</h4>
                            <div class="service-price">
                                ₹<?= number_format($user['llr_price'], 2) ?>
                                <small>Service Fee</small>
                            </div>
                            <p class="card-text mb-3">Apply for Learner's License Registration</p>
                            <button class="btn btn-service">
                                <i class="bi bi-arrow-right me-1"></i> Get Started
                            </button>
                        </div>
                    </div>
                </a>
            </div>

            <!-- DL PDF Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="dl_pdf.php" class="text-decoration-none">
                    <div class="card card-service h-100 service-card-dl">
                        <div class="card-body py-3 text-center">
                            <div class="service-icon">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </div>
                            <h4 class="card-title">DL PDF</h4>
                            <div class="service-price">
                                ₹<?= number_format($user['dl_price'], 2) ?>
                                <small>Service Fee</small>
                            </div>
                            <p class="card-text mb-3">Download Driving License PDF</p>
                            <button class="btn btn-service">
                                <i class="bi bi-arrow-right me-1"></i> Get Started
                            </button>
                        </div>
                    </div>
                </a>
            </div>

            <!-- RC PDF Card -->
            <div class="col-12 col-md-6 col-lg-4">
                <a href="rc_pdf.php" class="text-decoration-none">
                    <div class="card card-service h-100 service-card-rc">
                        <div class="card-body py-3 text-center">
                            <div class="service-icon">
                                <i class="bi bi-file-earmark-break"></i>
                            </div>
                            <h4 class="card-title">RC PDF</h4>
                            <div class="service-price">
                                ₹<?= number_format($user['rc_price'], 2) ?>
                                <small>Service Fee</small>
                            </div>
                            <p class="card-text mb-3">Download Registration Certificate PDF</p>
                            <button class="btn btn-service">
                                <i class="bi bi-arrow-right me-1"></i> Get Started
                            </button>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap components
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>