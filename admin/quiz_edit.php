<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';
$legacyQuizId = (int)($_GET['id'] ?? 0);
$legacyResourceId = (int)($_GET['resource_id'] ?? 0);
if ($legacyQuizId > 0) {
    redirect_legacy_php('admin/quiz/edit/' . $legacyQuizId, ['id' => null]);
}
if ($legacyResourceId > 0) {
    redirect_legacy_php('admin/quiz/add/' . $legacyResourceId, ['resource_id' => null]);
}
require_admin();

$quizId = $legacyQuizId;
$resourceId = $legacyResourceId;
$quiz = null;
$questions = [];

if ($quizId > 0) {
    $quiz = get_quiz($quizId);
    if (!$quiz) {
        flash_message('error', 'Quiz not found.');
        header('Location: ' . app_path('admin'));
        exit;
    }
    $resourceId = (int)$quiz['resource_id'];
    $questions = get_quiz_questions($quizId);
} else {
    if ($resourceId <= 0) {
        flash_message('error', 'Resource not specified.');
        header('Location: ' . app_path('admin/resources'));
        exit;
    }
    $resStmt = $pdo->prepare("SELECT title FROM resources WHERE id = :id");
    $resStmt->execute([':id' => $resourceId]);
    $res = $resStmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        flash_message('error', 'Resource not found.');
        header('Location: ' . app_path('admin/resources'));
        exit;
    }
    $quiz = [
        'title' => $res['title'] . ' Quiz',
        'description' => '',
        'resource_title' => $res['title'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path($quizId > 0 ? ('admin/quiz/edit/' . $quizId) : ('admin/quiz/add/' . $resourceId)));
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isAi = !empty($_POST['is_ai_generated']);

    if ($title === '') {
        flash_message('error', 'Quiz title is required.');
    } else {
        if ($quizId > 0) {
            $stmt = $pdo->prepare("UPDATE quizzes SET title = :title, description = :desc WHERE id = :id");
            $stmt->execute([':title' => $title, ':desc' => $description, ':id' => $quizId]);

            // Remove existing questions
            $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = :qid")->execute([':qid' => $quizId]);
        } else {
            $quizId = create_quiz($resourceId, $title, $description, current_user()['id'], $isAi);
        }

        $questionsInput = $_POST['questions'] ?? [];
        $order = 0;
        foreach ($questionsInput as $q) {
            $questionText = trim($q['text'] ?? '');
            $options = $q['options'] ?? [];
            $options = array_values(array_filter(array_map('trim', $options), fn($o) => $o !== ''));
            $correct = trim($q['correct'] ?? '');
            $explanation = trim($q['explanation'] ?? '');

            if ($questionText === '' || empty($options) || $correct === '') {
                continue;
            }
            add_quiz_question($quizId, $questionText, 'multiple_choice', $options, $correct, $explanation, $order);
            $order++;
        }

        flash_message('success', 'Quiz saved successfully.');
        header('Location: ' . app_path('admin/quiz/edit/' . $quizId));
        exit;
    }
}

$csrf = get_csrf_token();
$meta_title = ($quizId > 0 ? 'Edit Quiz' : 'Add Quiz') . ' | ' . $APP_NAME;
$meta_description = 'Create or edit quiz questions.';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-clipboard-list me-2"></i><?= $quizId > 0 ? 'Edit Quiz' : 'Create Quiz' ?>
            </h2>
            <p class="text-muted mb-0">Resource: <?= h($quiz['resource_title'] ?? '') ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= h(app_path('resource/' . $resourceId)) ?>" class="btn btn-outline-secondary">Back to Resource</a>
            <?php if (ai_is_configured()): ?>
                <button type="button" class="btn btn-outline-primary" id="aiGenerateBtn">
                    <i class="fas fa-robot me-2"></i>Generate with AI
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" id="quizForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="is_ai_generated" id="isAiGenerated" value="0">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Quiz Title</label>
                    <input type="text" name="title" class="form-control" value="<?= h($quiz['title'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="<?= h($quiz['description'] ?? '') ?>">
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Questions</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="addQuestionBtn">
                    <i class="fas fa-plus me-1"></i>Add Question
                </button>
            </div>

            <div id="questionsContainer"></div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Quiz</button>
            </div>
        </form>
    </div>
