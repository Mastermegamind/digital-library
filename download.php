<?php
// Optional: direct download endpoint (admin-only)
require_once __DIR__ . '/includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource || empty($resource['file_path'])) {
    flash_message('error', 'File not found.');
    log_warning('Download requested for missing resource or file path', ['resource_id' => $id]);
header('Location: ' . app_path(''));
    exit;
}

$filePath = __DIR__ . '/' . $resource['file_path'];
if (!is_file($filePath)) {
    flash_message('error', 'File missing on server.');
    log_warning('Download file missing on disk', ['resource_id' => $id, 'path' => $filePath]);
header('Location: ' . app_path(''));
    exit;
}

$basename = basename($filePath);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $basename . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
log_info('Resource file downloaded', ['resource_id' => $id, 'path' => $resource['file_path']]);
exit;
