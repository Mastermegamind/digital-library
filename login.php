<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . app_path(''));
    exit;
}

$errors = [];
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrf       = $_POST['csrf_token'] ?? '';
    $rateKeyIp = 'login:ip:' . $clientIp;
    $rateKeyId = $identifier !== '' ? 'login:id:' . $clientIp . ':' . strtolower($identifier) : null;
    $rateBlockedIp = rate_limit_is_blocked($rateKeyIp);
    $rateBlockedId = $rateKeyId ? rate_limit_is_blocked($rateKeyId) : ['blocked' => false, 'retry_after' => 0];

    if ($rateBlockedIp['blocked'] || $rateBlockedId['blocked']) {
        $retryAfter = max($rateBlockedIp['retry_after'], $rateBlockedId['retry_after']);
        $minutes = max(1, (int)ceil($retryAfter / 60));
        $errors[] = 'Too many login attempts. Please try again in ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') . '.';
        log_warning('Login rate limit triggered', ['ip' => $clientIp, 'identifier' => $identifier]);
    } elseif (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session. Please try again.';
        log_warning('Login CSRF failed');
    } elseif ($identifier === '' || $password === '') {
        $errors[] = 'Please enter your login ID or email and password.';
    } else {
        global $pdo, $LOGIN_RATE_LIMIT, $LOGIN_RATE_WINDOW, $LOGIN_LOCK_SECONDS;

        // Try email first
        $stmt = $pdo->prepare("
            SELECT u.*, up.reg_no, up.staff_id 
            FROM users u 
            LEFT JOIN user_profiles up ON u.id = up.user_id 
            WHERE u.email = ? LIMIT 1
        ");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If not email, try Reg No / Staff ID / Admin Name
        if (!$user) {
            $stmt = $pdo->prepare("
                SELECT u.*, up.reg_no, up.staff_id 
                FROM users u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE 
                    (u.role = 'student' AND up.reg_no = ?)
                 OR (u.role = 'staff'   AND up.staff_id = ?)
                 OR (u.role = 'admin'   AND up.staff_id = ?)
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier, $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user['id']);
            rate_limit_reset($rateKeyIp);
            if ($rateKeyId) {
                rate_limit_reset($rateKeyId);
            }

            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                $method = 'Email';
            } else {
                $method = $user['role'] === 'student' ? 'Reg No' :
                         ($user['role'] === 'staff' ? 'Staff ID' : 'Admin ID / Username');
            }

            log_info('User logged in', [
                'user_id' => $user['id'],
                'name'    => $user['name'],
                'role'    => $user['role'],
                'method'  => $method
            ]);

            flash_message('success', 'Welcome back, ' . h($user['name']) . '!');
            header('Location: ' . app_path(''));
            exit;
        } else {
            log_warning('Invalid login attempt', ['identifier' => $identifier]);
            $rateLimit = (int)($LOGIN_RATE_LIMIT ?? 8);
            $rateWindow = (int)($LOGIN_RATE_WINDOW ?? 900);
            $rateLock = (int)($LOGIN_LOCK_SECONDS ?? 900);
            $rateResultIp = rate_limit_register_failure($rateKeyIp, $rateLimit, $rateWindow, $rateLock);
            if ($rateKeyId) {
                rate_limit_register_failure($rateKeyId, $rateLimit, $rateWindow, $rateLock);
            }
            if ($rateResultIp['blocked']) {
                $minutes = max(1, (int)ceil($rateResultIp['retry_after'] / 60));
                $errors[] = 'Too many login attempts. Please try again in ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') . '.';
            } else {
                $errors[] = 'Invalid login credentials. Please try again.';
            }
        }
    }
}

$csrf = get_csrf_token();

$headerScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$headerHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$headerCurrentUri = $_SERVER['REQUEST_URI'] ?? app_path('');

