<?php
// includes/functions.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

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

function app_url(string $relative = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . app_path($relative);
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

function is_legacy_php_request(): bool {
    if (PHP_SAPI === 'cli') {
        return false;
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        return false;
    }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    if ($path === '') {
        return false;
    }
    return str_ends_with(strtolower($path), '.php');
}

function redirect_legacy_php(string $cleanPath, array $queryOverrides = [], bool $preserveQuery = true): void {
    if (!is_legacy_php_request() || headers_sent()) {
        return;
    }
    $target = app_url($cleanPath);
    $query = $preserveQuery ? $_GET : [];
    foreach ($queryOverrides as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target, true, 301);
    exit;
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
        unset($srcImage, $dstImage);
        return false;
    }

    $result = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($dstImage, $filePath, max(60, min(95, $quality))),
        IMAGETYPE_PNG => imagepng($dstImage, $filePath, 6),
        IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($dstImage, $filePath, max(60, min(95, $quality))) : false,
        default => false,
    };

    unset($srcImage, $dstImage);

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
        'created' => time(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
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

// =============================================
// BOOKMARK HELPER FUNCTIONS
// =============================================

function get_user_bookmarks(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT resource_id FROM user_bookmarks WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'resource_id');
}

function is_bookmarked(int $userId, int $resourceId): bool {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM user_bookmarks WHERE user_id = :user_id AND resource_id = :resource_id LIMIT 1");
    $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
    return (bool)$stmt->fetch();
}

function toggle_bookmark(int $userId, int $resourceId): bool {
    global $pdo;
    if (is_bookmarked($userId, $resourceId)) {
        $stmt = $pdo->prepare("DELETE FROM user_bookmarks WHERE user_id = :user_id AND resource_id = :resource_id");
        $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
        log_info('Bookmark removed', ['resource_id' => $resourceId]);
        return false;
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_bookmarks (user_id, resource_id) VALUES (:user_id, :resource_id)");
        $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
        log_info('Bookmark added', ['resource_id' => $resourceId]);
        return true;
    }
}

// =============================================
// READING PROGRESS HELPER FUNCTIONS
// =============================================

function get_user_progress(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT resource_id, progress_percent, last_position FROM reading_progress WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[$row['resource_id']] = [
            'percent' => (float)$row['progress_percent'],
            'position' => (int)$row['last_position']
        ];
    }
    return $result;
}

function get_resource_progress(int $userId, int $resourceId): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM reading_progress WHERE user_id = :user_id AND resource_id = :resource_id LIMIT 1");
    $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function update_reading_progress(int $userId, int $resourceId, int $position, float $percent, ?int $totalPages = null): void {
    global $pdo;

    // Clamp percent between 0 and 100
    $percent = max(0, min(100, $percent));

    $stmt = $pdo->prepare("SELECT id FROM reading_progress WHERE user_id = :user_id AND resource_id = :resource_id LIMIT 1");
    $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $sql = "UPDATE reading_progress SET last_position = :position, progress_percent = :percent, last_viewed_at = CURRENT_TIMESTAMP";
        $params = [':position' => $position, ':percent' => $percent, ':user_id' => $userId, ':resource_id' => $resourceId];
        if ($totalPages !== null) {
            $sql .= ", total_pages = :total_pages";
            $params[':total_pages'] = $totalPages;
        }
        $sql .= " WHERE user_id = :user_id AND resource_id = :resource_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("INSERT INTO reading_progress (user_id, resource_id, last_position, progress_percent, total_pages) VALUES (:user_id, :resource_id, :position, :percent, :total_pages)");
        $stmt->execute([
            ':user_id' => $userId,
            ':resource_id' => $resourceId,
            ':position' => $position,
            ':percent' => $percent,
            ':total_pages' => $totalPages
        ]);
    }

    log_debug('Reading progress updated', ['resource_id' => $resourceId, 'position' => $position, 'percent' => $percent]);
}

