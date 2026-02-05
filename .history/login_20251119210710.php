<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        log_warning('Login CSRF validation failed', ['email' => $email]);
        flash_message('error', 'Invalid session. Please try again.');
        header('Location: login.php');
        exit;
    }

    if ($email === '' || $password === '') {
        flash_message('error', 'Please fill in all fields.');
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user['id']);
        flash_message('success', 'Welcome back, ' . $user['name'] . '!');
        log_info('User logged in', ['user_id' => $user['id']]);
        header('Location: index.php');
        exit;
    } else {
        log_warning('Invalid login attempt', ['email' => $email]);
        flash_message('error', 'Invalid email or password.');
        header('Location: login.php');
        exit;
    }
}

$csrf = get_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login - <?= h($APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -250px;
            left: -250px;
            animation: float 20s infinite ease-in-out;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            bottom: -200px;
            right: -200px;
            animation: float 15s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, 50px); }
        }

        .login-container {
            max-width: 480px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .login-header-content {
            position: relative;
            z-index: 1;
        }

        .logo-circle {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .logo-circle img {
            max-width: 70px;
            max-height: 70px;
        }

        .logo-circle i {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-title {
            color: white;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 500;
        }

        .login-body {
            padding: 2.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            border-radius: 12px 0 0 12px;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-left: none;
            padding: 0.875rem 1.25rem;
            font-size: 1rem;
            border-radius: 0 12px 12px 0;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 1rem;
            font-size: 1.125rem;
            font-weight: 700;
            border-radius: 12px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1px;
            background: #e2e8f0;
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            position: relative;
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .demo-credentials {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 12px;
            padding: 1.25rem;
            border: 2px solid #e2e8f0;
        }

        .demo-credentials strong {
            color: #1e293b;
            display: block;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .credential-item {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .credential-item:last-child {
            margin-bottom: 0;
        }

        .credential-label {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .credential-value {
            color: #667eea;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }

        .login-footer {
            background: #f8fafc;
            padding: 1.5rem;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }

        .login-footer p {
            margin: 0;
            color: #64748b;
            font-size: 0.875rem;
        }

        @media (max-width: 576px) {
            .login-header {
                padding: 2rem 1.5rem;
            }

            .login-body {
                padding: 2rem 1.5rem;
            }

            .logo-circle {
                width: 80px;
                height: 80px;
            }

            .logo-circle i {
                font-size: 2.5rem;
            }

            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div class="login-header-content">
                <div class="logo-circle">
                    <img src="<?= app_path('assets/logo.png') ?>" alt="Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-book-reader\'></i>';">
                </div>
                <h1 class="login-title"><?= h($APP_NAME) ?></h1>
                <p class="login-subtitle">Sign in to access your digital library</p>
            </div>
        </div>

        <!-- Body -->
        <div class="login-body">
            <form method="post" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                <div>
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="your.email@example.com"
                               required 
                               autofocus>
                    </div>
                </div>

                <div>
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Enter your password"
                               required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>

                <div class="divider">
                    <span>Demo Credentials</span>
                </div>

                <div class="demo-credentials">
                    <strong><i class="fas fa-info-circle me-2"></i>Quick Login</strong>
                    <div class="credential-item">
                        <span class="credential-label">Email:</span>
                        <span class="credential-value">ishikotevu@gmail.com</span>
                    </div>
                    <div class="credential-item">
                        <span class="credential-label">Password:</span>
                        <span class="credential-value">12345678</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            <p>
                <i class="fas fa-shield-alt me-2"></i>
                Secure Login &middot; &copy; <?= date('Y') ?> <?= h($APP_NAME) ?>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>