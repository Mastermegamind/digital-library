<?php
require_once __DIR__ . '/../includes/auth.php';
$legacyId = (int)($_GET['id'] ?? 0);
if ($legacyId > 0) {
    redirect_legacy_php('admin/user/edit/' . $legacyId, ['id' => null]);
}
require_admin();

$id = $legacyId;

$stmt = $pdo->prepare("
    SELECT u.*, up.reg_no, up.enrollment_year, up.department,
           up.staff_id, up.designation, up.department_staff, up.phone, up.gender
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE u.id = :id
");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    flash_message('error', 'User not found.');
    header('Location: ' . app_path('admin/users'));
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
        log_warning('User edit CSRF failed', ['user_id' => $id]);
    }

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    $allowedRoles = ['admin', 'student', 'staff'];
    if (!in_array($role, $allowedRoles, true)) $errors[] = 'Invalid role selected.';

    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $updatePassword = false;
    if ($password !== '') {
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        elseif ($password !== $passwordConfirm) $errors[] = 'Passwords do not match.';
        else $updatePassword = true;
    }

    if ($role === 'student') {
        if (empty(trim($_POST['reg_no'] ?? ''))) $errors[] = 'Registration Number is required for students.';
        if (empty($_POST['enrollment_year'] ?? '')) $errors[] = 'Enrollment Year is required for students.';
    }
// Staff and Admin both must have an ID
if ($role === 'staff' || $role === 'admin') {
    if (empty(trim($_POST['staff_id'] ?? ''))) {
        $errors[] = 'Staff/Admin ID is required for staff and admin members.';
    }
}

    $removeProfile = isset($_POST['remove_profile_image']);

    if (empty($errors)) {
        $avatarUpload = handle_file_upload(
            $_FILES['profile_image'] ?? null,
            ['jpg','jpeg','png','gif','webp'],
            __DIR__ . '/../uploads/avatars',
            'uploads/avatars',
            10 * 1024 * 1024,
            ['max_width' => 512, 'max_height' => 512, 'max_pixels' => 4000000, 'quality' => 85]
        );
        if ($avatarUpload['error']) {
            $errors[] = $avatarUpload['error'];
        } else {
            if ($avatarUpload['path']) {
                if ($profilePath) delete_uploaded_file($profilePath);
                $profilePath = $avatarUpload['path'];
            } elseif ($removeProfile && $profilePath) {
                delete_uploaded_file($profilePath);
                $profilePath = null;
            }
        }
    }

    if (empty($errors)) {
        // Normalize profile fields based on role
        $regNo           = null;
        $enrollmentYear  = null;
        $studentDept     = null;
        $staffId         = null;
        $designation     = null;
        $departmentStaff = null;

        if ($role === 'student') {
            $regNo          = trim($_POST['reg_no'] ?? '');
            $enrollmentYear = $_POST['enrollment_year'] ?? null;
            $studentDept    = trim($_POST['department'] ?? '');
        }

        if ($role === 'staff' || $role === 'admin') {
            // For admin we treat staff_id as "Admin ID" but it still goes in staff_id column
            $staffId         = trim($_POST['staff_id'] ?? '');
            $designation     = trim($_POST['designation'] ?? '');
            $departmentStaff = trim($_POST['department_staff'] ?? '');
        }

        $phone = trim($_POST['phone'] ?? '');
        if ($phone === '') {
            $phone = null;
        }
        $gender = $_POST['gender'] ?? null;

        try {
            $pdo->beginTransaction();

            // Update users table
            $sql = "UPDATE users SET name = ?, email = ?, role = ?, profile_image_path = ?" .
                   ($updatePassword ? ", password_hash = ?" : "") . " WHERE id = ?";
            $params = [$name, $email, $role, $profilePath];
            if ($updatePassword) {
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);

            // Ensure user_profiles row exists
            $profileStmt = $pdo->prepare("SELECT id FROM user_profiles WHERE user_id = ?");
            $profileStmt->execute([$id]);
            $profileId = $profileStmt->fetchColumn();

            if ($profileId) {
                // UPDATE existing profile
                $stmt = $pdo->prepare("
                    UPDATE user_profiles SET
                        reg_no = ?,
                        enrollment_year = ?,
                        department = ?,
                        staff_id = ?,
                        designation = ?,
                        department_staff = ?,
                        phone = ?,
                        gender = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $regNo,
                    $enrollmentYear,
                    $studentDept,
                    $staffId,
                    $designation,
                    $departmentStaff,
                    $phone,
                    $gender,
                    $id
                ]);
            } else {
                // INSERT new profile (for users that didn't have one yet)
                $stmt = $pdo->prepare("
                    INSERT INTO user_profiles (
                        user_id,
                        reg_no,
                        enrollment_year,
                        department,
                        staff_id,
                        designation,
                        department_staff,
                        phone,
                        gender
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    $regNo,
                    $enrollmentYear,
                    $studentDept,
                    $staffId,
                    $designation,
                    $departmentStaff,
                    $phone,
                    $gender
                ]);
            }

            $pdo->commit();
            log_info('User updated', ['user_id' => $id]);
            flash_message('success', 'User updated successfully!');
            header('Location: ' . app_path('admin/users'));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                // email (users.email), reg_no or staff_id (user_profiles) uniqueness
                $errors[] = 'Email, Registration Number, or Staff/Admin ID already in use.';
            } else {
                log_error('User edit failed', ['exception' => $e]);
                $errors[] = 'Database error occurred.';
            }
        }
    }

}

