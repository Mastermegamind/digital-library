<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$csrf = $_POST['csrf_token'] ?? ($_GET['csrf'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_warning('User delete attempted via GET', ['user_id' => $id]);
}

if (!verify_csrf_token($csrf)) {
    flash_message('error', 'Invalid session token.');
    log_warning('User delete CSRF validation failed', ['target_user_id' => $id]);
    header('Location: ' . app_path('admin/users'));
    exit;
}

if ($id <= 0) {
    flash_message('error', 'Invalid user selected.');
    log_warning('User delete invalid ID provided', ['target_user_id' => $id]);
    header('Location: ' . app_path('admin/users'));
    exit;
}

if ($id === (int) current_user()['id']) {
    flash_message('error', 'You cannot delete your own account while logged in.');
    log_warning('User delete attempted on self', ['user_id' => $id]);
    header('Location: ' . app_path('admin/users'));
    exit;
}

$stmt = $pdo->prepare("SELECT id, role, profile_image_path FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    flash_message('error', 'User not found.');
    log_warning('User delete attempted on missing user', ['target_user_id' => $id]);
    header('Location: ' . app_path('admin/users'));
    exit;
}

if ($user['role'] === 'admin') {
    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount <= 1) {
        flash_message('error', 'Cannot delete the last admin account.');
        log_warning('User delete prevented last admin removal', ['target_user_id' => $id]);
        header('Location: ' . app_path('admin/users'));
        exit;
    }
}

$profilePath = $user['profile_image_path'] ?? null;
$del = $pdo->prepare("DELETE FROM users WHERE id = :id");
$del->execute([':id' => $id]);
if ($profilePath) {
    delete_uploaded_file($profilePath);
}

log_info('User deleted', ['target_user_id' => $id]);

flash_message('success', 'User deleted successfully.');
header('Location: ' . app_path('admin/users'));
exit;
