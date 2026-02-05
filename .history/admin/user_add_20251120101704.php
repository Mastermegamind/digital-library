<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors = [];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid session token.';
        log_warning('User add CSRF validation failed');
    }

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    $allowedRoles = ['admin', 'student', 'staff'];
    if (!in_array($role, $allowedRoles, true)) $errors[] = 'Invalid role selected.';

    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($role === 'student') {
        if (empty(trim($_POST['reg_no'] ?? ''))) $errors[] = 'Registration Number is required.';
        if (empty($_POST['enrollment_year'] ?? '')) $errors[] = 'Enrollment Year is required.';
    }
    if ($role === 'staff') {
        if (empty(trim($_POST['staff_id'] ?? ''))) $errors[] = 'Staff ID is required.';
    }

    if (empty($errors)) {
        $avatarUpload = handle_file_upload($_FILES['profile_image'] ?? null, ['jpg','jpeg','png','gif','webp'], __DIR__.'/../uploads/avatars', 'uploads/avatars', 10*1024*1024);
        if ($avatarUpload['error']) {
            $errors[] = $avatarUpload['error'];
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, profile_image_path, role) VALUES (:name, :email, :pw, :profile, :role)");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':pw' => password_hash($password, PASSWORD_DEFAULT),
                ':profile' => $avatarUpload['path'] ?? null,
                ':role' => $role
            ]);
            $newUserId = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO user_profiles (user_id, reg_no, enrollment_year, department, staff_id, designation, department_staff, phone, gender) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([
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
            log_info('User created', ['new_user_id' => $newUserId]);
            flash_message('success', 'User created successfully!');
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $errors[] = 'Email, Registration Number, or Staff ID already exists.';
            } else {
                log_error('User add error', ['exception' => $e]);
                $errors[] = 'An error occurred. Please try again.';
            }
        }
    }
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>

<style>
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

    /* Drag & Drop Avatar */
    .avatar-upload-area{border:2px dashed var(--border-color);border-radius:16px;padding:2.5rem;text-align:center;transition:all .3s ease;background:rgba(37,99,235,0.02);cursor:pointer;position:relative}
    .avatar-upload-area:hover{border-color:var(--primary-color);background:rgba(37,99,235,0.05)}
    .avatar-upload-area.dragover{border-color:var(--primary-color);background:rgba(37,99,235,0.1);border-style:solid}
    .avatar-upload-icon{font-size:4rem;color:var(--primary-color);opacity:0.5;transition:all .3s ease}
    .avatar-upload-area:hover .avatar-upload-icon{opacity:0.8;transform:scale(1.1)}
    .avatar-preview{margin-top:1.5rem;display:none;border-radius:50%;overflow:hidden;box-shadow:0 8px 25px rgba(0,0,0,0.2);width:180px;height:180px;margin:0 auto;position:relative}
    .avatar-preview.active{display:block}
    .avatar-preview img{width:100%;height:100%;object-fit:cover}
    .avatar-preview-remove{position:absolute;top:10px;right:10px;background:rgba(239,68,68,0.9);color:white;border:none;padding:.5rem 1rem;border-radius:8px;cursor:pointer;font-weight:600}
    .hidden-input{display:none}
    .role-selector{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
    .role-option{border:2px solid var(--border-color);border-radius:12px;padding:1.5rem;cursor:pointer;transition:all .3s ease;text-align:center}
    .role-option:hover{border-color:var(--primary-color);background:rgba(37,99,235,0.03)}
    .role-option input[type=radio]{display:none}
    .role-option input[type=radio]:checked ~ .role-content .role-icon{background:linear-gradient(135deg,var(--primary-color),var(--accent-color));color:white}
    .role-icon{width:60px;height:60px;margin:0 auto 1rem;background:rgba(37,99,235,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.75rem;transition:all .3s ease}
</style>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color:var(--primary-color)"><i class="fas fa-user-plus me-3"></i>Add New User</h2>
            <p class="text-muted mb-0">Create a new user account</p>
        </div>
        <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
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

        <!-- Personal Info -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-user"></i> Personal Information</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($name) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?= h($email) ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Role <span class="required">*</span></label>
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

        <!-- Role-specific fields (same as before) -->
        <div class="form-section" id="student-fields" style="display:<?= $role==='student'?'block':'none' ?>;">
            <h5 class="section-title"><i class="fas fa-id-card"></i> Student Details</h5>
            <!-- ... your student fields ... -->
        </div>
        <div class="form-section" id="staff-fields" style="display:<?= $role==='staff'?'block':'none' ?>;">
            <h5 class="section-title"><i class="fas fa-chalkboard-teacher"></i> Staff Details</h5>
            <!-- ... your staff fields ... -->
        </div>

        <!-- Avatar Upload -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-camera"></i> Profile Picture (Optional)</h5>
            <div class="avatar-upload-area" id="avatarDropZone">
                <i class="fas fa-user-circle avatar-upload-icon"></i>
                <p class="mt-3"><strong>Drag & Drop</strong> photo or <strong>click to browse</strong></p>
                <small class="text-muted">JPG, PNG, GIF, WEBP • Max 10MB</small>
            </div>
            <input type="file" name="profile_image" id="avatarInput" class="hidden-input" accept="image/*">

            <div class="avatar-preview" id="avatarPreview">
                <img src="" alt="Preview" id="previewImg">
                <button type="button" class="avatar-preview-remove" id="removeAvatar">Remove</button>
            </div>
        </div>

        <!-- Password & Buttons -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-lock"></i> Security</h5>
            <div class="row g-4">
                <div class="col-md-6"><input type="password" name="password" class="form-control" required placeholder="Password (8+ chars)"></div>
                <div class="col-md-6"><input type="password" name="password_confirm" class="form-control" required placeholder="Confirm Password"></div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="users.php" class="btn btn-outline-secondary btn-lg"><i class="fas fa-times me-2"></i>Cancel</a>
            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-user-plus me-2"></i>Create User</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('avatarDropZone');
    const input = document.getElementById('avatarInput');
    const preview = document.getElementById('avatarPreview');
    const img = document.getElementById('previewImg');
    const removeBtn = document.getElementById('removeAvatar');

    dropZone.addEventListener('click', () => input.click());
    ['dragover','dragenter'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('dragover'); }));
    dropZone.addEventListener('drop', e => { if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; handleFile(e.dataTransfer.files[0]); } });
    input.addEventListener('change', () => { if (input.files[0]) handleFile(input.files[0]); });

    function handleFile(file) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; preview.classList.add('active'); dropZone.style.display = 'none'; };
        reader.readAsDataURL(file);
    }

    removeBtn.addEventListener('click', () => {
        input.value = ''; preview.classList.remove('active'); img.src = ''; dropZone.style.display = 'block';
    });

    // Role toggle
    document.querySelectorAll('input[name="role"]').forEach(r => r.addEventListener('change', () => {
        document.getElementById('student-fields').style.display = r.value === 'student' ? 'block' : 'none';
        document.getElementById('staff-fields').style.display = r.value === 'staff' ? 'block' : 'none';
    }));
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>