$csrf = get_csrf_token();
$meta_title = 'Edit User - ' . $user['name'] . ' | ' . $APP_NAME;
$meta_description = 'Update user account details in the ' . $APP_NAME . ' admin panel.';
include __DIR__ . '/../includes/header.php';
?>



<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-user-edit me-3"></i>Edit User Account
            </h2>
            <p class="text-muted mb-0">Updating: <strong><?= h($user['name']) ?></strong></p>
        </div>
        <a href="<?= h(app_path('admin/users')) ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Users</a>
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

    <form method="post" enctype="multipart/form-data" id="userForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <!-- Personal Information -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-user"></i> Personal Information</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($name) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?= h($email) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">User Role <span class="required">*</span></label>
                    <div class="role-selector">
                        <label class="role-option">
                            <input type="radio" name="role" value="student" <?= $role==='student'?'checked':'' ?> required>
                            <div class="role-content">
                                <div class="role-icon"><i class="fas fa-user-graduate"></i></div>
                                <div class="fw-bold">Student</div>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="staff" <?= $role==='staff'?'checked':'' ?>>
                            <div class="role-content">
                                <div class="role-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                                <div class="fw-bold">Staff</div>
                            </div>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="admin" <?= $role==='admin'?'checked':'' ?>>
                            <div class="role-content">
                                <div class="role-icon"><i class="fas fa-shield-alt"></i></div>
                                <div class="fw-bold">Admin</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Fields -->
        <div class="form-section role-section <?= $role==='student'?'is-visible':'is-hidden' ?>" id="student-fields">
            <h5 class="section-title"><i class="fas fa-id-card"></i> Student Details</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Registration Number <span class="required">*</span></label>
                    <input type="text" name="reg_no" class="form-control" value="<?= h($role==='student' ? ($_POST['reg_no'] ?? $user['reg_no']) : '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Enrollment Year <span class="required">*</span></label>
                    <select name="enrollment_year" class="form-select">
                        <option value="">-- Select Year --</option>
                        <?php for($y=date('Y')+5;$y>=2000;$y--): ?>
                            <option value="<?= $y ?>" <?= ($role==='student' && ($_POST['enrollment_year'] ?? $user['enrollment_year'])==$y)?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" value="<?= h($role==='student' ? ($_POST['department'] ?? $user['department']) : '') ?>">
                </div>
            </div>
        </div>

        <!-- Staff Fields -->
<!-- Staff / Admin Fields -->
<div class="form-section role-section <?= ($role==='staff' || $role==='admin') ? 'is-visible' : 'is-hidden' ?>" id="staff-fields">
    <h5 class="section-title"><i class="fas fa-chalkboard-teacher"></i> Staff / Admin Details</h5>

            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Staff ID <span class="required">*</span></label>
<input type="text" name="staff_id" class="form-control"
       value="<?= h(($role==='staff' || $role==='admin') ? ($_POST['staff_id'] ?? $user['staff_id']) : '') ?>">

                </div>
                <div class="col-md-6">
                    <label class="form-label">Designation</label>
<input type="text" name="designation" class="form-control"
       value="<?= h(($role==='staff' || $role==='admin') ? ($_POST['designation'] ?? $user['designation']) : '') ?>">

                </div>
                <div class="col-md-12">
                    <label class="form-label">Department / Section</label>
