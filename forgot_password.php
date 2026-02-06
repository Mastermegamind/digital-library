<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . app_path(''));
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $csrf  = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session. Please try again.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        // Always show success to prevent email enumeration
        $success = true;

        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = :email AND status = 'active' LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && mailer_is_configured()) {
            $token = issue_password_reset_token($user['id']);
            $resetUrl = app_url('reset-password?token=' . urlencode($token));
            $htmlBody = '<p>Hello ' . h($user['name']) . ',</p>'
                      . '<p>You requested a password reset. Click the link below to set a new password:</p>'
                      . '<p><a href="' . h($resetUrl) . '">' . h($resetUrl) . '</a></p>'
                      . '<p>This link expires in 1 hour. If you did not request this, you can ignore this email.</p>';
            $textBody = "Hello {$user['name']},\n\nReset your password: {$resetUrl}\n\nThis link expires in 1 hour.";

            send_app_mail($user['email'], 'Password Reset Request', $htmlBody, $textBody);
            log_info('Password reset requested', ['user_id' => $user['id']]);
        }
    }
}

$csrf = get_csrf_token();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Forgot Password - <?= h($APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= app_path('assets/images/favicon.png') ?>">
    <link href="<?= app_path('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= app_path('assets/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= app_path('assets/css/inter.css') ?>">
    <link rel="stylesheet" href="<?= app_path('assets/css/components.css') ?>">
</head>
<body class="login-page">

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
            <p class="login-subtitle">Forgot Password</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if ($success): ?>
                <div style="background:rgba(34,197,94,0.15);border-left:5px solid #22c55e;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;color:#16a34a;">
                    <strong><i class="fas fa-check-circle me-2"></i>Check your inbox</strong><br>
                    If an account with that email exists, a password reset link has been sent.
                </div>
                <a href="<?= h(app_path('login')) ?>" class="btn-login" style="display:block;text-align:center;text-decoration:none;">
                    <i class="fas fa-sign-in-alt me-2"></i>Back to Login
                </a>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert-danger">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Oops!</strong><br>
                        <?= implode('<br>', array_map('h', $errors)) ?>
                    </div>
                <?php endif; ?>

                <p style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6;margin-bottom:1.25rem;">
                    Enter your email address and we'll send you a link to reset your password.
                </p>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                    <div>
                        <label class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email Address
                        </label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" required autofocus
                                   placeholder="Enter your email address"
                                   value="<?= h($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-login mt-3">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="<?= h(app_path('login')) ?>" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            <p><i class="fas fa-shield-alt me-2"></i>
                Secure Login &bull; &copy; <?= date('Y') ?> <?= h($APP_NAME) ?>
            </p>
        </div>
    </div>
</div>

<script src="<?= app_path('assets/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
