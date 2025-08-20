<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Get user data including wallet balance
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header("Location: login.php");
    exit;
}

// Fetch current wallet balance from DB
$stmt = $pdo->prepare("SELECT wallet_balance, llr_price FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$walletBalance = $userData['wallet_balance'] ?? 0;
$llrPrice = $userData['llr_price'] ?? 100;

// Calculate exam availability
$currentTime = time();
$startTime = strtotime('8:00 AM');
$endTime = strtotime('11:00 PM');
$isExamTime = ($currentTime >= $startTime && $currentTime <= $endTime);

// Calculate time until next exam session
if ($currentTime < $startTime) {
    // Before exam hours - count down to 8 AM
    $timeUntilNext = $startTime - $currentTime;
} elseif ($currentTime > $endTime) {
    // After exam hours - count down to next day 8 AM
    $timeUntilNext = ($startTime + 86400) - $currentTime;
} else {
    // During exam hours
    $timeUntilNext = 0;
}

// Format time for display
if ($timeUntilNext > 0) {
    $hours = floor($timeUntilNext / 3600);
    $minutes = floor(($timeUntilNext % 3600) / 60);
    $seconds = $timeUntilNext % 60;
    $countdownDisplay = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
} else {
    $countdownDisplay = "00:00:00";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LLR Exam - Live Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 10px;
        }
        
        .main-container { 
            padding: 1rem 0; 
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .wallet-info {
            margin-bottom: 1.5rem;
        }
        
        .wallet-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 1.2rem;
            box-shadow: 0 5px 20px rgba(31, 38, 135, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            height: 100%;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(31, 38, 135, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .form-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.2rem;
            border-radius: 15px 15px 0 0;
        }
        
        .form-body {
            padding: 1.5rem;
        }
        
        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .results-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(31, 38, 135, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .table-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.2rem;
            border-radius: 15px 15px 0 0;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-submitted { background: #e3f2fd; color: #1976d2; }
        .status-processing { background: #fff3e0; color: #f57c00; }
        .status-completed { background: #e8f5e8; color: #388e3c; }
        .status-refunded { background: #ffebee; color: #d32f2f; }
        
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--warning-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .processing-row {
            background-color: rgba(255, 243, 224, 0.3);
        }
        
        .completed-row {
            background-color: rgba(232, 245, 232, 0.3);
        }
        
        .refunded-row {
            background-color: rgba(255, 235, 238, 0.3);
        }
        
        .queue-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 4px;
            font-weight: 500;
        }

        .live-updates {
            position: fixed;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.9);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #666;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        /* Table styling */
        .applicant-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            font-size: 1rem;
        }
        
        .applicant-details {
            font-size: 0.85rem;
            color: #666;
        }
        
        .applicant-details span {
            display: block;
            margin-bottom: 2px;
        }
        
        .applicant-token {
            font-size: 0.75rem;
            color: #999;
            margin-top: 6px;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.03) !important;
        }
        
        .table th {
            border-top: none;
            border-bottom: 2px solid #dee2e6;
            padding: 0.75rem;
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
            padding: 0.75rem;
        }
        
        .remarks-cell {
            max-width: 250px;
            word-wrap: break-word;
            font-size: 0.9rem;
        }
        
        .badge {
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        /* Back button styling */
        .back-btn-container {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1000;
        }
        
        .back-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: translateX(-3px);
            background: white;
        }
        
        /* Filter buttons */
        .filter-buttons {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 5px 10px;
            border-radius: 18px;
            font-size: 0.8rem;
            border: 1px solid #dee2e6;
            background: white;
            transition: all 0.2s;
        }
        
        .filter-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .filter-btn:hover {
            transform: translateY(-1px);
        }
        
        /* Password resend styles */
        .password-resend-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(31, 38, 135, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin-bottom: 1.5rem;
            display: none;
            overflow: hidden;
        }
        
        .password-resend-header {
            background: var(--secondary-gradient);
            color: white;
            padding: 1.2rem;
            border-radius: 15px 15px 0 0;
        }
        
        .password-resend-body {
            padding: 1.5rem;
        }
        
        .btn-resend {
            background: var(--secondary-gradient);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            width: 100%;
        }
        
        .btn-resend:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        #resendAppInput {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
            text-align: center;
            font-size: 1rem;
            margin-bottom: 15px;
        }
        
        #resendAppInput:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        /* Resend Password Button */
        #showResendBtn {
            width: 100%;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: var(--secondary-gradient);
            color: white;
            border: none;
            margin-top: 15px;
        }
        
        #showResendBtn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        /* Timer styles */
        .exam-timer-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 1.2rem;
            box-shadow: 0 5px 20px rgba(31, 38, 135, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .timer-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.8rem;
        }
        
        .countdown-timer {
            font-size: 2rem;
            font-weight: 700;
            color: var(--success-color);
            font-family: 'Courier New', monospace;
            background: rgba(0, 0, 0, 0.05);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-block;
            margin: 0.5rem 0;
        }
        
        .exam-status {
            font-size: 1rem;
            font-weight: 600;
            margin-top: 0.8rem;
        }
        
        .status-available {
            color: var(--success-color);
        }
        
        .status-unavailable {
            color: var(--danger-color);
        }
        
        .exam-hours {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-container {
                padding: 0.5rem 0;
            }
            
            .form-body, .password-resend-body {
                padding: 1rem;
            }
            
            .form-header, .password-resend-header, .table-header {
                padding: 1rem;
            }
            
            .countdown-timer {
                font-size: 1.5rem;
                padding: 0.4rem 0.8rem;
            }
            
            .timer-title {
                font-size: 1rem;
            }
            
            .exam-status {
                font-size: 0.9rem;
            }
            
            .applicant-name {
                font-size: 0.9rem;
            }
            
            .applicant-details {
                font-size: 0.8rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
                font-size: 0.85rem;
            }
            
            .status-badge {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
            
            .filter-buttons {
                gap: 4px;
            }
            
            .filter-btn {
                padding: 4px 8px;
                font-size: 0.75rem;
            }
            
            .live-updates {
                top: 10px;
                right: 10px;
                font-size: 0.7rem;
                padding: 6px 10px;
            }
            
            .back-btn-container {
                top: 10px;
                left: 10px;
            }
            
            .back-btn {
                width: 35px;
                height: 35px;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 5px;
            }
            
            .wallet-card {
                padding: 1rem;
            }
            
            .countdown-timer {
                font-size: 1.2rem;
            }
            
            .btn-submit, .btn-resend, #showResendBtn {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            
            .form-control {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .applicant-details span {
                font-size: 0.75rem;
            }
            
            .applicant-token {
                font-size: 0.7rem;
            }
            
            .remarks-cell {
                font-size: 0.8rem;
                max-width: 150px;
            }
        }
        
        /* Print styles */
        @media print {
            body {
                background: white !important;
            }
            
            .back-btn-container, .live-updates, .btn {
                display: none !important;
            }
            
            .wallet-card, .form-container, .results-table, .exam-timer-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                background: white !important;
            }
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <button class="back-btn" onclick="window.location.href='../dashboard.php'">
            <i class="bi bi-arrow-left" style="font-size: 1.2rem;"></i>
        </button>
    </div>

    <div class="live-updates">
        <i class="bi bi-arrow-clockwise"></i>
        <span id="updateIndicator">Auto-updating...</span>
    </div>

    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <!-- Exam Timer -->
                <!-- <div class="exam-timer-container">
                    <div class="timer-title">
                        <i class="bi bi-clock me-2"></i>LLR Exam Availability
                    </div>
                    <div class="countdown-timer" id="countdownTimer">
                        <?= $countdownDisplay ?>
                    </div>
                    <div class="exam-status <?= $isExamTime ? 'status-available' : 'status-unavailable' ?>" id="examStatus">
                        <?= $isExamTime ? 'Exams are currently available' : 'Exams will be available in' ?>
                    </div>
                    <div class="exam-hours">
                        <i class="bi bi-info-circle me-1"></i>
                        Exam hours: 8:00 AM to 11:00 PM
                    </div>
                </div> -->
                
                <!-- Wallet Balance -->
                <div class="wallet-info">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="wallet-card">
                                <h5 class="text-muted mb-2">
                                    <i class="bi bi-wallet2 me-2"></i>Current Balance
                                </h5>
                                <h2 class="text-success mb-0" id="walletBalance">₹<?= number_format($walletBalance, 2) ?></h2>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="wallet-card">
                                <h5 class="text-muted mb-2">
                                    <i class="bi bi-tag me-2"></i>LLR Service Fee
                                </h5>
                                <h2 class="text-primary mb-0" id="serviceFee">₹<?= number_format($llrPrice, 2) ?></h2>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Application Form -->
                <div class="form-container">
                    <div class="form-header text-center">
                        <h3 class="mb-0">
                            <i class="bi bi-file-text me-2"></i>
                            LLR Exam Application
                        </h3>
                        <p class="mb-0 mt-2">Enter your details to start the exam</p>
                        <div class="exam-hours" style="color:rgb(255, 255, 255)">
                        <i class="bi bi-info-circle me-1"></i>
                        <b>Exam hours: 8:00 AM to 11:00 PM</b>
                    </div>
                    </div>
                    <div class="form-body">
                        <form id="llrForm">
                            <div class="form-group">
                                <label for="applno" class="form-label">
                                    <i class="bi bi-card-text me-2"></i>Application Number
                                </label>
                                <input type="text" class="form-control" id="applno" name="applno" required 
                                       placeholder="Enter your application number" maxlength="20" <?= !$isExamTime ? 'disabled' : '' ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="dob" class="form-label">
                                    <i class="bi bi-calendar me-2"></i>Date of Birth (DD-MM-YYYY)
                                </label>
                                <input type="text" class="form-control" id="dob" name="dob" required 
                                       placeholder="Enter your date of birth in DD-MM-YYYY format" <?= !$isExamTime ? 'disabled' : '' ?>>
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock me-2"></i>Password
                                </label>
                                <input type="text" class="form-control" id="password" name="password" required 
                                       placeholder="Enter your password" <?= !$isExamTime ? 'disabled' : '' ?>>
                                <small class="form-text text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This is the password you used when applying for LLR
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="exam_pin" class="form-label">
                                    <i class="bi bi-pin me-2"></i>Exam PIN (Optional)
                                </label>
                                <input type="text" class="form-control" id="exam_pin" name="exam_pin" 
                                       placeholder="Enter exam PIN if required" <?= !$isExamTime ? 'disabled' : '' ?>>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-submit text-white btn-lg" id="submitBtn" <?= !$isExamTime ? 'disabled' : '' ?>>
                                    <i class="bi bi-send-fill me-2"></i>
                                    START LLR EXAM (₹<?= number_format($llrPrice, 2) ?>)
                                </button>
                            </div>
                        </form>
                        
                        <!-- Password Resend Button -->
                        <div class="d-grid mt-3">
                            <button type="button" class="btn btn-resend" id="showResendBtn">
                                <i class="bi bi-key me-2"></i>
                                RESEND PASSWORD
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Password Resend Section (Initially Hidden) -->
                <div class="password-resend-container" id="passwordResendContainer">
                    <div class="password-resend-header text-center">
                        <h4 class="mb-0">
                            <i class="bi bi-key-fill me-2"></i>Resend Password
                        </h4>
                        <p class="mb-0 mt-2">Get your password sent to your registered mobile</p>
                    </div>
                    <div class="password-resend-body">
                        <div class="text-center mb-3">
                            <input type="number" max="999999999999" id="resendAppInput" 
                                   placeholder="Enter Application Number" autocomplete="off">
                        </div>
                        <div class="d-grid">
                            <button type="button" class="btn btn-resend" id="sendPasswordBtn">
                                <i class="bi bi-send me-2"></i>
                                SEND PASSWORD
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Applications Table -->
                <div class="results-table">
                    <div class="table-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>Your Applications
                            <span id="lastUpdateTime" class="badge bg-light text-dark ms-2" style="font-size: 0.7rem;"></span>
                        </h4>
                        <div>
                            <button class="btn btn-sm btn-light refresh-btn" id="refreshAllBtn">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="p-3">
                        <!-- Filter Buttons -->
                        <!-- <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">All</button>
                            <button class="filter-btn" data-filter="completed">Completed</button>
                            <button class="filter-btn" data-filter="processing">Processing</button>
                            <button class="filter-btn" data-filter="submitted">Submitted</button>
                            <button class="filter-btn" data-filter="refunded">Refunded</button>
                        </div> -->
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="applicationsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Applicant Name</th>
                                        <th>Application Details</th>
                                        <th>Status</th>
                                        <th>Live Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Applications will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        <div id="noApplications" class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i>
                            No applications found. Submit your first application above.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Configuration
        const config = {
            refreshInterval: 5000, // 5 seconds for updates
            statusCheckInterval: 5000, // 5 seconds for status checks
            endpoints: {
                submit: 'process_llr.php',
                status: 'check_llr_status.php',
                applications: 'get_applications.php'
            },
            user: {
                balance: <?= $walletBalance ?>,
                llrPrice: <?= $llrPrice ?>
            },
            examTimes: {
                start: 8, // 8 AM
                end: 23   // 11 PM
            }
        };

        let refreshTimer;
        let statusCheckTimer;
        let currentFilter = 'all';
        let timeUntilNext = <?= $timeUntilNext ?>;

        // Initialize when DOM is ready
        $(document).ready(function() {
            setupEventListeners();
            setupToastr();
            loadApplications();
            startAutoRefresh();
            startStatusChecking();
            
            // Start countdown if needed
            if (timeUntilNext > 0) {
                startCountdown();
            }
        });

        function startCountdown() {
            const countdownElement = $('#countdownTimer');
            const examStatusElement = $('#examStatus');
            
            const countdownInterval = setInterval(function() {
                timeUntilNext--;
                
                if (timeUntilNext <= 0) {
                    clearInterval(countdownInterval);
                    countdownElement.text('00:00:00');
                    examStatusElement.removeClass('status-unavailable').addClass('status-available').text('Exams are currently available');
                    
                    // Enable form inputs
                    $('#applno, #dob, #password, #exam_pin, #submitBtn').prop('disabled', false);
                    
                    return;
                }
                
                // Format the time
                const hours = Math.floor(timeUntilNext / 3600);
                const minutes = Math.floor((timeUntilNext % 3600) / 60);
                const seconds = timeUntilNext % 60;
                
                countdownElement.text(
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
                );
            }, 1000);
        }

        function setupToastr() {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: "toast-top-right",
                timeOut: 5000
            };
        }

        function setupEventListeners() {
            // Form submission
            $('#llrForm').on('submit', function(e) {
                e.preventDefault();
                
                // Check if it's exam time
                const now = new Date();
                const currentHour = now.getHours();
                
                if (currentHour < config.examTimes.start || currentHour >= config.examTimes.end) {
                    toastr.error('Exams are only available between 8:00 AM and 11:00 PM');
                    return;
                }
                
                submitApplication();
            });

            // Refresh button
            $('#refreshAllBtn').click(function() {
                refreshAllStatuses();
            });

            // Back button
            $('#backButton').click(function() {
                window.location.href = 'dashboard.php';
            });

            // Filter buttons
            $('.filter-btn').click(function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                currentFilter = $(this).data('filter');
                filterApplications();
            });

            // Auto-format application number
            $('#applno').on('input', function() {
                let value = $(this).val().toUpperCase();
                $(this).val(value);
            });
            
            // Format DOB input
            $('#dob').on('input', function() {
                let value = $(this).val().replace(/[^0-9-]/g, '');
                $(this).val(value);
            });
            
            // Convert password to uppercase
            $('#password').on('input', function() {
                let value = $(this).val().toUpperCase();
                $(this).val(value);
            });
            
            // Password resend functionality
            $('#showResendBtn').click(function() {
                $('#passwordResendContainer').slideToggle();
            });
            
            $('#sendPasswordBtn').click(function() {
                sendPassword();
            });
            
            // Format application number input for resend
            $('#resendAppInput').on('input', function() {
                let value = $(this).val().replace(/[^0-9]/g, '');
                $(this).val(value);
            });
        }

        function sendPassword() {
            var applno = $("#resendAppInput").val();
            
            if(applno == "") {
                Swal.fire({
                    title: 'Alert!',
                    html: '<b>Please Enter Application Number!</b>',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            if(applno.length < 5 || applno.length > 11) {
                Swal.fire({
                    title: 'Alert!',
                    html: '<b>Please Enter Valid Application Number!</b>',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show loading state
            $('#sendPasswordBtn').prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>SENDING...');
            
            // Make the AJAX request
            $.ajax({
                url: 'https://sarathi.parivahan.gov.in/sarathiservice/passwordresendSTALL.do',
                type: 'GET',
                cache: false,
                data: { applno: applno },
                success: function(data) {
                    Swal.fire({
                        title: 'Success!',
                        html: '<b>Password sent successfully for application number!<br>' + applno + '</b>',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                    
                    // Reset the form
                    $("#resendAppInput").val('');
                    $('#passwordResendContainer').slideUp();
                },
                error: function(e) {
                    Swal.fire({
                        title: 'Error!',
                        html: '<b>Error while sending password!</b>',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                },
                complete: function() {
                    $('#sendPasswordBtn').prop('disabled', false).html('<i class="bi bi-send me-2"></i>SEND PASSWORD');
                }
            });
        }

        function filterApplications() {
            if (currentFilter === 'all') {
                $('#applicationsTable tbody tr').show();
            } else {
                $('#applicationsTable tbody tr').each(function() {
                    const status = $(this).find('.status-badge').text().toLowerCase();
                    if (status === currentFilter) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
            
            // Show no applications message if all filtered out
            const visibleRows = $('#applicationsTable tbody tr:visible').length;
            if (visibleRows === 0 && $('#applicationsTable tbody tr').length > 0) {
                $('#noApplications').show().text('No ' + currentFilter + ' applications found');
                $('.table-responsive').hide();
            } else if ($('#applicationsTable tbody tr').length === 0) {
                $('#noApplications').show().text('No applications found. Submit your first application above.');
                $('.table-responsive').hide();
            } else {
                $('#noApplications').hide();
                $('.table-responsive').show();
            }
        }

        function submitApplication() {
            const submitBtn = $('#submitBtn');
            const applno = $('#applno').val().trim();
            const dob = $('#dob').val().trim();
            const password = $('#password').val().trim();
            const exam_pin = $('#exam_pin').val().trim();
            
            // Validate form data
            if (!applno) {
                toastr.error('Please enter application number');
                return;
            }
            
            if (!dob || !/^\d{2}-\d{2}-\d{4}$/.test(dob)) {
                toastr.error('Please enter a valid date of birth in DD-MM-YYYY format');
                return;
            }
            
            if (!password) {
                toastr.error('Please enter your password');
                return;
            }
            
            // Check wallet balance
            if (config.user.balance < config.user.llrPrice) {
                toastr.error('Insufficient wallet balance. Please recharge your wallet.');
                return;
            }

            // Disable submit button
            submitBtn.prop('disabled', true);
            submitBtn.html('<i class="bi bi-hourglass-split me-2"></i>SUBMITTING...');

            // Prepare form data
            const formData = {
                applno: applno,
                dob: dob,
                password: password,
                exam_pin: exam_pin,
                exam_type: 'day' // Always day exam
            };

            // Submit via AJAX
            $.ajax({
                url: config.endpoints.submit,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(formData),
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        $('#llrForm')[0].reset();
                        
                        // Update wallet balance
                        config.user.balance = response.new_balance;
                        $('#walletBalance').text('₹' + response.new_balance.toFixed(2));
                        
                        // Reload applications immediately
                        loadApplications();
                        
                        // Start intensive checking for new applications
                        setTimeout(() => {
                            checkNewApplicationStatus();
                        }, 2000);
                    } else {
                        toastr.error(response.message || 'Submission failed');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Error submitting application';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) errorMsg = response.message;
                    } catch (e) {
                        errorMsg = xhr.responseText || 'Server error';
                    }
                    toastr.error(errorMsg);
                },
                complete: function() {
                    submitBtn.prop('disabled', false);
                    submitBtn.html(`<i class="bi bi-send-fill me-2"></i>START LLR EXAM (₹${config.user.llrPrice.toFixed(2)})`);
                }
            });
        }

        function loadApplications() {
            $.get(config.endpoints.applications)
                .done(function(data) {
                    if (data.success) {
                        const tbody = $('#applicationsTable tbody');
                        tbody.empty();
                        
                        if (data.applications.length === 0) {
                            $('#noApplications').show();
                            $('.table-responsive').hide();
                            return;
                        }
                        
                        $('#noApplications').hide();
                        $('.table-responsive').show();
                        
                        data.applications.forEach(app => {
                            const row = createApplicationRow(app);
                            tbody.append(row);
                        });

                        // Apply current filter
                        filterApplications();
                        
                        // Update last refresh time
                        $('#lastUpdateTime').text('Updated: ' + new Date().toLocaleTimeString());
                } else {
                    toastr.error(data.message || 'Failed to load applications');
                }
            })
            .fail(function() {
                toastr.error('Error loading applications');
            });
        }

        function createApplicationRow(app) {
            const rowClass = getRowClass(app.status);
            const liveStatus = getLiveStatusIndicator(app);
            
            // Format dates
            const submittedTime = formatDateTime(app.created_at);
            const lastApiResponse = app.last_api_response ? formatDateTime(app.last_api_response) : 'Not available';
            const completedTime = app.completed_at ? formatDateTime(app.completed_at) : 'Not completed';
            
            return `
                <tr id="appRow-${app.token}" class="${rowClass}" data-status="${app.status}">
                    <td>
                        <div class="applicant-name">${app.applname || 'Processing...'}</div>
                        <div class="applicant-token">Token: ${app.token}</div>
                    </td>
                    <td>
                        <div class="applicant-details">
                            <span><strong>App No:</strong> ${app.applno}</span>
                            <span><strong>DOB:</strong> ${app.dob || 'Not available'}</span>
                            <span><strong>Password:</strong> ${app.password || 'Not set'}</span>
                            <span><strong></strong> ${submittedTime}</span>
                            <span><strong></strong> ${completedTime}</span>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge status-${app.status}">
                            ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                        </span>
                    </td>
                    <td class="live-status-cell">
                        ${liveStatus}
                        ${app.queue ? `
                        <div class="queue-position mt-2">
                            <i class="bi bi-arrow-up-right-circle"></i>
                            Queue: ${app.queue}
                        </div>
                        ` : ''}
                    </td>
                    <td class="remarks-cell">
                        ${app.remarks || 'Processing your exam...'}
                    </td>
                </tr>`;
        }

        function getRowClass(status) {
            switch(status) {
                case 'processing': return 'processing-row';
                case 'completed': return 'completed-row';
                case 'refunded': return 'refunded-row';
                default: return '';
            }
        }

        function getLiveStatusIndicator(app) {
            switch(app.status) {
                case 'processing':
                    return `
                        <div class="live-indicator pulse">
                            <div class="spinner"></div>
                            <span class="text-warning fw-bold">Processing</span>
                            ${app.queue ? `<div class="queue-info text-info">Queue: #${app.queue}</div>` : ''}
                        </div>
                    `;
                case 'completed':
                    return `
                        <div class="live-indicator">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span class="text-success fw-bold">Completed</span>
                        </div>
                    `;
                case 'refunded':
                    return `
                        <div class="live-indicator">
                            <i class="bi bi-x-circle-fill text-danger"></i>
                            <span class="text-danger fw-bold">Refunded</span>
                            <div class="queue-info text-danger">Amount refunded</div>
                        </div>
                    `;
                default:
                    return `
                        <div class="live-indicator">
                            <i class="bi bi-clock text-info"></i>
                            <span class="text-info fw-bold">Submitted</span>
                            <div class="queue-info text-info">Waiting for processing</div>
                        </div>
                    `;
            }
        }

        function formatDateTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleString('en-IN');
        }

        function refreshAllStatuses() {
            if ($('#refreshAllBtn').prop('disabled')) return;
            
            $('#refreshAllBtn').prop('disabled', true);
            $('#refreshAllBtn').html('<i class="bi bi-arrow-clockwise"></i> Refreshing...');
            
            loadApplications();
            
            setTimeout(() => {
                $('#refreshAllBtn').prop('disabled', false);
                $('#refreshAllBtn').html('<i class="bi bi-arrow-clockwise"></i> Refresh');
            }, 2000);
        }

        function startAutoRefresh() {
            refreshTimer = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    loadApplications();
                    $('#updateIndicator').text('Updated: ' + new Date().toLocaleTimeString());
                }
            }, config.refreshInterval);
        }

        function startStatusChecking() {
            statusCheckTimer = setInterval(() => {
                checkProcessingApplications();
            }, config.statusCheckInterval);
        }

        function checkProcessingApplications() {
            // Get all processing applications and check their status
            $('#applicationsTable tbody tr').each(function() {
                const row = $(this);
                const token = row.attr('id').replace('appRow-', '');
                const statusBadge = row.find('.status-badge');
                
                if (statusBadge.hasClass('status-processing') || statusBadge.hasClass('status-submitted')) {
                    checkSingleApplicationStatus(token);
                }
            });
        }

        function checkSingleApplicationStatus(token) {
            $.get(config.endpoints.status + '?token=' + token)
                .done(function(response) {
                    if (response.success) {
                        // Always update the row with latest data
                        const row = $('#appRow-' + token);
                        
                        // Update status badge
                        const statusBadge = row.find('.status-badge');
                        statusBadge.removeClass('status-submitted status-processing status-completed status-refunded')
                                   .addClass('status-' + response.status)
                                   .text(response.status.charAt(0).toUpperCase() + response.status.slice(1));
                        
                        // Update live status indicator
                        const liveStatus = getLiveStatusIndicator({
                            status: response.status,
                            queue: response.queue,
                            remarks: response.message
                        });
                        row.find('.live-status-cell').html(liveStatus);
                        
                        // Update remarks
                        row.find('.remarks-cell').text(response.message || 'Processing...');
                        
                        // Add/remove row classes based on status
                        row.removeClass('processing-row completed-row refunded-row')
                           .addClass(getRowClass(response.status));
                        
                        // Update data-status attribute
                        row.attr('data-status', response.status);
                        
                        if (response.status_changed) {
                            toastr.success('Status updated for token: ' + token);
                        }
                    }
                });
        }

        function checkNewApplicationStatus() {
            // Intensive checking for newly submitted applications
            let checkCount = 0;
            const intensiveCheck = setInterval(() => {
                loadApplications();
                checkCount++;
                
                // Stop intensive checking after 1 minute (12 checks every 5 seconds)
                if (checkCount >= 12) {
                    clearInterval(intensiveCheck);
                }
            }, 5000);
        }

        // Handle visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                loadApplications();
            }
        });

        // Cleanup timers when leaving page
        window.addEventListener('beforeunload', function() {
            if (refreshTimer) clearInterval(refreshTimer);
            if (statusCheckTimer) clearInterval(statusCheckTimer);
        });
    </script>
</body>
</html>