<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$legacyId = (int)($_GET['id'] ?? 0);
if ($legacyId > 0) {
    redirect_legacy_php('download/' . $legacyId, ['id' => null]);
}

$id = $legacyId;
if ($id <= 0) {
    flash_message('error', 'Invalid resource.');
    header('Location: ' . app_path(''));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    flash_message('error', 'Resource not found.');
    log_warning('Download requested for missing resource', ['resource_id' => $id]);
    header('Location: ' . app_path(''));
    exit;
}

if (!resource_is_visible($resource, current_user())) {
    flash_message('error', 'This resource is not available.');
    header('Location: ' . app_path(''));
    exit;
}

if (empty($resource['file_path'])) {
    flash_message('error', 'This resource has no downloadable file.');
    header('Location: ' . app_path('resource/' . $id));
    exit;
}

$relativePath = ltrim(str_replace('\\', '/', $resource['file_path']), '/');
$baseDir = realpath(__DIR__);
$fullPath = realpath($baseDir . '/' . $relativePath);

if (!$fullPath || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
    flash_message('error', 'File missing on server.');
    log_warning('Download file missing on disk', ['resource_id' => $id, 'path' => $resource['file_path']]);
    header('Location: ' . app_path('resource/' . $id));
    exit;
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
$filename = basename($fullPath);

record_resource_download(current_user()['id'] ?? null, $id);
log_info('Resource file downloaded', ['resource_id' => $id, 'path' => $resource['file_path']]);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Accel-Buffering: no');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
readfile($fullPath);
exit;
