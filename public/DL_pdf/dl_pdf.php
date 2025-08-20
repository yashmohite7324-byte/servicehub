<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

// Check user authentication
$user = $_SESSION['user'] ?? null;
if (!$user) {
    header("Location: login.php");
    exit;
}

// Get user's wallet balance and DL price
$stmt = $pdo->prepare("SELECT wallet_balance, dl_price FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$walletBalance = $userData['wallet_balance'] ?? 0;
$dlPrice = $userData['dl_price'] ?? 150;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DL PDF - Service Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container { 
            padding: 2rem 0; 
        }
        
        .wallet-info {
            margin-bottom: 2rem;
        }
        
        .wallet-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-header {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 20px 20px 0 0;
        }
        
        .form-body {
            padding: 2rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(54, 185, 204, 0.4);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #36b9cc;
            box-shadow: 0 0 0 0.2rem rgba(54, 185, 204, 0.25);
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
        
        .demo-btn {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        
        .demo-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
            color: white;
        }
        
        .demo-links {
            margin-top: 15px;
            text-align: center;
        }
        
        /* Custom Alert Modal Styles */
        .custom-alert-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        .custom-alert-content {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: modalAppear 0.5s ease-out;
        }
        
        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .custom-alert-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
            position: relative;
        }
        
        .custom-alert-body {
            padding: 2rem;
            text-align: center;
        }
        
        .custom-alert-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ee5a24;
        }
        
        .custom-alert-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            font-weight: 700;
            color: #333;
        }
        
        .custom-alert-message {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #555;
            margin-bottom: 1.5rem;
        }
        
        .custom-alert-btn {
            background: linear-gradient(135deg, #36b9cc 0%, #258391 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(54, 185, 204, 0.4);
            margin: 0.5rem;
        }
        
        .custom-alert-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(54, 185, 204, 0.6);
        }
    </style>
</head>
<body>
    <!-- Custom Alert Modal -->
    <div class="custom-alert-modal" id="customAlert">
        <div class="custom-alert-content">
            <div class="custom-alert-header">
                <h3 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Important Notice</h3>
            </div>
            <div class="custom-alert-body">
                <div class="custom-alert-icon">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                </div>
                <h2 class="custom-alert-title">DL PDF Download Only Once</h2>
                <p class="custom-alert-message">
                    Please note that you can download your DL PDF only once. 
                    After downloading, it's your responsibility to save it properly 
                    as the history will not be shown and you won't be able to download it again.
                </p>
                
                <div>
                    <button class="custom-alert-btn" id="alertConfirmBtn">
                        <i class="bi bi-check-circle-fill me-2"></i>I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

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
                <div class="wallet-info">
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
                                    <i class="bi bi-tag me-2"></i>DL PDF Service Fee
                                </h5>
                                <h2 class="text-primary mb-0">₹<?= number_format($dlPrice, 2) ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Demo PDF Links - Simple and clean below the wallet cards -->
                    <div class="demo-links">
                        <a href="./Latest_formate.pdf" target="_blank" class="demo-btn">
                            <i class="bi bi-eye-fill me-1"></i>Demo Latest Format
                        </a>
                        <a href="./Blue_Formate .pdf" target="_blank" class="demo-btn">
                            <i class="bi bi-eye-fill me-1"></i>Demo Blue Format
                        </a>
                    </div>
                </div>
                
                <!-- Application Form -->
                <div class="form-container">
                    <div class="form-header text-center">
                        <h3 class="mb-0">
                            <i class="bi bi-file-earmark-pdf me-2"></i>
                            DL PDF Generator
                        </h3>
                        <p class="mb-0 mt-2">Enter your DL details to generate PDF</p>
                    </div>
                    <div class="form-body">
                        <form id="dlForm">
                            <div class="form-group">
                                <label for="dlno" class="form-label">
                                    <i class="bi bi-card-text me-2"></i>DL Number
                                </label>
                                <input type="text" class="form-control" id="dlno" name="dlno" required 
                                       placeholder="Enter your DL number" maxlength="20">
                            </div>
                            
                            <div class="form-group">
                                <label for="dob" class="form-label">
                                    <i class="bi bi-calendar me-2"></i>Date of Birth (DD-MM-YYYY)
                                </label>
                                <input type="text" class="form-control" id="dob" name="dob" required 
                                       placeholder="Enter your date of birth in DD-MM-YYYY format">
                            </div>
                            
                            <div class="form-group">
                                <label for="pdf_type" class="form-label">
                                    <i class="bi bi-filetype-pdf me-2"></i>PDF Format
                                </label>
                                <select class="form-control" id="pdf_type" name="pdf_type" required>
                                    <option value="type1">Blue Format (Old)</option>
                                    <option value="type2">Latest Format</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="blood_group" class="form-label">
                                    <i class="bi bi-droplet me-2"></i>Blood Group
                                </label>
                                <select class="form-control" id="blood_group" name="blood_group" required>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="address_type" class="form-label">
                                    <i class="bi bi-house me-2"></i>Address Type
                                </label>
                                <select class="form-control" id="address_type" name="address_type" required>
                                    <option value="perm">Permanent Address (on DL)</option>
                                    <option value="pres">Present Address (on DL)</option>
                                </select>
                            </div>
                            
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-submit text-white btn-lg" id="submitBtn">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>
                                    GENERATE DL PDF (₹<?= number_format($dlPrice, 2) ?>)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        $(document).ready(function() {
            // Show custom alert on page load
            $('#customAlert').show();
            
            // Handle alert confirmation button click
            $('#alertConfirmBtn').on('click', function() {
                $('#customAlert').fadeOut(500);
            });
            
            // Initialize toastr
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: "toast-top-right",
                timeOut: 5000
            };
            
            // Format inputs
            $('#dlno').on('input', function() {
                $(this).val($(this).val().toUpperCase());
            });
            
            $('#dob').on('input', function() {
                $(this).val($(this).val().replace(/[^0-9-]/g, ''));
            });
            
            // Form submission
            $('#dlForm').on('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = $('#submitBtn');
                const formData = $(this).serialize();
                
                // Disable submit button
                submitBtn.prop('disabled', true);
                submitBtn.html('<i class="bi bi-hourglass-split me-2"></i>PROCESSING...');
                
                $.ajax({
                    url: 'process_dl_pdf.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (response.pdf) {
                                // Create a download link and trigger it automatically
                                const link = document.createElement('a');
                                link.href = response.pdf;
                                link.download = response.filename || 'DL_Document.pdf';
                                document.body.appendChild(link);
                                
                                // Simulate click to trigger download
                                link.click();
                                
                                // Clean up
                                document.body.removeChild(link);
                                
                                toastr.success('DL PDF generated and downloaded successfully');
                                
                                // Optional: Redirect after successful download
                                setTimeout(() => {
                                    window.location.href = '../dashboard.php';
                                }, 2000);
                            } else {
                                toastr.error('PDF generation failed - no PDF data received');
                            }
                        } else {
                            toastr.error(response.message || 'PDF generation failed');
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Error generating PDF';
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
                        submitBtn.html(`<i class="bi bi-file-earmark-pdf me-2"></i>GENERATE DL PDF (₹<?= number_format($dlPrice, 2) ?>)`);
                    }
                });
            });
        });
    </script>
</body>
</html>