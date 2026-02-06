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
global $APP_NAME;
$appName = $APP_NAME ?? 'E-Library';
?><!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — <?= h($appName) ?></title>
    <link rel="stylesheet" href="<?= h(app_path('assets/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_path('assets/css/components.css')) ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%)">
    <div class="card shadow-lg border-0" style="max-width:440px;width:100%">
        <div class="card-body p-4">
            <h4 class="text-center mb-3">Reset Password</h4>

            <?php if (!$tokenValid): ?>
                <div class="alert alert-danger">
                    This password reset link is invalid or has expired.
                </div>
                <div class="text-center">
                    <a href="<?= h(app_path('forgot-password')) ?>" class="btn btn-primary">Request New Link</a>
                </div>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endforeach; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required
                               placeholder="At least 8 characters" minlength="8">
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirm" id="password_confirm" class="form-control" required
                               placeholder="Repeat password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