</div>

<script>
const existingQuestions = <?= json_encode($questions) ?>;
const questionsContainer = document.getElementById('questionsContainer');
const addQuestionBtn = document.getElementById('addQuestionBtn');
const aiBtn = document.getElementById('aiGenerateBtn');
const isAiGenerated = document.getElementById('isAiGenerated');

function renderQuestionBlock(index, data = {}) {
    const q = document.createElement('div');
    q.className = 'card mb-3 question-block';
    q.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Question ${index + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-question">Remove</button>
            </div>
            <div class="mb-2">
                <textarea name="questions[${index}][text]" class="form-control" rows="2" placeholder="Enter question">${data.question || ''}</textarea>
            </div>
            <div class="row g-2">
                ${(data.options || ['','','','']).map((opt, i) => `
                    <div class="col-md-6">
                        <input type="text" name="questions[${index}][options][]" class="form-control" placeholder="Option ${String.fromCharCode(65+i)}" value="${opt || ''}">
                    </div>
                `).join('')}
            </div>
            <div class="mt-2">
                <input type="text" name="questions[${index}][correct]" class="form-control" placeholder="Correct answer (must match an option)" value="${data.correct_answer || ''}">
            </div>
            <div class="mt-2">
                <textarea name="questions[${index}][explanation]" class="form-control" rows="2" placeholder="Explanation (optional)">${data.explanation || ''}</textarea>
            </div>
        </div>
    `;
    q.querySelector('.remove-question').addEventListener('click', () => {
        q.remove();
        renumberQuestions();
    });
    return q;
}

function renumberQuestions() {
    const blocks = document.querySelectorAll('.question-block');
    blocks.forEach((block, idx) => {
        const title = block.querySelector('h6');
        if (title) title.textContent = `Question ${idx + 1}`;
        // Update input names
        block.querySelectorAll('textarea, input').forEach(input => {
            const name = input.getAttribute('name');
            if (!name) return;
            const updated = name.replace(/questions\[\d+\]/, `questions[${idx}]`);
            input.setAttribute('name', updated);
        });
    });
}

function addQuestion(data = {}) {
    const index = document.querySelectorAll('.question-block').length;
    const block = renderQuestionBlock(index, data);
    questionsContainer.appendChild(block);
}

if (existingQuestions.length) {
    existingQuestions.forEach(q => {
        addQuestion({
            question: q.question,
            options: q.options || [],
            correct_answer: q.correct_answer,
            explanation: q.explanation
        });
    });
} else {
    addQuestion();
}

if (addQuestionBtn) {
    addQuestionBtn.addEventListener('click', () => addQuestion());
}

if (aiBtn) {
    aiBtn.addEventListener('click', () => {
        if (!confirm('Generate questions with AI? This will replace current questions.')) return;
        aiBtn.disabled = true;
        aiBtn.innerText = 'Generating...';
        fetch('<?= h(app_path('api/generate-quiz')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token: '<?= h($csrf) ?>',
                resource_id: '<?= (int)$resourceId ?>',
                num_questions: '5'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                showToast(data.error, 'error');
                return;
            }
            const questions = data.questions || [];
            questionsContainer.innerHTML = '';
            questions.forEach(q => addQuestion(q));
            if (isAiGenerated) isAiGenerated.value = '1';
        })
        .catch(() => showToast('Failed to generate quiz', 'error'))
        .finally(() => {
            aiBtn.disabled = false;
            aiBtn.innerText = 'Generate with AI';
        });
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
