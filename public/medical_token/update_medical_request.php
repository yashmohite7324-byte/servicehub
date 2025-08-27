<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Certificate Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 800px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 20px auto;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .content {
            padding: 2rem;
        }
        
        .request-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 1rem;
            align-items: center;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--gray);
            min-width: 180px;
            margin-right: 1rem;
        }
        
        .detail-value {
            color: var(--dark);
            font-weight: 500;
        }
        
        .status-form {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .btn-update {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .badge {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 50px;
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .refund-info {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        
        .quick-remarks {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .quick-remark-btn {
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 6px 15px;
            font-size: 0.875rem;
            color: #495057;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .quick-remark-btn:hover {
            background-color: #dee2e6;
            color: #212529;
        }
        
        .access-denied {
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .access-denied-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        
        .auto-submit-info {
            background-color: #d1ecf1;
            border-left: 4px solid #0c5460;
            color: #0c5460;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .detail-label {
                min-width: auto;
                margin-right: 0;
                margin-bottom: 0.25rem;
            }
            
            .toast-container {
                left: 20px;
                right: 20px;
            }
            
            .header {
                padding: 1.5rem;
            }
            
            .content {
                padding: 1.5rem;
            }
            
            .quick-remarks {
                flex-direction: column;
            }
            
            .quick-remark-btn {
                width: 100%;
                text-align: left;
            }
            
            .btn-update {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .container {
                border-radius: 15px;
                margin: 10px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .request-details, .status-form {
                padding: 1rem;
            }
            
            .access-denied {
                padding: 2rem 1rem;
            }
            
            .access-denied-icon {
                font-size: 3rem;
            }
            
            .detail-label {
                font-size: 0.9rem;
            }
            
            .detail-value {
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 400px) {
            .header {
                padding: 1rem;
            }
            
            .content {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
            
            .badge {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="mb-2">
                <i class="bi bi-file-medical me-2"></i>
                Medical Certificate Request
            </h1>
            <p class="mb-0">Status: <span class="badge bg-info" id="currentStatus">Loading...</span></p>
        </div>
        
        <div class="content" id="mainContent">
            <div id="messageContainer"></div>
            
            <div class="request-details">
                <h3 class="mb-3 text-center">
                    <i class="bi bi-info-circle me-2"></i>Request Information
                </h3>
                <div id="requestDetails">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading request details...</p>
                    </div>
                </div>
            </div>
            
            <div class="status-form">
                <h3 class="mb-3 text-center">
                    <i class="bi bi-pencil-square me-2"></i>Update Status
                </h3>
                
                <form id="statusForm">
                    <input type="hidden" id="requestId" value="">
                    <input type="hidden" id="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    <input type="hidden" id="servicePrice" value="">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">
                            <i class="bi bi-flag me-1"></i>Status
                        </label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">-- Select Status --</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                        <div id="refundInfo" class="refund-info">
                            <i class="bi bi-info-circle me-1"></i>
                            When rejected, the service amount will be automatically refunded to the user's wallet.
                        </div>
                        <div id="autoSubmitInfo" class="auto-submit-info">
                            <i class="bi bi-info-circle me-1"></i>
                            When completed, the remark will be automatically set and the form will be submitted.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">
                            <i class="bi bi-chat-square-text me-1"></i>Remarks
                        </label>
                        
                        <div class="quick-remarks">
                            <button type="button" class="quick-remark-btn" data-remark="MEDICAL CERTIFICATE GENERATED SUCCESSFULLY">
                                <i class="bi bi-check-circle me-1"></i>MEDICAL CERTIFICATE GENERATED
                            </button>
                            <button type="button" class="quick-remark-btn" data-remark="APPLICATION NOT FOUNDD">
                                <i class="bi bi-x-circle me-1"></i>APPLICATION NOT FOUND
                            </button>
                        </div>
                        
                        <textarea class="form-control" id="remarks" name="remarks" rows="4" 
                                  placeholder="Add remarks about this status update..." required></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-update text-white btn-lg">
                            <i class="bi bi-arrow-up-circle me-2"></i>
                            Update Status
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="bi bi-shield-check me-1"></i>
                    This is a secure status update portal. All changes are logged.
                </small>
            </div>
        </div>
        
        <div id="accessDenied" class="access-denied" style="display: none;">
            <div class="access-denied-icon">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h2>Access Denied</h2>
            <p class="lead">This request has already been processed and cannot be modified.</p>
        </div>
    </div>

    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusForm = document.getElementById('statusForm');
            const token = document.getElementById('token').value;
            const requestDetails = document.getElementById('requestDetails');
            const currentStatus = document.getElementById('currentStatus');
            const statusSelect = document.getElementById('status');
            const refundInfo = document.getElementById('refundInfo');
            const autoSubmitInfo = document.getElementById('autoSubmitInfo');
            const servicePriceInput = document.getElementById('servicePrice');
            const remarksTextarea = document.getElementById('remarks');
            const mainContent = document.getElementById('mainContent');
            const accessDenied = document.getElementById('accessDenied');
            
            if (!token) {
                showToast('Invalid request token.', 'error');
                return;
            }
            
            // Load request details on page load
            loadRequestDetails();
            
            // Show/hide info messages when status is selected
            statusSelect.addEventListener('change', function() {
                if (this.value === 'rejected') {
                    refundInfo.style.display = 'block';
                    autoSubmitInfo.style.display = 'none';
                    // Auto-set remark for rejected status
                    remarksTextarea.value = "APPLICATION NOT FOUNDD";
                } else if (this.value === 'completed') {
                    refundInfo.style.display = 'none';
                    autoSubmitInfo.style.display = 'block';
                    // Auto-set remark for completed status
                    remarksTextarea.value = "MEDICAL CERTIFICATE GENERATED SUCCESSFULLY";
                    
                    // Auto-submit after a short delay
                    setTimeout(() => {
                        statusForm.dispatchEvent(new Event('submit'));
                    }, 300);
                } else {
                    refundInfo.style.display = 'none';
                    autoSubmitInfo.style.display = 'none';
                }
            });
            
            // Add event listeners to quick remark buttons
            document.querySelectorAll('.quick-remark-btn').forEach(button => {
                button.addEventListener('click', function() {
                    remarksTextarea.value = this.getAttribute('data-remark');
                });
            });
            
            // Function to show toast message
            function showToast(message, type = 'success') {
                const toastContainer = document.querySelector('.toast-container');
                const toastId = 'toast-' + Date.now();
                
                const toastEl = document.createElement('div');
                toastEl.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
                toastEl.setAttribute('role', 'alert');
                toastEl.setAttribute('aria-live', 'assertive');
                toastEl.setAttribute('aria-atomic', 'true');
                toastEl.id = toastId;
                
                toastEl.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                
                toastContainer.appendChild(toastEl);
                
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 5000
                });
                toast.show();
                
                // Remove toast from DOM after it's hidden
                toastEl.addEventListener('hidden.bs.toast', function() {
                    toastEl.remove();
                });
            }
            
            // Function to load request details
            function loadRequestDetails() {
                const formData = new FormData();
                formData.append('token', token);
                formData.append('action', 'get_details');
                
                fetch('process_medical_status_update.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Check if status is pending
                        if (data.request.status !== 'pending') {
                            // Hide main content and show access denied message
                            mainContent.style.display = 'none';
                            accessDenied.style.display = 'block';
                            
                            // Update status badge in header
                            currentStatus.textContent = data.request.status.charAt(0).toUpperCase() + data.request.status.slice(1);
                            currentStatus.className = 'badge bg-' + 
                                (data.request.status === 'completed' ? 'success' : 
                                 data.request.status === 'rejected' ? 'danger' : 
                                 data.request.status === 'processing' ? 'warning' : 'info');
                            
                            return;
                        }
                        
                        // Update request ID
                        document.getElementById('requestId').value = data.request.id;
                        servicePriceInput.value = data.request.service_price;
                        
                        // Update page title and header
                        document.title = `Medical Certificate Request #${data.request.id}`;
                        document.querySelector('.header h1').innerHTML = 
                            `<i class="bi bi-file-medical me-2"></i>Medical Certificate Request #${data.request.id}`;
                        
                        // Update status badge
                        currentStatus.textContent = data.request.status.charAt(0).toUpperCase() + data.request.status.slice(1);
                        currentStatus.className = 'badge bg-' + 
                            (data.request.status === 'completed' ? 'success' : 
                             data.request.status === 'rejected' ? 'danger' : 
                             data.request.status === 'processing' ? 'warning' : 'info');
                        
                        // Update request details
                        requestDetails.innerHTML = `
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="bi bi-card-text me-1"></i>Application Number:
                                </span>
                                <span class="detail-value">${data.request.application_no}</span>
                            </div>
                            
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="bi bi-clock me-1"></i>Submitted:
                                </span>
                                <span class="detail-value">${data.request.created_at}</span>
                            </div>
                            
                            ${data.request.admin_remarks ? `
                            <div class="detail-row">
                                <span class="detail-label">
                                    <i class="bi bi-chat-square-text me-1"></i>Admin Remarks:
                                </span>
                                <span class="detail-value">${data.request.admin_remarks}</span>
                            </div>
                            ` : ''}
                        `;
                    } else {
                        showToast(data.message || 'Error loading request details.', 'error');
                        requestDetails.innerHTML = `<div class="alert alert-danger">${data.message || 'Error loading request details.'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error. Please try again.', 'error');
                    requestDetails.innerHTML = `<div class="alert alert-danger">Network error. Please try again.</div>`;
                });
            }
            
            statusForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const status = document.getElementById('status').value;
                const remarks = document.getElementById('remarks').value;
                const requestId = document.getElementById('requestId').value;
                const servicePrice = document.getElementById('servicePrice').value;
                
                if (!status || !remarks) {
                    showToast('Please select a status and add remarks.', 'error');
                    return;
                }
                
                // Disable submit button
                const submitBtn = statusForm.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Updating...';
                
                // Prepare form data
                const formData = new FormData();
                formData.append('status', status);
                formData.append('remarks', remarks);
                formData.append('token', token);
                formData.append('action', 'update_status');
                formData.append('service_price', servicePrice);
                
                // Send AJAX request to backend
                fetch('process_medical_status_update.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let message = data.message || 'Status updated successfully!';
                        if (status === 'rejected' && data.refund_amount) {
                            message += ' The service amount has been refunded to the user\'s wallet.';
                        }
                        
                        showToast(message);
                        
                        // Update the status badge
                        currentStatus.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                        currentStatus.className = 'badge bg-' + 
                            (status === 'completed' ? 'success' : 'danger');
                        
                        // Clear form
                        statusForm.reset();
                        refundInfo.style.display = 'none';
                        autoSubmitInfo.style.display = 'none';
                        
                        // Hide form and show access denied message
                        mainContent.style.display = 'none';
                        accessDenied.style.display = 'block';
                        
                    } else {
                        showToast(data.message || 'Error updating status.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error. Please try again.', 'error');
                })
                .finally(() => {
                    // Re-enable submit button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-arrow-up-circle me-2"></i>Update Status';
                });
            });
        });
    </script>
</body>
</html>