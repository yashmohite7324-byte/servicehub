<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if ($requestId > 0 && in_array($status, ['processing', 'completed', 'rejected'])) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            if ($status === 'rejected') {
                // Get user ID and DL update price for refund
                $stmt = $pdo->prepare("
                    SELECT d.user_id, u.dl_update_price 
                    FROM dl_update_requests d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.id = ?
                ");
                $stmt->execute([$requestId]);
                $requestData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $userId = $requestData['user_id'];
                $paymentAmount = $requestData['dl_update_price'];
                
                // Refund amount to user account if payment was made
                if ($paymentAmount > 0) {
                    // Update user's balance
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET wallet_balance = wallet_balance + ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$paymentAmount, $userId]);
                    
                    // Record the transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (user_id, amount, type, description, created_at) 
                        VALUES (?, ?, 'refund', 'Refund for rejected DL update request #$requestId', NOW())
                    ");
                    $stmt->execute([$userId, $paymentAmount]);
                    
                    // Change status to 'refunded' instead of 'rejected'
                    $status = 'refunded';
                }
            }
            
            // Update the request status
            $stmt = $pdo->prepare("
                UPDATE dl_update_requests 
                SET status = ?, admin_remarks = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $remarks, $requestId]);
            
            // Commit transaction
            $pdo->commit();
            
            // If status is completed or rejected/refunded, we might want to notify the user
            if ($status === 'completed' || $status === 'refunded') {
                // Get user details for notification
                $stmt = $pdo->prepare("
                    SELECT u.mobile, d.dl_number, d.document_type 
                    FROM dl_update_requests d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.id = ?
                ");
                $stmt->execute([$requestId]);
                $requestData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Here you could implement SMS/email notification
                // For now, we'll just log it
                error_log("DL Update Request #$requestId status changed to $status");
            }
            
            $_SESSION['flash_message'] = "Request #$requestId updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['flash_message'] = "Error updating request: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'pending';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$query = "
    SELECT d.*, u.name as user_name, u.mobile as user_mobile, u.dl_update_price 
    FROM dl_update_requests d 
    JOIN users u ON d.user_id = u.id 
    WHERE 1=1
";

$params = [];

if (!empty($statusFilter) && $statusFilter !== 'all') {
    $query .= " AND d.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $query .= " AND (d.dl_number LIKE ? OR u.name LIKE ? OR u.mobile LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY d.created_at DESC";

// Fetch requests
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts for status tabs
$statusCounts = [];
$statuses = ['pending', 'processing', 'completed', 'refunded', 'all'];

foreach ($statuses as $status) {
    if ($status === 'all') {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM dl_update_requests");
    } else {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dl_update_requests WHERE status = ?");
        $countStmt->execute([$status]);
    }
    $statusCounts[$status] = $countStmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DL Update Requests - Admin Panel</title>
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
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 700;
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
        
        .status-refunded {
            background: #e8f4fd;
            color: #0a58ca;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .nav-tabs .nav-link {
            border: none;
            padding: 0.8rem 1.2rem;
            font-weight: 600;
            color: var(--gray);
            border-radius: 8px 8px 0 0;
            position: relative;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: transparent;
            border-bottom: 3px solid var(--primary);
        }
        
        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--primary);
        }
        
        .badge-count {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .form-control {
            border-radius: 50px;
            padding-left: 2.5rem;
        }
        
        .search-box .bi-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
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
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .request-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            margin-bottom: 0.5rem;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--gray);
            margin-right: 0.5rem;
        }
        
        .pagination {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">Service Hub Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_users.php">Users</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="admin_dl_requests.php">DL Requests</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_settings.php">Settings</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="admin_logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">DL Update Requests</h4>
                    <div class="d-flex">
                        <div class="search-box me-2">
                            <i class="bi bi-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search..." 
                                   value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>
                        <button class="btn btn-outline-secondary" onclick="performSearch()">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Status Tabs -->
                    <ul class="nav nav-tabs" id="statusTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" 
                                    id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" 
                                    type="button" role="tab" onclick="changeStatus('pending')">
                                Pending
                                <span class="position-absolute badge rounded-pill bg-primary badge-count">
                                    <?= $statusCounts['pending'] ?>
                                </span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $statusFilter === 'processing' ? 'active' : '' ?>" 
                                    id="processing-tab" data-bs-toggle="tab" data-bs-target="#processing" 
                                    type="button" role="tab" onclick="changeStatus('processing')">
                                Processing
                                <span class="position-absolute badge rounded-pill bg-primary badge-count">
                                    <?= $statusCounts['processing'] ?>
                                </span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $statusFilter === 'completed' ? 'active' : '' ?>" 
                                    id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" 
                                    type="button" role="tab" onclick="changeStatus('completed')">
                                Completed
                                <span class="position-absolute badge rounded-pill bg-primary badge-count">
                                    <?= $statusCounts['completed'] ?>
                                </span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $statusFilter === 'refunded' ? 'active' : '' ?>" 
                                    id="refunded-tab" data-bs-toggle="tab" data-bs-target="#refunded" 
                                    type="button" role="tab" onclick="changeStatus('refunded')">
                                Refunded
                                <span class="position-absolute badge rounded-pill bg-primary badge-count">
                                    <?= $statusCounts['refunded'] ?>
                                </span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $statusFilter === 'all' ? 'active' : '' ?>" 
                                    id="all-tab" data-bs-toggle="tab" data-bs-target="#all" 
                                    type="button" role="tab" onclick="changeStatus('all')">
                                All Requests
                                <span class="position-absolute badge rounded-pill bg-primary badge-count">
                                    <?= $statusCounts['all'] ?>
                                </span>
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Flash Messages -->
                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show mt-3" role="alert">
                            <?= $_SESSION['flash_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                    <?php endif; ?>
                    
                    <!-- Requests Table -->
                    <div class="table-responsive mt-3">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>User</th>
                                    <th>Document Type</th>
                                    <th>Document Number</th>
                                    <th>Refund Amount</th>
                                    <th>Date of Birth</th>
                                    <th>Mobile</th>
                                    <th>Status</th>
                                    <th>Request Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                            <p class="mt-2">No requests found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td>#<?= $request['id'] ?></td>
                                            <td><?= htmlspecialchars($request['user_name']) ?></td>
                                            <td><?= htmlspecialchars($request['document_type']) ?></td>
                                            <td><?= htmlspecialchars($request['dl_number']) ?></td>
                                            <td>₹<?= number_format($request['dl_update_price'] ?? 0, 2) ?></td>
                                            <td><?= htmlspecialchars($request['dob']) ?></td>
                                            <td><?= htmlspecialchars($request['user_mobile'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = 'status-' . $request['status'];
                                                ?>
                                                <span class="status-badge <?= $statusClass ?>">
                                                    <?= ucfirst($request['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d M Y, h:i A', strtotime($request['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary action-btn" 
                                                        data-bs-toggle="modal" data-bs-target="#detailModal" 
                                                        onclick="showDetails(<?= htmlspecialchars(json_encode($request)) ?>)">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-outline-success action-btn mt-1" 
                                                        onclick="updateStatus(<?= $request['id'] ?>, 'processing')">
                                                    <i class="bi bi-check-circle"></i> Process
                                                </button>
                                                <?php elseif ($request['status'] === 'processing'): ?>
                                                <button class="btn btn-sm btn-outline-success action-btn mt-1" 
                                                        onclick="updateStatus(<?= $request['id'] ?>, 'completed')">
                                                    <i class="bi bi-check-circle"></i> Complete
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger action-btn mt-1" 
                                                        onclick="updateStatus(<?= $request['id'] ?>, 'rejected')">
                                                    <i class="bi bi-arrow-return-left"></i> Reject & Refund
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Form (Hidden) -->
<form id="statusUpdateForm" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="request_id" id="statusRequestId">
    <input type="hidden" name="status" id="statusValue">
    <input type="hidden" name="remarks" id="statusRemarks" value="">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function changeStatus(status) {
        const url = new URL(window.location.href);
        url.searchParams.set('status', status);
        if (status === 'all') {
            url.searchParams.delete('status');
        }
        window.location.href = url.toString();
    }
    
    function performSearch() {
        const searchQuery = document.getElementById('searchInput').value;
        const url = new URL(window.location.href);
        
        if (searchQuery) {
            url.searchParams.set('search', searchQuery);
        } else {
            url.searchParams.delete('search');
        }
        
        window.location.href = url.toString();
    }
    
    function showDetails(request) {
        // Create a modal for details or use existing one
        // This is a simplified version - you might want to implement a proper modal
        alert(`Request Details:\n\nID: #${request.id}\nUser: ${request.user_name}\nDocument: ${request.document_type}\nNumber: ${request.dl_number}\nRefund Amount: ₹${request.dl_update_price}\nStatus: ${request.status}\nSubmitted: ${new Date(request.created_at).toLocaleString()}`);
    }
    
    function updateStatus(requestId, status) {
        if (confirm(`Are you sure you want to change the status of request #${requestId} to ${status}?`)) {
            document.getElementById('statusRequestId').value = requestId;
            document.getElementById('statusValue').value = status;
            
            // For rejected status, ask for a reason
            if (status === 'rejected') {
                const remarks = prompt('Please provide a reason for rejection (this will be refunded to the user):');
                if (remarks === null) return; // User cancelled
                document.getElementById('statusRemarks').value = remarks;
            }
            
            document.getElementById('statusUpdateForm').submit();
        }
    }
    
    // Enable search on Enter key
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
</script>
</body>
</html>