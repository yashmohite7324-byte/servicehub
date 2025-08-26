<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin_login();
require_once __DIR__ . '/../../includes/db.php';
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
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE mobile = ?');
        $stmt->execute([$mobile]);
        if ($stmt->fetch()) {
            $error = 'Mobile number already registered.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (name, mobile, password, wallet_balance) VALUES (?, ?, ?, 0.0)');
            if ($stmt->execute([$name, $mobile, $password])) {
                $success = 'User added successfully!';
                // Clear form fields
                $name = $mobile = $password = '';
            } else {
                $error = 'An error occurred while adding the user.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: 600;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.08);
        }
        .card-header {
            background: linear-gradient(120deg, #4e73df, #224abe);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.5rem;
        }
        .form-control {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.3rem rgba(78, 115, 223, 0.15);
        }
        .btn-primary {
            background: linear-gradient(120deg, #4e73df, #224abe);
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: linear-gradient(120deg, #224abe, #4e73df);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .password-status {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            margin-top: 5px;
            display: inline-block;
        }
        .alert {
            border-radius: 8px;
            padding: 0.8rem 1.25rem;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #4a4a4a;
        }
        .password-field {
            position: relative;
        }
        .password-field input {
            padding-right: 2.5rem;
        }
        .password-visible-icon {
            position: absolute;
            right: 12px;
            top: 38px;
            color: #6c757d;
            cursor: default;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="admin_dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>Admin Panel
        </a>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link" href="admin_users.php">
                    <i class="fas fa-users me-1"></i> Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="m-0"><i class="fas fa-user-plus me-2"></i>Add New User</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="mt-3" autocomplete="off" id="userForm">
                        <div class="mb-4">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= isset($name) ? htmlspecialchars($name) : '' ?>" 
                                   required placeholder="Enter user's full name">
                        </div>
                        
                        <div class="mb-4">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" 
                                   value="<?= isset($mobile) ? htmlspecialchars($mobile) : '' ?>" 
                                   maxlength="10" required placeholder="10-digit mobile number">
                            <div class="form-text">Must be exactly 10 digits</div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="password-field">
                                <input type="text" class="form-control" id="password" name="password" 
                                       value="<?= isset($password) ? htmlspecialchars($password) : '' ?>" 
                                       required minlength="6" placeholder="Enter password (min 6 characters)">
                                <span class="password-visible-icon">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div id="passwordStatus" class="password-status mt-2"></div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="admin_users.php" class="btn btn-secondary me-md-2 px-4">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-user-plus me-1"></i> Add User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const passwordStatus = document.getElementById('passwordStatus');
        const form = document.getElementById('userForm');
        
        // Update password status in real-time
        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            
            if (password.length === 0) {
                passwordStatus.textContent = '';
                passwordStatus.className = 'password-status';
            } else if (password.length < 6) {
                passwordStatus.textContent = 'Password is too short (min 6 characters)';
                passwordStatus.className = 'password-status text-danger bg-danger-light';
                passwordStatus.style.backgroundColor = '#f8d7da';
            } else {
                passwordStatus.textContent = 'Password meets minimum length requirement';
                passwordStatus.className = 'password-status text-success bg-success-light';
                passwordStatus.style.backgroundColor = '#d4edda';
            }
        });
        
        // Form validation
        form.addEventListener('submit', function(event) {
            const mobileInput = document.getElementById('mobile');
            const passwordInput = document.getElementById('password');
            
            // Validate mobile number
            if (!/^\d{10}$/.test(mobileInput.value)) {
                event.preventDefault();
                mobileInput.focus();
                alert('Mobile number must be exactly 10 digits.');
                return false;
            }
            
            // Validate password length
            if (passwordInput.value.length < 6) {
                event.preventDefault();
                passwordInput.focus();
                alert('Password must be at least 6 characters long.');
                return false;
            }
        });
        
        // Auto-format mobile number (digits only)
        document.getElementById('mobile').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
    });
</script>
</body>
</html>