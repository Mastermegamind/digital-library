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
        flash_message('error', 'Invalid session token.');
        header('Location: login.php');
        exit;
    }

    if ($email === '' || $password === '') {
        log_warning('Login missing credentials', ['email' => $email]);
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
        log_info('User logged in', ['user_id' => $user['id'], 'email' => $user['email']]);
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
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-header text-center">
        <strong>Login</strong>
      </div>
      <div class="card-body">
        <form method="post" action="login.php">
          <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required placeholder="admin@example.com">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required placeholder="admin123">
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-primary">Login</button>
          </div>
          <p class="mt-3 mb-0 small text-muted">
            Default admin: admin@example.com / admin123
          </p>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
