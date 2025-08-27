<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';

// Stats
$total_users = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$total_services = $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
$total_requests = $pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn();
$total_payments = $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();

// Get today's completed exam count
$today_completed_exams = $pdo->query("
    SELECT COUNT(*) 
    FROM llr_tokens 
    WHERE DATE(completed_at) = CURDATE() 
    AND status = 'completed'
")->fetchColumn();

// Get top 10 users with most exam submissions (only successful ones)
$top_exam_users = $pdo->query("
    SELECT 
        u.id, 
        u.name, 
        u.mobile, 
        COUNT(lt.id) as exam_count,
        SUM(CASE WHEN DATE(lt.completed_at) = CURDATE() AND lt.status = 'completed' THEN 1 ELSE 0 END) as today_count
    FROM llr_tokens lt
    JOIN users u ON lt.user_id = u.id
    WHERE lt.status = 'completed'
    GROUP BY u.id, u.name, u.mobile
    ORDER BY today_count DESC, exam_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Recent activities
$recent_users = $pdo->query("SELECT id, name, mobile, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recent_payments = $pdo->query("SELECT p.id, u.name, p.amount, p.status, p.created_at 
                               FROM payments p 
                               JOIN users u ON p.user_id = u.id 
                               ORDER BY p.created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PHP Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --secondary-color: #858796;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }

        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            min-height: 100vh;
            position: fixed;
            width: 14rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            padding: 1rem;
            margin: 0 0.5rem;
            border-radius: 0.35rem;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }

        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Topbar Styles */
        .topbar {
            height: 4.375rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            background-color: #fff;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .main-content {
            margin-left: 14rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            font-weight: 700;
        }

        /* Stat Card Styles */
        .stat-card {
            border-left: 0.25rem solid;
            transition: transform 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.primary {
            border-left-color: var(--primary-color);
        }

        .stat-card.success {
            border-left-color: var(--success-color);
        }

        .stat-card.info {
            border-left-color: var(--info-color);
        }

        .stat-card.warning {
            border-left-color: var(--warning-color);
        }

        .stat-card.danger {
            border-left-color: var(--danger-color);
        }

        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            font-size: 0.85rem;
            width: 100%;
        }

        .table th {
            white-space: nowrap;
        }

        /* Badge Styles */
        .badge-success {
            background-color: var(--success-color);
        }

        .badge-warning {
            background-color: var(--warning-color);
        }

        .badge-danger {
            background-color: var(--danger-color);
        }

        .top-user-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }

        .exam-count {
            font-weight: bold;
            color: var(--primary-color);
        }

        .today-count {
            font-size: 0.85rem;
            padding: 0.4em 0.8em;
            min-width: 30px;
            display: inline-block;
            text-align: center;
            background-color: var(--success-color);
            color: white;
            border-radius: 10px;
            font-weight: bold;
        }

        /* Mobile Toggle Button */
        #sidebarToggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 16rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            #sidebarToggle {
                display: block;
            }

            .topbar {
                padding-left: 4rem;
            }
        }

        @media (max-width: 768px) {
            .stat-card .h5 {
                font-size: 1.2rem;
            }

            .stat-icon {
                font-size: 1.5rem;
            }

            .card-header {
                padding: 0.75rem 1rem;
            }

            .table {
                font-size: 0.8rem;
            }

            .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
            }

            .stat-card {
                margin-bottom: 1rem;
            }

            .stat-card .row {
                flex-direction: column;
                text-align: center;
            }

            .stat-card .col-auto {
                margin-top: 0.5rem;
            }

            .table-responsive {
                font-size: 0.75rem;
            }

            .topbar h1 {
                font-size: 1.5rem;
            }
        }

        /* Overlay for mobile when sidebar is open */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }

        .today-highlight {
            background-color: rgba(28, 200, 138, 0.1);
        }
    </style>
</head>

<body>
    <!-- Mobile Sidebar Toggle -->
    <button id="sidebarToggle" class="btn">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="text-center py-4">
            <h4 class="text-white">Admin Panel</h4>
        </div>
        <hr class="bg-white mx-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_users.php">
                    <i class="fas fa-fw fa-users"></i>
                    Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="dl_no_req.php">
                    <i class="fas fa-fw fa-id-card"></i>
                    DL REQUESTS
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="medical_report.php">
                    <i class="fas fa-fw fa-notes-medical"></i>
                    Medical Requests
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="maintain.php">
                    <i class="fas fa-fw fa-list"></i>
                    Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="payment_record.php">
                    <i class="fas fa-fw fa-money-bill"></i>
                    Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_logout.php">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Topbar -->
        <nav class="navbar navbar-expand topbar mb-4 static-top shadow">
            <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-user-circle fa-fw"></i>
                        Welcome, Admin
                    </span>
                </li>
            </ul>
        </nav>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card primary h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Users</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_users ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x stat-icon text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card success h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Services</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_services ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-cog fa-2x stat-icon text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card info h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Today's Exams</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $today_completed_exams ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x stat-icon text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card warning h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Total Payments</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_payments ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-money-bill fa-2x stat-icon text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Users by Exam Submissions -->
        <div class="row">
            <div class="col-lg-12 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Top Users by Exam Submissions (Completed Only)</h6>
                        <span class="badge bg-success">Today: <?= date('M d, Y') ?></span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>User Name</th>
                                        <th>Mobile</th>
                                        <th>Total Completed Exams</th>
                                        <th>Today's Completed Exams</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_exam_users as $index => $user): ?>
                                        <tr class="<?= $user['today_count'] > 0 ? 'today-highlight' : '' ?>">
                                            <td>
                                                <span class="badge bg-primary top-user-badge"><?= $index + 1 ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($user['name']) ?></td>
                                            <td><?= htmlspecialchars($user['mobile']) ?></td>
                                            <td>
                                                <span class="exam-count"><?= $user['exam_count'] ?></span>
                                            </td>
                                            <td>
                                                <?php if ($user['today_count'] > 0): ?>
                                                    <span class="today-count"><?= $user['today_count'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="admin_users.php?search=<?= urlencode($user['mobile']) ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
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
        </div>

        <!-- Recent Activities Row -->
        <div class="row">
            <!-- Recent Users -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($user['id']) ?></td>
                                            <td><?= htmlspecialchars($user['name']) ?></td>
                                            <td><?= htmlspecialchars($user['mobile']) ?></td>
                                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Payments</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($payment['id']) ?></td>
                                            <td><?= htmlspecialchars($payment['name']) ?></td>
                                            <td>₹<?= number_format($payment['amount'], 2) ?></td>
                                            <td>
                                                <?php if ($payment['status'] == 'success'): ?>
                                                    <span class="badge badge-success">Success</span>
                                                <?php elseif ($payment['status'] == 'pending'): ?>
                                                    <span class="badge badge-warning">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Failed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($payment['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        });

        // Close sidebar when clicking on overlay
        document.getElementById('sidebarOverlay').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });

        // Auto-adjust sidebar on resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 1200) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        });
    </script>
</body>

</html>