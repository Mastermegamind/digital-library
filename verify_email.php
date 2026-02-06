<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('verify-email');

$token = $_GET['token'] ?? '';
$verifiedUser = null;

if ($token !== '') {
    $verifiedUser = verify_email_token($token);
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Email Verification - <?= h($APP_NAME) ?></title>
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
            <p class="login-subtitle">Email Verification</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if ($verifiedUser): ?>
                <div style="text-align:center;padding:1rem 0;">
                    <i class="fas fa-check-circle" style="font-size:3rem;color:#22c55e;margin-bottom:1rem;"></i>
                    <h3 style="font-weight:700;margin-bottom:0.5rem;color:var(--text-primary);">Email Verified</h3>
                    <p style="color:var(--text-secondary);margin-bottom:1.5rem;">Your email has been verified successfully.</p>
                </div>

                <?php if (($verifiedUser['status'] ?? 'active') !== 'active'): ?>
                    <div class="alert-danger">
                        <i class="fas fa-clock me-2"></i>Your account is pending admin approval. You will be able to log in once approved.
                    </div>
                <?php else: ?>
                    <a href="<?= h(app_path('login')) ?>" class="btn-login" style="display:block;text-align:center;text-decoration:none;">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div style="text-align:center;padding:1rem 0;">
                    <i class="fas fa-times-circle" style="font-size:3rem;color:#ef4444;margin-bottom:1rem;"></i>
                    <h3 style="font-weight:700;margin-bottom:0.5rem;color:var(--text-primary);">Verification Failed</h3>
                    <p style="color:var(--text-secondary);margin-bottom:1.5rem;">The verification link is invalid or has expired.</p>
                </div>

                <a href="<?= h(app_path('resend-verification')) ?>" class="btn-login" style="display:block;text-align:center;text-decoration:none;">
                    <i class="fas fa-redo me-2"></i>Resend Verification
                </a>

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
