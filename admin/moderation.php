<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/moderation');
require_admin();

$csrf = get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/moderation'));
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $return = $_POST['return'] ?? 'admin/moderation';
    if (preg_match('#^https?://#i', $return)) {
        $return = 'admin/moderation';
    }
    $returnPath = ltrim($return, '/');

    if ($id > 0) {
        if (in_array($action, ['resource_approve', 'resource_reject', 'resource_request_changes'], true)) {
            $status = $action === 'resource_approve' ? 'approved' : ($action === 'resource_request_changes' ? 'changes_requested' : 'rejected');
            $stmt = $pdo->prepare("UPDATE resources
                                   SET status = :status, approved_by = :admin_id, approved_at = CURRENT_TIMESTAMP, review_notes = :notes
                                   WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':admin_id' => current_user()['id'],
                ':notes' => $notes !== '' ? $notes : null,
                ':id' => $id,
            ]);

            $infoStmt = $pdo->prepare("SELECT r.title, r.created_by, u.email, u.name
                                       FROM resources r
                                       LEFT JOIN users u ON r.created_by = u.id
                                       WHERE r.id = :id");
            $infoStmt->execute([':id' => $id]);
            $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
            if ($info && !empty($info['email']) && mailer_is_configured() && is_email_notifications_enabled()) {
                $subject = 'Resource Submission Update - ' . $APP_NAME;
                $statusLabel = ucwords(str_replace('_', ' ', $status));
                $body = '<p>Hello ' . h($info['name'] ?? 'there') . ',</p>'
                      . '<p>Your resource submission has been reviewed.</p>'
                      . '<p><strong>Title:</strong> ' . h($info['title']) . '</p>'
                      . '<p><strong>Status:</strong> ' . h($statusLabel) . '</p>';
                if ($notes !== '') {
                    $body .= '<p><strong>Notes from reviewer:</strong><br>' . nl2br(h($notes)) . '</p>';
                }
                send_app_mail($info['email'], $subject, $body);
            }

            if (!empty($info['created_by'])) {
                $creatorId = (int)$info['created_by'];
                if ($status === 'approved') {
                    create_notification(
                        $creatorId,
                        'submission_approved',
                        'Your submission was approved',
                        $info['title'] ?? 'Resource approved',
                        app_path('resource/' . $id)
                    );
                } elseif ($status === 'changes_requested') {
                    create_notification(
                        $creatorId,
                        'submission_changes',
                        'Changes requested on your submission',
                        $info['title'] ?? 'Please review notes',
                        app_path('my-submissions')
                    );
                } elseif ($status === 'rejected') {
                    create_notification(
                        $creatorId,
                        'submission_rejected',
                        'Your submission was rejected',
                        $info['title'] ?? 'Submission rejected',
                        app_path('my-submissions')
                    );
                }
            }

            if ($status === 'approved') {
                notify_all_users(
                    'resource_new',
                    'New resource available',
                    $info['title'] ?? 'A new resource has been added',
                    app_path('resource/' . $id),
                    !empty($info['created_by']) ? (int)$info['created_by'] : null
                );

                $resourceLink = app_url('resource/' . $id);
                $emailSubject = 'New resource available - ' . $APP_NAME;
                $emailTitle = $info['title'] ?? 'A new resource has been added';
                $emailHtml = '<p>A new resource is now available.</p>'
                    . '<p><strong>' . h($emailTitle) . '</strong></p>'
                    . '<p><a href="' . h($resourceLink) . '">View resource</a></p>';
                $emailText = "A new resource is now available.\n\n{$emailTitle}\n{$resourceLink}";
                notify_all_users_email($emailSubject, $emailHtml, $emailText, !empty($info['created_by']) ? (int)$info['created_by'] : null);
            }

            flash_message('success', 'Resource status updated.');
        }

        if (in_array($action, ['comment_approve', 'comment_reject'], true)) {
            $status = $action === 'comment_approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE resource_comments SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            flash_message('success', 'Comment moderation updated.');
        }

        if (in_array($action, ['review_approve', 'review_reject'], true)) {
            $status = $action === 'review_approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE resource_reviews SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            flash_message('success', 'Review moderation updated.');
        }
    }

    header('Location: ' . app_path($returnPath));
    exit;
}

