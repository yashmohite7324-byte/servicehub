<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';
$error = '';
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
            header('Location: admin_users.php');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">Admin Panel</a>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item"><a class="nav-link" href="admin_users.php">Users</a></li>
            <li class="nav-item"><a class="nav-link" href="admin_logout.php">Logout</a></li>
        </ul>
    </div>
</nav>
<div class="container mt-4">
    <h2>Add User</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" class="mt-3" autocomplete="off">
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
        <button type="submit" class="btn btn-success">Add User</button>
        <a href="admin_users.php" class="btn btn-secondary ms-2">Cancel</a>
    </form>
</div>
</body>
</html> 