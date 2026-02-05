<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    flash_message('error', 'User not found.');
    log_warning('User edit attempted on missing user', ['user_id' => $id]);
    header('Location: users.php');
    exit;
}

$errors = [];
$name = trim($_POST['name'] ?? $user['name']);
$email = trim($_POST['email'] ?? $user['email']);
$role = $_POST['role'] ?? $user['role'];
$profilePath = $user['profile_image_path'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session token.';
        log_warning('User edit CSRF validation failed', ['user_id' => $id]);
    }

    if ($name === '') {
        $errors[] = 'Name is required.';
        log_warning('User edit missing name', ['user_id' => $id]);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
        log_warning('User edit invalid email', ['user_id' => $id, 'email' => $email]);
    }
    $allowedRoles = ['admin', 'student'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Invalid role selected.';
        log_warning('User edit invalid role', ['user_id' => $id, 'role' => $role]);
    }

    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $updatePassword = false;
    if ($password !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
            log_warning('User edit password too short', ['user_id' => $id]);
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
            log_warning('User edit password mismatch', ['user_id' => $id]);
        } else {
            $updatePassword = true;
        }
    }

    $removeProfile = isset($_POST['remove_profile_image']);

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
            if ($avatarUpload['path']) {
                delete_uploaded_file($profilePath);
                $profilePath = $avatarUpload['path'];
            } elseif ($removeProfile && $profilePath) {
                delete_uploaded_file($profilePath);
                $profilePath = null;
            }
        }
    }

    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET name = :name, email = :email, role = :role, profile_image_path = :profile";
            $params = [
                ':name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':profile' => $profilePath,
                ':id' => $id,
            ];
            if ($updatePassword) {
                $sql .= ", password_hash = :password_hash";
                $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            log_info('User updated', ['user_id' => $id, 'email' => $email]);
            flash_message('success', 'User updated successfully.');
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Email already exists.';
                log_warning('User edit duplicate email', ['user_id' => $id, 'email' => $email]);
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
                log_error('User edit database error', ['user_id' => $id, 'exception' => $e->getMessage()]);
            }
        }
    }
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3">Edit User</h4>
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
          <label class="form-label">New Password (optional)</label>
          <input type="password" name="password" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="password_confirm" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Profile Image</label>
          <input type="file" name="profile_image" class="form-control" accept="image/*">
          <div class="form-text">Optional; JPG, PNG, GIF, or WEBP up to 10MB.</div>
          <?php if (!empty($profilePath)): ?>
            <?php $avatarUrl = app_path($profilePath); ?>
            <div class="mt-2 d-flex align-items-center gap-2">
              <img src="<?php echo h($avatarUrl); ?>" alt="Current avatar" style="width:80px;height:80px;object-fit:cover;" class="rounded-circle border">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remove_profile_image" id="remove_profile_image">
                <label class="form-check-label" for="remove_profile_image">Remove current image</label>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">Update User</button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
