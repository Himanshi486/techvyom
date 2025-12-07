<?php
session_start();
include('connect.php');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username']);
    $pass = trim($_POST['password']);

    if ($user == '' || $pass == '') {
        $message = "Please enter username and password.";
    } else {
        $sql = "SELECT * FROM admin_users WHERE username='$user' AND password='$pass' LIMIT 1";
        $result = $conn->query($sql);

        if (!$result) {
            die("❌ Query error: " . $conn->error);
        }

        if ($result->num_rows > 0) {
            $_SESSION['admin_id'] = $user;
            header("Location: dashboard.php");
            exit();
        } else {
            $message = "❌ Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | TechVyom</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0515 0%, #2d1250 25%, #4a1a75 50%, #2d1250 75%, #0a0515 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
        }

        .login-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 14px;
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 16px;
        }

        .form-input {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: #9333ea;
            box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.1);
        }

        .form-input::placeholder {
            color: #9ca3af;
        }

        .login-button {
            width: 100%;
            background: linear-gradient(135deg, #9333ea, #7c3aed);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-button:hover {
            background: linear-gradient(135deg, #7c3aed, #6b21a8);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(147, 51, 234, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #dc2626;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: #9333ea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: #7c3aed;
            gap: 8px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 40px 30px;
            }

            .login-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="login-title">Admin Login</h1>
            <p class="login-subtitle">TechVyom Control Panel</p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" 
                           name="username" 
                           class="form-input" 
                           placeholder="Enter your username" 
                           required 
                           autofocus />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Enter your password" 
                           required />
                </div>
            </div>

            <button type="submit" name="login" class="login-button">
                <i class="fas fa-sign-in-alt"></i>
                Sign In
            </button>

            <?php if ($message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
        </form>

        <div class="back-link">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i>
                Back to Website
            </a>
        </div>
    </div>
</body>
</html>
