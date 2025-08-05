<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../includes/db.php';

// Stats
$total_users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_services = $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
$total_requests = $pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn();
$total_payments = $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - PHP Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">Admin Panel</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="admin_users.php">Users</a></li>
                <li class="nav-item"><a class="nav-link" href="admin_services.php">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="admin_requests.php">Requests</a></li>
                <li class="nav-item"><a class="nav-link" href="admin_payments.php">Payments</a></li>
                <li class="nav-item"><a class="nav-link" href="admin_logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-5">
    <h2>Admin Dashboard</h2>
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card text-bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Users</h5>
                    <p class="card-text display-6"><?= $total_users ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Services</h5>
                    <p class="card-text display-6"><?= $total_services ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Requests</h5>
                    <p class="card-text display-6"><?= $total_requests ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-bg-info mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Payments</h5>
                    <p class="card-text display-6"><?= $total_payments ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html> 