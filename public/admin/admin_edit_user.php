<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';

$user_id = intval($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: admin_users.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: admin_users.php');
    exit;
}

$services = [
    ['id' => 1, 'name' => 'LLR Exam', 'icon' => 'file-earmark-text', 'color' => 'success'],
    ['id' => 2, 'name' => 'DL PDF', 'icon' => 'file-earmark-pdf', 'color' => 'info'],
    ['id' => 3, 'name' => 'RC PDF', 'icon' => 'file-earmark-break', 'color' => 'warning'],
    ['id' => 4, 'name' => 'DL Update', 'icon' => 'arrow-repeat', 'color' => 'primary']
];

$current_prices = [
    1 => $user['llr_price'] ?? 100.00,
    2 => $user['dl_price'] ?? 50.00,
    3 => $user['rc_price'] ?? 100.00,
    4 => $user['dl_update_price'] ?? 75.00
];

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $wallet = floatval($_POST['wallet_balance'] ?? 0);
        $is_blocked = isset($_POST['is_blocked']) ? 1 : 0;

        $stmt = $pdo->prepare('UPDATE users SET wallet_balance = ?, is_blocked = ? WHERE id = ?');
        $stmt->execute([$wallet, $is_blocked, $user_id]);

        foreach ($services as $service) {
            $price = floatval($_POST['service_price'][$service['id']] ?? $current_prices[$service['id']]);
            $column = match($service['id']) {
                1 => 'llr_price',
                2 => 'dl_price',
                3 => 'rc_price',
                4 => 'dl_update_price',
                default => null
            };
            if ($column) {
                $update = $pdo->prepare("UPDATE users SET $column = ? WHERE id = ?");
                $update->execute([$price, $user_id]);
            }
        }

        $pdo->commit();
        $success = 'User and service prices updated successfully!';
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $current_prices = [
            1 => $user['llr_price'],
            2 => $user['dl_price'],
            3 => $user['rc_price'],
            4 => $user['dl_update_price']
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error updating user: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Edit User</title>
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
            max-width: 1000px;
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
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 8px;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
        }
        
        .btn-outline-secondary {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
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
        
        .price-card {
            border-left: 4px solid;
            margin-bottom: 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .price-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-ll {
            border-color: #1cc88a;
        }
        
        .card-dl {
            border-color: #36b9cc;
        }
        
        .card-rc {
            border-color: #f6c23e;
        }
        
        .card-du {
            border-color: #4361ee;
        }
        
        .price-input {
            max-width: 120px;
        }
        
        .help-text {
            font-size: 0.8rem;
            color: var(--text-light);
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: var(--shadow);
        }
        
        .alert-danger {
            background: rgba(230, 57, 70, 0.15);
            color: #e63946;
        }
        
        .alert-success {
            background: rgba(76, 201, 240, 0.15);
            color: #4cc9f0;
        }
        
        .user-info-header {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 0 0.5rem;
            }
            
            .card-header {
                padding: 0.75rem 1rem;
            }
            
            .btn-primary, .btn-outline-secondary {
                padding: 0.6rem 1rem;
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
    <h1 class="page-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h1>
    
    <div class="user-info-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h4 class="mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                <p class="mb-1 text-muted">ID: <?= $user['id'] ?> | Mobile: <?= htmlspecialchars($user['mobile']) ?></p>
                <p class="mb-0">Joined: <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
            </div>
            <span class="status-badge <?= $user['is_blocked'] ? 'badge-blocked' : 'badge-active' ?> mt-2 mt-md-0">
                <i class="bi <?= $user['is_blocked'] ? 'bi-x-circle' : 'bi-check-circle' ?> me-1"></i>
                <?= $user['is_blocked'] ? 'Blocked' : 'Active' ?>
            </span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
            <div><?= htmlspecialchars($error) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($success): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-4"></i>
            <div><?= $success ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-wallet2 me-2"></i> Wallet & Status
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="wallet_balance" class="form-label">Wallet Balance</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₹</span>
                            <input type="number" step="0.01" class="form-control" id="wallet_balance" 
                                   name="wallet_balance" value="<?= $user['wallet_balance'] ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex align-items-center mb-3">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="is_blocked" name="is_blocked" 
                                   <?= $user['is_blocked'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-medium" for="is_blocked">Block User</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-tags me-2"></i> Service Prices
            </div>
            <div class="card-body">
                <p class="help-text mb-3">Set custom prices for this user. These will override default prices.</p>
                
                <?php foreach ($services as $service): 
                    $code = strtolower(substr($service['name'], 0, 2)); ?>
                <div class="price-card card card-<?= $code ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-2 mb-md-0">
                                <label class="form-label fw-medium">
                                    <i class="bi bi-<?= $service['icon'] ?> me-1 text-<?= $service['color'] ?>"></i> <?= $service['name'] ?>
                                </label>
                                <div class="d-flex align-items-center">
                                    <span class="input-group-text bg-light">₹</span>
                                    <input type="text" class="form-control ms-2" 
                                           value="<?= number_format($current_prices[$service['id']], 2) ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2 mb-md-0">
                                <label for="price_<?= $service['id'] ?>" class="form-label">New Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₹</span>
                                    <input type="number" step="0.01" class="form-control price-input" 
                                           id="price_<?= $service['id'] ?>" 
                                           name="service_price[<?= $service['id'] ?>]" 
                                           value="<?= $current_prices[$service['id']] ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-<?= $service['color'] ?> w-100 btn-reset" 
                                        data-service="<?= $service['id'] ?>"
                                        data-default="<?= match($service['id']) {
                                            1 => 100.00, 2 => 150.00, 3 => 200.00, 4 => 75.00,
                                        } ?>">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </button>
                            </div>
                        </div>
                        <div class="help-text mt-2">
                            <?php if ($service['id'] == 1): ?>
                                LLR Exam - Learner's License Service
                            <?php elseif ($service['id'] == 2): ?>
                                DL PDF - Driving License Download
                            <?php elseif ($service['id'] == 3): ?>
                                RC PDF - Vehicle Registration Certificate
                            <?php else: ?>
                                DL Update - Driving License Update Service
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="admin_users.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Users
            </a>
            <div>
                <button type="reset" class="btn btn-warning me-2">
                    <i class="bi bi-eraser me-1"></i> Reset
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-reset').forEach(btn => {
    btn.addEventListener('click', function () {
        const serviceId = this.dataset.service;
        const defaultPrice = this.dataset.default;
        const input = document.querySelector(`input[name="service_price[${serviceId}]"]`);
        input.value = defaultPrice;
        this.innerHTML = '<i class="bi bi-check me-1"></i> Reset!';
        setTimeout(() => {
            this.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i> Reset';
        }, 1200);
    });
});

document.querySelectorAll('.price-input').forEach(input => {
    const initial = input.value;
    input.addEventListener('input', function () {
        if (this.value !== initial) {
            this.classList.add('border-success', 'border-2');
        } else {
            this.classList.remove('border-success', 'border-2');
        }
    });
});
</script>
</body>
</html>