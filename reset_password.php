<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . app_path(''));
    exit;
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$tokenValid = false;

if ($token !== '') {
    $user = verify_password_reset_token($token);
    $tokenValid = $user !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $csrf     = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session. Please try again.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id")
            ->execute([':hash' => $hash, ':id' => $user['id']]);
        invalidate_password_reset_tokens($user['id']);

        log_info('Password reset completed', ['user_id' => $user['id']]);
        flash_message('success', 'Password reset successfully. You can now log in.');
        header('Location: ' . app_path('login'));
        exit;
    }
}

$csrf = get_csrf_token();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Password - <?= h($APP_NAME) ?></title>
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
            <p class="login-subtitle">Reset Password</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if (!$tokenValid): ?>
                <div class="alert-danger">
                    <strong><i class="fas fa-exclamation-triangle me-2"></i>Link Expired</strong><br>
                    This password reset link is invalid or has expired.
                </div>
                <a href="<?= h(app_path('forgot-password')) ?>" class="btn-login" style="display:block;text-align:center;text-decoration:none;">
                    <i class="fas fa-redo me-2"></i>Request New Link
                </a>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert-danger">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Oops!</strong><br>
                        <?= implode('<br>', array_map('h', $errors)) ?>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="token" value="<?= h($token) ?>">

                    <div>
                        <label class="form-label">
                            <i class="fas fa-lock me-2"></i>New Password
                        </label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-key"></i></span>
                            <input type="password" name="password" class="form-control" required autofocus
                                   placeholder="At least 8 characters" minlength="8">
                        </div>
                    </div>

                    <div>
                        <label class="form-label">
                            <i class="fas fa-lock me-2"></i>Confirm Password
                        </label>
                        <div class="input-wrapper">
                            <span class="input-icon"><i class="fas fa-check-double"></i></span>
                            <input type="password" name="password_confirm" class="form-control" required
                                   placeholder="Repeat your password">
                        </div>
                    </div>

                    <button type="submit" class="btn-login mt-3">
                        <i class="fas fa-save me-2"></i>Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="<?= h(app_path('login')) ?>" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
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
