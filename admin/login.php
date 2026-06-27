<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($_POST['password'], $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];

        // Log login
        $db->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, created_at) VALUES (?, 'login', ?, NOW())")
           ->execute([$admin['id'], $_SERVER['REMOTE_ADDR']]);

        redirect('index.php');
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - WiFi Hotspot Billing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-page::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: float 20s ease-in-out infinite;
        }

        .login-page::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            bottom: -100px;
            left: -100px;
            animation: float 25s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 0 1.5rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        [data-theme="dark"] .login-card {
            background: rgba(30, 41, 59, 0.95);
        }

        .login-logo {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .login-subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.9375rem;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.125rem;
            transition: color var(--transition-fast);
        }

        .input-group input:focus + i,
        .input-group input:focus ~ i {
            color: var(--primary);
        }

        .input-group input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.9375rem;
            transition: all var(--transition-fast);
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .theme-toggle-login {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all var(--transition-fast);
        }

        .theme-toggle-login:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(20deg);
        }
    </style>
</head>
<body>
    <div class="login-page">
        <button class="theme-toggle-login" id="themeToggle">
            <i class="bi bi-moon"></i>
        </button>

        <div class="login-container">
            <div class="login-card animate-fade-in">
                <div class="login-logo">
                    <i class="bi bi-wifi"></i>
                </div>
                <h1 class="login-title">Welcome Back</h1>
                <p class="login-subtitle">Sign in to your WiFi Billing dashboard</p>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= e($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <div class="input-group">
                        <input type="text" name="username" placeholder="Username" required autofocus>
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="Password" required>
                        <i class="bi bi-lock"></i>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="bi bi-box-arrow-in-right"></i>
                        Sign In
                    </button>
                </form>

                <div class="login-footer">
                    WiFi Hotspot Billing System v2.0
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-login');
            btn.innerHTML = '<span style="width:20px;height:20px;border:2px solid rgba(255,255,255,0.3);border-top-color:white;border-radius:50%;display:inline-block;animation:spin 0.8s linear infinite;"></span> Signing in...';
            btn.disabled = true;
        });
    </script>
</body>
</html>
