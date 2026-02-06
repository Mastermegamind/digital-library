<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';
$legacyId = (int)($_GET['id'] ?? 0);
if ($legacyId > 0) {
    redirect_legacy_php('resource/' . $legacyId, ['id' => null]);
}
require_login();

$id = $legacyId;
$user = current_user();
$isAdmin = is_admin();
$stmt = $pdo->prepare("SELECT r.*, COALESCE(r.status, 'approved') AS status, c.name AS category_name, u.name AS creator_name
                       FROM resources r
                       LEFT JOIN categories c ON r.category_id = c.id
                       LEFT JOIN users u ON r.created_by = u.id
                       WHERE r.id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    flash_message('error', 'Resource not found.');
    log_warning('Resource view requested for missing resource', ['resource_id' => $id]);
    header('Location: ' . app_path(''));
    exit;
}

if (!resource_is_visible($resource, $user)) {
    flash_message('error', 'This resource is not yet available.');
    log_warning('Resource access blocked', ['resource_id' => $id, 'status' => $resource['status'] ?? null]);
    header('Location: ' . app_path(''));
    exit;
}

$csrf = get_csrf_token();
$reviewSort = $_GET['review_sort'] ?? 'newest';
if (!in_array($reviewSort, ['newest', 'oldest', 'rating_high', 'rating_low'], true)) {
    $reviewSort = 'newest';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('resource/' . $id));
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'add_review') {
        $rating = (int)($_POST['rating'] ?? 0);
        $reviewText = trim($_POST['review'] ?? '');
        if ($rating < 1 || $rating > 5) {
            flash_message('error', 'Please select a rating between 1 and 5.');
        } else {
            $status = save_resource_review($id, $user['id'], $rating, $reviewText);
            $message = $status === 'approved'
                ? 'Your review has been posted.'
                : 'Your review has been submitted for approval.';
            flash_message('success', $message);
        }
        header('Location: ' . app_path('resource/' . $id));
        exit;
    }

    if ($action === 'add_comment') {
        $commentText = trim($_POST['comment'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0);
        if ($parentId > 0) {
            $check = $pdo->prepare("SELECT id FROM resource_comments WHERE id = :id AND resource_id = :rid");
            $check->execute([':id' => $parentId, ':rid' => $id]);
            if (!$check->fetch()) {
                $parentId = 0;
            }
        }
        if ($commentText === '') {
            flash_message('error', 'Comment cannot be empty.');
        } else {
            $status = save_resource_comment($id, $user['id'], $commentText, $parentId > 0 ? $parentId : null);
            $message = $status === 'approved'
                ? 'Your comment is now visible.'
                : 'Your comment has been submitted for approval.';
            flash_message('success', $message);
        }
        header('Location: ' . app_path('resource/' . $id));
        exit;
    }

    if ($action === 'report_comment' || $action === 'report_review') {
        $contentId = (int)($_POST['content_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $type = $action === 'report_comment' ? 'comment' : 'review';
        if ($contentId > 0) {
            $reported = report_resource_content($type, $contentId, $user['id'], $reason);
            if ($reported) {
                flash_message('success', 'Report submitted. Thank you for the feedback.');
            } else {
                flash_message('error', 'Unable to submit report.');
            }
        } else {
            flash_message('error', 'Invalid report target.');
        }
        header('Location: ' . app_path('resource/' . $id));
        exit;
    }
}

$title       = $resource['title'];
$type        = $resource['type'];
$filePath    = $resource['file_path'];
$externalUrl = $resource['external_url'];

$meta_title = $title . ' - ' . $APP_NAME;
$meta_description = !empty($resource['description'])
    ? substr(trim(strip_tags($resource['description'])), 0, 160)
    : 'View resource details in ' . ($FULL_APP_NAME ?? $APP_NAME) . '.';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$baseUrl = $scheme . '://' . $host . $basePath . '/';

$fileUrl = $filePath ? $baseUrl . $filePath : null;
$viewerUrl = app_path('viewer/' . $id);
$downloadUrl = (!empty($filePath) && !in_array($type, ['link','video_link'], true))
    ? app_path('download/' . $id)
    : null;

$ratingSummary = get_resource_rating_summary($id);
$userReview = $user ? get_user_review($id, $user['id']) : null;
$reviews = get_resource_reviews_for_display($id, $user['id'] ?? null, $isAdmin, $reviewSort);
$comments = get_resource_comments_for_display($id, $user['id'] ?? null, $isAdmin, 'oldest');
$commentsByParent = [];
foreach ($comments as $comment) {
    $parentKey = (int)($comment['parent_id'] ?? 0);
    $commentsByParent[$parentKey][] = $comment;
}

$resourceTags = get_resource_tags($id);
$similarResources = get_similar_resources($id, 4);
$similarTags = get_tags_for_resources(array_column($similarResources, 'id'));
$resourceQuizzes = get_resource_quizzes($id);
$quizBestScores = [];
if ($user && !empty($resourceQuizzes)) {
    $attempts = get_user_quiz_attempts($user['id']);
    foreach ($attempts as $attempt) {
        $qid = (int)($attempt['quiz_id'] ?? 0);
        if (!$qid) {
            continue;
        }
        $score = (int)($attempt['score'] ?? 0);
        $total = (int)($attempt['total_questions'] ?? 0);
        if (!isset($quizBestScores[$qid]) || $score > ($quizBestScores[$qid]['score'] ?? -1)) {
            $quizBestScores[$qid] = [
                'score' => $score,
                'total' => $total,
            ];
        }
    }
}
$aiAvailable = function_exists('ai_is_configured') && ai_is_configured();

include __DIR__ . '/includes/header.php';
?>
<h3 class="mb-1"><?php echo h($title); ?></h3>
<span class="badge bg-info text-dark"><?php echo h(strtoupper($type)); ?></span>
<?php if (!empty($resource['category_name'])): ?>
  <span class="badge bg-secondary"><?php echo h($resource['category_name']); ?></span>
<?php endif; ?>
<?php if (!empty($resource['creator_name'])): ?>
  <span class="badge bg-light text-muted">by <?php echo h($resource['creator_name']); ?></span>
<?php endif; ?>
<?php if (!empty($resourceTags)): ?>
  <div class="d-flex flex-wrap gap-1 mt-2">
    <?php foreach ($resourceTags as $tag): ?>
      <span class="badge bg-light text-muted">#<?= h($tag) ?></span>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mt-3">
  <a href="<?php echo h($viewerUrl); ?>" class="btn btn-primary">
    <i class="fas fa-eye me-2"></i>Open in Viewer
  </a>
  <?php if ($downloadUrl): ?>
    <a href="<?php echo h($downloadUrl); ?>" class="btn btn-outline-secondary">
      <i class="fas fa-download me-2"></i>Download
    </a>
  <?php endif; ?>
  <?php if ($externalUrl): ?>
    <a href="<?php echo h($externalUrl); ?>" target="_blank" rel="noopener" class="btn btn-outline-info">
      <i class="fas fa-external-link-alt me-2"></i>Open Link
    </a>
  <?php endif; ?>
</div>

<?php if (!empty($resource['description'])): ?>
  <p class="mt-3"><?php echo nl2br(h($resource['description'])); ?></p>
<?php endif; ?>

<?php if (!empty($resource['ai_summary'])): ?>
  <div class="card mt-3" id="aiSummaryCard">
    <div class="card-body">
      <h5 class="card-title"><i class="fas fa-robot me-2"></i>AI Summary</h5>
      <p class="mb-0" id="aiSummaryText"><?= nl2br(h($resource['ai_summary'])) ?></p>
    </div>
  </div>
<?php elseif ($isAdmin && $aiAvailable): ?>
  <div class="card mt-3" id="aiSummaryCard">
    <div class="card-body">
      <h5 class="card-title"><i class="fas fa-robot me-2"></i>AI Summary</h5>
      <p class="text-muted mb-3">No summary generated yet.</p>
      <button type="button" class="btn btn-outline-primary" id="generateSummaryBtn">
        <i class="fas fa-magic me-2"></i>Generate Summary
      </button>
      <div class="mt-3 d-none" id="aiSummaryPreview"></div>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($resource['cover_image_path'])): ?>
  <div class="my-3">
    <img src="<?php echo h(app_path($resource['cover_image_path'])); ?>" alt="Cover image" class="img-fluid rounded shadow-sm border">
  </div>
<?php endif; ?>

<div class="viewer-container mt-3 rounded shadow-sm">
<?php if ($type === 'pdf' && $fileUrl): ?>

    <iframe src="<?php echo h($fileUrl); ?>#toolbar=0&navpanes=0"
            allowfullscreen class="resource-frame"></iframe>

<?php elseif ($type === 'epub' && $fileUrl): ?>

    <div id="epub-reader" class="resource-epub"></div>

<?php elseif ($type === 'video_file' && $fileUrl): ?>

    <video controls controlsList="nodownload" oncontextmenu="return false;" class="resource-video">
        <source src="<?php echo h($fileUrl); ?>" type="video/mp4">
        Your browser does not support the video tag.
    </video>

<?php elseif ($type === 'video_link' && $externalUrl): ?>

    <?php
    $youtubeEmbed = null;
    if (strpos($externalUrl, 'youtube.com/watch') !== false || strpos($externalUrl, 'youtu.be') !== false) {
        if (preg_match('~(?:v=|youtu\.be/)([A-Za-z0-9_-]{11})~', $externalUrl, $m)) {
            $youtubeEmbed = 'https://www.youtube.com/embed/' . $m[1];
        }
    }
    ?>
    <?php if ($youtubeEmbed): ?>
        <iframe src="<?php echo h($youtubeEmbed); ?>" allowfullscreen class="resource-frame"></iframe>
    <?php else: ?>
        <div class="p-3 text-white">
            <p>Video link:</p>
            <a href="<?php echo h($externalUrl); ?>" target="_blank" class="btn btn-light">Open Video</a>
        </div>
    <?php endif; ?>

<?php elseif (in_array($type, ['doc','ppt','xls'], true) && $fileUrl): ?>

    <?php
    if (!empty($ONLYOFFICE_BASE_URL)) {
        $encoded = urlencode($fileUrl);
        $viewerUrl = rtrim($ONLYOFFICE_BASE_URL, '/') . "/web-apps/apps/documenteditor/main/index.html?fileID=" . $encoded;
    } else {
        $encoded = urlencode($fileUrl);
        $viewerUrl = "https://view.officeapps.live.com/op/embed.aspx?src=" . $encoded;
    }
    ?>
    <iframe src="<?php echo h($viewerUrl); ?>" class="resource-embed"></iframe>

<?php elseif ($type === 'link' && $externalUrl): ?>

    <iframe src="<?php echo h($externalUrl); ?>" class="resource-embed"></iframe>

<?php else: ?>

    <div class="p-3 text-white">
        <p>Preview not available for this resource.</p>
    </div>

<?php endif; ?>
</div>

<?php if (!empty($resourceQuizzes) || $isAdmin): ?>
  <div class="dashboard-section mt-4">
    <div class="dashboard-section-header">
      <h2><i class="fas fa-clipboard-list me-2 text-primary"></i>Quizzes</h2>
      <?php if ($isAdmin): ?>
        <a href="<?= h(app_path('admin/quiz/add/' . $id)) ?>" class="btn btn-sm btn-outline-primary">
          <i class="fas fa-plus me-1"></i>Create Quiz
        </a>
      <?php endif; ?>
    </div>
    <?php if (empty($resourceQuizzes)): ?>
      <p class="text-muted mb-0">No quizzes available for this resource.</p>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($resourceQuizzes as $quiz): ?>
          <?php
            $best = $quizBestScores[$quiz['id']] ?? null;
            $bestText = $best ? ($best['score'] . '/' . max(1, $best['total'])) : 'No attempts yet';
          ?>
          <div class="col-md-6 col-lg-4">
            <div class="card h-100">
              <div class="card-body">
                <h5 class="card-title"><?= h($quiz['title']) ?></h5>
                <?php if (!empty($quiz['description'])): ?>
                  <p class="card-text text-muted"><?= h(mb_strimwidth($quiz['description'], 0, 120, '...')) ?></p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                  <span class="badge bg-secondary"><?= (int)$quiz['question_count'] ?> questions</span>
                  <span class="badge bg-info text-dark">Best: <?= h($bestText) ?></span>
                </div>
              </div>
              <div class="card-footer bg-transparent">
                <a href="<?= h(app_path('quiz/' . $quiz['id'])) ?>" class="btn btn-primary w-100">Take Quiz</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!empty($similarResources)): ?>
  <div class="dashboard-section mt-4">
    <div class="dashboard-section-header">
      <h2><i class="fas fa-lightbulb me-2 text-warning"></i>Similar Resources</h2>
    </div>
    <div class="row g-4 mb-4">
      <?php foreach ($similarResources as $sim): ?>
        <?php
          $simCover = !empty($sim['cover_image_path'])
              ? app_path($sim['cover_image_path'])
              : 'https://via.placeholder.com/400x280/667eea/ffffff?text=' . urlencode($sim['title']);
          $simBadgeColor = 'secondary';
          if (!empty($sim['type'])) {
            $simBadgeColor = str_contains($sim['type'], 'pdf') ? 'danger' : (str_contains($sim['type'], 'video') ? 'warning' : 'primary');
          }
          $simTags = $similarTags[$sim['id']] ?? [];
          $simDisplayTags = array_slice($simTags, 0, 2);
        ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
          <div class="resource-card">
            <div class="resource-image-wrapper">
              <img src="<?= h($simCover) ?>" class="resource-image" alt="<?= h($sim['title']) ?>" loading="lazy">
              <span class="resource-badge text-<?= h($simBadgeColor) ?>">
                <i class="fas fa-book me-1"></i><?= strtoupper($sim['type'] ?? 'FILE') ?>
              </span>
            </div>
            <div class="resource-body">
              <h5 class="resource-title"><?= h($sim['title']) ?></h5>
              <?php if (!empty($sim['category_name'])): ?>
                <span class="resource-category">
                  <i class="fas fa-folder"></i>
                  <?= h($sim['category_name']) ?>
                </span>
              <?php endif; ?>
              <?php if (!empty($simDisplayTags)): ?>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  <?php foreach ($simDisplayTags as $tag): ?>
                    <span class="badge bg-light text-muted">#<?= h($tag) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($simTags) > 2): ?>
                    <span class="badge bg-secondary">+<?= count($simTags) - 2 ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <div class="resource-actions">
                <a href="<?= h(app_path('viewer/' . $sim['id'])) ?>" class="btn btn-primary flex-grow-1">
                  <i class="fas fa-eye me-2"></i>Open
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php
$renderStars = function (int $rating) {
    $rating = max(0, min(5, $rating));
    for ($i = 1; $i <= 5; $i++) {
        $class = $i <= $rating ? 'text-warning' : 'text-muted';
        echo '<i class="fas fa-star ' . $class . '"></i>';
    }
};

