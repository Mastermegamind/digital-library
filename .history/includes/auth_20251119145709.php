<?php
ini_set('display_errors', 1);
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

function require_login() {
    if (!is_logged_in()) {
        flash_message('error', 'Please login to continue.');
        header('Location: login.php');
        exit;
    }
}

function require_admin() {
    if (!is_admin()) {
        flash_message('error', 'Admin access required.');
        header('Location: ../login.php');
        exit;
    }
}

function login_user($userId) {
    $_SESSION['user_id'] = $userId;
}

function logout_user() {
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
