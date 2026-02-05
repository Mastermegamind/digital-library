<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrf       = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session. Please try again.';
        log_warning('Login CSRF failed');
    } elseif ($identifier === '' || $password === '') {
        $errors[] = 'Please enter your login ID or email and password.';
    } else {
        global $pdo;

        // Try email first
        $stmt = $pdo->prepare("
            SELECT u.*, up.reg_no, up.staff_id 
            FROM users u 
            LEFT JOIN user_profiles up ON u.id = up.user_id 
            WHERE u.email = ? LIMIT 1
        ");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not email, try Reg No or Staff ID
        if (!$user) {
            $stmt = $pdo->prepare("
                SELECT u.*, up.reg_no, up.staff_id 
                FROM users u 
                JOIN user_profiles up ON u.id = up.user_id 
                WHERE (u.role = 'student' AND up.reg_no = ?)
                   OR (u.role = 'staff' AND up.staff_id = ?)
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user['id']);
if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    $method = 'Email';
} else {
    // Non-email identifiers
    if ($user['role'] === 'student') {
        $method = 'Reg No';
    } elseif ($user['role'] === 'staff') {
        $method = 'Staff ID';
    } elseif ($user['role'] === 'admin') {
        $method = 'Admin ID / Username';
    } else {
        $method = 'Login ID';
    }
}


            log_info('User logged in', [
                'user_id' => $user['id'],
                'name'    => $user['name'],
                'role'    => $user['role'],
                'method'  => $method
            ]);

            flash_message('success', 'Welcome back, ' . h($user['name']) . '!');
            header('Location: index.php');
            exit;
        } else {
            log_warning('Invalid login attempt', ['identifier' => $identifier]);
            $errors[] = 'Invalid login credentials. Please try again.';
        }
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
    <link href="<?= app_path('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= app_path('assets/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= app_path('assets/css/inter.css') ?>">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --tertiary-color: #0756a5ff;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--tertiary-color), var(--tertiary-color));
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-container { max-width: 480px; width: 100%; }

        .login-card {
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            padding: 3.5rem 2rem 2.5rem;
            text-align: center;
        }

        .logo-circle {
            width: 150px;
            height: 150px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .logo-circle img {
            width: 150px;
            height: 150px;
            object-fit: contain;
        }

        .login-title {
            color: white;
            font-size: 2.125rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .login-body {
            padding: 2.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-icon {
            position: absolute;
            left: 0; top: 0; height: 100%; width: 56px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px 0 0 16px;
            font-size: 1.25rem;
        }

        .form-control {
            padding-left: 70px;
            height: 58px;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.15);
            outline: none;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            height: 58px;
            font-size: 1.125rem;
            font-weight: 700;
            border-radius: 16px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(37, 99, 235, 0.4);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left: 5px solid var(--danger-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            color: #dc2626;
        }

        .login-footer {
            text-align: center;
            padding: 1.5rem;
            background: var(--light-bg);
            color: var(--text-secondary);
            font-size: 0.875rem;
            border-top: 1px solid var(--border-color);
        }

        .hint-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-circle">
                <img src="<?= app_path('assets/images/main.png') ?>" 
                     alt="Logo"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-book-reader\'></i>';">
            </div>
            <h1 class="login-title"><?= h($APP_NAME) ?></h1>
            <p class="login-subtitle">Digital Library Management System</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if (!empty($errors)): ?>
                <div class="alert-danger">
                    <strong><i class="fas fa-exclamation-triangle me-2"></i>Oops!</strong><br>
                    <?= implode('<br>', array_map('h', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                <div>
                    <label class="form-label">
                        <i class="fas fa-id-badge me-2"></i>Login ID / Email
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" name="identifier" class="form-control" required autofocus
                               placeholder="Reg No • Staff ID • Email" 
                               value="<?= h($_POST['identifier'] ?? '') ?>">
                    </div>
                    <div class="hint-text">
                        • Students: Use your <strong>Registration Number</strong><br>
                        • Staff: Use your <strong>Staff ID</strong><br>
                        • Everyone can also use <strong>Email Address</strong>
                    </div>
                </div>

                <div>
                    <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" name="password" class="form-control" required 
                               placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            <p><i class="fas fa-shield-alt me-2"></i>
                Secure Login • © <?= date('Y') ?> <?= h($APP_NAME) ?>
            </p>
        </div>
    </div>
</div>

<script src="<?= app_path('assets/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>