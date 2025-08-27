<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    $stmt = $pdo->prepare("SELECT wallet_balance, llr_price, dl_price, rc_price, dl_update_price,medical_price FROM users WHERE id = ?");
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
    $user['dl_update_price'] = $user['dl_update_price'] ?? 100.00;
    $user['medical_price'] = $user['medical_price'] ?? 100.00;
    
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #e6eeff;
            --success: #06d6a0;
            --success-dark: #05b286;
            --info: #118ab2;
            --info-dark: #0e7490;
            --warning: #ffd166;
            --warning-dark: #efbe42;
            --danger: #ef476f;
            --dark: #2d3748;
            --light: #f8f9fa;
            --gray: #a0aec0;
            --border-radius: 16px;
            --card-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        body {
            background-color: #f5f7ff;
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar {
            background: linear-gradient(120deg, var(--primary), var(--primary-dark));
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 12px 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .welcome-text {
            font-weight: 500;
        }
        
        .main-content {
            flex: 1;
            padding: 2rem 0;
        }
        
        .section-title {
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-left: 15px;
            font-size: 1.4rem;
        }
        
        .section-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 24px;
            width: 5px;
            background: var(--primary);
            border-radius: 10px;
        }
        
        /* Wallet Card */
        .wallet-card {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--card-shadow);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .wallet-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .wallet-card:before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
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
            margin-bottom: 1rem;
        }
        
        /* Service Cards */
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .service-card {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            background: white;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--hover-shadow);
        }
        
        .service-card-header {
            padding: 1.5rem 1.5rem 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .service-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .service-card-body {
            padding: 1rem 1.5rem;
            flex-grow: 1;
        }
        
        .service-card-footer {
            padding: 1rem 1.5rem 1.5rem;
        }
        
        .service-title {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }
        
        .service-description {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .service-price {
            font-weight: 700;
            font-size: 1.4rem;
            margin: 0.5rem 0;
            color: var(--primary);
        }
        
        .service-price small {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray);
            display: block;
        }
        
        .btn-service {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-service:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        /* Card Colors */
        .card-llr .service-icon {
            background: rgba(6, 214, 160, 0.15);
            color: var(--success);
        }
        
        .card-dl .service-icon {
            background: rgba(17, 138, 178, 0.15);
            color: var(--info);
        }
        
        .card-rc .service-icon {
            background: rgba(255, 209, 102, 0.15);
            color: var(--warning-dark);
        }
        
        .card-update .service-icon {
            background: rgba(239, 71, 111, 0.15);
            color: var(--danger);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 767.98px) {
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .main-content {
                padding: 1rem 0;
            }
            
            .service-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .wallet-icon {
                width: 50px;
                height: 50px;
                margin-bottom: 0.8rem;
            }
            
            .section-title {
                font-size: 1.2rem;
                margin-bottom: 1rem;
            }
            
            .service-card-header {
                padding: 1rem 1rem 0.5rem;
            }
            
            .service-card-body, .service-card-footer {
                padding: 0.8rem 1rem 1rem;
            }
        }
        
        @media (min-width: 768px) and (max-width: 991.98px) {
            .service-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .service-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .service-card:nth-child(1) { animation-delay: 0.1s; }
        .service-card:nth-child(2) { animation-delay: 0.2s; }
        .service-card:nth-child(3) { animation-delay: 0.3s; }
        .service-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
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
    <div class="main-content container">
        <!-- Wallet Balance Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card wallet-card">
                    <div class="card-body py-4">
                        <div class="row align-items-center">
                            <div class="col-md-8 d-flex align-items-center">
                                <div class="wallet-icon me-3">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div>
                                    <h4 class="card-title mb-1">Wallet Balance</h4>
                                    <h2 class="fw-bold mb-0">₹<?= number_format($user['wallet_balance'], 2) ?></h2>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <a href="./payment_token/add_money.php" class="btn btn-light btn-lg rounded-pill px-4">
                                    <i class="bi bi-plus-circle me-1"></i> Add Money
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Section -->
        <h4 class="section-title">Available Services</h4>
        <div class="service-grid">
            <!-- LLR Exam Card -->
            <a href="./llr token/llr_exam.php" class="text-decoration-none">
                <div class="service-card card-llr">
                    <div class="service-card-header">
                        <div class="service-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h4 class="service-title">LLR Exam</h4>
                    </div>
                    <div class="service-card-body">
                        <p class="service-description">Complete Your Learner's Exam test with our Porter.</p>
                        <div class="service-price">
                            ₹<?= number_format($user['llr_price'], 2) ?>
                            <small>Service Fee</small>
                        </div>
                    </div>
                    <div class="service-card-footer">
                        <button class="btn btn-service">
                            <i class="bi bi-arrow-right me-2"></i> Get Started
                        </button>
                    </div>
                </div>
            </a>
            
            <a href="./medical_token/medical_certificate.php" class="text-decoration-none">
                <div class="service-card card-update">
                    <div class="service-card-header">
                        <div class="service-icon">
                            <i class="bi bi-activity"></i>
                        </div>
                        <h4 class="service-title">Medical Certificate</h4>
                    </div>
                    <div class="service-card-body">
                        <p class="service-description">GET your Application Medical Certificate.</p>
                        <div class="service-price">
                            ₹<?= number_format($user['medical_price'], 2) ?>
                            <small>Service Fee</small>
                        </div>
                    </div>
                    <div class="service-card-footer">
                        <button class="btn btn-service">
                            <i class="bi bi-arrow-right me-2"></i> Get Started
                        </button>
                    </div>
                </div>
            </a>
            
            <a href="./DL_no_update/dl_update.php" class="text-decoration-none">
                <div class="service-card card-update">
                    <div class="service-card-header">
                        <div class="service-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <h4 class="service-title">DL NO UPDATE</h4>
                    </div>
                    <div class="service-card-body">
                        <p class="service-description">Update your Driving License number information in our system.</p>
                        <div class="service-price">
                            ₹<?= number_format($user['dl_update_price'], 2) ?>
                            <small>Service Fee</small>
                        </div>
                    </div>
                    <div class="service-card-footer">
                        <button class="btn btn-service">
                            <i class="bi bi-arrow-right me-2"></i> Get Started
                        </button>
                    </div>
                </div>
            </a>

            <!-- DL PDF Card -->
            <a href="./DL_pdf/dl_pdf.php" class="text-decoration-none">
                <div class="service-card card-dl">
                    <div class="service-card-header">
                        <div class="service-icon">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </div>
                        <h4 class="service-title">DL PDF</h4>
                    </div>
                    <div class="service-card-body">
                        <p class="service-description">Download your Driving License in PDF format for official use.</p>
                        <div class="service-price">
                            ₹<?= number_format($user['dl_price'], 2) ?>
                            <small>Service Fee</small>
                        </div>
                    </div>
                    <div class="service-card-footer">
                        <button class="btn btn-service">
                            <i class="bi bi-arrow-right me-2"></i> Get Started
                        </button>
                    </div>
                </div>
            </a>

            <!-- DL NUMBER UPDATE -->
            

            <!-- RC PDF Card -->
            <!-- Uncomment when needed -->
            
            

        
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add subtle hover effects
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.querySelector('.btn-service').style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.querySelector('.btn-service').style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>