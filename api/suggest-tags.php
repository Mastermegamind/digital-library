<?php
// api/suggest-tags.php - Suggest tags for a resource
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
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

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$text = trim($_POST['text'] ?? '');

$input = trim($title . "\n" . $description . "\n" . $text);
if ($input === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No content provided']);
    exit;
}

$prompt = "Suggest 3-8 relevant tags for this educational resource. Return JSON: {\"tags\": [\"tag1\", \"tag2\"]}";
$result = deepseek_chat_json($prompt, $input, ['max_tokens' => 200, 'temperature' => 0.4]);

if (!$result || empty($result['tags']) || !is_array($result['tags'])) {
    http_response_code(500);
    echo json_encode(['error' => 'AI response failed']);
    exit;
}

$tags = array_values(array_filter(array_map('trim', $result['tags']), fn($t) => $t !== ''));

echo json_encode(['success' => true, 'tags' => $tags]);
