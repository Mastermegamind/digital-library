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
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    $allowedRoles = ['admin', 'student', 'staff'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Invalid role selected.';
    }

    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    // Role-specific required fields
    if ($role === 'student') {
        if (empty(trim($_POST['reg_no'] ?? ''))) {
            $errors[] = 'Registration Number is required for students.';
        }
        if (empty($_POST['enrollment_year'] ?? '')) {
            $errors[] = 'Enrollment Year is required for students.';
        }
    }
    if ($role === 'staff') {
        if (empty(trim($_POST['staff_id'] ?? ''))) {
            $errors[] = 'Staff ID is required for staff members.';
        }
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
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, profile_image_path, role) VALUES (:name, :email, :password, :profile, :role)");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':profile' => $profileImagePath,
                ':role' => $role,
            ]);
            $newUserId = $pdo->lastInsertId();

            $profileStmt = $pdo->prepare("INSERT INTO user_profiles (
                user_id, reg_no, enrollment_year, department,
                staff_id, designation, department_staff, phone, gender
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $profileStmt->execute([
                $newUserId,
                $role === 'student' ? trim($_POST['reg_no']) : null,
                $role === 'student' ? $_POST['enrollment_year'] : null,
                $role === 'student' ? trim($_POST['department'] ?? '') : null,
                $role === 'staff' ? trim($_POST['staff_id']) : null,
                $role === 'staff' ? trim($_POST['designation'] ?? '') : null,
                $role === 'staff' ? trim($_POST['department_staff'] ?? '') : null,
                trim($_POST['phone'] ?? '') ?: null,
                $_POST['gender'] ?? null
            ]);

            $pdo->commit();

            log_info('User created', ['new_user_id' => $newUserId, 'email' => $email, 'role' => $role]);
            flash_message('success', 'User created successfully.');
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $errors[] = 'Email, Registration Number, or Staff ID already exists.';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
                log_error('User add error', ['exception' => $e]);
            }
        }
    }
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* (Keep your existing beautiful styles here - same as original) */
    .page-header{background:linear-gradient(135deg,rgba(37,99,235,0.1),rgba(59,130,246,0.05));border-radius:20px;padding:2rem;margin-bottom:2rem;border:1px solid rgba(37,99,235,0.1)}
    .form-card{background:white;border-radius:16px;padding:2.5rem;box-shadow:0 4px 6px rgba(0,0,0,0.07)}
    .form-section{margin-bottom:2rem;padding-bottom:2rem;border-bottom:2px solid var(--border-color)}
    .form-section:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
    .section-title{font-size:1.125rem;font-weight:700;color:var(--primary-color);margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem}
    .form-label{font-weight:600;color:var(--text-primary);margin-bottom:.5rem;font-size:.875rem;display:flex;align-items:center;gap:.5rem}
    .form-label .required{color:#ef4444}
    .form-control,.form-select{border:2px solid var(--border-color);border-radius:12px;padding:.875rem 1.25rem;font-size:1rem;transition:all .3s ease}
    .form-control:focus,.form-select:focus{border-color:var(--primary-color);box-shadow:0 0 0 4px rgba(37,99,235,0.1)}
    .form-text{color:var(--text-secondary);font-size:.8rem;margin-top:.375rem;display:flex;align-items:center;gap:.375rem}
    .action-buttons{display:flex;gap:1rem;justify-content:flex-end;padding-top:2rem;border-top:2px solid var(--border-color)}
    .alert-danger{background:rgba(239,68,68,0.1);border-left:4px solid #ef4444;border-radius:12px;padding:1.25rem;margin-bottom:2rem}
    .role-selector{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
    .role-option{border:2px solid var(--border-color);border-radius:12px;padding:1.5rem;cursor:pointer;transition:all .3s ease;text-align:center}
    .role-option:hover{border-color:var(--primary-color);background:rgba(37,99,235,0.03)}
    .role-option input[type=radio]{display:none}
    .role-option input[type=radio]:checked ~ .role-icon{background:linear-gradient(135deg,var(--primary-color),var(--accent-color));color:white}
    .role-icon{width:60px;height:60px;margin:0 auto 1rem;background:rgba(37,99,235,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.75rem;transition:all .3s ease}
</style>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color:var(--primary-color)"><i class="fas fa-user-plus me-3"></i>Add New User</h2>
            <p class="text-muted mb-0">Create a new user account for the system</p>
        </div>
        <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Users</a>
    </div>
</div>

<div class="form-card">
    <?php if (!empty($errors)): ?>
        <div class="alert-danger">
            <strong style="color:#dc2626">Please fix the following errors:</strong>
            <ul class="mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <!-- Personal Information -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-user"></i> Personal Information</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($name) ?>" placeholder="Enter full name">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?= h($email) ?>" placeholder="user@example.com">
                    <div class="form-text"><i class="fas fa-info-circle"></i> Used for login</div>
                </div>

                <div class="col-md-12">
                    <label class="form-label">User Role <span class="required">*</span></label>
                    <div class="role-selector">
                        <label class="role-option">
23                        <input type="radio" name="role" value="student" <?= $role === 'student' ? 'checked' : '' ?> required>
                            <div class="role-content">
                                <div class="role-icon"><i class="fas fa-user-graduate"></i></div>
                                <div class="fw-bold">Student</div>
                                <small class="text-muted">Library borrower</small>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="staff" <?= $role === 'staff' ? 'checked' : '' ?>>
                            <div class="role-content">
                                <div class="role-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                <div class="fw-bold">Staff</div>
                                <small class="text-muted">Librarian / Faculty</small>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>>
                            <div class="role-content">
                                <div class="role-icon"><i class="fas fa-shield-alt"></i></div>
                                <div class="fw-bold">Admin</div>
                                <small class="text-muted">Full system access</small>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Fields -->
        <div class="form-section" id="student-fields" style="display: <?= $role === 'student' ? 'block' : 'none' ?>;">
            <h5 class="section-title"><i class="fas fa-id-card"></i> Student Details</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Registration Number <span class="required">*</span></label>
                    <input type="text" name="reg_no" class="form-control" value="<?= h($_POST['reg_no'] ?? '') ?>" placeholder="e.g. CSE/2023/045">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Enrollment Year <span class="required">*</span></label>
                    <select name="enrollment_year" class="form-select">
                        <option value="">-- Select Year --</option>
                        <?php for($y=date('Y')+5; $y>=2000; $y--): ?>
                            <option value="<?= $y ?>" <?= ($_POST['enrollment_year'] ?? '') == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Department <small class="text-muted">(Optional)</small></label>
                    <input type="text" name="department" class="form-control" value="<?= h($_POST['department'] ?? '') ?>" placeholder="e.g. Computer Science and Engineering">
                </div>
            </div>
        </div>

        <!-- Staff Fields -->
        <div class="form-section" id="staff-fields" style="display: <?= $role === 'staff' ? 'block' : 'none' ?>;">
            <h5 class="section-title"><i class="fas fa-chalkboard-teacher"></i> Staff Details</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Staff ID <span class="required">*</span></label>
                    <input type="text" name="staff_id" class="form-control" value="<?= h($_POST['staff_id'] ?? '') ?>" placeholder="e.g. LIB/023">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" class="form-control" value="<?= h($_POST['designation'] ?? '') ?>" placeholder="e.g. Senior Librarian">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Department / Section</label>
                    <input type="text" name="department_staff" class="form-control" value="<?= h($_POST['department_staff'] ?? '') ?>" placeholder="e.g. Central Library">
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-address-card"></i> Additional Information</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="Male" <?= ($_POST['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Security -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-lock"></i> Security Credentials</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" required placeholder="Minimum 8 characters">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" required placeholder="Re-enter password">
                </div>
            </div>
        </div>

        <!-- Profile Picture -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-camera"></i> Profile Picture (Optional)</h5>
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label">Upload Image</label>
                    <input type="file" name="profile_image" class="form-control" accept="image/*">
                    <div class="form-text">JPG, PNG, GIF, WEBP (Max 10MB)</div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="users.php" class="btn btn-outline-secondary btn-lg"><i class="fas fa-times me-2"></i>Cancel</a>
            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-user-plus me-2"></i>Create User</button>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('input[name="role"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const student = document.getElementById('student-fields');
        const staff = document.getElementById('staff-fields');
        student.style.display = radio.value === 'student' ? 'block' : 'none';
        staff.style.display = radio.value === 'staff' ? 'block' : 'none';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>