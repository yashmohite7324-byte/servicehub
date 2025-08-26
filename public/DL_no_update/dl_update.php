<!-- dl_update.php -->

<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check user authentication
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header("Location: l../index.php");
    exit;
}

// Get user's wallet balance and DL update price
$stmt = $pdo->prepare("SELECT wallet_balance, dl_update_price FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$walletBalance = $userData['wallet_balance'] ?? 0;
$servicePrice = $userData['dl_update_price'] ?? 100;

// Get user's DL update history
$historyStmt = $pdo->prepare("
    SELECT * FROM dl_update_requests 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$historyStmt->execute([$user['id']]);
$updateHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DL Number Update - Service Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --card-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            padding-bottom: 2rem;
        }
        
        .main-container {
            padding-top: 2rem;
        }
        
        .back-btn {
            background: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background: var(--primary);
            color: white;
        }
        
        .wallet-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        
        .wallet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08);
        }
        
        .wallet-card h5 {
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 600;
        }
        
        .wallet-card h2 {
            font-weight: 700;
            margin-top: 0.5rem;
        }
        
        .form-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-top: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        
        .form-header {
            background: linear-gradient(120deg, var(--primary), var(--secondary));
            color: white;
            padding: 1.5rem 2rem;
        }
        
        .form-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #343a40;
            display: flex;
            align-items: center;
        }
        
        .form-control {
            padding: 0.875rem 1rem;
            border-radius: 12px;
            border: 1px solid #e1e5eb;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
        }
        
        .btn-submit {
            background: linear-gradient(120deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-submit:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        
        .history-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-top: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        
        .history-header {
            background: #f8f9fa;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .history-header h4 {
            font-weight: 700;
            color: #343a40;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background: #f8f9fa;
            padding: 1rem 0.75rem;
        }
        
        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .detail-cell {
            font-size: 0.9rem;
        }
        
        .detail-item {
            margin-bottom: 0.25rem;
            display: flex;
        }
        
        .detail-label {
            font-weight: 600;
            min-width: 120px;
            color: var(--gray);
        }
        
        /* Animation for form elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .wallet-card {
                margin-bottom: 1rem;
            }
            
            .form-body {
                padding: 1.5rem;
            }
            
            .back-btn {
                width: 40px;
                height: 40px;
            }
            
            .detail-label {
                min-width: 100px;
            }
            
            .table-responsive {
                font-size: 0.85rem;
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

    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Wallet Balance -->
                <div class="wallet-info mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="wallet-card">
                                <h5 class="text-muted mb-2">
                                    <i class="bi bi-wallet2 me-2"></i>Current Balance
                                </h5>
                                <h2 class="text-success mb-0">₹<?= number_format($walletBalance, 2) ?></h2>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="wallet-card">
                                <h5 class="text-muted mb-2">
                                    <i class="bi bi-tag me-2"></i>DL Update Service Fee
                                </h5>
                                <h2 class="text-primary mb-0">₹<?= number_format($servicePrice, 2) ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Application Form -->
                <div class="form-container">
                    <div class="form-header text-center">
                        <h3 class="mb-0">
                            <i class="bi bi-card-checklist me-2"></i>
                            DL Number Update Request
                        </h3>
                        <p class="mb-0 mt-2">Submit your DL details for update</p>
                    </div>
                    <div class="form-body">
                        <form id="dlUpdateForm">
                            <div class="form-group">
                                <label for="document_type" class="form-label">
                                    <i class="bi bi-card-heading me-2"></i>Document Type
                                </label>
                                <select class="form-control" id="document_type" name="document_type" required>
                                    <option value="">Select Document Type</option>
                                    <option value="DL Number">DL Number</option>
                                    <option value="Learning No">Learning License No</option>
                                    <option value="Conductor Licence">Conductor Licence</option>
                                    <option value="Application No">Application No</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="dl_number" class="form-label">
                                    <i class="bi bi-card-text me-2"></i>Document Number
                                </label>
                                <input type="text" class="form-control" id="dl_number" name="dl_number" required 
                                       placeholder="Enter your document number" maxlength="20">
                            </div>
                            
                            <div class="form-group">
                                <label for="dob" class="form-label">
                                    <i class="bi bi-calendar me-2"></i>Date of Birth (DD-MM-YYYY)
                                </label>
                                <input type="text" class="form-control" id="dob" name="dob" required 
                                       placeholder="Enter your date of birth in DD-MM-YYYY format">
                            </div>
                            
                            <div class="form-group">
                                <label for="mobile" class="form-label">
                                    <i class="bi bi-phone me-2"></i>Mobile Number
                                </label>
                                <input type="text" class="form-control" id="mobile" name="mobile" required 
                                       placeholder="Enter your mobile number" maxlength="10" minlength="10">
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-submit text-white btn-lg" id="submitBtn">
                                    <i class="bi bi-send-check me-2"></i>
                                    SUBMIT UPDATE REQUEST (₹<?= number_format($servicePrice, 2) ?>)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Update History -->
                <?php if (!empty($updateHistory)): ?>
                <div class="history-container mt-4">
                    <div class="history-header">
                        <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Your Update History</h4>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Document Type</th>
                                    <th>Request Details</th>
                                    <th>Status</th>
                                    <th>Request Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($updateHistory as $request): ?>
                                <tr>
                                    <td>#<?= $request['id'] ?></td>
                                    <td><?= htmlspecialchars($request['document_type']) ?></td>
                                    <td class="detail-cell">
                                        <div class="detail-item">
                                            <span class="detail-label">Document No:</span>
                                            <span><?= htmlspecialchars($request['dl_number']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Date of Birth:</span>
                                            <span><?= htmlspecialchars($request['dob']) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Mobile:</span>
                                            <span><?= htmlspecialchars($request['mobile'] ?? 'N/A') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        switch ($request['status']) {
                                            case 'pending': $statusClass = 'status-pending'; break;
                                            case 'processing': $statusClass = 'status-processing'; break;
                                            case 'completed': $statusClass = 'status-completed'; break;
                                            case 'rejected': $statusClass = 'status-rejected'; break;
                                            case 'refund rejected': $statusClass = 'status-rejected'; break;
                                            default: $statusClass = 'status-pending';
                                        }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                        <?php if (!empty($request['admin_remarks'])): ?>
                                        <div class="mt-1 small text-muted">
                                            <strong>Remarks:</strong> <?= htmlspecialchars($request['admin_remarks']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d M Y, h:i A', strtotime($request['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize toastr
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: "toast-top-right",
                timeOut: 5000
            };
            
            // Format inputs
            $('#dl_number').on('input', function() {
                $(this).val($(this).val().toUpperCase());
            });
            
            $('#dob').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9-]/g, ''));
                
                // Auto-format date as DD-MM-YYYY
                let input = $(this).val();
                if (input.length === 2 || input.length === 5) {
                    $(this).val(input + '-');
                }
            });
            
            $('#mobile').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9]/g, ''));
            });
            
            // Form submission
            $('#dlUpdateForm').on('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = $('#submitBtn');
                const formData = $(this).serialize();
                
                // Validate document type
                const docType = $('#document_type').val();
                if (!docType) {
                    toastr.error('Please select a document type');
                    $('#document_type').focus();
                    return;
                }
                
                // Validate mobile number
                const mobile = $('#mobile').val();
                if (mobile.length !== 10) {
                    toastr.error('Please enter a valid 10-digit mobile number');
                    $('#mobile').focus();
                    return;
                }
                
                // Validate date format
                const dob = $('#dob').val();
                const dobRegex = /^(0[1-9]|[12][0-9]|3[01])-(0[1-9]|1[012])-\d{4}$/;
                if (!dobRegex.test(dob)) {
                    toastr.error('Please enter date in DD-MM-YYYY format');
                    $('#dob').focus();
                    return;
                }
                
                // Disable submit button
                submitBtn.prop('disabled', true);
                submitBtn.html('<i class="bi bi-hourglass-split me-2"></i>PROCESSING...');
                
                $.ajax({
                    url: 'process_dl_update.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message || 'DL update request submitted successfully');
                            
                            // Open WhatsApp link in a new tab if available
                            if (response.whatsapp_link) {
                                window.open(response.whatsapp_link, '_blank');
                            }
                            
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            toastr.error(response.message || 'Request submission failed');
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Error submitting request';
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
                        submitBtn.html(`<i class="bi bi-send-check me-2"></i>SUBMIT UPDATE REQUEST (₹<?= number_format($servicePrice, 2) ?>)`);
                    }
                });
            });
        });
    </script>
</body>
</html>