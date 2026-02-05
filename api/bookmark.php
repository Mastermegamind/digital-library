<?php
// api/bookmark.php - Toggle bookmark for a resource
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

    // Toggle bookmark
    $bookmarked = toggle_bookmark($user['id'], $resourceId);
    echo json_encode([
        'bookmarked' => $bookmarked,
        'message' => $bookmarked ? 'Bookmark added' : 'Bookmark removed'
    ]);
} else {
    // GET - check bookmark status
    $bookmarked = is_bookmarked($user['id'], $resourceId);
    echo json_encode(['bookmarked' => $bookmarked]);
}
