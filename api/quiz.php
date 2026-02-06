<?php
// api/quiz.php - Quiz submission and stats
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $quizId = (int)($_GET['quiz_id'] ?? 0);
    if ($quizId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid quiz']);
        exit;
    }
    echo json_encode([
        'stats' => get_quiz_stats($quizId),
        'attempts' => get_user_quiz_attempts($userId, $quizId),
    ]);
    exit;
}

// POST - submit quiz
$payload = $_POST;
if (empty($payload)) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?? '', true);
    if (is_array($json)) {
        $payload = $json;
    }
}

if (!verify_csrf_token($payload['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$quizId = (int)($payload['quiz_id'] ?? 0);
$answers = $payload['answers'] ?? [];
if ($quizId <= 0 || !is_array($answers)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid submission']);
    exit;
}

$result = submit_quiz_attempt($quizId, $userId, $answers);

echo json_encode(['success' => true, 'result' => $result]);
