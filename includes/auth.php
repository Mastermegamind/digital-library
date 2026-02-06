<?php
// includes/auth.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function current_user() {
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $cachedUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    return $cachedUser;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function is_admin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_staff(): bool {
    $u = current_user();
    return $u && $u['role'] === 'staff';
}

function is_admin_or_staff(): bool {
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'staff'], true);
}

function require_login() {
    if (!is_logged_in()) {
        log_warning('require_login redirect triggered');
        flash_message('error', 'Please login to continue.');
        header('Location: ' . app_path('login'));
        exit;
    }

    $u = current_user();
    if (($u['status'] ?? 'active') !== 'active') {
        logout_user();
        flash_message('error', 'Your account is not active.');
        header('Location: ' . app_path('login'));
        exit;
    }

    if (is_email_verification_required() && empty($u['email_verified_at'])) {
        logout_user();
        flash_message('error', 'Please verify your email before continuing.');
        header('Location: ' . app_path('login'));
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        log_warning('require_admin blocked access');
        flash_message('error', 'Admin access required.');
        header('Location: ' . app_path('login'));
        exit;
    }
}

function login_user($userId) {
    $_SESSION['user_id'] = $userId;
    log_info('Session login established', ['user_id' => $userId]);
}

function logout_user() {
    if (!empty($_SESSION['user_id'])) {
        log_info('User logging out', ['user_id' => (int)$_SESSION['user_id']]);
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
