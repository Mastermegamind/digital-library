<?php
// api/progress.php - Save/retrieve reading progress
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Require login
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$user = current_user();
$resourceId = (int)($_REQUEST['resource_id'] ?? 0);

if ($resourceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid resource ID']);
    exit;
}

// Verify resource exists
$stmt = $pdo->prepare("SELECT id FROM resources WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $resourceId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Resource not found']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $position = max(0, (int)($_POST['position'] ?? 0));
    $percent = max(0, min(100, (float)($_POST['percent'] ?? 0)));
    $totalPages = isset($_POST['total_pages']) && $_POST['total_pages'] !== ''
        ? max(1, (int)$_POST['total_pages'])
        : null;

    update_reading_progress($user['id'], $resourceId, $position, $percent, $totalPages);

    echo json_encode([
        'success' => true,
        'position' => $position,
        'percent' => $percent
    ]);
} else {
    // GET - fetch progress
    $progress = get_resource_progress($user['id'], $resourceId);

    if ($progress) {
        echo json_encode([
            'last_position' => (int)$progress['last_position'],
            'progress_percent' => (float)$progress['progress_percent'],
            'total_pages' => $progress['total_pages'] ? (int)$progress['total_pages'] : null,
            'last_viewed_at' => $progress['last_viewed_at']
        ]);
    } else {
        echo json_encode([
            'last_position' => 0,
            'progress_percent' => 0,
            'total_pages' => null,
            'last_viewed_at' => null
        ]);
    }
}
