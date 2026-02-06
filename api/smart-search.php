<?php
// api/smart-search.php - AI-assisted search intent extraction
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

$query = trim($_POST['query'] ?? '');
if ($query === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Query is required']);
    exit;
}

$prompt = "Extract search intent from this query. Return JSON: {\"keywords\": [], \"category_hint\": \"\", \"type_hint\": \"\", \"description\": \"\"}";
$result = deepseek_chat_json($prompt, $query, ['max_tokens' => 200, 'temperature' => 0.2]);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'AI response failed']);
    exit;
}

if (!isset($result['keywords']) || !is_array($result['keywords'])) {
    $result['keywords'] = [];
}

$result['keywords'] = array_values(array_filter(array_map('trim', $result['keywords']), fn($k) => $k !== ''));

echo json_encode(['success' => true, 'data' => $result]);