function record_resource_view(int $userId, int $resourceId): void {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM reading_progress WHERE user_id = :user_id AND resource_id = :resource_id LIMIT 1");
    $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE reading_progress SET last_viewed_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND resource_id = :resource_id");
        $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO reading_progress (user_id, resource_id, last_position, progress_percent) VALUES (:user_id, :resource_id, 0, 0)");
        $stmt->execute([':user_id' => $userId, ':resource_id' => $resourceId]);
    }

    // Record view analytics (best-effort)
    try {
        $sessionId = session_id() ?: null;
        $stmt = $pdo->prepare("INSERT INTO resource_views (resource_id, user_id, session_id) VALUES (:resource_id, :user_id, :session_id)");
        $stmt->execute([
            ':resource_id' => $resourceId,
            ':user_id' => $userId,
            ':session_id' => $sessionId,
        ]);
    } catch (Throwable $e) {
        log_debug('Resource view analytics insert failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
    }
}

function record_resource_download(?int $userId, int $resourceId): void {
    global $pdo;
    try {
        $sessionId = session_id() ?: null;
        $stmt = $pdo->prepare("INSERT INTO resource_downloads (resource_id, user_id, session_id) VALUES (:resource_id, :user_id, :session_id)");
        $stmt->execute([
            ':resource_id' => $resourceId,
            ':user_id' => $userId,
            ':session_id' => $sessionId,
        ]);
    } catch (Throwable $e) {
        log_debug('Resource download analytics insert failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
    }
}

// =============================================
// SEARCH + RECOMMENDATION HELPERS
// =============================================

function normalize_tag_name(string $tag): string {
    $tag = trim($tag);
    $tag = preg_replace('/\s+/', ' ', $tag);
    return $tag ?? '';
}

function tag_slug(string $tag): string {
    $tag = strtolower(trim($tag));
    $tag = preg_replace('/[^a-z0-9\s-]/', '', $tag);
    $tag = preg_replace('/\s+/', '-', $tag);
    $tag = preg_replace('/-+/', '-', $tag);
    return trim($tag, '-');
}

function parse_tag_list(string $tagList): array {
    $raw = array_map('trim', explode(',', $tagList));
    $tags = [];
    foreach ($raw as $tag) {
        $tag = normalize_tag_name($tag);
        if ($tag === '') {
            continue;
        }
        $tags[] = $tag;
    }
    return array_values(array_unique($tags));
}

function get_tag_ids(array $tags): array {
    global $pdo, $DB_DRIVER;
    $tagIds = [];
    foreach ($tags as $tag) {
        $name = normalize_tag_name($tag);
        $slug = tag_slug($name);
        if ($name === '' || $slug === '') {
            continue;
        }
        $stmt = $pdo->prepare("SELECT id FROM tags WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            $tagIds[] = (int)$existing;
            continue;
        }

        if ($DB_DRIVER === 'mysql') {
            $insert = $pdo->prepare("INSERT IGNORE INTO tags (name, slug) VALUES (:name, :slug)");
            $insert->execute([':name' => $name, ':slug' => $slug]);
        } else {
            $insert = $pdo->prepare("INSERT OR IGNORE INTO tags (name, slug) VALUES (:name, :slug)");
            $insert->execute([':name' => $name, ':slug' => $slug]);
        }

        $stmt = $pdo->prepare("SELECT id FROM tags WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $newId = $stmt->fetchColumn();
        if ($newId) {
            $tagIds[] = (int)$newId;
        }
    }
    return array_values(array_unique($tagIds));
}

function set_resource_tags(int $resourceId, array $tags): void {
    global $pdo, $DB_DRIVER;
    $tagIds = get_tag_ids($tags);

    $pdo->prepare("DELETE FROM resource_tags WHERE resource_id = :rid")->execute([':rid' => $resourceId]);
    if (empty($tagIds)) {
        return;
    }

    if ($DB_DRIVER === 'mysql') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO resource_tags (resource_id, tag_id) VALUES (:rid, :tid)");
    } else {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO resource_tags (resource_id, tag_id) VALUES (:rid, :tid)");
    }
    foreach ($tagIds as $tagId) {
        $stmt->execute([':rid' => $resourceId, ':tid' => $tagId]);
    }
}

function get_resource_tags(int $resourceId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT t.name
                           FROM resource_tags rt
                           JOIN tags t ON rt.tag_id = t.id
                           WHERE rt.resource_id = :rid
                           ORDER BY t.name ASC");
    $stmt->execute([':rid' => $resourceId]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
}

function get_tags_for_resources(array $resourceIds): array {
    global $pdo;
    $resourceIds = array_values(array_unique(array_filter(array_map('intval', $resourceIds))));
    if (empty($resourceIds)) {
        return [];
    }
    $placeholders = [];
    $params = [];
    foreach ($resourceIds as $i => $rid) {
        $ph = ':id' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $rid;
    }
    $sql = "SELECT rt.resource_id, t.name
            FROM resource_tags rt
            JOIN tags t ON rt.tag_id = t.id
            WHERE rt.resource_id IN (" . implode(',', $placeholders) . ")
            ORDER BY t.name ASC";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $rid = (int)$row['resource_id'];
        $map[$rid][] = $row['name'];
    }
    return $map;
}

function log_search_query(?int $userId, string $query, array $filters, int $resultsCount): void {
    global $pdo;
    $query = trim($query);
    $filters = array_filter($filters, fn($v) => $v !== '' && $v !== null);
    if ($query === '' && empty($filters)) {
        return;
    }
    $filtersJson = !empty($filters) ? json_encode($filters, JSON_UNESCAPED_UNICODE) : null;
    try {
        $stmt = $pdo->prepare("INSERT INTO search_logs (user_id, query, filters, results_count) VALUES (:user_id, :query, :filters, :results)");
        $stmt->execute([
            ':user_id' => $userId,
            ':query' => $query !== '' ? $query : null,
            ':filters' => $filtersJson,
            ':results' => $resultsCount,
        ]);
    } catch (Throwable $e) {
        log_debug('Search log insert failed', ['error' => $e->getMessage()]);
    }
}

function get_trending_resources(int $limit = 8, int $days = 7): array {
    global $pdo;
    $limit = max(1, min(50, $limit));
    $days = max(1, min(90, $days));
    $since = date('Y-m-d H:i:s', time() - ($days * 86400));

    $sql = "SELECT r.*, c.name AS category_name,
                   COALESCE(v.view_count, 0) AS view_count,
                   COALESCE(d.download_count, 0) AS download_count
            FROM resources r
            LEFT JOIN (
                SELECT resource_id, COUNT(*) AS view_count
                FROM resource_views
                WHERE created_at >= :since_views
                GROUP BY resource_id
            ) v ON r.id = v.resource_id
            LEFT JOIN (
                SELECT resource_id, COUNT(*) AS download_count
                FROM resource_downloads
                WHERE created_at >= :since_downloads
                GROUP BY resource_id
            ) d ON r.id = d.resource_id
            LEFT JOIN categories c ON r.category_id = c.id
            WHERE r.status = 'approved'
              AND (v.view_count IS NOT NULL OR d.download_count IS NOT NULL)
            ORDER BY (COALESCE(v.view_count, 0) + (2 * COALESCE(d.download_count, 0))) DESC,
                     r.created_at DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':since_views', $since);
    $stmt->bindValue(':since_downloads', $since);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_similar_resources(int $resourceId, int $limit = 4): array {
    global $pdo;
    $limit = max(1, min(12, $limit));
    $scores = [];

    $infoStmt = $pdo->prepare("SELECT category_id, type FROM resources WHERE id = :id LIMIT 1");
    $infoStmt->execute([':id' => $resourceId]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC) ?: ['category_id' => null, 'type' => null];

    $addScore = function (int $rid, float $score) use (&$scores, $resourceId) {
        if ($rid === $resourceId) {
            return;
        }
        if (!isset($scores[$rid])) {
            $scores[$rid] = 0.0;
        }
        $scores[$rid] += $score;
    };

    // Co-viewed resources (weight 2)
    try {
        $stmt = $pdo->prepare("SELECT rv2.resource_id AS rid, COUNT(*) AS cnt
                               FROM resource_views rv1
                               JOIN resource_views rv2 ON rv1.user_id = rv2.user_id AND rv2.resource_id <> :rid
                               JOIN resources r ON r.id = rv2.resource_id
                               WHERE rv1.resource_id = :rid
                                 AND rv1.user_id IS NOT NULL
                                 AND r.status = 'approved'
                               GROUP BY rv2.resource_id
                               ORDER BY cnt DESC
                               LIMIT 50");
        $stmt->execute([':rid' => $resourceId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $addScore((int)$row['rid'], (float)$row['cnt'] * 2.0);
        }
    } catch (Throwable $e) {
        log_debug('Similar resources co-view query failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
    }

    // Co-downloaded resources (weight 3)
    try {
        $stmt = $pdo->prepare("SELECT rd2.resource_id AS rid, COUNT(*) AS cnt
                               FROM resource_downloads rd1
                               JOIN resource_downloads rd2 ON rd1.user_id = rd2.user_id AND rd2.resource_id <> :rid
                               JOIN resources r ON r.id = rd2.resource_id
                               WHERE rd1.resource_id = :rid
                                 AND rd1.user_id IS NOT NULL
                                 AND r.status = 'approved'
                               GROUP BY rd2.resource_id
                               ORDER BY cnt DESC
                               LIMIT 50");
        $stmt->execute([':rid' => $resourceId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $addScore((int)$row['rid'], (float)$row['cnt'] * 3.0);
        }
    } catch (Throwable $e) {
        log_debug('Similar resources co-download query failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
    }

    // Tag overlap (weight 4)
    try {
        $stmt = $pdo->prepare("SELECT rt2.resource_id AS rid, COUNT(*) AS cnt
                               FROM resource_tags rt1
                               JOIN resource_tags rt2 ON rt1.tag_id = rt2.tag_id AND rt2.resource_id <> :rid
                               JOIN resources r ON r.id = rt2.resource_id
                               WHERE rt1.resource_id = :rid
                                 AND r.status = 'approved'
                               GROUP BY rt2.resource_id
                               ORDER BY cnt DESC
                               LIMIT 50");
        $stmt->execute([':rid' => $resourceId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $addScore((int)$row['rid'], (float)$row['cnt'] * 4.0);
        }
    } catch (Throwable $e) {
        log_debug('Similar resources tag overlap query failed', ['resource_id' => $resourceId, 'error' => $e->getMessage()]);
    }

    // Same category (weight 1.5)
    if (!empty($info['category_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM resources WHERE status = 'approved' AND category_id = :cid AND id <> :rid");
        $stmt->execute([':cid' => (int)$info['category_id'], ':rid' => $resourceId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rid) {
            $addScore((int)$rid, 1.5);
        }
    }

    // Same type (weight 1)
    if (!empty($info['type'])) {
        $stmt = $pdo->prepare("SELECT id FROM resources WHERE status = 'approved' AND type = :type AND id <> :rid");
        $stmt->execute([':type' => $info['type'], ':rid' => $resourceId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rid) {
            $addScore((int)$rid, 1.0);
        }
    }

    if (empty($scores)) {
        return [];
    }

    arsort($scores);
    $candidateIds = array_slice(array_keys($scores), 0, $limit);

    $placeholders = [];
    $params = [];
    foreach ($candidateIds as $i => $rid) {
        $ph = ':id' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $rid;
    }

    $sql = "SELECT r.*, c.name AS category_name
            FROM resources r
            LEFT JOIN categories c ON r.category_id = c.id
            WHERE r.id IN (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $order = array_flip($candidateIds);
    usort($rows, function ($a, $b) use ($order) {
        return ($order[$a['id']] ?? 0) <=> ($order[$b['id']] ?? 0);
    });

    return array_slice($rows, 0, $limit);
}

// =============================================
// USER SETTINGS HELPER FUNCTIONS
// =============================================

function get_user_dark_mode(int $userId): bool {
    global $pdo;
    $stmt = $pdo->prepare("SELECT dark_mode FROM user_settings WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (bool)$row['dark_mode'] : false;
}

function set_user_dark_mode(int $userId, bool $darkMode): void {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id FROM user_settings WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $userId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE user_settings SET dark_mode = :dark_mode WHERE user_id = :user_id");
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, dark_mode) VALUES (:user_id, :dark_mode)");
    }
    $stmt->execute([':user_id' => $userId, ':dark_mode' => $darkMode ? 1 : 0]);
}

// =============================================
// APPLICATION SETTINGS
// =============================================

function get_app_setting(string $key, ?string $default = null): ?string {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $default;
    }
    return $row['setting_value'];
}

function set_app_setting(string $key, string $value): void {
    global $pdo, $DB_DRIVER;
    if ($DB_DRIVER === 'mysql') {
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value)
                               VALUES (:key, :value)
                               ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    } else {
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value)
                               VALUES (:key, :value)
                               ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
    }
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function is_registration_enabled(): bool {
    $value = get_app_setting('registration_enabled', '1');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function get_registration_mode(): string {
    $mode = get_app_setting('registration_mode', 'open');
    return in_array($mode, ['open', 'admin_approved'], true) ? $mode : 'open';
}

function is_email_verification_required(): bool {
    $value = get_app_setting('require_email_verification', '1');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function is_inapp_notifications_enabled(): bool {
    $value = get_app_setting('notifications_inapp_enabled', '1');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function is_email_notifications_enabled(): bool {
    $value = get_app_setting('notifications_email_enabled', '0');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function is_phone_notifications_enabled(): bool {
    $value = get_app_setting('notifications_phone_enabled', '0');
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function issue_email_verification_token(int $userId, int $ttlSeconds = 86400): string {
    global $pdo;
    $token = bin2hex(random_bytes(20));
    $expiresAt = date('Y-m-d H:i:s', time() + max(3600, $ttlSeconds));

    $pdo->prepare("DELETE FROM email_verification_tokens WHERE user_id = :uid")
        ->execute([':uid' => $userId]);

    $stmt = $pdo->prepare("INSERT INTO email_verification_tokens (user_id, token, expires_at)
                           VALUES (:uid, :token, :expires_at)");
    $stmt->execute([
        ':uid' => $userId,
        ':token' => $token,
        ':expires_at' => $expiresAt,
    ]);
    return $token;
}

function verify_email_token(string $token): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM email_verification_tokens WHERE token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    if (strtotime($row['expires_at']) < time()) {
        $pdo->prepare("DELETE FROM email_verification_tokens WHERE id = :id")->execute([':id' => $row['id']]);
        return null;
    }

    $pdo->prepare("UPDATE users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = :uid")
        ->execute([':uid' => $row['user_id']]);
    $pdo->prepare("DELETE FROM email_verification_tokens WHERE id = :id")
        ->execute([':id' => $row['id']]);

    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $row['user_id']]);
    return $userStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// =============================================
// NOTIFICATIONS
// =============================================

function create_notification(int $userId, string $type, string $title, string $body = '', ?string $link = null): void {
    global $pdo;
    if (!is_inapp_notifications_enabled()) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, body, link)
                           VALUES (:user_id, :type, :title, :body, :link)");
    $stmt->execute([
        ':user_id' => $userId,
        ':type' => $type,
        ':title' => $title,
        ':body' => $body !== '' ? $body : null,
        ':link' => $link,
    ]);
}

function notify_all_users(string $type, string $title, string $body = '', ?string $link = null, ?int $excludeUserId = null): int {
    global $pdo;
    if (!is_inapp_notifications_enabled()) {
        return 0;
    }
    $sql = "SELECT id FROM users WHERE status = 'active'";
    $params = [];
    if ($excludeUserId !== null) {
        $sql .= " AND id <> :exclude";
        $params[':exclude'] = $excludeUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($userIds)) {
        return 0;
    }
    $insert = $pdo->prepare("INSERT INTO notifications (user_id, type, title, body, link)
                             VALUES (:user_id, :type, :title, :body, :link)");
    $count = 0;
    foreach ($userIds as $uid) {
        $insert->execute([
            ':user_id' => (int)$uid,
            ':type' => $type,
            ':title' => $title,
            ':body' => $body !== '' ? $body : null,
            ':link' => $link,
        ]);
        $count++;
    }
    return $count;
}

function notify_all_users_email(string $subject, string $htmlBody, ?string $textBody = null, ?int $excludeUserId = null): int {
    global $pdo;
    if (!mailer_is_configured() || !is_email_notifications_enabled()) {
        return 0;
    }
    $sql = "SELECT id, email, name FROM users WHERE status = 'active' AND email IS NOT NULL AND email <> ''";
    $params = [];
    if ($excludeUserId !== null) {
        $sql .= " AND id <> :exclude";
        $params[':exclude'] = $excludeUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($users)) {
        return 0;
    }
    $sent = 0;
    foreach ($users as $user) {
        if (send_app_mail($user['email'], $subject, $htmlBody, $textBody)) {
            $sent++;
        }
    }
    return $sent;
}

function get_unread_notification_count(int $userId): int {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL");
    $stmt->execute([':user_id' => $userId]);
    return (int)$stmt->fetchColumn();
}

function get_user_notifications(int $userId, int $limit = 10, bool $onlyUnread = false): array {
    global $pdo;
    $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
    if ($onlyUnread) {
        $sql .= " AND read_at IS NULL";
    }
    $sql .= " ORDER BY created_at DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_user_notifications_paginated(int $userId, int $page, int $perPage, string $statusFilter = 'all', ?string $typeFilter = null): array {
    global $pdo;
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ["user_id = :user_id"];
    $params = [':user_id' => $userId];

    if ($statusFilter === 'unread') {
        $where[] = "read_at IS NULL";
    } elseif ($statusFilter === 'read') {
        $where[] = "read_at IS NOT NULL";
    }

    if ($typeFilter !== null && $typeFilter !== '') {
        $where[] = "type = :type";
        $params[':type'] = $typeFilter;
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT * FROM notifications $whereSql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function mark_notification_read(int $userId, int $notificationId): void {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
}

function mark_all_notifications_read(int $userId): void {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND read_at IS NULL");
    $stmt->execute([':user_id' => $userId]);
}

function get_notification_types_for_user(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT type, COUNT(*) AS count
                           FROM notifications
                           WHERE user_id = :user_id
                           GROUP BY type
                           ORDER BY type ASC");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =============================================
// RESOURCE MODERATION + COMMUNITY FEATURES
// =============================================

function resource_is_visible(array $resource, ?array $user): bool {
    $status = $resource['status'] ?? 'approved';
    if ($status === 'approved') {
        return true;
    }
    if ($user && is_admin()) {
        return true;
    }
    if ($user && isset($resource['created_by']) && (int)$resource['created_by'] === (int)$user['id']) {
        return true;
    }
    return false;
}

function get_resource_rating_summary(int $resourceId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
                           FROM resource_reviews
                           WHERE resource_id = :id AND status = 'approved'");
    $stmt->execute([':id' => $resourceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['avg_rating' => 0, 'total_reviews' => 0];
    return [
        'avg_rating' => (float)($row['avg_rating'] ?? 0),
        'total_reviews' => (int)($row['total_reviews'] ?? 0),
    ];
}

function get_user_review(int $resourceId, int $userId): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM resource_reviews WHERE resource_id = :rid AND user_id = :uid LIMIT 1");
    $stmt->execute([':rid' => $resourceId, ':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function save_resource_review(int $resourceId, int $userId, int $rating, ?string $reviewText): string {
    global $pdo, $REVIEWS_REQUIRE_APPROVAL;
    $rating = max(1, min(5, $rating));
    $reviewText = trim((string)$reviewText);

    $status = (!empty($REVIEWS_REQUIRE_APPROVAL) && !is_admin()) ? 'pending' : 'approved';

    $existing = get_user_review($resourceId, $userId);
    if ($existing) {
        $stmt = $pdo->prepare("UPDATE resource_reviews
                               SET rating = :rating, review = :review, status = :status, updated_at = CURRENT_TIMESTAMP
                               WHERE id = :id");
        $stmt->execute([
            ':rating' => $rating,
            ':review' => $reviewText !== '' ? $reviewText : null,
            ':status' => $status,
            ':id' => $existing['id'],
        ]);
        return $status;
    }

    $stmt = $pdo->prepare("INSERT INTO resource_reviews (resource_id, user_id, rating, review, status)
                           VALUES (:rid, :uid, :rating, :review, :status)");
    $stmt->execute([
        ':rid' => $resourceId,
        ':uid' => $userId,
        ':rating' => $rating,
        ':review' => $reviewText !== '' ? $reviewText : null,
        ':status' => $status,
    ]);
    return $status;
}

function get_resource_reviews_for_display(int $resourceId, ?int $userId, bool $isAdmin, string $sort = 'newest'): array {
    global $pdo;
    $orderBy = match ($sort) {
        'oldest' => 'rr.created_at ASC',
        'rating_high' => 'rr.rating DESC, rr.created_at DESC',
        'rating_low' => 'rr.rating ASC, rr.created_at DESC',
        default => 'rr.created_at DESC',
    };
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT rr.*, u.name AS user_name
                               FROM resource_reviews rr
                               JOIN users u ON rr.user_id = u.id
                               WHERE rr.resource_id = :rid
                               ORDER BY $orderBy");
        $stmt->execute([':rid' => $resourceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($userId) {
        $stmt = $pdo->prepare("SELECT rr.*, u.name AS user_name
                               FROM resource_reviews rr
                               JOIN users u ON rr.user_id = u.id
                               WHERE rr.resource_id = :rid AND (rr.status = 'approved' OR (rr.user_id = :uid AND rr.status = 'pending'))
                               ORDER BY $orderBy");
        $stmt->execute([':rid' => $resourceId, ':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("SELECT rr.*, u.name AS user_name
                           FROM resource_reviews rr
                           JOIN users u ON rr.user_id = u.id
                           WHERE rr.resource_id = :rid AND rr.status = 'approved'
                           ORDER BY $orderBy");
    $stmt->execute([':rid' => $resourceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function save_resource_comment(int $resourceId, int $userId, string $content, ?int $parentId = null): string {
    global $pdo, $COMMENTS_REQUIRE_APPROVAL;
    $content = trim($content);
    $status = (!empty($COMMENTS_REQUIRE_APPROVAL) && !is_admin()) ? 'pending' : 'approved';
    $stmt = $pdo->prepare("INSERT INTO resource_comments (resource_id, user_id, parent_id, content, status)
                           VALUES (:rid, :uid, :parent_id, :content, :status)");
    $stmt->execute([
        ':rid' => $resourceId,
        ':uid' => $userId,
        ':parent_id' => $parentId,
        ':content' => $content,
        ':status' => $status,
    ]);
    return $status;
}

function get_resource_comments_for_display(int $resourceId, ?int $userId, bool $isAdmin, string $sort = 'oldest'): array {
    global $pdo;
    $orderBy = $sort === 'newest' ? 'rc.created_at DESC' : 'rc.created_at ASC';
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT rc.*, u.name AS user_name
                               FROM resource_comments rc
                               JOIN users u ON rc.user_id = u.id
                               WHERE rc.resource_id = :rid
                               ORDER BY $orderBy");
        $stmt->execute([':rid' => $resourceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($userId) {
        $stmt = $pdo->prepare("SELECT rc.*, u.name AS user_name
                               FROM resource_comments rc
                               JOIN users u ON rc.user_id = u.id
                               WHERE rc.resource_id = :rid AND (rc.status = 'approved' OR (rc.user_id = :uid AND rc.status = 'pending'))
                               ORDER BY $orderBy");
        $stmt->execute([':rid' => $resourceId, ':uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("SELECT rc.*, u.name AS user_name
                           FROM resource_comments rc
                           JOIN users u ON rc.user_id = u.id
                           WHERE rc.resource_id = :rid AND rc.status = 'approved'
                           ORDER BY $orderBy");
    $stmt->execute([':rid' => $resourceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function report_resource_content(string $contentType, int $contentId, int $reportedBy, ?string $reason = null): bool {
    global $pdo;
    $contentType = strtolower($contentType);
    if (!in_array($contentType, ['comment', 'review'], true)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT id FROM resource_reports WHERE content_type = :type AND content_id = :cid AND reported_by = :uid LIMIT 1");
    $stmt->execute([':type' => $contentType, ':cid' => $contentId, ':uid' => $reportedBy]);
    if ($stmt->fetch()) {
        return false;
    }

    $insert = $pdo->prepare("INSERT INTO resource_reports (content_type, content_id, reported_by, reason)
                             VALUES (:type, :cid, :uid, :reason)");
    $insert->execute([
        ':type' => $contentType,
        ':cid' => $contentId,
        ':uid' => $reportedBy,
        ':reason' => $reason ? trim($reason) : null,
    ]);

    if ($contentType === 'comment') {
        $pdo->prepare("UPDATE resource_comments SET status = CASE WHEN status = 'approved' THEN 'flagged' ELSE status END WHERE id = :id")
            ->execute([':id' => $contentId]);
    } else {
        $pdo->prepare("UPDATE resource_reviews SET status = CASE WHEN status = 'approved' THEN 'flagged' ELSE status END WHERE id = :id")
            ->execute([':id' => $contentId]);
    }

    return true;
}
