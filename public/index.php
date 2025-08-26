<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ServiceHub - User Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --dark: #1b263b;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --gray: #adb5bd;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 0;
        }

        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo h1 {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 600;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 25px;
        }

        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }

        nav ul li a:hover {
            color: var(--primary);
        }

        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        
        .container {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h2 {
            color: var(--dark);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 500;
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        button:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        button:disabled {
            background: var(--gray);
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }
        
        .error {
            color: var(--danger);
            margin: 15px 0;
            text-align: center;
            font-size: 0.9rem;
            padding: 10px;
            background: rgba(247, 37, 133, 0.1);
            border-radius: 6px;
        }
        
        .success {
            color: var(--success);
            margin: 15px 0;
            text-align: center;
            font-size: 0.9rem;
            padding: 10px;
            background: rgba(76, 201, 240, 0.1);
            border-radius: 6px;
        }
        
        .switch-link {
            text-align: center;
            margin-top: 25px;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .switch-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .switch-link a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        
        footer {
            background: white;
            padding: 15px 0;
            text-align: center;
            font-size: 0.8rem;
            color: var(--gray);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }

            nav ul {
                margin-top: 15px;
            }

            nav ul li {
                margin: 0 10px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <a href="index.html" class="logo">
                <h1>ServiceHub</h1>
            </a>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="serviceses.php">Services</a></li>
                    <li><a href="contact.html">Contact Us</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <h2>User Login</h2>
            
            <form onsubmit="return loginUser(event)">
                <div class="form-group">
                    <label for="userMobile">Mobile Number</label>
                    <input type="text" id="userMobile" maxlength="10" placeholder="Enter 10-digit mobile number" required />
                </div>
                
                <div class="form-group">
                    <label for="userPassword">Password</label>
                    <input type="password" id="userPassword" placeholder="Enter your password" required />
                </div>
                
                <div class="error" id="userError"></div>
                <div class="success" id="userSuccess"></div>
                
                <button type="submit">Login</button>
                
            </form>
        </div>
    </div>
    
    <footer>
        &copy; 2023 ServiceHub. All rights reserved.
    </footer>
    
    <script src="index.js"></script>
</body>
</html>