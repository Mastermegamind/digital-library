<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('register');

if (is_logged_in()) {
    header('Location: ' . app_path(''));
    exit;
}

if (!is_registration_enabled()) {
    flash_message('error', 'Registration is currently closed.');
    header('Location: ' . app_path('login'));
    exit;
}

$errors = [];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$regNo = trim($_POST['reg_no'] ?? '');
$enrollmentYear = trim($_POST['enrollment_year'] ?? '');
$department = trim($_POST['department'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session token.';
    }

    if ($name === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($regNo === '') $errors[] = 'Registration number is required.';
    if ($enrollmentYear === '' || !preg_match('/^\d{4}$/', $enrollmentYear)) {
        $errors[] = 'Valid enrollment year is required.';
    }

    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $existing = $pdo->prepare("SELECT 1 FROM users WHERE email = :email LIMIT 1");
            $existing->execute([':email' => $email]);
            if ($existing->fetch()) {
                $errors[] = 'Email already exists.';
            }

            if (empty($errors)) {
                $mode = get_registration_mode();
                $status = $mode === 'admin_approved' ? 'pending' : 'active';
                $emailVerificationRequired = is_email_verification_required();
                $emailVerifiedAt = $emailVerificationRequired ? null : date('Y-m-d H:i:s');

                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status, email_verified_at)
                                       VALUES (:name, :email, :pw, 'student', :status, :email_verified_at)");
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':pw' => password_hash($password, PASSWORD_DEFAULT),
                    ':status' => $status,
                    ':email_verified_at' => $emailVerifiedAt,
                ]);

                $newUserId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO user_profiles (user_id, reg_no, enrollment_year, department)
                               VALUES (:uid, :reg_no, :year, :dept)")
                    ->execute([
                        ':uid' => $newUserId,
                        ':reg_no' => $regNo,
                        ':year' => $enrollmentYear,
                        ':dept' => $department !== '' ? $department : null,
                    ]);

                $pdo->commit();

                if ($emailVerificationRequired && mailer_is_configured()) {
                    $token = issue_email_verification_token($newUserId);
                    $verifyUrl = app_url('verify-email') . '?token=' . urlencode($token);
                    $subject = 'Verify your email - ' . $APP_NAME;
                    $body = '<p>Hello ' . h($name) . ',</p>'
                          . '<p>Thanks for registering. Please verify your email to continue:</p>'
                          . '<p><a href="' . h($verifyUrl) . '">' . h($verifyUrl) . '</a></p>';
                    send_app_mail($email, $subject, $body);
                }

                if ($status === 'pending' && $emailVerificationRequired) {
                    flash_message('success', 'Registration received. Please verify your email and wait for admin approval.');
                } elseif ($status === 'pending') {
                    flash_message('success', 'Registration received. Please wait for admin approval.');
                } elseif ($emailVerificationRequired) {
                    flash_message('success', 'Registration successful. Please verify your email before logging in.');
                } else {
                    flash_message('success', 'Registration successful. You can now log in.');
                }

                header('Location: ' . app_path('login'));
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $errors[] = 'Email or Registration Number already exists.';
            } else {
                log_error('Registration failed', ['exception' => $e->getMessage()]);
                $errors[] = 'An error occurred. Please try again.';
            }
        }
    }
}

$csrf = get_csrf_token();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register - <?= h($APP_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Create a student account for <?= h($APP_NAME) ?>.">
    <link rel="icon" type="image/png" href="<?= app_path('assets/images/favicon.png') ?>">
    <link href="<?= app_path('assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= app_path('assets/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= app_path('assets/css/inter.css') ?>">
    <link rel="stylesheet" href="<?= app_path('assets/css/components.css') ?>">
</head>
<body class="login-page">

<div class="login-container" style="max-width:520px;">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div class="logo-circle">
                <img src="<?= app_path('assets/images/main.png') ?>"
                     alt="Logo"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-book-reader fa-4x\'></i>';">
            </div>
            <h1 class="login-title"><?= h($APP_NAME) ?></h1>
            <p class="login-subtitle">Create Student Account</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if (!empty($errors)): ?>
                <div class="alert-danger">
                    <strong><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following:</strong>
                    <ul class="mt-2 mb-0" style="padding-left:1.25rem;">
                        <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                <div>
                    <label class="form-label">
                        <i class="fas fa-user me-2"></i>Full Name
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" name="name" class="form-control" required autofocus
                               placeholder="Enter your full name"
                               value="<?= h($name) ?>">
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        <i class="fas fa-envelope me-2"></i>Email
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required
                               placeholder="Enter your email address"
                               value="<?= h($email) ?>">
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        <i class="fas fa-id-card me-2"></i>Registration Number
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-id-card"></i></span>
                        <input type="text" name="reg_no" class="form-control" required
                               placeholder="Enter your registration number"
                               value="<?= h($regNo) ?>">
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;">
                    <div style="flex:1;">
                        <label class="form-label">
                            <i class="fas fa-calendar me-2"></i>Enrollment Year
                        </label>
                        <input type="text" name="enrollment_year" class="form-control"
                               required placeholder="2026"
                               style="height:clamp(46px,6.5vh,58px);border:2px solid var(--border-color);border-radius:16px;font-size:clamp(0.95rem,1.75vh,1rem);background:rgba(255,255,255,0.7);"
                               value="<?= h($enrollmentYear) ?>">
                    </div>
                    <div style="flex:1;">
                        <label class="form-label">
                            <i class="fas fa-building me-2"></i>Department
                        </label>
                        <input type="text" name="department" class="form-control"
                               placeholder="Optional"
                               style="height:clamp(46px,6.5vh,58px);border:2px solid var(--border-color);border-radius:16px;font-size:clamp(0.95rem,1.75vh,1rem);background:rgba(255,255,255,0.7);"
                               value="<?= h($department) ?>">
                    </div>
                </div>

                <div style="margin-top:clamp(0.75rem,1.8vh,1.5rem);">
                    <label class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-key"></i></span>
                        <input type="password" name="password" class="form-control" required
                               placeholder="At least 8 characters">
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
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= h(app_path('login')) ?>" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i>Already have an account? Sign In
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
