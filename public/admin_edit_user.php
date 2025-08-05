<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../includes/db.php';

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
    ['id' => 3, 'name' => 'RC PDF', 'icon' => 'file-earmark-break', 'color' => 'warning']
];

$current_prices = [
    1 => $user['llr_price'] ?? 100.00,
    2 => $user['dl_price'] ?? 150.00,
    3 => $user['rc_price'] ?? 200.00
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
            3 => $user['rc_price']
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
    <title>Edit User Prices - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            font-size: 0.9rem;
        }
        .price-card {
            border-left: 4px solid;
            margin-bottom: 0.75rem;
            padding: 0.75rem;
        }
        .price-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.075);
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
        .price-input {
            max-width: 120px;
            height: 32px;
            font-size: 0.85rem;
        }
        .help-text {
            font-size: 0.75rem;
            color: #6c757d;
        }
        .form-control, .form-select, .form-check-input {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            height: 32px;
        }
        .input-group-text {
            padding: 0.25rem 0.5rem;
        }
        .btn-xs {
            padding: 0.25rem 0.4rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">Admin Panel</a>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="admin_users.php">Users</a></li>
            <li class="nav-item"><a class="nav-link" href="admin_logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="container mt-3" style="max-width: 850px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>
            <i class="bi bi-person-gear"></i> Edit Prices: <?= htmlspecialchars($user['name']) ?> <small class="text-muted">(ID: <?= $user['id'] ?>)</small>
        </h5>
        <span class="badge bg-<?= $user['is_blocked'] ? 'danger' : 'success' ?>">
            <?= $user['is_blocked'] ? 'Blocked' : 'Active' ?>
        </span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="card mb-3">
            <div class="card-header bg-primary text-white py-2">
                <i class="bi bi-wallet2"></i> Wallet & Status
            </div>
            <div class="card-body row g-2">
                <div class="col-md-6">
                    <label for="wallet_balance" class="form-label">Wallet Balance</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" class="form-control" id="wallet_balance" 
                               name="wallet_balance" value="<?= $user['wallet_balance'] ?>" required>
                    </div>
                </div>
                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="is_blocked" name="is_blocked" 
                               <?= $user['is_blocked'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_blocked">Block User</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-primary text-white py-2">
                <i class="bi bi-tags"></i> Service Prices
            </div>
            <div class="card-body">
                <p class="help-text mb-2">Set custom prices for this user. Overrides default prices.</p>
                
                <?php foreach ($services as $service): 
                    $code = strtolower(substr($service['name'], 0, 2)); ?>
                <div class="price-card card card-<?= $code ?>">
                    <div class="card-body p-2">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="bi bi-<?= $service['icon'] ?>"></i> <?= $service['name'] ?>
                                </label>
                                <input type="text" class="form-control form-control-sm" 
                                       value="<?= number_format($current_prices[$service['id']], 2) ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label for="price_<?= $service['id'] ?>" class="form-label">New Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control price-input" 
                                           id="price_<?= $service['id'] ?>" 
                                           name="service_price[<?= $service['id'] ?>]" 
                                           value="<?= $current_prices[$service['id']] ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-<?= $service['color'] ?> btn-xs btn-reset w-100" 
                                        data-service="<?= $service['id'] ?>"
                                        data-default="<?= match($service['id']) {
                                            1 => 100.00, 2 => 150.00, 3 => 200.00,
                                        } ?>">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                            </div>
                        </div>
                        <div class="help-text mt-1">
                            <?php if ($service['id'] == 1): ?>
                                LLR Exam - Learner's License Service
                            <?php elseif ($service['id'] == 2): ?>
                                DL PDF - Driving License Download
                            <?php else: ?>
                                RC PDF - Vehicle Registration Certificate
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="admin_users.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <div>
                <button type="reset" class="btn btn-warning btn-sm me-2">
                    <i class="bi bi-eraser"></i> Reset
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-save"></i> Save
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
        this.innerHTML = '<i class="bi bi-check"></i> Reset!';
        setTimeout(() => {
            this.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Reset';
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
