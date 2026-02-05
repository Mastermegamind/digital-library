<?php
// api/settings.php - Save/retrieve user settings (dark mode, etc.)
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Require login
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    // Handle dark mode setting
    if (isset($_POST['dark_mode'])) {
        $darkMode = (bool)(int)$_POST['dark_mode'];
        set_user_dark_mode($user['id'], $darkMode);

        echo json_encode([
            'success' => true,
            'dark_mode' => $darkMode
        ]);
        exit;
    }

    echo json_encode(['error' => 'No settings provided']);
} else {
    // GET - fetch all settings
    $darkMode = get_user_dark_mode($user['id']);

    echo json_encode([
        'dark_mode' => $darkMode
    ]);
}