$renderComments = function (int $parentId = 0, int $depth = 0) use (&$renderComments, $commentsByParent, $csrf, $user, $COMMENTS_ENABLE_REPORTING) {
    if (empty($commentsByParent[$parentId])) {
        return;
    }
    foreach ($commentsByParent[$parentId] as $comment) {
        $commentId = (int)$comment['id'];
        $status = $comment['status'] ?? 'approved';
        echo '<div class="comment-item">';
        echo '  <div class="comment-header">';
        echo '      <span class="comment-author">' . h($comment['user_name']) . '</span>';
        echo '      <span class="comment-date">' . date('M d, Y', strtotime($comment['created_at'])) . '</span>';
        echo '  </div>';
        if ($status !== 'approved') {
            echo '  <span class="status-badge status-' . h($status) . '">Pending</span>';
        }
        echo '  <div class="comment-body">' . nl2br(h($comment['content'])) . '</div>';
        echo '  <div class="comment-actions">';
        echo '      <button type="button" class="btn btn-sm btn-outline-secondary comment-reply-toggle" data-target="reply-form-' . $commentId . '">Reply</button>';
        if (!empty($COMMENTS_ENABLE_REPORTING) && (int)$comment['user_id'] !== (int)$user['id']) {
            echo '  <form method="post" class="report-form">';
            echo '      <input type="hidden" name="csrf_token" value="' . h($csrf) . '">';
            echo '      <input type="hidden" name="action" value="report_comment">';
            echo '      <input type="hidden" name="content_id" value="' . $commentId . '">';
            echo '      <button type="submit" class="btn btn-sm btn-outline-danger">Report</button>';
            echo '  </form>';
        }
        echo '  </div>';
        echo '  <form method="post" class="comment-reply-form" id="reply-form-' . $commentId . '">';
        echo '      <input type="hidden" name="csrf_token" value="' . h($csrf) . '">';
        echo '      <input type="hidden" name="action" value="add_comment">';
        echo '      <input type="hidden" name="parent_id" value="' . $commentId . '">';
        echo '      <textarea name="comment" class="form-control" rows="2" placeholder="Write a reply..."></textarea>';
        echo '      <button type="submit" class="btn btn-sm btn-outline-primary mt-2">Post Reply</button>';
        echo '  </form>';

        if (!empty($commentsByParent[$commentId])) {
            echo '<div class="comment-children">';
            $renderComments($commentId, $depth + 1);
            echo '</div>';
        }
        echo '</div>';
    }
};
?>