$toAbsoluteUrl = static function (?string $value) use ($headerScheme, $headerHost): string {
    $value = trim((string)$value);
    if ($value === '') {
        return $headerScheme . '://' . $headerHost . '/';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    if ($value[0] !== '/') {
        $value = '/' . $value;
    }
    return $headerScheme . '://' . $headerHost . $value;
};

$headerMetaTitle = trim((string)($meta_title ?? ('Login - ' . $APP_NAME)));
if ($headerMetaTitle === '') {
    $headerMetaTitle = 'Login - ' . $APP_NAME;
}

$headerMetaSiteName = trim((string)($meta_site_name ?? ($FULL_APP_NAME ?? $APP_NAME)));
if ($headerMetaSiteName === '') {
    $headerMetaSiteName = $APP_NAME;
}

$headerMetaDescription = trim((string)($meta_description ?? ('Sign in to ' . $headerMetaSiteName . ' digital library.')));
if ($headerMetaDescription === '') {
    $headerMetaDescription = 'Sign in to ' . $headerMetaSiteName . ' digital library.';
}

$headerMetaType = trim((string)($meta_type ?? 'website'));
if ($headerMetaType === '') {
    $headerMetaType = 'website';
}

$headerMetaUrl = trim((string)($meta_url ?? ($headerScheme . '://' . $headerHost . $headerCurrentUri)));
if ($headerMetaUrl === '') {
    $headerMetaUrl = $headerScheme . '://' . $headerHost . '/';
}
$headerMetaUrl = $toAbsoluteUrl($headerMetaUrl);

$headerMetaImage = trim((string)($meta_image ?? app_path('assets/images/seo.png')));
if ($headerMetaImage === '') {
    $headerMetaImage = app_path('assets/images/seo.png');
}
$headerMetaImage = $toAbsoluteUrl($headerMetaImage);

$headerFavicon = trim((string)($favicon_path ?? app_path('assets/images/favicon.png')));
if ($headerFavicon === '') {
    $headerFavicon = app_path('assets/images/favicon.png');
}
$headerFavicon = $toAbsoluteUrl($headerFavicon);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= h($headerMetaTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($headerMetaDescription) ?>">
    <link rel="canonical" href="<?= h($headerMetaUrl) ?>">

    <meta property="og:type" content="<?= h($headerMetaType) ?>">
    <meta property="og:site_name" content="<?= h($headerMetaSiteName) ?>">
    <meta property="og:title" content="<?= h($headerMetaTitle) ?>">
    <meta property="og:description" content="<?= h($headerMetaDescription) ?>">
    <meta property="og:url" content="<?= h($headerMetaUrl) ?>">
    <meta property="og:image" content="<?= h($headerMetaImage) ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($headerMetaTitle) ?>">
    <meta name="twitter:description" content="<?= h($headerMetaDescription) ?>">
    <meta name="twitter:image" content="<?= h($headerMetaImage) ?>">

    <link rel="icon" type="image/png" href="<?= h($headerFavicon) ?>">
    <link rel="shortcut icon" href="<?= h($headerFavicon) ?>">
    <link rel="apple-touch-icon" href="<?= h($headerFavicon) ?>">
    <link href="<?= app_path('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= app_path('assets/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= app_path('assets/css/inter.css') ?>">
    <style>
        :root {
--primary-color: #009D52;
--secondary-color: #187048;
--accent-color: #00A050;
            --danger-color: #ef4444;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --card-bg: rgba(255, 255, 255, 0.92);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(0.75rem, 2.2vh, 2rem) 1rem;
            overflow-y: auto;

            /* Full background image */
            background: url('<?= app_path('assets/images/login-bg.jpeg') ?>') center/cover no-repeat fixed;
        }

        /* Faded blue overlay - keeps your original blue vibe */
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, 
                rgba(4, 122, 44, 0.78),   /* your primary blue */
                rgba(5, 87, 32, 0.68)); /* slightly lighter */
            pointer-events: none;
            z-index: 1;
        }

        .login-container { 
            max-width: 480px;
            width: 100%; 
            position: relative;
            z-index: 2;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
            overflow: hidden auto;
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-height: calc(100vh - 1.5rem);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            padding: clamp(1.5rem, 4.5vh, 3.5rem) clamp(1rem, 2.5vw, 2rem) clamp(1.25rem, 3vh, 2.5rem);
            text-align: center;
        }

        .logo-circle {
            width: clamp(90px, 17vh, 150px);
            height: clamp(90px, 17vh, 150px);
            background: white;
            border-radius: 50%;
            margin: 0 auto clamp(0.75rem, 2vh, 1.5rem);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .logo-circle img {
            width: clamp(90px, 17vh, 150px);
            height: clamp(90px, 17vh, 150px);
            object-fit: contain;
        }

        .login-title {
            color: white;
            font-size: clamp(1.35rem, 3.4vh, 2.125rem);
            font-weight: 800;
            margin-bottom: clamp(0.25rem, 1vh, 0.5rem);
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: clamp(0.88rem, 2vh, 1.1rem);
            font-weight: 500;
        }

        .login-body {
            padding: clamp(1rem, 3vh, 2.5rem) clamp(1rem, 2.2vw, 2.5rem);
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
            margin-bottom: clamp(0.75rem, 1.8vh, 1.5rem);
        }

        .input-icon {
            position: absolute;
            left: 0; top: 0; height: 100%; width: clamp(44px, 6vh, 56px);
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px 0 0 16px;
            font-size: 1.25rem;
        }

        .form-control {
            padding-left: clamp(56px, 8vh, 70px);
            height: clamp(46px, 6.5vh, 58px);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            font-size: clamp(0.95rem, 1.75vh, 1rem);
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.7);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.2);
            background: white;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            height: clamp(46px, 6.5vh, 58px);
            font-size: clamp(1rem, 1.95vh, 1.125rem);
            font-weight: 700;
            border-radius: 16px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(37, 99, 235, 0.45);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border-left: 5px solid var(--danger-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            color: #dc2626;
        }

        .login-footer {
            text-align: center;
            padding: clamp(0.75rem, 1.8vh, 1.5rem);
            background: rgba(248, 250, 252, 0.9);
            color: var(--text-secondary);
            font-size: clamp(0.78rem, 1.5vh, 0.875rem);
            border-top: 1px solid var(--border-color);
        }

        .hint-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-top: 1rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .login-container {
                max-width: 440px;
            }
        }

        @media (max-height: 820px) {
            .login-header {
                padding-top: 1rem;
                padding-bottom: 0.85rem;
            }

            .logo-circle,
            .logo-circle img {
                width: 84px;
                height: 84px;
            }

            .login-title {
                font-size: 1.35rem;
            }

            .login-subtitle {
                font-size: 0.86rem;
            }

            .login-body {
                padding-top: 0.9rem;
                padding-bottom: 0.9rem;
            }

            .form-label {
                font-size: 0.8rem;
                margin-bottom: 0.35rem;
            }

            .hint-text {
                line-height: 1.45;
                margin-top: 0.65rem;
            }

            .btn-login.mt-4 {
                margin-top: 0.75rem !important;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }

            .login-header {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .login-body {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Background overlay is handled by ::before -->

<div class="login-container">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-circle">
                <img src="<?= app_path('assets/images/main.png') ?>" 
                     alt="Logo"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-book-reader fa-4x\'></i>';">
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
                </div>

                <div>
                    <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" name="password" class="form-control" required 
                               placeholder="Enter your password">
                    </div>

                    <div class="hint-text">
                        • Students: Use your <strong>Registration Number</strong><br>
                        • Staff: Use your <strong>Staff ID</strong><br>
                        • Everyone can also use <strong>Email Address</strong>
                    </div>
                </div>

                <button type="submit" class="btn-login mt-4">
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
