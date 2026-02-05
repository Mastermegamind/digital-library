<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors = [];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'student';
$profileImagePath = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session token.';
        log_warning('User add CSRF validation failed');
    }

    if ($name === '') {
        $errors[] = 'Name is required.';
        log_warning('User add missing name');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
        log_warning('User add invalid email', ['email' => $email]);
    }
    $allowedRoles = ['admin', 'student'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Invalid role selected.';
        log_warning('User add invalid role', ['role' => $role]);
    }

    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
        log_warning('User add password too short', ['email' => $email]);
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
        log_warning('User add password mismatch', ['email' => $email]);
    }

    if (empty($errors)) {
        $avatarUpload = handle_file_upload(
            $_FILES['profile_image'] ?? null,
            ['jpg','jpeg','png','gif','webp'],
            __DIR__ . '/../uploads/avatars',
            'uploads/avatars',
            10 * 1024 * 1024
        );
        if ($avatarUpload['error']) {
            $errors[] = $avatarUpload['error'];
        } else {
            $profileImagePath = $avatarUpload['path'];
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, profile_image_path, role) VALUES (:name, :email, :password, :profile, :role)");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':profile' => $profileImagePath,
                ':role' => $role,
            ]);
            log_info('User created', ['new_user_id' => $pdo->lastInsertId(), 'email' => $email, 'role' => $role]);
            flash_message('success', 'User created successfully.');
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Email already exists.';
                log_warning('User add duplicate email', ['email' => $email]);
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
                log_error('User add database error', ['exception' => $e->getMessage()]);
            }
        }
    }
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3">Add User</h4>
<?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?>
        <li><?php echo h($error); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<div class="card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" value="<?php echo h($name); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?php echo h($email); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Role</label>
          <select name="role" class="form-select" required>
            <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
          </select>
        </div>
        <div class="col-md-6"></div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="password_confirm" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Profile Image</label>
          <input type="file" name="profile_image" class="form-control" accept="image/*">
          <div class="form-text">Optional; JPG, PNG, GIF, or WEBP up to 10MB.</div>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Create User</button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
