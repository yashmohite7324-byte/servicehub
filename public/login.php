<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = $_POST['mobile'] ?? '';
    $password = $_POST['password'] ?? '';
    if (!$mobile || !$password) {
        $error = 'Please enter both mobile and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, mobile, wallet_balance, is_blocked FROM users WHERE mobile = ? AND password = ? LIMIT 1');
        $stmt->execute([$mobile, $password]);
        $user = $stmt->fetch();
        if ($user && !$user['is_blocked']) {
            $_SESSION['user'] = $user;
            header('Location: dashboard.php');
            exit;
        } elseif ($user && $user['is_blocked']) {
            $error = 'Your account has been blocked.';
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - PHP Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="card-title mb-4 text-center">Login</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="mobile" class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" maxlength="15" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="register.php">New user? Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html> 