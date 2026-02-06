<?php
// api/generate-quiz.php - Generate quiz questions with AI
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
$numQuestions = max(1, min(20, (int)($_POST['num_questions'] ?? 5)));

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

$prompt = "Generate {$numQuestions} multiple-choice quiz questions about this content. Return JSON: {\"questions\": [{\"question\": \"...\", \"options\": [\"a\", \"b\", \"c\", \"d\"], \"correct_answer\": \"a\", \"explanation\": \"...\"}]}";
$result = deepseek_chat_json($prompt, $text, ['max_tokens' => 1200, 'temperature' => 0.4]);

if (!$result || empty($result['questions']) || !is_array($result['questions'])) {
    http_response_code(500);
    echo json_encode(['error' => 'AI response failed']);
    exit;
}

$questions = [];
foreach ($result['questions'] as $q) {
    if (empty($q['question']) || empty($q['options']) || empty($q['correct_answer'])) {
        continue;
    }
    $questions[] = [
        'question' => trim((string)$q['question']),
        'options' => array_values(array_map('trim', (array)$q['options'])),
        'correct_answer' => trim((string)$q['correct_answer']),
        'explanation' => trim((string)($q['explanation'] ?? '')),
    ];
}

echo json_encode(['success' => true, 'questions' => $questions]);
