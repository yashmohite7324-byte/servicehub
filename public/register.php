<?php
require_once __DIR__ . '/../includes/db.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$name || !$mobile || !$password) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^\d{10}$/', $mobile)) {
        $error = 'Mobile number must be exactly 10 digits.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile = ?');
        $stmt->execute([$mobile]);
        if ($stmt->fetch()) {
            $error = 'Mobile number already registered.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (name, mobile, password, wallet_balance) VALUES (?, ?, ?, 0.0)');
            $stmt->execute([$name, $mobile, $password]);
            $success = 'Registration successful! You can now <a href="login.php">login</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - PHP Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="card-title mb-4 text-center">Register</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php elseif ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="mobile" class="form-label">Mobile</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" maxlength="10" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Register</button>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="login.php">Already have an account? Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html> 