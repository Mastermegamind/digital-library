<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        log_warning('Login CSRF validation failed', ['email' => $email]);
        flash_message('error', 'Invalid session. Please try again.');
        header('Location: login.php');
        exit;
    }

    if ($email === '' || $password === '') {
        flash_message('error', 'Please fill in all fields.');
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        login_user($user['id']);
        flash_message('success', 'Welcome back, ' . $user['name'] . '!');
        log_info('User logged in', ['user_id' => $user['id']]);
        header('Location: index.php');
        exit;
    } else {
        log_warning('Invalid login attempt', ['email' => $email]);
        flash_message('error', 'Invalid email or password.');
        header('Location: login.php');
        exit;
    }
}

$csrf = get_csrf_token();
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5 col-xl-4">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <!-- Header with Logo & Gradient -->
                <div class="bg-primary text-white text-center py-5 position-relative overflow-hidden">
                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10"
                         style="background: radial-gradient(circle at top left, var(--accent-blue), transparent 70%);">
                    </div>
                    <div class="text-center position-relative z-1">
                        <img src="<?= app_path('assets/logo.png') ?>"
                             alt="Logo"
                             class="mb-3"
                             height="80"
                             onerror="this.src='https://via.placeholder.com/80?text=📚';">
                        <h3 class="fw-bold mb-1"><?= h($APP_NAME) ?></h3>
                        <p class="mb-0 opacity-90">Sign in to access CONS-UNTH E-LIBRARY</p>
                    </div>
                </div>

                <!-- Login Form -->
                <div class="card-body p-4 p-md-5">
                    <form method="post" action="login.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark">Email Address</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-envelope text-primary"></i>
                                </span>
                                <input type="email"
                                       name="email"
                                       class="form-control border-start-0 ps-0"
                                       placeholder="admin@example.com"
                                       value="admin@example.com"
                                       required
                                       autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark">Password</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-primary"></i>
                                </span>
                                <input type="password"
                                       name="password"
                                       class="form-control border-start-0 ps-0"
                                       placeholder="••••••••"
                                       value="admin123"
                                       required>
                            </div>
                        </div>

                        <div class="d-grid mb-4">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill shadow-sm fw-semibold">
                                <i class="fas fa-sign-in-alt me-2"></i> Sign In
                            </button>
                        </div>

                        <div class="text-center">
                            <p class="text-muted small mb-0">
                                <strong>Default Admin Account</strong><br>
                                <span class="text-primary">admin@example.com</span> / <span class="text-primary">admin123</span>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-light border-0 text-center py-4">
                    <small class="text-muted">
                        © <?= date('Y') ?> <?= h($APP_NAME) ?>. All rights reserved.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>