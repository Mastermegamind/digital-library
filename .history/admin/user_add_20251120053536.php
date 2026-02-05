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

    .info-box {
        background: rgba(37, 99, 235, 0.05);
        border-left: 4px solid var(--primary-color);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }

    .info-box-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-box-text {
        color: var(--text-secondary);
        font-size: 0.875rem;
        margin: 0;
    }

    .password-strength {
        height: 4px;
        background: var(--border-color);
        border-radius: 2px;
        margin-top: 0.5rem;
        overflow: hidden;
    }

    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: all 0.3s ease;
        border-radius: 2px;
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
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
                <i class="fas fa-user-plus me-3"></i>Add New User
            </h2>
            <p class="text-muted mb-0">
                Create a new user account for the system
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

    <!-- Info Box -->
    <div class="info-box">
        <div class="info-box-title">
            <i class="fas fa-info-circle"></i>
            User Account Requirements
        </div>
        <p class="info-box-text">
            All new users must have a valid email address and a secure password (minimum 8 characters). Profile images are optional.
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
                           placeholder="Enter full name..."
                           value="<?= h($name) ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" name="email" class="form-control" required
                           placeholder="user@example.com"
                           value="<?= h($email) ?>">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        This will be used for login
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
            </div>
        </div>

        <!-- Security Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-lock"></i>
                Security Credentials
            </h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-key"></i>
                        Password <span class="required">*</span>
                    </label>
                    <input type="password" name="password" class="form-control" required
                           placeholder="Minimum 8 characters">
                    <div class="form-text">
                        <i class="fas fa-shield-alt"></i>
                        Use a strong, unique password
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-check-double"></i>
                        Confirm Password <span class="required">*</span>
                    </label>
                    <input type="password" name="password_confirm" class="form-control" required
                           placeholder="Re-enter password">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        Must match the password above
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Picture Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-camera"></i>
                Profile Picture (Optional)
            </h5>
            
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-image"></i>
                        Upload Profile Image
                    </label>
                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                    <div class="form-text">
                        <i class="fas fa-check-circle"></i>
                        Accepted: JPG, PNG, GIF, WEBP (Max 10MB)
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="users.php" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-user-plus me-2"></i>Create User
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>