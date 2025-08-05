<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

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
        /* Your existing CSS styles here */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container { 
            padding: 2rem 0; 
        }
        
        /* Keep all your existing styles */
    </style>
</head>
<body>
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
                                <h2 class="text-success mb-0" id="walletBalance">₹<?= number_format($walletBalance, 2) ?></h2>
                            </div>
                        </div>
                        <div class="col-md-6">
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
                    <div class="form-header">
                        <h3 class="mb-0">
                            <i class="bi bi-file-text me-2"></i>
                            LLR Exam Application
                        </h3>
                        <p class="mb-0 mt-2">Submit your Learner's License exam application</p>
                    </div>
                    <div class="form-body">
                        <form id="llrForm">
                            <div class="mb-4">
                                <label for="userData" class="form-label fw-bold">
                                    <i class="bi bi-person-lines-fill me-2"></i>Application Details
                                </label>
                                <textarea class="form-control" id="userData" name="userData" rows="6" required 
                                          placeholder="Application Number&#10;Date of Birth (DD-MM-YYYY)&#10;Password&#10;Exam PIN (Optional)&#10;Exam Type (day/night)"></textarea>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Enter each detail on a new line. Exam PIN is optional.
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-submit text-white" id="submitBtn">
                                    <i class="bi bi-send-fill me-2"></i>
                                    SUBMIT APPLICATION (₹<?= number_format($llrPrice, 2) ?>)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Applications Table -->
                <div class="results-table">
                    <div class="table-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>Your Applications
                        </h4>
                        <button class="btn btn-sm btn-light refresh-btn" id="refreshAllBtn">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    <div class="p-3">
                        <div class="table-responsive">
                            <table class="table table-hover" id="applicationsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Application Details</th>
                                        <th>Status</th>
                                        <th>Live Status</th>
                                        <th>Remarks</th>
                                        <th>Actions</th>
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

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle-fill me-2"></i>Exam Completed
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="success-animation">
                        <i class="bi bi-trophy-fill text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="text-success mt-3">Congratulations!</h4>
                    <p id="successMessage" class="lead"></p>
                    <button class="btn btn-success" id="downloadPdfBtn">
                        <i class="bi bi-download me-2"></i>Download Certificate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        // Configuration
const config = {
    refreshInterval: 5000, // 5 seconds
    endpoints: {
        submit: 'process_llr.php',
        status: 'check_llr_status.php',
        download: 'download_llr.php',
        balance: 'get_wallet_balance.php',
        applications: 'get_applications.php'
    },
    user: {
        balance: <?= $walletBalance ?>,
        llrPrice: <?= $llrPrice ?>
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    setupEventListeners();
    setupToastr();
    loadApplications();
    startAutoRefresh();
});

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
        submitApplication();
    });

    // Refresh button
    $('#refreshAllBtn').click(function() {
        refreshAllStatuses();
    });

    // Download button in modal
    $('#downloadPdfBtn').click(function() {
        const token = $(this).data('token');
        if (token) downloadPdf(token);
    });
}

function submitApplication() {
    const submitBtn = $('#submitBtn');
    const userData = $('#userData').val().trim();
    
    // Validate form data
    if (!userData) {
        toastr.error('Please enter application details');
        return;
    }
    
    const lines = userData.split('\n').filter(line => line.trim());
    if (lines.length < 3) {
        toastr.error('Please provide at least: Application Number, Date of Birth, and Password');
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

    // Submit via AJAX
    $.ajax({
        url: config.endpoints.submit,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ userData }),
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                $('#userData').val('');
                
                // Update wallet balance
                config.user.balance = response.new_balance;
                updateWalletUI();
                
                // Add new application to the table
                addNewApplication(response.application);
                
                // If submitted to API, start checking status
                if (response.application.status === 'submitted' || response.application.status === 'processing') {
                    updateApplicationStatus(response.application.token);
                }
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
            submitBtn.html(`<i class="bi bi-send-fill me-2"></i>SUBMIT APPLICATION (₹${config.user.llrPrice.toFixed(2)})`);
        }
    });
}

function addNewApplication(app) {
    const tbody = $('#applicationsTable tbody');
    
    // Create the new row
    const row = `
        <tr id="appRow-${app.token}" class="${getRowClass(app.status)}">
            <td>
                <div class="d-flex flex-column">
                    <strong class="text-primary">${app.applno}</strong>
                    <small class="text-muted">DOB: ${app.dob}</small>
                    <small class="text-muted">Token: ${app.token}</small>
                    <small class="text-muted">${formatDateTime(app.created_at)}</small>
                </div>
            </td>
            <td>
                <span class="status-badge status-${app.status}">
                    ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                </span>
            </td>
            <td class="live-status-cell">
                ${getLiveStatusIndicator(app)}
            </td>
            <td class="remarks-cell">
                <small>${app.remarks || 'Processing...'}</small>
            </td>
            <td class="actions-cell">
                ${getActionButtons(app)}
            </td>
        </tr>`;
    
    // Add to the top of the table
    if (tbody.children().length === 0) {
        tbody.html(row);
    } else {
        tbody.prepend(row);
    }
    
    // Show the table if it was hidden
    $('#noApplications').hide();
    $('.table-responsive').show();
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
                    
                    // If processing, start checking status
                    if (app.status === 'processing' || app.status === 'submitted') {
                        updateApplicationStatus(app.token);
                    }
                });
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
    const actionButtons = getActionButtons(app);
    
    return `
        <tr id="appRow-${app.token}" class="${rowClass}">
            <td>
                <div class="d-flex flex-column">
                    <strong class="text-primary">${app.applno}</strong>
                    <small class="text-muted">DOB: ${app.dob}</small>
                    <small class="text-muted">Token: ${app.token}</small>
                    <small class="text-muted">${formatDateTime(app.created_at)}</small>
                </div>
            </td>
            <td>
                <span class="status-badge status-${app.status}">
                    ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                </span>
            </td>
            <td class="live-status-cell">
                ${liveStatus}
            </td>
            <td class="remarks-cell">
                <small>${app.remarks || 'Processing...'}</small>
            </td>
            <td class="actions-cell">
                ${actionButtons}
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
                    ${app.queue ? `<div class="queue-info">Queue: ${app.queue}</div>` : ''}
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
                </div>
            `;
        default:
            return `
                <div class="live-indicator">
                    <i class="bi bi-clock text-info"></i>
                    <span class="text-info fw-bold">Submitted</span>
                </div>
            `;
    }
}

