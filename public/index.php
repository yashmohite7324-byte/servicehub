<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - PHP Service Project</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; margin: 0; padding: 0; }
        .container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 32px; }
        h2 { color: #007bff; text-align: center; }
        .tabs { display: flex; margin-bottom: 24px; }
        .tab { flex: 1; padding: 12px; text-align: center; cursor: pointer; background: #f1f1f1; border-radius: 8px 8px 0 0; font-weight: bold; }
        .tab.active { background: #007bff; color: #fff; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #007bff; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:disabled { background: #aaa; }
        .error { color: #d9534f; margin-bottom: 12px; text-align: center; }
        .success { color: #28a745; margin-bottom: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <div class="tabs">
            <div class="tab active" id="userTab" onclick="showTab('user')">User Login</div>
            <div class="tab" id="adminTab" onclick="showTab('admin')">Admin Login</div>
        </div>
        <div id="userForm">
            <form onsubmit="return loginUser(event)">
                <div class="form-group">
                    <label for="userMobile">Mobile</label>
                    <input type="text" id="userMobile" maxlength="10" required />
                </div>
                <div class="form-group">
                    <label for="userPassword">Password</label>
                    <input type="password" id="userPassword" required />
                </div>
                <div class="error" id="userError"></div>
                <div class="success" id="userSuccess"></div>
                <button type="submit">Login as User</button>
            </form>
        </div>
        <div id="adminForm" style="display:none;">
            <form onsubmit="return loginAdmin(event)">
                <div class="form-group">
                    <label for="adminUsername">Username</label>
                    <input type="text" id="adminUsername" required />
                </div>
                <div class="form-group">
                    <label for="adminPassword">Password</label>
                    <input type="password" id="adminPassword" required />
                </div>
                <div class="error" id="adminError"></div>
                <div class="success" id="adminSuccess"></div>
                <button type="submit">Login as Admin</button>
            </form>
        </div>
    </div>
    <script src="index.js"></script>
</body>
</html> 