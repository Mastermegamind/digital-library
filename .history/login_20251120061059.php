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

        // First: Try login by email (works for ALL roles)
        $stmt = $pdo->prepare("
            SELECT u.*, up.reg_no, up.staff_id 
            FROM users u 
            LEFT JOIN user_profiles up ON u.id = up.user_id 
            WHERE u.email = ? LIMIT 1
        ");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not found by email, try role-specific ID
        if (!$user) {
            $sql = "
                SELECT u.*, up.reg_no, up.staff_id 
                FROM users u 
                JOIN user_profiles up ON u.id = up.user_id 
                WHERE (u.role = 'student' AND up.reg_no = ?)
                   OR (u.role = 'staff' AND up.staff_id = ?)
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user['id']);

            $loginMethod = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 
                          ($user['role'] === 'student' ? 'Reg No' : 'Staff ID');

            log_info('User logged in', [
                'user_id' => $user['id'],
                'name'    => $user['name'],
                'role'    => $user['role'],
                'method'  => $loginMethod
            ]);

            flash_message('success', 'Welcome back, ' . h($user['name']) . '!');
            header('Location: index.php');
            exit;
        } else {
            log_warning('Invalid login attempt', ['identifier' => $identifier]);
            $errors[] = 'Invalid login credentials. Please check and try again.';
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
        /* Keep your beautiful original styles */
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-container { max-width: 500px; width: 100%; }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem 2rem;
            text-align: center;
        }
        .logo-circle {
            width: 100px; height: 100px; background: white; border-radius: 50%;
            margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .logo-circle i { font-size: 3rem; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .login-title { color: white; font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
        .login-subtitle { color: rgba(255,255,255,0.9); font-size: 1rem; }

        .login-body { padding: 2.5rem; }
        .form-label { font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .input-group { position: relative; margin-bottom: 1.5rem; }
        .input-icon {
            position: absolute; left: 0; top: 0; height: 100%; width: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2); color: white;
            display: flex; align-items: center; justify-content: center;
            border-radius: 12px 0 0 12px; z-index: 5;
        }
        .form-control {
            padding-left: 65px; border: 2px solid #e2e8f0; border-radius: 12px;
            padding: 0.875rem 1.25rem; font-size: 1rem; transition: all 0.3s ease;
        }
        .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 4px rgba(102,126,234,0.1); }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none; color: white; padding: 1rem; font-size: 1.125rem; font-weight: 700;
            border-radius: 12px; width: 100%; box-shadow: 0 10px 25px rgba(102,126,234,0.3);
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 15px 35px rgba(102,126,234,0.4); }

        .demo-credentials {
            background: #f8fafc; border-radius: 12px; padding: 1.25rem; border: 2px solid #e2e8f0; margin-top: 1.5rem;
        }
        .credential-item {
            background: white; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 0.5rem;
            display: flex; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .credential-label { color: #64748b; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .credential-value { color: #667eea; font-weight: 700; font-family: 'Courier New', monospace; }

        .alert-danger { background: rgba(239,68,68,0.1); border-left: 4px solid #ef4444; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
<div class="login-header">
    <div class="logo-circle">
        <img src="<?= app_path('assets/images/main.png') ?>" 
             alt="Logo" 
             style="width:200px; height:200px; object-fit:contain; border-radius:50%;"
             onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-book-reader\' style=\'font-size:5rem; background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;\'></i>';">
    </div>
    <h1 class="login-title"><?= h($APP_NAME) ?></h1>
    <p class="login-subtitle">Digital Library System</p>
</div>

        <div class="login-body">
            <?php if (!empty($errors)): ?>
                <div class="alert-danger">
                    <strong>Oops!</strong> <?= implode('<br>', array_map('h', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                <div>
                    <label class="form-label">
                        <i class="fas fa-id-badge me-2"></i>Login ID / Email
                    </label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" name="identifier" class="form-control" required autofocus
                               placeholder="Reg No • Staff ID • Email" value="<?= h($_POST['identifier'] ?? '') ?>">
                    </div>
                    <small class="text-muted d-block mt-1">
                        • Students: Use your <strong>Registration Number</strong><br>
                        • Staff: Use your <strong>Staff ID</strong><br>
                        • Everyone can also use <strong>Email</strong>
                    </small>
                </div>

                <div>
                    <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" name="password" class="form-control" required placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <!-- <div class="demo-credentials mt-4">
                <strong><i class="fas fa-user-graduate me-2"></i>Demo Logins</strong>
                <div class="credential-item">
                    <span class="credential-label">Admin</span>
                    <span class="credential-value">ishikotevu@gmail.com</span>
                </div>
                <div class="credential-item">
                    <span class="credential-label">Student Reg No</span>
                    <span class="credential-value">CSE/2023/001</span>
                </div>
                <div class="credential-item">
                    <span class="credential-label">Staff ID</span>
                    <span class="credential-value">LIB/001</span>
                </div>
                <div class="credential-item">
                    <span class="credential-label">Password (all)</span>
                    <span class="credential-value">12345678</span>
                </div>
            </div> -->
        </div>

        <div class="text-center py-3 text-muted small">
            Secure Login • © <?= date('Y') ?> <?= h($APP_NAME) ?>
        </div>
    </div>
</div>

<script src="<?= app_path('assets/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>