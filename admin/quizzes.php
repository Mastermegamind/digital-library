<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/quizzes');
require_admin();

$stmt = $pdo->query("SELECT q.*, r.title AS resource_title, u.name AS creator_name,
                            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count,
                            (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) AS attempt_count
                     FROM quizzes q
                     JOIN resources r ON q.resource_id = r.id
                     JOIN users u ON q.created_by = u.id
                     ORDER BY q.created_at DESC");
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'Quizzes - Admin | ' . $APP_NAME;
$meta_description = 'Manage quizzes and questions.';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-clipboard-list me-2"></i>Quizzes
            </h2>
            <p class="text-muted mb-0">Manage quizzes across all resources.</p>
        </div>
        <a href="<?= h(app_path('admin/quiz/generate')) ?>" class="btn btn-outline-primary">
            <i class="fas fa-plus me-2"></i>Create Quiz (AI)
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($quizzes)): ?>
            <p class="text-muted mb-0">No quizzes created yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>Resource</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Creator</th>
                            <th>AI</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $q): ?>
                            <tr>
                                <td>
                                    <strong><?= h($q['title']) ?></strong>
                                    <?php if (!empty($q['description'])): ?>
                                        <div class="text-muted small"><?= h(mb_strimwidth($q['description'], 0, 80, '...')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= h(app_path('resource/' . $q['resource_id'])) ?>" class="text-decoration-none">
                                        <?= h($q['resource_title']) ?>
                                    </a>
                                </td>
                                <td><?= (int)$q['question_count'] ?></td>
                                <td><?= (int)$q['attempt_count'] ?></td>
                                <td><?= h($q['creator_name']) ?></td>
                                <td><?= !empty($q['is_ai_generated']) ? 'Yes' : 'No' ?></td>
                                <td><?= date('M d, Y', strtotime($q['created_at'])) ?></td>
                                <td class="text-end">
                                    <a href="<?= h(app_path('admin/quiz/edit/' . $q['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <a href="<?= h(app_path('quiz/' . $q['id'])) ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
