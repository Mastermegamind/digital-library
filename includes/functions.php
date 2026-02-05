<?php
// includes/functions.php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $scriptDir;
}

function app_base_path_prefix(): string {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir === '.' || $scriptDir === '\\') {
        $scriptDir = '';
    }
    $scriptDir = rtrim($scriptDir, '/');
    $base = preg_replace('#/admin$#', '', $scriptDir);
    if ($base === '/' || $base === '.') {
        $base = '';
    }
    if ($base !== '' && $base[0] !== '/') {
        $base = '/' . $base;
    }
    return $base;
}

function app_path(string $relative = ''): string {
    $base = app_base_path_prefix();
    $relative = ltrim($relative, '/');
    if ($relative === '') {
        return $base === '' ? '/' : $base;
    }
    if ($base === '') {
        return '/' . $relative;
    }
    return rtrim($base, '/') . '/' . $relative;
}

function h(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function session_array(string $key): array {
    if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    return $_SESSION[$key];
}

// Simple CSRF
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function flash_message(?string $type = null, ?string $message = null) {
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return;
    }
    if (isset($_SESSION['flash'])) {
        $data = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $data;
    }
    return null;
}

function app_log_file(): string {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }
    return $logDir . '/app.log';
}

function app_log(string $level, string $message, array $context = []): void {
    $baseContext = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'script' => $_SERVER['SCRIPT_NAME'] ?? 'cli',
    ];
    if (!empty($_SESSION['user_id'])) {
        $baseContext['user_id'] = (int)$_SESSION['user_id'];
    }
    $context = array_merge($baseContext, $context);
    $line = sprintf(
        "[%s] [%s] %s | %s%s",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        json_encode($context, JSON_UNESCAPED_SLASHES),
        PHP_EOL
    );
    @file_put_contents(app_log_file(), $line, FILE_APPEND | LOCK_EX);
}

function log_error(string $message, array $context = []): void {
    app_log('error', $message, $context);
}

function log_warning(string $message, array $context = []): void {
    app_log('warning', $message, $context);
}

function log_info(string $message, array $context = []): void {
    app_log('info', $message, $context);
}

function upload_error_message(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the server\'s maximum allowed size.';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder on the server.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write the uploaded file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload.';
        default:
            return 'File upload error (code ' . $code . ').';
    }
}

function handle_file_upload(?array $file, array $allowedExtensions, string $uploadDir, string $publicPrefix = 'uploads', int $maxBytes = 52428800): array {
    $result = ['path' => null, 'error' => null];

    if (empty($file) || (!isset($file['name']) || $file['name'] === '')) {
        return $result;
    }

    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return $result;
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        $message = upload_error_message($errorCode);
        log_error('File upload error', ['error_code' => $errorCode, 'message' => $message, 'original_name' => $file['name'] ?? null]);
        $result['error'] = $message;
        return $result;
    }

    $size = (int)($file['size'] ?? 0);
    if ($size > $maxBytes) {
        $maxMb = max(1, round($maxBytes / 1048576, 1));
        $result['error'] = 'File is too large. Maximum allowed size is ' . $maxMb . ' MB.';
        log_warning('File exceeded size limit', ['size_bytes' => $size, 'limit_bytes' => $maxBytes, 'original_name' => $file['name'] ?? null]);
        return $result;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        $result['error'] = 'Invalid file type. Allowed types: ' . implode(', ', array_map('strtoupper', $allowedExtensions)) . '.';
        log_warning('Invalid file extension', ['extension' => $ext, 'allowed' => $allowedExtensions, 'original_name' => $file['name'] ?? null]);
        return $result;
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $result['error'] = 'Unable to create the upload directory.';
        log_error('Failed to create upload directory', ['upload_dir' => $uploadDir]);
        return $result;
    }

    if (!is_writable($uploadDir) && !@chmod($uploadDir, 0775)) {
        $result['error'] = 'Upload directory is not writable.';
        log_error('Upload directory not writable', ['upload_dir' => $uploadDir]);
        return $result;
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $result['error'] = 'Invalid uploaded file.';
        log_error('Invalid uploaded temporary file', ['tmp_name' => $file['tmp_name'] ?? null]);
        return $result;
    }

    $newName = uniqid('file_', true) . '.' . $ext;
    $targetPath = rtrim($uploadDir, "/\\") . DIRECTORY_SEPARATOR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $result['error'] = 'Failed to move the uploaded file.';
        log_error('move_uploaded_file failed', ['target_path' => $targetPath]);
        return $result;
    }

    @chmod($targetPath, 0664);

    $publicPrefix = trim($publicPrefix, '/');
    $result['path'] = $publicPrefix === '' ? $newName : $publicPrefix . '/' . $newName;
    log_info('File uploaded', [
        'path' => $result['path'],
        'original_name' => $file['name'] ?? null,
        'size_bytes' => $size,
    ]);

    return $result;
}

function delete_uploaded_file(?string $relativePath): void {
    if (empty($relativePath)) {
        return;
    }
    $cleanPath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($cleanPath === '' || strpos($cleanPath, 'uploads/') !== 0) {
        return;
    }
    $baseDir = dirname(__DIR__);
    $fullPath = $baseDir . '/' . $cleanPath;
    if (is_file($fullPath)) {
        if (!@unlink($fullPath)) {
            log_warning('Failed to delete uploaded file', ['path' => $fullPath]);
        } else {
            log_info('Deleted uploaded file', ['path' => $fullPath]);
        }
    }
}

function issue_resource_token(int $resourceId, string $filePath, int $ttlSeconds = 300, ?string $mimeHint = null): string {
    $token = bin2hex(random_bytes(16));
    if (!isset($_SESSION['resource_tokens']) || !is_array($_SESSION['resource_tokens'])) {
        $_SESSION['resource_tokens'] = [];
    }
    $_SESSION['resource_tokens'][$token] = [
        'resource_id' => $resourceId,
        'path' => $filePath,
        'mime' => $mimeHint,
        'expires' => time() + max(30, $ttlSeconds),
    ];
    return $token;
}

function get_resource_token(string $token): ?array {
    if (empty($_SESSION['resource_tokens'][$token])) {
        return null;
    }
    $entry = $_SESSION['resource_tokens'][$token];
    if (($entry['expires'] ?? 0) < time()) {
        unset($_SESSION['resource_tokens'][$token]);
        return null;
    }
    return $entry;
}

function revoke_resource_token(string $token): void {
    unset($_SESSION['resource_tokens'][$token]);
}