<div class="resource-community mt-4">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="review-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Ratings & Reviews</h4>
                    <span class="rating-count"><?= (int)$ratingSummary['total_reviews'] ?> reviews</span>
                </div>
                <div class="rating-summary">
                    <div class="rating-score"><?= number_format((float)$ratingSummary['avg_rating'], 1) ?></div>
                    <div class="rating-stars">
                        <?php $renderStars((int)round($ratingSummary['avg_rating'])); ?>
                    </div>
                </div>

                <form method="post" class="review-form mt-3">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="add_review">
                    <label class="form-label">Your Rating</label>
                    <div class="rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="rating<?= $i ?>" name="rating" value="<?= $i ?>"
                                   <?= !empty($userReview) && (int)$userReview['rating'] === $i ? 'checked' : '' ?>>
                            <label for="rating<?= $i ?>" title="<?= $i ?> stars"><i class="fas fa-star"></i></label>
                        <?php endfor; ?>
                    </div>
                    <label class="form-label mt-3">Review (optional)</label>
                    <textarea name="review" class="form-control" rows="3"
                              placeholder="Share your thoughts..."><?= h($userReview['review'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-primary mt-3 w-100">
                        <?= $userReview ? 'Update Review' : 'Submit Review' ?>
                    </button>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="review-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h4 class="mb-0">Reviews</h4>
                    <form method="get" class="review-sort-form">
                        <label class="me-2 text-muted">Sort by:</label>
                        <select name="review_sort" class="form-select form-select-sm review-sort-select" onchange="this.form.submit()">
                            <option value="newest" <?= $reviewSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $reviewSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="rating_high" <?= $reviewSort === 'rating_high' ? 'selected' : '' ?>>Highest Rating</option>
                            <option value="rating_low" <?= $reviewSort === 'rating_low' ? 'selected' : '' ?>>Lowest Rating</option>
                        </select>
                    </form>
                </div>
                <?php if (empty($reviews)): ?>
                    <p class="text-muted mb-0">No reviews yet. Be the first to review this resource.</p>
                <?php else: ?>
                    <div class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <div>
                                        <span class="review-author"><?= h($review['user_name']) ?></span>
                                        <span class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                                    </div>
                                    <div class="review-stars">
                                        <?php $renderStars((int)$review['rating']); ?>
                                    </div>
                                </div>
                                <?php if (($review['status'] ?? 'approved') !== 'approved'): ?>
                                    <span class="status-badge status-<?= h($review['status']) ?>">Pending</span>
                                <?php endif; ?>
                                <?php if (!empty($review['review'])): ?>
                                    <p class="review-text"><?= nl2br(h($review['review'])) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($REVIEWS_ENABLE_REPORTING) && (int)$review['user_id'] !== (int)$user['id']): ?>
                                    <form method="post" class="report-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="action" value="report_review">
                                        <input type="hidden" name="content_id" value="<?= (int)$review['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Report</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="comments-card mt-4">
        <h4 class="mb-3">Comments</h4>
        <form method="post" class="comment-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="add_comment">
            <textarea name="comment" class="form-control" rows="3" placeholder="Join the discussion..."></textarea>
            <button type="submit" class="btn btn-outline-primary mt-3">Post Comment</button>
        </form>

        <?php if (empty($comments)): ?>
            <p class="text-muted mt-3 mb-0">No comments yet.</p>
        <?php else: ?>
            <div class="comment-list mt-3">
                <?php $renderComments(0); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.comment-reply-toggle').forEach(function(button) {
        button.addEventListener('click', function() {
            var targetId = button.getAttribute('data-target');
            if (!targetId) return;
            var form = document.getElementById(targetId);
            if (form) {
                form.classList.toggle('is-visible');
            }
        });
    });
});
</script>

