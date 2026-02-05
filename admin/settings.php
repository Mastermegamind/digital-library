<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/settings');
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/settings'));
        exit;
    }

    $registrationEnabled = !empty($_POST['registration_enabled']) ? '1' : '0';
    $registrationMode = $_POST['registration_mode'] ?? 'open';
    if (!in_array($registrationMode, ['open', 'admin_approved'], true)) {
        $registrationMode = 'open';
    }

    $requireVerification = !empty($_POST['require_email_verification']) ? '1' : '0';

    set_app_setting('registration_enabled', $registrationEnabled);
    set_app_setting('registration_mode', $registrationMode);
    set_app_setting('require_email_verification', $requireVerification);

    flash_message('success', 'Settings updated.');
    header('Location: ' . app_path('admin/settings'));
    exit;
}

$csrf = get_csrf_token();
$registrationEnabled = is_registration_enabled();
$registrationMode = get_registration_mode();
$requireVerification = is_email_verification_required();

$meta_title = 'Settings - Admin | ' . $APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-sliders-h me-2"></i>Settings
            </h2>
            <p class="text-muted mb-0">Control registration and verification options.</p>
        </div>
        <a href="<?= h(app_path('admin')) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="form-card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-user-plus"></i>Registration</h5>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="registrationEnabled" name="registration_enabled" <?= $registrationEnabled ? 'checked' : '' ?>>
                <label class="form-check-label" for="registrationEnabled">Enable self-registration page</label>
            </div>

            <label class="form-label">Registration Mode</label>
            <select name="registration_mode" class="form-select">
                <option value="open" <?= $registrationMode === 'open' ? 'selected' : '' ?>>Open (auto-approve)</option>
                <option value="admin_approved" <?= $registrationMode === 'admin_approved' ? 'selected' : '' ?>>Admin approval required</option>
            </select>
        </div>

        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-envelope-open-text"></i>Email Verification</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="requireVerification" name="require_email_verification" <?= $requireVerification ? 'checked' : '' ?>>
                <label class="form-check-label" for="requireVerification">Require email verification before login</label>
            </div>
            <small class="text-muted d-block mt-2">
                Ensure mailer settings are configured in `includes/config.php`.
            </small>
        </div>

        <div class="action-buttons">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-save me-2"></i>Save Settings
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
