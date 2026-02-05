<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('login');

if (is_logged_in()) {
    header('Location: ' . app_path(''));
    exit;
}

$errors = [];
$needsVerification = false;
$pendingEmail = '';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$registrationEnabled = is_registration_enabled();

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
            $status = $user['status'] ?? 'active';
            if ($status !== 'active') {
                $errors[] = $status === 'pending'
                    ? 'Your account is pending admin approval.'
                    : 'Your account is not active. Please contact support.';
            } elseif (is_email_verification_required() && empty($user['email_verified_at'])) {
                $errors[] = 'Please verify your email before logging in.';
                $needsVerification = true;
                $pendingEmail = $user['email'] ?? '';
            } else {
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
            }
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
    <link rel="stylesheet" href="<?= app_path('assets/css/components.css') ?>">
</head>
<body class="login-page">

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

            <div class="text-center mt-3">
                <?php if ($registrationEnabled): ?>
                    <a href="<?= h(app_path('register')) ?>" class="text-decoration-none">
                        Create a new account
                    </a>
                <?php endif; ?>
                <?php if ($needsVerification): ?>
                    <div class="mt-2">
                        <a href="<?= h(app_path('resend-verification')) ?>" class="text-decoration-none">
                            Resend verification email
                        </a>
                    </div>
                <?php endif; ?>
            </div>
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

<script>
// Ensure input shows the start of the value (robust handling for autofill/paste/focus across browsers)
(function(){
    function resetInput(el){
        try{
            // place caret at the start and ensure left scroll is 0
            el.setSelectionRange(0,0);
            el.scrollLeft = 0;
        }catch(e){}
    }

    function attach(el){
        if(!el) return;
        var reset = function(){ setTimeout(function(){ resetInput(el); }, 60); };

        ['input','keyup','change','focus'].forEach(function(evt){ el.addEventListener(evt, reset); });
        el.addEventListener('paste', function(){ setTimeout(reset, 80); });

        // Listen for animationstart which some browsers trigger on autofill
        el.addEventListener('animationstart', function(e){
            // any animation associated with autofill will trigger this
            setTimeout(reset, 60);
        });

        // Initial attempts on load and multiple retries for the first 2 seconds
        resetInput(el);
        var tries = 0;
        var id = setInterval(function(){ resetInput(el); tries++; if(tries>20){ clearInterval(id); } }, 100);

        window.addEventListener('load', function(){ setTimeout(reset, 80); });
        window.addEventListener('resize', function(){ setTimeout(reset, 80); });
    }

    document.addEventListener('DOMContentLoaded', function(){
        attach(document.querySelector('input[name="identifier"]'));
        attach(document.querySelector('input[name="password"]'));
    });
})();
</script>

</body>
</html>
