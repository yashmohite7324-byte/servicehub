<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../includes/db.php';
$users = $pdo->query('SELECT id, name, mobile, wallet_balance, llr_price, dl_price, rc_price, is_blocked FROM users ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .price-cell {
            text-align: right;
            min-width: 100px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .status-badge {
            font-size: 0.8rem;
        }
        .compact-table th, .compact-table td {
            padding: 0.5rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">Admin Panel</a>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="admin_services.php">Services</a></li>
            <li class="nav-item"><a class="nav-link" href="admin_logout.php">Logout</a></li>
        </ul>
    </div>
</nav>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Users</h2>
        <a href="admin_add_user.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle"></i> Add User
        </a>
    </div>
    
    <div class="table-responsive">
        <table class="table table-bordered table-hover compact-table">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Wallet</th>
                    <th class="price-cell">LLR Price</th>
                    <th class="price-cell">DL Price</th>
                    <th class="price-cell">RC Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['mobile']) ?></td>
                    <td class="price-cell">₹<?= number_format($user['wallet_balance'], 2) ?></td>
                    <td class="price-cell">
                        <span class="text-success" title="LLR Exam Price">
                            <i class="bi bi-file-earmark-text"></i> ₹<?= number_format($user['llr_price'], 2) ?>
                        </span>
                    </td>
                    <td class="price-cell">
                        <span class="text-info" title="DL PDF Price">
                            <i class="bi bi-file-earmark-pdf"></i> ₹<?= number_format($user['dl_price'], 2) ?>
                        </span>
                    </td>
                    <td class="price-cell">
                        <span class="text-warning" title="RC PDF Price">
                            <i class="bi bi-file-earmark-break"></i> ₹<?= number_format($user['rc_price'], 2) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge status-badge bg-<?= $user['is_blocked'] ? 'danger' : 'success' ?>">
                            <?= $user['is_blocked'] ? 'Blocked' : 'Active' ?>
                        </span>
                    </td>
                    <td>
                        <a href="admin_edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>