function getActionButtons(app) {
    if (app.status === 'completed' && app.filename) {
        return `
            <button class="btn download-btn btn-sm" onclick="downloadPdf('${app.token}')">
                <i class="bi bi-download me-1"></i>Download
            </button>
        `;
    }
    return '<span class="text-muted">-</span>';
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
    
    $.get(config.endpoints.applications)
        .done(function(data) {
            if (data.success) {
                renderApplications(data.applications);
                
                // Check status for each processing application
                data.applications.forEach(app => {
                    if (app.status === 'processing' || app.status === 'submitted') {
                        updateApplicationStatus(app.token);
                    }
                });
            }
        })
        .always(function() {
            $('#refreshAllBtn').prop('disabled', false);
            $('#refreshAllBtn').html('<i class="bi bi-arrow-clockwise"></i> Refresh');
        });
}

function renderApplications(applications) {
    const tbody = $('#applicationsTable tbody');
    tbody.empty();
    
    applications.forEach(app => {
        const row = createApplicationRow(app);
        tbody.append(row);
    });
}

function startAutoRefresh() {
    setInterval(() => {
        if (document.visibilityState === 'visible') {
            refreshAllStatuses();
        }
    }, config.refreshInterval);
}

function updateApplicationStatus(token) {
    $.get(`${config.endpoints.status}?token=${token}`)
        .done(function(response) {
            if (response.success) {
                updateApplicationRow(token, {
                    token: token,
                    status: response.status,
                    queue: response.queue,
                    remarks: response.message,
                    filename: response.filename,
                    applno: response.applno || token.substring(0, 8) + '...'
                });
                
                if (response.status === 'completed' && response.status_changed) {
                    showExamCompletedModal({
                        token: token,
                        applno: response.applno || token.substring(0, 8) + '...',
                        filename: response.filename
                    });
                    toastr.success(`Exam completed for application ${response.applno || token}!`);
                } else if (response.status === 'refunded' && response.status_changed) {
                    toastr.warning(`Application ${response.applno || token} has been refunded.`);
                    // Update wallet balance
                    config.user.balance += config.user.llrPrice;
                    updateWalletUI();
                }
            }
        })
        .fail(function(xhr) {
            console.error(`Error updating status for ${token}:`, xhr.responseText);
        });
}

function updateApplicationRow(token, data) {
    const row = $(`#appRow-${token}`);
    if (!row.length) return;
    
    // Update status badge
    const statusBadge = row.find('.status-badge');
    statusBadge.removeClass('status-submitted status-processing status-completed status-refunded')
               .addClass(`status-${data.status}`)
               .text(data.status.charAt(0).toUpperCase() + data.status.slice(1));
    
    // Update live status
    const liveStatusCell = row.find('.live-status-cell');
    liveStatusCell.html(getLiveStatusIndicator(data));
    
    // Update remarks
    const remarksCell = row.find('.remarks-cell');
    remarksCell.html(`<small>${data.remarks || 'Processing...'}</small>`);
    
    // Update actions
    const actionsCell = row.find('.actions-cell');
    actionsCell.html(getActionButtons(data));
    
    // Update row class
    row.removeClass('processing-row completed-row refunded-row')
       .addClass(getRowClass(data.status));
}

function showExamCompletedModal(app) {
    $('#successMessage').text(`Your LLR exam for application ${app.applno} has been completed successfully!`);
    $('#downloadPdfBtn').data('token', app.token);
    new bootstrap.Modal(document.getElementById('successModal')).show();
}

function downloadPdf(token) {
    toastr.info('Preparing download...');
    
    $.get(`${config.endpoints.download}?token=${token}`)
        .done(function(response) {
            if (response.success) {
                // Create a temporary link to download the file
                const link = document.createElement('a');
                link.href = `${config.endpoints.download}?token=${token}`;
                link.download = `LLR_Certificate_${token}.pdf`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                toastr.success('Download started');
            } else {
                toastr.error(response.message || 'Download failed');
            }
        })
        .fail(function() {
            toastr.error('Error downloading PDF');
        });
}

// Handle visibility change to pause/resume refresh when tab is not active
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        refreshAllStatuses();
    }
});
    </script>
</body>
</html>