$pendingResources = $pdo->query("
    SELECT r.*, c.name AS category_name, u.name AS submitter_name
    FROM resources r
    LEFT JOIN categories c ON r.category_id = c.id
    LEFT JOIN users u ON r.created_by = u.id
    WHERE COALESCE(r.status, 'approved') <> 'approved'
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingComments = $pdo->query("
    SELECT rc.*, u.name AS user_name, r.title AS resource_title,
           (SELECT COUNT(*) FROM resource_reports WHERE content_type = 'comment' AND content_id = rc.id) AS report_count
    FROM resource_comments rc
    JOIN users u ON rc.user_id = u.id
    JOIN resources r ON rc.resource_id = r.id
    WHERE rc.status IN ('pending', 'flagged')
    ORDER BY rc.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pendingReviews = $pdo->query("
    SELECT rr.*, u.name AS user_name, r.title AS resource_title,
           (SELECT COUNT(*) FROM resource_reports WHERE content_type = 'review' AND content_id = rr.id) AS report_count
    FROM resource_reviews rr
    JOIN users u ON rr.user_id = u.id
    JOIN resources r ON rr.resource_id = r.id
    WHERE rr.status IN ('pending', 'flagged')
    ORDER BY rr.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$meta_title = 'Moderation - Admin | ' . $APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-gavel me-2"></i>Moderation
            </h2>
            <p class="text-muted mb-0">Approve or reject user submissions, comments, and reviews.</p>
        </div>
        <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="form-card mb-4">
    <h4 class="mb-3">Pending Resources</h4>
    <?php if (empty($pendingResources)): ?>
        <p class="text-muted mb-0">No pending resources.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Submitted By</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingResources as $res): ?>
                        <tr>
                            <td>
                                <?= h($res['title']) ?>
                                <?php if (can_view_resource_file_size()): ?>
                                    <div class="small text-muted">
                                        <i class="fas fa-hdd me-1"></i>File size: <?= h(get_resource_file_size_label($res)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= h($res['category_name'] ?? '-') ?></td>
                            <td><?= h($res['submitter_name'] ?? 'Unknown') ?></td>
                            <td>
                                <span class="status-badge status-<?= h($res['status'] ?? 'pending') ?>">
                                    <?= h(ucwords(str_replace('_', ' ', $res['status'] ?? 'pending'))) ?>
                                </span>
                            </td>
                            <td>
                                <textarea name="notes" form="moderation-<?= (int)$res['id'] ?>" class="form-control form-control-sm moderation-notes" rows="2"
                                    placeholder="Add notes for the submitter..."><?= h($res['review_notes'] ?? '') ?></textarea>
                            </td>
                            <td class="text-end">
                                <form method="post" id="moderation-<?= (int)$res['id'] ?>" class="moderation-form">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$res['id'] ?>">
                                    <div class="moderation-actions">
                                        <button type="submit" name="action" value="resource_approve" class="btn btn-sm btn-success">Approve</button>
                                        <button type="submit" name="action" value="resource_request_changes" class="btn btn-sm btn-outline-warning">Request Changes</button>
                                        <button type="submit" name="action" value="resource_reject" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="form-card h-100">
            <h4 class="mb-3">Comments Awaiting Review</h4>
            <?php if (empty($pendingComments)): ?>
                <p class="text-muted mb-0">No pending comments.</p>
            <?php else: ?>
                <?php foreach ($pendingComments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <span class="comment-author"><?= h($comment['user_name']) ?></span>
                            <span class="comment-date"><?= date('M d, Y', strtotime($comment['created_at'])) ?></span>
                        </div>
                        <div class="comment-body"><?= nl2br(h($comment['content'])) ?></div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="text-muted">Resource: <?= h($comment['resource_title']) ?></span>
                            <?php if (!empty($comment['report_count'])): ?>
                                <span class="status-badge status-flagged">Reports: <?= (int)$comment['report_count'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="comment_approve">
                                <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="comment_reject">
                                <input type="hidden" name="id" value="<?= (int)$comment['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="form-card h-100">
            <h4 class="mb-3">Reviews Awaiting Review</h4>
            <?php if (empty($pendingReviews)): ?>
                <p class="text-muted mb-0">No pending reviews.</p>
            <?php else: ?>
                <?php foreach ($pendingReviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <span class="review-author"><?= h($review['user_name']) ?></span>
                            <span class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                        </div>
                        <div class="review-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= (int)$review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <?php if (!empty($review['review'])): ?>
                            <p class="review-text"><?= nl2br(h($review['review'])) ?></p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="text-muted">Resource: <?= h($review['resource_title']) ?></span>
                            <?php if (!empty($review['report_count'])): ?>
                                <span class="status-badge status-flagged">Reports: <?= (int)$review['report_count'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="review_approve">
                                <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="review_reject">
                                <input type="hidden" name="id" value="<?= (int)$review['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
