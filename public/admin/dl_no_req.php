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
            $pdo->beginTransaction();
            
            if ($status === 'rejected') {
                $stmt = $pdo->prepare("
                    SELECT d.user_id, u.dl_update_price, d.dl_number, d.document_type
                    FROM dl_update_requests d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.id = ?
                ");
                $stmt->execute([$requestId]);
                $requestData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $userId = $requestData['user_id'];
                $paymentAmount = $requestData['dl_update_price'];
                $dlNumber = $requestData['dl_number'];
                $documentType = $requestData['document_type'];
                
                if ($paymentAmount > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET wallet_balance = wallet_balance + ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$paymentAmount, $userId]);
                    
                    $status = 'refunded';
                }
            }
            
            $stmt = $pdo->prepare("
                UPDATE dl_update_requests 
                SET status = ?, admin_remarks = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $remarks, $requestId]);
            
            $pdo->commit();

            if ($status === 'completed' || $status === 'refunded') {
                $stmt = $pdo->prepare("
                    SELECT u.mobile, d.dl_number, d.document_type 
                    FROM dl_update_requests d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.id = ?
                ");
                $stmt->execute([$requestId]);
                $requestData = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("DL Update Request #$requestId status changed to $status");
            }
            $_SESSION['flash_message'] = "Request #$requestId updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = "Error updating request: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$searchQuery = $_GET['search'] ?? '';

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
    $query .= " AND (d.dl_number LIKE ? OR u.name LIKE ? OR u.mobile LIKE ? OR d.id = ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = is_numeric($searchQuery) ? $searchQuery : 0;
}
$query .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin - DL Requests Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />
<style>
    :root {
        --primary: #4361ee;
        --secondary: #3f37c9;
        --success: #4cc9f0;
        --warning: #f72585;
        --light: #f8f9fa;
        --dark: #212529;
        --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-bg: rgba(255, 255, 255, 0.95);
        --text-dark: #2d3748;
        --text-light: #718096;
        --shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        --gray: #6c757d;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: var(--bg-gradient);
        color: var(--text-dark);
        min-height: 100vh;
        padding-bottom: 2rem;
    }
    .navbar {
        background: rgba(255, 255, 255, 0.95);
        box-shadow: var(--shadow);
        padding: 0.8rem 1rem;
    }
    .navbar-brand {
        font-weight: 700;
        color: var(--primary) !important;
        font-size: 1.5rem;
    }
    .nav-link {
        font-weight: 500;
        color: var(--text-dark) !important;
        transition: all 0.3s ease;
        border-radius: 8px;
        margin: 0 0.2rem;
    }
    .nav-link:hover {
        background: rgba(67, 97, 238, 0.1);
        color: var(--primary) !important;
    }
    .nav-link.active {
        background: var(--primary);
        color: white !important;
    }
    .admin-container {
        max-width: 1400px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    .page-title {
        font-weight: 700;
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        margin-bottom: 1.5rem;
    }
    .card {
        background: var(--card-bg);
        border-radius: 16px;
        border: none;
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .card-header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        font-weight: 600;
        padding: 1rem 1.5rem;
        border-bottom: none;
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
        font-size: 0.7rem;
        padding: 0.15rem 0.4rem;
    }
    .table-responsive {
        border-radius: 0 0 16px 16px;
        overflow: hidden;
    }
    .table th {
        background: #f7fafc;
        color: var(--primary);
        font-weight: 600;
        padding: 1rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
    }
    .table td {
        padding: 1rem 0.75rem;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
    }
    .table tr:last-child td {
        border-bottom: none;
    }
    .table-hover tbody tr:hover {
        background-color: rgba(67, 97, 238, 0.05);
        transition: background-color 0.2s ease;
    }
    .status-badge {
        padding: 0.35rem 0.65rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
    }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-processing { background: #cce5ff; color: #004085; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-refunded { background: #e8f4fd; color: #0a58ca; }
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border: none;
        border-radius: 8px;
        font-weight: 500;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
    }
    .btn-outline-primary, .btn-outline-success, .btn-outline-danger {
        border-radius: 8px;
    }
    .btn-sm {
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
    }
    .action-btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
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
        min-width: 100px;
        color: var(--gray);
        margin-right: 0.5rem;
    }
    .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .modal-header {
        background: linear-gradient(120deg, var(--primary), var(--secondary));
        color: white;
        border-radius: 12px 12px 0 0;
    }
    .btn-close-white {
        filter: invert(1);
    }
    @media (max-width: 768px) {
        .table-responsive { overflow-x: auto; }
        .detail-label { min-width: 80px; }
    }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">
            <i class="bi bi-shield-lock-fill me-2"></i>Admin Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin_users.php">
                        <i class="bi bi-people-fill me-1"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="admin_dl_requests.php">
                        <i class="bi bi-card-list me-1"></i> DL Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="maintain.php">
                        <i class="bi bi-gear-fill me-1"></i> Services
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="maintain.php">
                        <i class="bi bi-currency-exchange me-1"></i> Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="admin_logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="admin-container">
    <h1 class="page-title"><i class="bi bi-card-list me-2"></i>DL Update Requests</h1>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2"></i>All DL Requests</span>
            <div class="d-flex">
                <div class="search-box me-2">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by ID, Name, Mobile, DL No..." value="<?= htmlspecialchars($searchQuery) ?>" />
                </div>
                <button class="btn btn-outline-primary" onclick="performSearch()">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs mb-2" id="statusTabs" role="tablist">
                <?php foreach ($statuses as $status): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $statusFilter === $status ? 'active' : (($status==='all' && $statusFilter==='') ? 'active' : '') ?>" 
                            id="<?= $status ?>-tab" data-bs-toggle="tab" type="button" role="tab" onclick="changeStatus('<?= $status ?>')">
                        <?= ucfirst($status) ?>
                        <span class="badge rounded-pill bg-primary badge-count"><?= $statusCounts[$status] ?></span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show mt-3" role="alert">
                <?= $_SESSION['flash_message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Document Details</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Request Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2">No requests found</p>
                            </td>
                        </tr>
                        <?php else: foreach ($requests as $request): ?>
                        <tr>
                            <td><strong>#<?= $request['id'] ?></strong></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($request['user_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($request['user_mobile'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="detail-cell">
                                    <div class="detail-item">
                                        <span class="detail-label">Req ID:</span>
                                        <span>#<?= htmlspecialchars($request['id']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Doc Type:</span>
                                        <span><?= htmlspecialchars($request['document_type']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Doc No:</span>
                                        <span><?= htmlspecialchars($request['dl_number']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">DOB:</span>
                                        <span><?= htmlspecialchars($request['dob']) ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Mobile:</span>
                                        <span><?= htmlspecialchars($request['mobile'] ?? 'N/A') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>₹<?= number_format($request['dl_update_price'] ?? 0, 2) ?></td>
                            <td>
                                <?php $statusClass = 'status-' . $request['status']; ?>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= ucfirst($request['status']) ?>
                                </span>
                                <?php if (!empty($request['admin_remarks'])): ?>
                                <div class="mt-1 small text-muted">
                                    <strong>Remarks:</strong> <?= htmlspecialchars($request['admin_remarks']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y, h:i A', strtotime($request['created_at'])) ?></td>
                            <td class="text-center action-cell">
                                <button class="btn btn-sm btn-outline-primary action-btn" 
                                        data-bs-toggle="modal" data-bs-target="#detailModal" 
                                        onclick="showDetails(<?= htmlspecialchars(json_encode($request)) ?>)">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <?php if ($request['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-outline-success action-btn mt-1" 
                                        onclick="updateStatus(<?= $request['id'] ?>, 'processing')">
                                    <i class="bi bi-play-circle"></i> Process
                                </button>
                                <?php elseif ($request['status'] === 'processing'): ?>
                                <button class="btn btn-sm btn-outline-success action-btn mt-1" 
                                        onclick="updateStatus(<?= $request['id'] ?>, 'completed')">
                                    <i class="bi bi-check-circle"></i> Complete
                                </button>
                                <button class="btn btn-sm btn-outline-danger action-btn mt-1" 
                                        data-bs-toggle="modal" data-bs-target="#rejectModal"
                                        onclick="setRejectRequestId(<?= $request['id'] ?>)">
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

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Please provide a reason for rejection. The payment will be refunded to the user's wallet.</p>
                <div class="mb-3">
                    <label for="rejectReason" class="form-label">Reason for Rejection</label>
                    <textarea class="form-control" id="rejectReason" rows="3" placeholder="Enter reason for rejection..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmReject()">Reject & Refund</button>
            </div>
        </div>
    </div>
</div>

<form id="statusUpdateForm" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
    <input type="hidden" name="action" value="update_status" />
    <input type="hidden" name="request_id" id="statusRequestId" />
    <input type="hidden" name="status" id="statusValue" />
    <input type="hidden" name="remarks" id="statusRemarks" value="" />
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 5000
    };
    let currentRejectRequestId = null;

    function changeStatus(status) {
        const url = new URL(window.location.href);
        if (status === 'all') {
            url.searchParams.delete('status');
        } else {
            url.searchParams.set('status', status);
        }
        window.location.href = url.toString();
    }
    function performSearch() {
        const searchQuery = document.getElementById('searchInput').value;
        const url = new URL(window.location.href);
        if (searchQuery) url.searchParams.set('search', searchQuery);
        else url.searchParams.delete('search');
        window.location.href = url.toString();
    }
    function showDetails(request) {
        const modalBody = document.getElementById('detailModalBody');
        const formattedDate = new Date(request.created_at).toLocaleString();
        modalBody.innerHTML = `
            <div class="request-details">
                <div class="detail-item"><span class="detail-label">Request ID:</span> <span>#${request.id}</span></div>
                <div class="detail-item"><span class="detail-label">User Name:</span> <span>${escapeHtml(request.user_name)}</span></div>
                <div class="detail-item"><span class="detail-label">User Mobile:</span> <span>${escapeHtml(request.user_mobile || 'N/A')}</span></div>
                <div class="detail-item"><span class="detail-label">Document:</span> <span>${escapeHtml(request.document_type)}</span></div>
                <div class="detail-item"><span class="detail-label">Document Number:</span> <span>${escapeHtml(request.dl_number)}</span></div>
                <div class="detail-item"><span class="detail-label">Date of Birth:</span> <span>${escapeHtml(request.dob)}</span></div>
                <div class="detail-item"><span class="detail-label">Amount:</span> <span>₹${parseFloat(request.dl_update_price || 0).toFixed(2)}</span></div>
                <div class="detail-item"><span class="detail-label">Status:</span> <span class="status-badge status-${request.status}">${request.status.charAt(0).toUpperCase() + request.status.slice(1)}</span></div>
                ${request.admin_remarks ? `
                <div class="detail-item"><span class="detail-label">Admin Remarks:</span> <span>${escapeHtml(request.admin_remarks)}</span></div>
                ` : ''}
                <div class="detail-item"><span class="detail-label">Request Date:</span> <span>${formattedDate}</span></div>
                ${request.updated_at ? `<div class="detail-item"><span class="detail-label">Last Updated:</span> <span>${new Date(request.updated_at).toLocaleString()}</span></div>` : ''}
            </div>
        `;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }
    function updateStatus(requestId, status) {
        if (confirm(`Are you sure you want to change the status of request #${requestId} to ${status}?`)) {
            document.getElementById('statusRequestId').value = requestId;
            document.getElementById('statusValue').value = status;
            document.getElementById('statusRemarks').value = '';
            document.getElementById('statusUpdateForm').submit();
        }
    }
    function setRejectRequestId(requestId) {
        currentRejectRequestId = requestId;
    }
    function confirmReject() {
        if (!currentRejectRequestId) return;
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            toastr.error('Please provide a reason for rejection');
            return;
        }
        document.getElementById('statusRequestId').value = currentRejectRequestId;
        document.getElementById('statusValue').value = 'rejected';
        document.getElementById('statusRemarks').value = reason;
        document.getElementById('statusUpdateForm').submit();
    }
    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') performSearch();
    });
    document.getElementById('rejectModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('rejectReason').value = '';
        currentRejectRequestId = null;
    });
</script>
</body>
</html>
