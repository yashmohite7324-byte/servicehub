<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';

// Get date ranges
$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Get payment statistics
$today_earnings = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE status = 'success' AND DATE(created_at) = ?
");
$today_earnings->execute([$today]);
$today_earnings = $today_earnings->fetch(PDO::FETCH_ASSOC)['total'];

$month_earnings = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM payments 
    WHERE status = 'success' AND created_at BETWEEN ? AND ?
");
$month_earnings->execute([$month_start, $month_end]);
$month_earnings = $month_earnings->fetch(PDO::FETCH_ASSOC)['total'];

$today_completed = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM payments 
    WHERE status = 'success' AND DATE(created_at) = ?
");
$today_completed->execute([$today]);
$today_completed = $today_completed->fetch(PDO::FETCH_ASSOC)['total'];

$today_failed = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM payments 
    WHERE status = 'failed' AND DATE(created_at) = ?
");
$today_failed->execute([$today]);
$today_failed = $today_failed->fetch(PDO::FETCH_ASSOC)['total'];

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_from_date = $_GET['from_date'] ?? '';
$filter_to_date = $_GET['to_date'] ?? '';
$filter_search = $_GET['search'] ?? '';

// Build filter query
$filter_conditions = [];
$filter_params = [];

if (!empty($filter_status)) {
    $filter_conditions[] = "p.status = ?";
    $filter_params[] = $filter_status;
}

if (!empty($filter_from_date) && !empty($filter_to_date)) {
    $filter_conditions[] = "DATE(p.created_at) BETWEEN ? AND ?";
    $filter_params[] = $filter_from_date;
    $filter_params[] = $filter_to_date;
} elseif (!empty($filter_from_date)) {
    $filter_conditions[] = "DATE(p.created_at) >= ?";
    $filter_params[] = $filter_from_date;
} elseif (!empty($filter_to_date)) {
    $filter_conditions[] = "DATE(p.created_at) <= ?";
    $filter_params[] = $filter_to_date;
}

if (!empty($filter_search)) {
    $filter_conditions[] = "(u.name LIKE ? OR u.mobile LIKE ? OR p.utr_id LIKE ?)";
    $search_term = "%$filter_search%";
    $filter_params = array_merge($filter_params, [$search_term, $search_term, $search_term]);
}

// Get filtered payments
$filter_query = "
    SELECT p.*, u.name, u.mobile 
    FROM payments p 
    JOIN users u ON p.user_id = u.id
";

if (!empty($filter_conditions)) {
    $filter_query .= " WHERE " . implode(" AND ", $filter_conditions);
}

$filter_query .= " ORDER BY p.created_at DESC LIMIT 100";

$filtered_payments = $pdo->prepare($filter_query);
$filtered_payments->execute($filter_params);
$filtered_payments = $filtered_payments->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Payment History</title>
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
    
    .table-responsive {
        border-radius: 0 0 16px 16px;
        overflow: hidden;
    }
    
    .table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
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
    
    .badge-success {
        background: rgba(72, 187, 120, 0.2);
        color: #38a169;
    }
    
    .badge-pending {
        background: rgba(237, 137, 54, 0.2);
        color: #ed8936;
    }
    
    .badge-failed {
        background: rgba(229, 62, 62, 0.2);
        color: #e53e3e;
    }
    
    .badge-initiated {
        background: rgba(66, 153, 225, 0.2);
        color: #4299e1;
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
    
    .wallet-cell {
        font-weight: 700;
        color: #38a169;
    }
    
    .action-cell {
        min-width: 120px;
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
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.5rem;
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
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }
    
    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }
    
    .form-group label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
    }
    
    .date-range {
        display: flex;
        gap: 1rem;
    }
    
    @media (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-form {
            grid-template-columns: 1fr;
        }
        
        .date-range {
            flex-direction: column;
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
                    <a class="nav-link active" href="admin_payments.php">
                        <i class="bi bi-currency-exchange me-1"></i> Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="maintain.php">
                        <i class="bi bi-gear-fill me-1"></i> Services
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
    <h1 class="page-title"><i class="bi bi-currency-exchange me-2"></i>Payment History & Earnings</h1>
    
    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-value">₹<?= number_format($today_earnings, 2) ?></div>
            <div class="stat-label">Today's Earnings</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">₹<?= number_format($month_earnings, 2) ?></div>
            <div class="stat-label">This Month Earnings</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= $today_completed ?></div>
            <div class="stat-label">Today's Completed</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= $today_failed ?></div>
            <div class="stat-label">Today's Failed</div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Filter Payments</h5>
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="success" <?= $filter_status == 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $filter_status == 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="initiated" <?= $filter_status == 'initiated' ? 'selected' : '' ?>>Initiated</option>
                </select>
            </div>
            <div class="form-group">
                <label for="date_range">Date Range</label>
                <div class="date-range">
                    <input type="date" class="form-control" id="from_date" name="from_date" 
                           value="<?= $filter_from_date ?>" placeholder="From Date">
                    <input type="date" class="form-control" id="to_date" name="to_date" 
                           value="<?= $filter_to_date ?>" placeholder="To Date">
                </div>
            </div>
            <div class="form-group">
                <label for="search">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Name, Mobile, UTR" value="<?= htmlspecialchars($filter_search) ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-2"></i>Apply Filters</button>
            </div>
        </form>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2"></i>Payment History</span>
            <div>
                <span class="me-2">Showing <?= count($filtered_payments) ?> records</span>
                <a href="payment_record.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset
                </a>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Name</th>
                            <th>Mobile</th>
                            <th>Amount</th>
                            <th>UTR ID</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filtered_payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">No payments found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filtered_payments as $payment): ?>
                        <tr>
                            <td><strong>#<?= $payment['id'] ?></strong></td>
                            <td><?= htmlspecialchars($payment['name']) ?></td>
                            <td><?= htmlspecialchars($payment['mobile']) ?></td>
                            <td class="wallet-cell">₹<?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payment['utr_id'] ?? 'N/A') ?></td>
                            <td>
                                <?php
                                $status_class = '';
                                switch ($payment['status']) {
                                    case 'success':
                                        $status_class = 'badge-success';
                                        break;
                                    case 'pending':
                                        $status_class = 'badge-pending';
                                        break;
                                    case 'failed':
                                        $status_class = 'badge-failed';
                                        break;
                                    case 'initiated':
                                        $status_class = 'badge-initiated';
                                        break;
                                }
                                ?>
                                <span class="status-badge <?= $status_class ?>">
                                    <i class="bi 
                                        <?= $payment['status'] == 'success' ? 'bi-check-circle' : '' ?>
                                        <?= $payment['status'] == 'pending' ? 'bi-clock' : '' ?>
                                        <?= $payment['status'] == 'failed' ? 'bi-x-circle' : '' ?>
                                        <?= $payment['status'] == 'initiated' ? 'bi-play-circle' : '' ?>
                                        me-1"></i>
                                    <?= ucfirst($payment['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y h:i A', strtotime($payment['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple search functionality for the table
    document.querySelector('#search').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const mobile = row.cells[2].textContent.toLowerCase();
            const utr = row.cells[4].textContent.toLowerCase();
            
            const shouldShow = name.includes(searchText) || mobile.includes(searchText) || utr.includes(searchText);
            row.style.display = shouldShow ? '' : 'none';
        });
    });
</script>
</body>
</html>