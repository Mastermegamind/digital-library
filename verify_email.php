<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('verify-email');

$token = $_GET['token'] ?? '';
$verifiedUser = null;

if ($token !== '') {
    $verifiedUser = verify_email_token($token);
}

$meta_title = 'Email Verification - ' . $APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <?php if ($verifiedUser): ?>
        <h3 class="fw-bold mb-2">Email Verified</h3>
        <p class="text-muted">Your email has been verified successfully.</p>
        <?php if (($verifiedUser['status'] ?? 'active') !== 'active'): ?>
            <div class="alert-danger">
                Your account is pending admin approval. You will be able to log in once approved.
            </div>
        <?php else: ?>
            <a href="<?= h(app_path('login')) ?>" class="btn btn-primary">Go to Login</a>
        <?php endif; ?>
    <?php else: ?>
        <h3 class="fw-bold mb-2">Verification Failed</h3>
        <p class="text-muted">The verification link is invalid or has expired.</p>
        <a href="<?= h(app_path('resend-verification')) ?>" class="btn btn-outline-primary">Resend Verification</a>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
