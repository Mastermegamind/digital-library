<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$quizId = (int)($_GET['id'] ?? 0);
$quiz = $quizId > 0 ? get_quiz($quizId) : null;

if (!$quiz) {
    flash_message('error', 'Quiz not found.');
    header('Location: ' . app_path(''));
    exit;
}

$questions = get_quiz_questions($quizId);
$csrf = get_csrf_token();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('quiz/' . $quizId));
        exit;
    }

    $answers = $_POST['answers'] ?? [];
    $result = submit_quiz_attempt($quizId, current_user()['id'], $answers);
}

$meta_title = $quiz['title'] . ' - Quiz';
$meta_description = $quiz['description'] ?: 'Take a quiz for ' . $quiz['resource_title'];
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-clipboard-list me-2"></i><?= h($quiz['title']) ?>
            </h2>
            <p class="text-muted mb-0"><?= h($quiz['description'] ?? '') ?></p>
            <div class="small text-muted mt-1">Resource: <a href="<?= h(app_path('resource/' . $quiz['resource_id'])) ?>"><?= h($quiz['resource_title']) ?></a></div>
        </div>
        <a href="<?= h(app_path('resource/' . $quiz['resource_id'])) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Resource
        </a>
    </div>
</div>

<?php if ($result): ?>
    <div class="alert alert-success">
        <strong>Score:</strong> <?= (int)$result['score'] ?>/<?= (int)$result['total_questions'] ?>
        (<?= (int)$result['percentage'] ?>%)
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($questions)): ?>
            <p class="text-muted mb-0">No questions available.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <?php foreach ($questions as $index => $q): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold">Q<?= $index + 1 ?>. <?= h($q['question']) ?></h6>
                        <?php foreach ($q['options'] as $optIndex => $opt): ?>
                            <?php
                                $qId = (int)$q['id'];
                                $optValue = (string)$opt;
                                $inputId = 'q' . $qId . '_' . $optIndex;
                                $isChecked = isset($_POST['answers'][$qId]) && $_POST['answers'][$qId] === $optValue;
                            ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answers[<?= $qId ?>]" id="<?= h($inputId) ?>" value="<?= h($optValue) ?>" <?= $isChecked ? 'checked' : '' ?> <?= $result ? 'disabled' : '' ?>>
                                <label class="form-check-label" for="<?= h($inputId) ?>">
                                    <?= h($optValue) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($result): ?>
                            <?php
                                $resItem = null;
                                foreach ($result['results'] as $r) {
                                    if ((int)$r['question_id'] === (int)$q['id']) { $resItem = $r; break; }
                                }
                            ?>
                            <?php if ($resItem): ?>
                                <div class="mt-2">
                                    <span class="badge bg-<?= $resItem['is_correct'] ? 'success' : 'danger' ?>">
                                        <?= $resItem['is_correct'] ? 'Correct' : 'Incorrect' ?>
                                    </span>
                                    <?php if (!empty($resItem['explanation'])): ?>
                                        <div class="small text-muted mt-1">Explanation: <?= h($resItem['explanation']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <hr>
                <?php endforeach; ?>

                <?php if (!$result): ?>
                    <button type="submit" class="btn btn-primary">Submit Quiz</button>
                <?php else: ?>
                    <a href="<?= h(app_path('quiz/' . $quizId)) ?>" class="btn btn-outline-primary">Retake Quiz</a>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
