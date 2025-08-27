<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';

// Initialize variables for filtering
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query with filters
$query = 'SELECT mcr.*, u.name as user_name, u.mobile as user_mobile 
          FROM medical_certificate_requests mcr 
          LEFT JOIN users u ON mcr.user_id = u.id 
          WHERE 1=1';

$params = [];

if (!empty($status_filter)) {
    $query .= ' AND mcr.status = ?';
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= ' AND (u.name LIKE ? OR u.mobile LIKE ? OR mcr.application_no LIKE ?)';
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= ' ORDER BY mcr.created_at DESC LIMIT ?';
$params[] = $limit;

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Get statistics
$today = date('Y-m-d');
$this_month = date('Y-m');

// Today's completed requests
$stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_certificate_requests WHERE status = 'completed' AND DATE(created_at) = ?");
$stmt->execute([$today]);
$today_completed = $stmt->fetchColumn();

// Today's updated requests
$stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_certificate_requests WHERE DATE(updated_at) = ? AND updated_at != created_at");
$stmt->execute([$today]);
$today_updated = $stmt->fetchColumn();

// This month's requests
$stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_certificate_requests WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
$stmt->execute([$this_month]);
$this_month_requests = $stmt->fetchColumn();

// Failed requests (rejected)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_certificate_requests WHERE status = 'rejected'");
$stmt->execute();
$failed_requests = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Medical Certificate Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --primary: #4361ee;
        --secondary: #3f37c9;
        --success: #4cc9f0;
        --info: #4895ef;
        --warning: #f72585;
        --danger: #e63946;
        --light: #f8f9fa;
        --dark: #212529;
        --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-bg: rgba(255, 255, 255, 0.95);
        --text-dark: #2d3748;
        --text-light: #718096;
        --shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
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
        max-width: 1600px;
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
    
    .table-responsive {
        border-radius: 0 0 16px 16px;
        overflow: hidden;
    }
    
    .table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }
    
    .table th {
        background: #f7fafc;
        color: var(--primary);
        font-weight: 600;
        padding: 1rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
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
    
    .badge-pending {
        background: rgba(247, 37, 133, 0.2);
        color: var(--warning);
    }
    
    .badge-processing {
        background: rgba(73, 149, 239, 0.2);
        color: var(--info);
    }
    
    .badge-completed {
        background: rgba(76, 201, 240, 0.2);
        color: var(--success);
    }
    
    .badge-rejected {
        background: rgba(230, 57, 70, 0.2);
        color: var(--danger);
    }
    
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
    
    .btn-sm {
        padding: 0.35rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .btn-icon {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .price-cell {
        font-weight: 600;
        color: var(--primary);
    }
    
    .action-cell {
        min-width: 150px;
    }
    
    .stats-card {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        text-align: center;
        transition: transform 0.3s ease;
    }
    
    .stat-item:hover {
        transform: translateY(-5px);
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    
    .stat-today {
        color: #4cc9f0;
    }
    
    .stat-updated {
        color: #4895ef;
    }
    
    .stat-month {
        color: #4361ee;
    }
    
    .stat-failed {
        color: #e63946;
    }
    
    .stat-label {
        color: var(--text-light);
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    .search-box {
        position: relative;
        margin-bottom: 1.5rem;
    }
    
    .search-box input {
        border-radius: 50px;
        padding-left: 3rem;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .search-box input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
    }
    
    .search-icon {
        position: absolute;
        left: 1.25rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
    }
    
    .filter-section {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }
    
    .filter-form {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: end;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-group label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
    }
    
    @media (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
        }
        
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .filter-form {
            flex-direction: column;
            align-items: stretch;
        }
    }
    
    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
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
                    <a class="nav-link active" href="#">
                        <i class="bi bi-file-medical me-1"></i> Medical Certificates
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
    <h1 class="page-title"><i class="bi bi-file-medical me-2"></i>Medical Certificate Requests</h1>
    
    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-value stat-today"><?= $today_completed ?></div>
            <div class="stat-label">Today's Completed</div>
        </div>
        <div class="stat-item">
            <div class="stat-value stat-updated"><?= $today_updated ?></div>
            <div class="stat-label">Today's Updated</div>
        </div>
        <div class="stat-item">
            <div class="stat-value stat-month"><?= $this_month_requests ?></div>
            <div class="stat-label">This Month's Requests</div>
        </div>
        <div class="stat-item">
            <div class="stat-value stat-failed"><?= $failed_requests ?></div>
            <div class="stat-label">Failed Requests</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2"></i>All Medical Certificate Requests</span>
        </div>
        
        <div class="card-body">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, mobile or application no..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $status_filter === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="limit">Records to Show</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="admin_medical_requests.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Application No</th>
                            <th>User</th>
                            <th>Mobile</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($requests) > 0): ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><strong>#<?= $request['id'] ?></strong></td>
                                <td><?= htmlspecialchars($request['application_no']) ?></td>
                                <td><?= htmlspecialchars($request['user_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($request['user_mobile'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge badge-<?= $request['status'] ?>">
                                        <i class="bi 
                                            <?= $request['status'] === 'pending' ? 'bi-clock' : '' ?>
                                            <?= $request['status'] === 'processing' ? 'bi-gear' : '' ?>
                                            <?= $request['status'] === 'completed' ? 'bi-check-circle' : '' ?>
                                            <?= $request['status'] === 'rejected' ? 'bi-x-circle' : '' ?>
                                            me-1"></i>
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td class="price-cell">₹<?= number_format($request['service_price'], 2) ?></td>
                                <td><?= date('M j, Y g:i A', strtotime($request['created_at'])) ?></td>
                                <td><?= date('M j, Y g:i A', strtotime($request['updated_at'])) ?></td>
                                <td class="text-center action-cell">
                                    <a href="admin_medical_request_detail.php?id=<?= $request['id'] ?>" class="btn btn-primary btn-sm btn-icon">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">No medical certificate requests found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>