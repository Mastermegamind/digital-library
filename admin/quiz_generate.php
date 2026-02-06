<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
redirect_legacy_php('admin/quiz/generate');
require_admin();

$aiAvailable = function_exists('ai_is_configured') && ai_is_configured();

$resources = $pdo->query("SELECT id, title, type FROM resources ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/quiz/generate'));
        exit;
    }

    if (!$aiAvailable) {
        flash_message('error', 'AI is not configured. Set DEEPSEEK_API_KEY first.');
        header('Location: ' . app_path('admin/quiz/generate'));
        exit;
    }

    $resourceId = (int)($_POST['resource_id'] ?? 0);
    $numQuestions = max(1, min(20, (int)($_POST['num_questions'] ?? 5)));
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($resourceId <= 0) {
        flash_message('error', 'Please select a resource.');
        header('Location: ' . app_path('admin/quiz/generate'));
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$resource) {
        flash_message('error', 'Resource not found.');
        header('Location: ' . app_path('admin/quiz/generate'));
        exit;
    }

    if ($title === '') {
        $title = $resource['title'] . ' Quiz';
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
        flash_message('error', 'No content available to generate questions.');
        header('Location: ' . app_path('admin/quiz/generate'));
        exit;
    }

    $prompt = "Generate {$numQuestions} multiple-choice quiz questions about this content. Return JSON: {\"questions\": [{\"question\": \"...\", \"options\": [\"a\", \"b\", \"c\", \"d\"], \"correct_answer\": \"a\", \"explanation\": \"...\"}]}";
    $result = deepseek_chat_json($prompt, $text, ['max_tokens' => 1200, 'temperature' => 0.4]);

    if (!$result || empty($result['questions']) || !is_array($result['questions'])) {
        flash_message('error', 'AI failed to generate questions.');
        header('Location: ' . app_path('admin/quiz/generate'));
        exit;
    }

    $quizId = create_quiz($resourceId, $title, $description, current_user()['id'], true);
    $order = 0;
    foreach ($result['questions'] as $q) {
        if (empty($q['question']) || empty($q['options']) || empty($q['correct_answer'])) {
            continue;
        }
        $questionText = trim((string)$q['question']);
        $options = array_values(array_filter(array_map('trim', (array)$q['options']), fn($o) => $o !== ''));
        $correct = trim((string)$q['correct_answer']);
        $explanation = trim((string)($q['explanation'] ?? ''));
        if ($questionText === '' || empty($options) || $correct === '') {
            continue;
        }
        add_quiz_question($quizId, $questionText, 'multiple_choice', $options, $correct, $explanation, $order);
        $order++;
    }

    flash_message('success', 'AI quiz generated. Review and edit as needed.');
    header('Location: ' . app_path('admin/quiz/edit/' . $quizId));
    exit;
}

$csrf = get_csrf_token();
$meta_title = 'Generate Quiz - Admin | ' . $APP_NAME;
$meta_description = 'Generate quizzes from a resource using AI.';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-robot me-2"></i>Generate Quiz with AI
            </h2>
            <p class="text-muted mb-0">Select a resource and let AI draft the questions.</p>
        </div>
        <a href="<?= h(app_path('admin/quizzes')) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Quizzes
        </a>
    </div>
</div>

<?php if (!$aiAvailable): ?>
    <div class="alert alert-warning">
        AI is not configured. Set <code>DEEPSEEK_API_KEY</code> in your environment to enable this feature.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Resource</label>
                    <select name="resource_id" class="form-select" required>
                        <option value="">Select a resource...</option>
                        <?php foreach ($resources as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= h($r['title']) ?> (<?= h(strtoupper($r['type'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"># Questions</label>
                    <input type="number" name="num_questions" class="form-control" value="5" min="1" max="20">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Quiz Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Optional. Defaults to resource title + Quiz">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="Optional description">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary" <?= $aiAvailable ? '' : 'disabled' ?>>
                    <i class="fas fa-magic me-2"></i>Generate Quiz
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