<input type="text" name="department_staff" class="form-control"
       value="<?= h(($role==='staff' || $role==='admin') ? ($_POST['department_staff'] ?? $user['department_staff']) : '') ?>">

                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-address-card"></i> Additional Information</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" value="<?= h($_POST['phone'] ?? $user['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Select --</option>
                        <option value="Male" <?= ($_POST['gender'] ?? $user['gender'])==='Male'?'selected':'' ?>>Male</option>
                        <option value="Female" <?= ($_POST['gender'] ?? $user['gender'])==='Female'?'selected':'' ?>>Female</option>
                        <option value="Other" <?= ($_POST['gender'] ?? $user['gender'])==='Other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-lock"></i> Change Password (Optional)</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_confirm" class="form-control" placeholder="Re-enter if changing">
                </div>
            </div>
        </div>

        <!-- Profile Picture -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-camera"></i> Profile Picture</h5>

            <div class="avatar-upload-area" id="avatarDropZone">
                <i class="fas fa-user-circle avatar-upload-icon"></i>
                <p class="mt-3"><strong>Drag & Drop</strong> new photo or <strong>click to browse</strong></p>
                <small class="text-muted">JPG, PNG, GIF, WEBP • Max 10MB</small>
            </div>
            <input type="file" name="profile_image" id="avatarInput" class="hidden-input" accept="image/*">

            <!-- Preview of newly selected image -->
            <div class="avatar-preview" id="avatarPreview">
                <img src="" alt="New avatar" id="previewImg">
                <button type="button" class="avatar-preview-remove" id="removeNewAvatar">Remove</button>
            </div>

            <!-- Current avatar (if exists) -->
            <?php if ($user['profile_image_path']): ?>
                <?php $currentAvatar = app_path($user['profile_image_path']); ?>
                <div class="avatar-preview active" id="currentAvatar">
                    <img src="<?= h($currentAvatar) ?>" alt="Current avatar">
                    <button type="button" class="avatar-preview-remove" id="removeCurrentAvatar">Remove Current</button>
                    <input type="hidden" name="remove_profile_image" id="removeFlag" value="0">
                </div>
                <div class="current-info-text">
                    <i class="fas fa-info-circle"></i>
                    Upload a new image to replace, or remove the current one
                </div>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="<?= h(app_path('admin/users')) ?>" class="btn btn-outline-secondary btn-lg"><i class="fas fa-times me-2"></i>Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Update User</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('avatarDropZone');
    const input = document.getElementById('avatarInput');
    const preview = document.getElementById('avatarPreview');
    const img = document.getElementById('previewImg');
    const removeNew = document.getElementById('removeNewAvatar');
    const currentAvatar = document.getElementById('currentAvatar');
    const removeCurrent = document.getElementById('removeCurrentAvatar');
    const removeFlag = document.getElementById('removeFlag');

    // Drag & Drop + Click
    dropZone.addEventListener('click', () => input.click());
    ['dragover','dragenter'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('dragover'); }));
    dropZone.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            input.files = e.dataTransfer.files;
            handleFile(file);
        }
    });
    input.addEventListener('change', () => { if (input.files[0]) handleFile(input.files[0]); });

    function handleFile(file) {
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            preview.classList.add('active');
            dropZone.style.display = 'none';
            if (currentAvatar) currentAvatar.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    if (removeNew) {
        removeNew.addEventListener('click', () => {
            input.value = '';
            preview.classList.remove('active');
            img.src = '';
            dropZone.style.display = 'block';
            if (currentAvatar) currentAvatar.style.display = 'block';
        });
    }

    if (removeCurrent) {
        removeCurrent.addEventListener('click', () => {
            currentAvatar.remove();
            if (removeFlag) removeFlag.value = '1';
        });
    }

    const studentFields = document.getElementById('student-fields');
    const staffFields = document.getElementById('staff-fields');

    function setVisibility(el, show) {
        if (!el) return;
        el.classList.toggle('is-hidden', !show);
        el.classList.toggle('is-visible', show);
    }

    // Role toggle
    document.querySelectorAll('input[name="role"]').forEach(r => {
        r.addEventListener('change', () => {
            if (r.checked) {
                setVisibility(studentFields, r.value === 'student');
                setVisibility(staffFields, r.value === 'staff' || r.value === 'admin');
            }
        });
    });

    // Trigger on load for current role
    const currentRoleInput = document.querySelector(`input[name="role"][value="<?= $role ?>"]`);
    if (currentRoleInput) {
        currentRoleInput.dispatchEvent(new Event('change'));
    }

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
