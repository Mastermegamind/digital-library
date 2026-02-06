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
global $APP_NAME;
$appName = $APP_NAME ?? 'E-Library';
?><!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password — <?= h($appName) ?></title>
    <link rel="stylesheet" href="<?= h(app_path('assets/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_path('assets/css/components.css')) ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%)">
    <div class="card shadow-lg border-0" style="max-width:440px;width:100%">
        <div class="card-body p-4">
            <h4 class="text-center mb-3">Forgot Password</h4>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    If an account with that email exists, a password reset link has been sent. Please check your inbox.
                </div>
                <div class="text-center">
                    <a href="<?= h(app_path('login')) ?>" class="btn btn-primary">Back to Login</a>
                </div>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endforeach; ?>

                <p class="text-muted small">Enter your email address and we'll send you a link to reset your password.</p>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" required
                               placeholder="Enter your email" autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                </form>

                <div class="text-center mt-3">
                    <a href="<?= h(app_path('login')) ?>" class="small text-decoration-none">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
