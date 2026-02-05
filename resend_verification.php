<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('resend-verification');

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('resend-verification'));
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email_verified_at FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && empty($user['email_verified_at']) && mailer_is_configured()) {
            $token = issue_email_verification_token((int)$user['id']);
            $verifyUrl = app_url('verify-email') . '?token=' . urlencode($token);
            $subject = 'Verify your email - ' . $APP_NAME;
            $body = '<p>Hello ' . h($user['name']) . ',</p>'
                  . '<p>Please verify your email:</p>'
                  . '<p><a href="' . h($verifyUrl) . '">' . h($verifyUrl) . '</a></p>';
            send_app_mail($email, $subject, $body);
        }

        $message = 'If the email exists, a verification link has been sent.';
    }
}

$csrf = get_csrf_token();
$meta_title = 'Resend Verification - ' . $APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="form-card">
    <h3 class="fw-bold mb-2">Resend Verification</h3>
    <p class="text-muted">Enter your email to receive a new verification link.</p>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= h($message) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <label class="form-label"><i class="fas fa-envelope me-2"></i>Email Address</label>
        <input type="email" name="email" class="form-control" required>
        <div class="action-buttons mt-3">
            <a href="<?= h(app_path('login')) ?>" class="btn btn-outline-secondary">Back to Login</a>
            <button type="submit" class="btn btn-primary">Send Link</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
