<?php
// includes/functions.php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('APP_RUNTIME_READY')) {
    define('APP_RUNTIME_READY', true);

    if (!empty($APP_DEBUG)) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
        ini_set('display_errors', '0');
    }

    if (PHP_SAPI !== 'cli') {
        apply_cache_headers();
    }

    if (empty($APP_DEBUG)) {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function ($exception) {
            log_error('Unhandled exception', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            http_response_code(500);
            render_error_page(500, 'Server Error', 'Something went wrong. Please try again later.');
            exit;
        });
    }
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

function apply_cache_headers(): void {
    if (headers_sent()) {
        return;
    }
    global $APP_DEBUG, $APP_CACHE_MAX_AGE;
    if (!empty($APP_DEBUG)) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    } else {
        $maxAge = max(0, (int)($APP_CACHE_MAX_AGE ?? 0));
        header('Cache-Control: private, max-age=' . $maxAge);
    }
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
    if (!should_log($level)) {
        return;
    }
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

function log_debug(string $message, array $context = []): void {
    app_log('debug', $message, $context);
}

function should_log(string $level): bool {
    global $APP_LOG_LEVEL;
    $map = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];
    $configured = strtolower((string)($APP_LOG_LEVEL ?? 'info'));
    $configuredValue = $map[$configured] ?? 1;
    $levelValue = $map[strtolower($level)] ?? 1;
    return $levelValue >= $configuredValue;
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

function handle_file_upload(
    ?array $file,
    array $allowedExtensions,
    string $uploadDir,
    string $publicPrefix = 'uploads',
    int $maxBytes = 52428800,
    ?array $imageOptions = null
): array {
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

    if ($imageOptions && in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
        $maxWidth = (int)($imageOptions['max_width'] ?? 0);
        $maxHeight = (int)($imageOptions['max_height'] ?? 0);
        $maxPixels = (int)($imageOptions['max_pixels'] ?? 0);
        $quality = (int)($imageOptions['quality'] ?? 85);

        $info = @getimagesize($targetPath);
        if (!$info) {
            @unlink($targetPath);
            $result['error'] = 'Uploaded image file is invalid.';
            log_warning('Invalid image upload', ['path' => $targetPath, 'original_name' => $file['name'] ?? null]);
            return $result;
        }

        $width = (int)$info[0];
        $height = (int)$info[1];
        if ($maxPixels > 0 && ($width * $height) > $maxPixels && !extension_loaded('gd')) {
            @unlink($targetPath);
            $result['error'] = 'Image is too large to process on the server.';
            log_warning('Image too large for processing', ['path' => $targetPath, 'width' => $width, 'height' => $height]);
            return $result;
        }

        if (($maxWidth > 0 && $width > $maxWidth) || ($maxHeight > 0 && $height > $maxHeight)) {
            $resized = resize_image_in_place($targetPath, $maxWidth, $maxHeight, $quality);
            if (!$resized) {
                log_warning('Image resize failed', ['path' => $targetPath, 'width' => $width, 'height' => $height]);
            }
        }
    }

    $publicPrefix = trim($publicPrefix, '/');
    $result['path'] = $publicPrefix === '' ? $newName : $publicPrefix . '/' . $newName;
    log_info('File uploaded', [
        'path' => $result['path'],
        'original_name' => $file['name'] ?? null,
        'size_bytes' => $size,
    ]);

    return $result;
}

function resize_image_in_place(string $filePath, int $maxWidth, int $maxHeight, int $quality = 85): bool {
    if ($maxWidth <= 0 || $maxHeight <= 0) {
        return true;
    }
    if (!extension_loaded('gd')) {
        return false;
    }

    $info = @getimagesize($filePath);
    if (!$info) {
        return false;
    }

    [$srcWidth, $srcHeight, $type] = $info;
    if ($srcWidth <= $maxWidth && $srcHeight <= $maxHeight) {
        return true;
    }

    if ($type === IMAGETYPE_GIF) {
        return true;
    }

    $scale = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);
    $newWidth = max(1, (int)round($srcWidth * $scale));
    $newHeight = max(1, (int)round($srcHeight * $scale));

    $srcImage = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($filePath),
        IMAGETYPE_PNG => @imagecreatefrompng($filePath),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
        default => false,
    };

    if (!$srcImage) {
        return false;
    }

    $dstImage = imagecreatetruecolor($newWidth, $newHeight);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
    }

    if (!imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight)) {
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        return false;
    }

    $result = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($dstImage, $filePath, max(60, min(95, $quality))),
        IMAGETYPE_PNG => imagepng($dstImage, $filePath, 6),
        IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($dstImage, $filePath, max(60, min(95, $quality))) : false,
        default => false,
    };

    imagedestroy($srcImage);
    imagedestroy($dstImage);

    return (bool)$result;
}

function rate_limit_storage_dir(): string {
    $dir = __DIR__ . '/../logs/rate_limits';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function rate_limit_path(string $key): string {
    return rate_limit_storage_dir() . '/' . sha1($key) . '.json';
}

function rate_limit_read(string $key): array {
    $path = rate_limit_path($key);
    if (!is_file($path)) {
        return ['count' => 0, 'window_start' => time(), 'locked_until' => 0];
    }
    $json = @file_get_contents($path);
    $data = json_decode($json ?: '', true);
    if (!is_array($data)) {
        return ['count' => 0, 'window_start' => time(), 'locked_until' => 0];
    }
    return [
        'count' => (int)($data['count'] ?? 0),
        'window_start' => (int)($data['window_start'] ?? time()),
        'locked_until' => (int)($data['locked_until'] ?? 0),
    ];
}

function rate_limit_write(string $key, array $data): void {
    $path = rate_limit_path($key);
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

function rate_limit_is_blocked(string $key): array {
    $data = rate_limit_read($key);
    $now = time();
    $lockedUntil = (int)($data['locked_until'] ?? 0);
    if ($lockedUntil > $now) {
        return ['blocked' => true, 'retry_after' => $lockedUntil - $now];
    }
    return ['blocked' => false, 'retry_after' => 0];
}

function rate_limit_register_failure(string $key, int $limit, int $windowSeconds, int $lockSeconds): array {
    $data = rate_limit_read($key);
    $now = time();

    if (!empty($data['locked_until']) && $data['locked_until'] > $now) {
        return ['blocked' => true, 'retry_after' => $data['locked_until'] - $now];
    }

    if (($now - (int)$data['window_start']) >= $windowSeconds) {
        $data['window_start'] = $now;
        $data['count'] = 0;
    }

    $data['count'] = (int)$data['count'] + 1;
    if ($data['count'] >= $limit) {
        $data['locked_until'] = $now + max(60, $lockSeconds);
    }

    rate_limit_write($key, $data);

    $blocked = !empty($data['locked_until']) && $data['locked_until'] > $now;
    return ['blocked' => $blocked, 'retry_after' => $blocked ? ($data['locked_until'] - $now) : 0];
}

function rate_limit_reset(string $key): void {
    $path = rate_limit_path($key);
    if (is_file($path)) {
        @unlink($path);
    }
}

function render_error_page(int $code, string $title, string $message): void {
    $error_code = $code;
    $error_title = $title;
    $error_message = $message;
    require __DIR__ . '/../error.php';
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
