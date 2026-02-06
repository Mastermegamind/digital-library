<?php
// api/chatbot.php - Study assistant chatbot
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

if (!ai_is_configured()) {
    http_response_code(400);
    echo json_encode(['error' => 'AI is not configured']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $msgStmt = $pdo->prepare("SELECT role, content, created_at FROM chatbot_messages WHERE user_id = :uid ORDER BY created_at ASC LIMIT 50");
    $msgStmt->execute([':uid' => $userId]);
    echo json_encode(['messages' => $msgStmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

// Build library context
$totalResources = (int)$pdo->query("SELECT COUNT(*) FROM resources WHERE COALESCE(status,'approved')='approved'")->fetchColumn();
$categoryRows = $pdo->query("SELECT c.name, COUNT(r.id) AS cnt
                             FROM categories c
                             LEFT JOIN resources r ON r.category_id = c.id AND COALESCE(r.status,'approved')='approved'
                             GROUP BY c.id
                             ORDER BY cnt DESC, c.name ASC
                             LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$categoryList = [];
foreach ($categoryRows as $row) {
    $categoryList[] = $row['name'] . ' (' . (int)$row['cnt'] . ')';
}
$categoryListText = $categoryList ? implode(', ', $categoryList) : 'No categories available';

$systemPrompt = "You are a study assistant for {$APP_NAME} e-library. Help students find resources, explain concepts, and provide study tips. The library has {$totalResources} resources across categories: {$categoryListText}.";

// Last 10 messages
$historyStmt = $pdo->prepare("SELECT role, content FROM chatbot_messages WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
$historyStmt->execute([':uid' => $userId]);
$historyRows = array_reverse($historyStmt->fetchAll(PDO::FETCH_ASSOC));

$response = deepseek_chat($systemPrompt, $message, [
    'messages' => $historyRows,
    'max_tokens' => 700,
    'temperature' => 0.6,
]);

if ($response === null) {
    http_response_code(500);
    echo json_encode(['error' => 'AI response failed']);
    exit;
}

$insert = $pdo->prepare("INSERT INTO chatbot_messages (user_id, role, content) VALUES (:uid, :role, :content)");
$insert->execute([':uid' => $userId, ':role' => 'user', ':content' => $message]);
$insert->execute([':uid' => $userId, ':role' => 'assistant', ':content' => $response]);

echo json_encode(['success' => true, 'reply' => $response]);
