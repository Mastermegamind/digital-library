<?php
// api/summarize.php - Generate AI summary for a resource
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

if (!ai_is_configured()) {
    http_response_code(400);
    echo json_encode(['error' => 'AI is not configured']);
    exit;
}

$resourceId = (int)($_POST['resource_id'] ?? 0);
if ($resourceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid resource']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $resourceId]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$resource) {
    http_response_code(404);
    echo json_encode(['error' => 'Resource not found']);
    exit;
}

$text = '';
if (($resource['type'] ?? '') === 'pdf' && !empty($resource['file_path'])) {
    $filePath = dirname(__DIR__) . '/' . ltrim($resource['file_path'], '/');
    $text = extract_pdf_text($filePath, 15000) ?: '';
}
if ($text === '') {
    $text = trim((string)($resource['description'] ?? ''));
}
if ($text === '') {
    $text = trim((string)($resource['title'] ?? ''));
}
if ($text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No content to summarize']);
    exit;
}

$prompt = 'Summarize this educational resource in 2-3 paragraphs for students.';
$summary = deepseek_chat($prompt, $text, ['max_tokens' => 400, 'temperature' => 0.3]);

if ($summary === null) {
    http_response_code(500);
    echo json_encode(['error' => 'AI response failed']);
    exit;
}

$update = $pdo->prepare("UPDATE resources SET ai_summary = :summary WHERE id = :id");
$update->execute([':summary' => $summary, ':id' => $resourceId]);

echo json_encode(['success' => true, 'summary' => $summary]);
