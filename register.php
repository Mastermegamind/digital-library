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
$meta_title = 'Register - ' . $APP_NAME;
$meta_description = 'Create a student account for ' . $APP_NAME . '.';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-user-plus me-2"></i>Create Student Account
            </h2>
            <p class="text-muted mb-0">Register to access the library resources.</p>
        </div>
        <a href="<?= h(app_path('login')) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-sign-in-alt me-2"></i>Back to Login
        </a>
    </div>
</div>

<div class="form-card">
    <?php if (!empty($errors)): ?>
        <div class="alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mt-2 mb-0">
                <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="row g-4">
            <div class="col-md-6">
                <label class="form-label"><i class="fas fa-user me-2"></i>Full Name</label>
                <input type="text" name="name" class="form-control" required value="<?= h($name) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                <input type="email" name="email" class="form-control" required value="<?= h($email) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><i class="fas fa-id-card me-2"></i>Registration Number</label>
                <input type="text" name="reg_no" class="form-control" required value="<?= h($regNo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-calendar me-2"></i>Enrollment Year</label>
                <input type="text" name="enrollment_year" class="form-control" required placeholder="2026" value="<?= h($enrollmentYear) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-building me-2"></i>Department</label>
                <input type="text" name="department" class="form-control" value="<?= h($department) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                <input type="password" name="password_confirm" class="form-control" required>
            </div>
        </div>

        <div class="action-buttons mt-4">
            <a href="<?= h(app_path('login')) ?>" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Back to Login
            </a>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-user-plus me-2"></i>Create Account
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
