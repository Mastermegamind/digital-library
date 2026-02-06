<?php
// api/chat.php - Chat with PDF
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];
$resourceId = (int)($_REQUEST['resource_id'] ?? 0);

if ($resourceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid resource']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $resourceId]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$resource || !resource_is_visible($resource, $user)) {
    http_response_code(403);
    echo json_encode(['error' => 'Resource not available']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $msgStmt = $pdo->prepare("SELECT role, content, created_at FROM chat_messages WHERE user_id = :uid AND resource_id = :rid ORDER BY created_at ASC LIMIT 50");
    $msgStmt->execute([':uid' => $userId, ':rid' => $resourceId]);
    echo json_encode(['messages' => $msgStmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// POST
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

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Extract/cached text
$cacheKey = 'pdf_text_cache';
if (!isset($_SESSION[$cacheKey]) || !is_array($_SESSION[$cacheKey])) {
    $_SESSION[$cacheKey] = [];
}

$contextText = $_SESSION[$cacheKey][$resourceId] ?? null;
if ($contextText === null) {
    if (($resource['type'] ?? '') === 'pdf' && !empty($resource['file_path'])) {
        $filePath = dirname(__DIR__) . '/' . ltrim($resource['file_path'], '/');
        $contextText = extract_pdf_text($filePath, 15000) ?: '';
    } else {
        $contextText = trim((string)($resource['description'] ?? ''));
    }
    $_SESSION[$cacheKey][$resourceId] = $contextText;
}

$systemPrompt = "You are a helpful study assistant. Answer questions based on the following document content. If the answer is not in the document, say so. Be concise and educational.\n\nDocument content:\n" . $contextText;

// Get last 10 messages
$historyStmt = $pdo->prepare("SELECT role, content FROM chat_messages WHERE user_id = :uid AND resource_id = :rid ORDER BY created_at DESC LIMIT 10");
$historyStmt->execute([':uid' => $userId, ':rid' => $resourceId]);
$historyRows = array_reverse($historyStmt->fetchAll(PDO::FETCH_ASSOC));

$response = deepseek_chat($systemPrompt, $message, [
    'messages' => $historyRows,
    'max_tokens' => 1024,
    'temperature' => 0.7,
]);

if ($response === null) {
    http_response_code(500);
    echo json_encode(['error' => 'AI response failed']);
    exit;
}

// Store messages
$insert = $pdo->prepare("INSERT INTO chat_messages (user_id, resource_id, role, content) VALUES (:uid, :rid, :role, :content)");
$insert->execute([':uid' => $userId, ':rid' => $resourceId, ':role' => 'user', ':content' => $message]);
$insert->execute([':uid' => $userId, ':rid' => $resourceId, ':role' => 'assistant', ':content' => $response]);

echo json_encode(['success' => true, 'reply' => $response]);
