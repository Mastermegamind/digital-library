<?php
require_once __DIR__ . '/includes/auth.php';
$legacyToken = $_GET['token'] ?? '';
if ($legacyToken !== '') {
    redirect_legacy_php('secure/' . $legacyToken, ['token' => null]);
}
require_login();

$token = $legacyToken;
$tokenData = $token ? get_resource_token($token) : null;

if (!$tokenData) {
    log_warning('Secure file token invalid', ['token' => $token]);
    http_response_code(403);
    exit('Invalid or expired token.');
}

$relativePath = ltrim(str_replace('\\', '/', $tokenData['path']), '/');
$baseDir = realpath(__DIR__);
$fullPath = realpath($baseDir . '/' . $relativePath);

if (!$fullPath || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
    log_warning('Secure file missing path', ['token' => $token, 'path' => $tokenData['path'] ?? null]);
    http_response_code(404);
    exit('File not found.');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = $tokenData['mime'] ?? mime_content_type($fullPath) ?: 'application/octet-stream';

switch ($ext) {
    case 'pdf':
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
        break;
    case 'mp4':
        header('Content-Type: video/mp4');
        break;
    case 'webm':
        header('Content-Type: video/webm');
        break;
    case 'epub':
        header('Content-Type: application/epub+zip');
        break;
    case 'doc':
        header('Content-Type: application/msword');
        break;
    case 'docx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        break;
    case 'ppt':
        header('Content-Type: application/vnd.ms-powerpoint');
        break;
    case 'pptx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        break;
    case 'xls':
        header('Content-Type: application/vnd.ms-excel');
        break;
    case 'xlsx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        break;
    default:
        header('Content-Type: ' . $mime);
        break;
}

$fileSize = filesize($fullPath);
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Handle range requests for video streaming
if (isset($_SERVER['HTTP_RANGE']) && in_array($ext, ['mp4', 'webm'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
        $start = $matches[1] === '' ? 0 : (int)$matches[1];
        $end = $matches[2] === '' ? $fileSize - 1 : (int)$matches[2];

        if ($start > $end || $start >= $fileSize) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;

        http_response_code(206);
        header("Content-Range: bytes $start-$end/$fileSize");
        header("Content-Length: $length");

        $fp = fopen($fullPath, 'rb');
        fseek($fp, $start);
        echo fread($fp, $length);
        fclose($fp);
        exit;
    }
}

header('Content-Length: ' . $fileSize);
header('X-Accel-Buffering: no');
readfile($fullPath);
exit;
