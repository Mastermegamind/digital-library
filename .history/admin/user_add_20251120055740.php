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

    // Role-specific validation
    if ($role === 'student') {
        if (empty(trim($_POST['reg_no'] ?? ''))) $errors[] = 'Registration Number is required for students.';
        if (empty($_POST['enrollment_year'] ?? '')) $errors[] = 'Enrollment Year is required for students.';
    }
    if ($role === 'staff') {
        if (empty(trim($_POST['staff_id'] ?? ''))) $errors[] = 'Staff ID is required for staff members.';
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
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $errors[] = 'Email or ID already exists.';
            } else {
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>

<!-- Same styles as before + add this script at the end -->
<script>
document.querySelectorAll('input[name="role"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('student-fields').style.display = this.value === 'student' ? 'block' : 'none';
        document.getElementById('staff-fields').style.display = this.value === 'staff' ? 'block' : 'none';
    });
});
</script>

<!-- Form content same as previous messages, but with added Staff role option and conditional fields -->
<!-- (Insert the full form HTML from previous response here – too long to repeat, but includes student-fields, staff-fields, and role selector with 3 options) -->

<?php include __DIR__ . '/../includes/footer.php'; ?>