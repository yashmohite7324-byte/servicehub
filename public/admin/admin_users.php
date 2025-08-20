<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';
$users = $pdo->query('SELECT id, name, mobile, wallet_balance, llr_price, dl_price, rc_price, dl_update_price, is_blocked, created_at FROM users ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Users Management</title>
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
    
    .badge-active {
        background: rgba(72, 187, 120, 0.2);
        color: #38a169;
    }
    
    .badge-blocked {
        background: rgba(229, 62, 62, 0.2);
        color: #e53e3e;
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
    
    @media (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
        }
        
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
                    <a class="nav-link active" href="admin_users.php">
                        <i class="bi bi-people-fill me-1"></i> Users
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
    <h1 class="page-title"><i class="bi bi-people-fill me-2"></i>Users Management</h1>
    
    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-value"><?= count($users) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">₹<?= number_format(array_sum(array_column($users, 'wallet_balance')), 2) ?></div>
            <div class="stat-label">Total Wallet Balance</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= count(array_filter($users, function($user) { return $user['is_blocked'] == 0; })) ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-item">
            <div class="stat-value"><?= count(array_filter($users, function($user) { return $user['is_blocked'] == 1; })) ?></div>
            <div class="stat-label">Blocked Users</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2"></i>All Users</span>
            <a href="admin_add_user.php" class="btn btn-light btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New User
            </a>
        </div>
        
        <div class="card-body p-0">
            <div class="search-box p-3 border-bottom">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="form-control" placeholder="Search users...">
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Wallet</th>
                            <th>LLR Price</th>
                            <th>DL Price</th>
                            <th>DL Update</th>
                            <th>RC Price</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong>#<?= $user['id'] ?></strong></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['mobile']) ?></td>
                            <td class="wallet-cell">₹<?= number_format($user['wallet_balance'], 2) ?></td>
                            <td class="price-cell">₹<?= number_format($user['llr_price'], 2) ?></td>
                            <td class="price-cell">₹<?= number_format($user['dl_price'], 2) ?></td>
                            <td class="price-cell">₹<?= number_format($user['dl_update_price'], 2) ?></td>
                            <td class="price-cell">₹<?= number_format($user['rc_price'], 2) ?></td>
                            <td>
                                <span class="status-badge <?= $user['is_blocked'] ? 'badge-blocked' : 'badge-active' ?>">
                                    <i class="bi <?= $user['is_blocked'] ? 'bi-x-circle' : 'bi-check-circle' ?> me-1"></i>
                                    <?= $user['is_blocked'] ? 'Blocked' : 'Active' ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                            <td class="text-center action-cell">
                                <a href="admin_edit_user.php?id=<?= $user['id'] ?>" class="btn btn-primary btn-sm btn-icon">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple search functionality
    document.querySelector('.search-box input').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const name = row.cells[1].textContent.toLowerCase();
            const mobile = row.cells[2].textContent.toLowerCase();
            const shouldShow = name.includes(searchText) || mobile.includes(searchText);
            row.style.display = shouldShow ? '' : 'none';
        });
    });
</script>
</body>
</html>