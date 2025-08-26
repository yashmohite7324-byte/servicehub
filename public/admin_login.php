<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Initialize variables
$error = '';
$username = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Prepare SQL statement (using PDO prepared statements for security)
            $stmt = $pdo->prepare('SELECT id, username, password FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify credentials
            if ($admin) {
                // For demonstration - in production, use password_verify() with hashed passwords
                if ($password === $admin['password']) {
                    $_SESSION['admin'] = [
                        'id' => $admin['id'],
                        'username' => $admin['username']
                    ];
                    
                    // Redirect to admin dashboard
                    header('Location: ./admin/admin_dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid credentials.';
                }
            } else {
                $error = 'Invalid credentials.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PHP Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), #224abe);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 2px solid #e3e6f0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.2);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), #224abe);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(78, 115, 223, 0.4);
            color: white;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 0.75rem 1rem;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #b7b9cc;
            z-index: 5;
        }
        
        /* Loading animation */
        .btn-loading {
            position: relative;
            color: transparent !important;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-header {
                padding: 1.5rem;
            }
            
            .login-body {
                padding: 1.5rem;
            }
            
            .login-header i {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 400px) {
            body {
                padding: 10px;
            }
            
            .login-header {
                padding: 1.25rem;
            }
            
            .login-body {
                padding: 1.25rem;
            }
            
            .login-header h3 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-lock"></i>
                <h3>Admin Login</h3>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" id="loginForm">
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required 
                               value="<?= htmlspecialchars($username) ?>" 
                               placeholder="Enter your username">
                    </div>
                    
                    <div class="mb-4 password-toggle">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required 
                               placeholder="Enter your password">
                        <span class="password-toggle-icon" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <button type="submit" class="btn btn-login w-100" id="loginBtn">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
                
                <!-- Demo credentials note (remove in production) -->
                <div class="mt-4 p-3 bg-light rounded text-center">
                    <small class="text-muted">
                        <strong>Demo:</strong> username: <code>123</code> / password: <code>123</code>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Focus on username field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            // Basic validation
            if (!username || !password) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            loginBtn.classList.add('btn-loading');
            loginBtn.disabled = true;
            
            // For demo purposes - simulate network delay
            setTimeout(function() {
                loginBtn.classList.remove('btn-loading');
                loginBtn.disabled = false;
            }, 1500);
        });
    </script>
</body>
</html>