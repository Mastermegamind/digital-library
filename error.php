<?php
require_once __DIR__ . '/includes/auth.php';

$error_code = $error_code ?? 500;
$error_title = $error_title ?? 'Server Error';
$error_message = $error_message ?? 'Something went wrong. Please try again later.';

$meta_title = $error_title . ' - ' . $APP_NAME;
$meta_description = $error_message;

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column align-items-center justify-content-center text-center py-5">
    <h1 class="display-4 fw-bold mb-3"><?= h($error_code) ?></h1>
    <h2 class="h4 mb-3"><?= h($error_title) ?></h2>
    <p class="text-muted mb-4"><?= h($error_message) ?></p>
    <a href="<?= h(app_path('')) ?>" class="btn btn-primary">
        <i class="fas fa-home me-2"></i>Back to Home
    </a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
