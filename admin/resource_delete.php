<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$csrf = $_GET['csrf'] ?? '';

if (!verify_csrf_token($csrf)) {
    log_warning('Resource delete CSRF validation failed', ['resource_id' => $id]);
    flash_message('error', 'Invalid session token.');
    header('Location: ' . app_path('admin/resources'));
    exit;
}

$stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id = :id");
$stmt->execute([':id' => $id]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if ($res) {
    if (!empty($res['file_path'])) {
        delete_uploaded_file($res['file_path']);
    }
    if (!empty($res['cover_image_path'])) {
        delete_uploaded_file($res['cover_image_path']);
    }
    $del = $pdo->prepare("DELETE FROM resources WHERE id = :id");
    $del->execute([':id' => $id]);
    log_info('Resource deleted', ['resource_id' => $id]);
    flash_message('success', 'Resource deleted.');
} else {
    log_warning('Resource delete attempted on missing resource', ['resource_id' => $id]);
    flash_message('error', 'Resource not found.');
}

header('Location: ' . app_path('admin/resources'));
exit;