<?php if ($isAdmin && $aiAvailable): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('generateSummaryBtn');
    if (!btn) return;
    btn.addEventListener('click', function() {
        btn.disabled = true;
        btn.innerText = 'Generating...';
        fetch(appPath + 'api/summarize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token: csrfToken,
                resource_id: '<?= (int)$id ?>'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                showToast(data.error, 'error');
                return;
            }
            const preview = document.getElementById('aiSummaryPreview');
            const card = document.getElementById('aiSummaryCard');
            if (preview) {
                preview.classList.remove('d-none');
                preview.textContent = data.summary || '';
            }
            if (card && data.summary) {
                const text = document.getElementById('aiSummaryText');
                if (text) text.textContent = data.summary;
            }
            showToast('Summary generated', 'success');
        })
        .catch(() => showToast('Failed to generate summary', 'error'))
        .finally(() => {
            btn.disabled = false;
            btn.innerText = 'Generate Summary';
        });
    });
});
</script>
<?php endif; ?>

<?php if ($type === 'epub' && $fileUrl): ?>
<!-- Load JSZip first (required for EPUB) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<!-- Load epub.js -->
<script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
<script>
(function() {
    // Wait for libraries to load
    if (typeof ePub === 'undefined' || typeof JSZip === 'undefined') {
        console.error('Required libraries failed to load');
        document.getElementById('epub-reader').innerHTML = '<p class="text-center p-3">Failed to load EPUB viewer libraries.</p>';
        return;
    }

    const container = document.getElementById('epub-reader');
    const loading = document.getElementById('epub-loading');
    
    try {
        // Initialize the book
        const book = ePub("<?php echo h($fileUrl); ?>");
        
        // Render to the container
        const rendition = book.renderTo("epub-reader", {
            width: "100%",
            height: "100%",
            spread: "none"
        });
        
        // Display the book
        rendition.display().then(function() {
            if (loading) loading.style.display = 'none';
            console.log('EPUB loaded successfully');
        }).catch(function(err) {
            console.error('EPUB display error:', err);
            container.innerHTML = '<p class="text-center p-3 text-danger">Failed to display EPUB: ' + err.message + '</p>';
        });
        
        // Add navigation controls
        const prevBtn = document.createElement('button');
        prevBtn.textContent = '← Previous';
        prevBtn.className = 'btn btn-sm btn-primary';
        prevBtn.style.cssText = 'position:absolute;bottom:20px;left:20px;z-index:100;';
        prevBtn.onclick = function() { rendition.prev(); };
        
        const nextBtn = document.createElement('button');
        nextBtn.textContent = 'Next →';
        nextBtn.className = 'btn btn-sm btn-primary';
        nextBtn.style.cssText = 'position:absolute;bottom:20px;right:20px;z-index:100;';
        nextBtn.onclick = function() { rendition.next(); };
        
        container.appendChild(prevBtn);
        container.appendChild(nextBtn);
        
    } catch (err) {
        console.error('EPUB initialization error:', err);
        container.innerHTML = '<p class="text-center p-3 text-danger">Failed to initialize EPUB viewer: ' + err.message + '</p>';
    }
})();
</script>
<?php endif; ?>
</div>

<div class="mt-3">
  <a href="<?= h(app_path('')) ?>" class="btn btn-secondary">Back to list</a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
