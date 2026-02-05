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

<style>
    .page-header {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(37, 99, 235, 0.1);
    }

    .form-card {
        background: white;
        border-radius: 16px;
        padding: 2.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid var(--border-color);
    }

    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-control, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .form-text {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: 0.375rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 2rem;
        border-top: 2px solid var(--border-color);
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border-left: 4px solid #ef4444;
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 2rem;
    }

    .alert-danger ul {
        margin: 0;
        padding-left: 1.25rem;
    }

    .alert-danger li {
        color: #dc2626;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .alert-danger li:last-child {
        margin-bottom: 0;
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border-left: 4px solid #f59e0b;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }

    .alert-warning strong {
        color: #d97706;
    }

    .current-avatar-preview {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        padding: 1.5rem;
        background: rgba(37, 99, 235, 0.03);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        margin-top: 1rem;
    }

    .avatar-preview-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .form-check {
        padding: 1rem;
        background: rgba(239, 68, 68, 0.05);
        border-radius: 8px;
        border: 1px solid rgba(239, 68, 68, 0.2);
        margin-top: 1rem;
    }

    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .form-check-label {
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
    }

    .role-selector {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .role-option {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }

    .role-option:hover {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.03);
    }

    .role-option input[type="radio"] {
        display: none;
    }

    .role-option input[type="radio"]:checked + .role-content {
        color: var(--primary-color);
    }

    .role-option input[type="radio"]:checked ~ .role-icon {
        background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        color: white;
    }

    .role-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        background: rgba(37, 99, 235, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        transition: all 0.3s ease;
    }

    .user-info-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
                <i class="fas fa-user-edit me-3"></i>Edit User Account
            </h2>
            <p class="text-muted mb-0">
                Updating account for: <strong><?= h($user['name']) ?></strong>
            </p>
        </div>
        <div>
            <a href="users.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Users
            </a>
        </div>
    </div>
</div>

<!-- Form Card -->
<div class="form-card">
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert-danger">
            <div class="d-flex align-items-start gap-3">
                <i class="fas fa-exclamation-circle" style="font-size: 1.5rem; color: #ef4444; margin-top: 0.125rem;"></i>
                <div class="flex-grow-1">
                    <strong style="color: #dc2626;">Please fix the following errors:</strong>
                    <ul class="mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Warning Alert -->
    <div class="alert-warning">
        <strong><i class="fas fa-exclamation-triangle me-2"></i>Editing User Account</strong>
        <p class="mb-0 mt-1" style="color: var(--text-secondary);">
            Changes will be saved immediately. Leave password fields empty to keep the current password.
        </p>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        
        <!-- Personal Information Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-user"></i>
                Personal Information
            </h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-id-card"></i>
                        Full Name <span class="required">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= h($name) ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= h($email) ?>">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        Used for login authentication
                    </div>
                </div>
                
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-user-shield"></i>
                        User Role <span class="required">*</span>
                    </label>
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="role" value="student" <?= $role === 'student' ? 'checked' : '' ?> required>
                            <div class="role-content">
                                <div class="role-icon">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="fw-bold">Student</div>
                                <small class="text-muted">Regular user access</small>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>>
                            <div class="role-content">
                                <div class="role-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="fw-bold">Admin</div>
                                <small class="text-muted">Full system access</small>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="user-info-badge">
                        <i class="fas fa-calendar-check"></i>
                        Account created: <?= date('F j, Y', strtotime($user['created_at'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-lock"></i>
                Update Password (Optional)
            </h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-key"></i>
                        New Password
                    </label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Leave empty to keep current">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        Minimum 8 characters if changing
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-check-double"></i>
                        Confirm New Password
                    </label>
                    <input type="password" name="password_confirm" class="form-control"
                           placeholder="Re-enter new password">
                    <div class="form-text">
                        <i class="fas fa-shield-alt"></i>
                        Must match if changing password
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Picture Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-camera"></i>
                Profile Picture
            </h5>
            
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-upload"></i>
                        Upload New Profile Image
                    </label>
                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                    <div class="form-text">
                        <i class="fas fa-check-circle"></i>
                        Accepted: JPG, PNG, GIF, WEBP (Max 10MB)
                    </div>
                    
                    <?php if (!empty($profilePath)): ?>
                        <?php $avatarUrl = app_path($profilePath); ?>
                        <div class="current-avatar-preview">
                            <img src="<?= h($avatarUrl) ?>" alt="Current avatar" class="avatar-preview-image">
                            <div class="flex-grow-1">
                                <div class="fw-bold mb-1">Current Profile Picture</div>
                                <small class="text-muted">Upload a new image to replace this one</small>
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_profile_image" id="remove_profile_image">
                            <label class="form-check-label" for="remove_profile_image">
                                <i class="fas fa-trash-alt me-2"></i>Remove current profile picture
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="users.php" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i>Update User
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>