<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    flash_message('error', 'Resource not found.');
    log_warning('Resource edit attempted on missing resource', ['resource_id' => $id]);
    header('Location: resources.php');
    exit;
}

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Resource edit CSRF validation failed', ['resource_id' => $id]);
        flash_message('error', 'Invalid session token.');
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $external_url = trim($_POST['external_url'] ?? '');
    $keep_file = isset($_POST['keep_file']);
    $remove_cover = isset($_POST['remove_cover']);

    if ($title === '' || $type === '') {
        log_warning('Resource edit validation failed', ['resource_id' => $id, 'title' => $title, 'type' => $type]);
        flash_message('error', 'Title and type are required.');
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 50 * 1024 * 1024;
    $uploadResult = handle_file_upload($_FILES['file'] ?? null, $allowedExt, __DIR__ . '/../uploads', 'uploads', $maxUploadBytes);
    if ($uploadResult['error']) {
        log_error('Resource edit upload failed', ['resource_id' => $id, 'error' => $uploadResult['error']]);
        flash_message('error', $uploadResult['error']);
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $filePath = $resource['file_path'];
    if ($uploadResult['path']) {
        delete_uploaded_file($resource['file_path']);
        $filePath = $uploadResult['path'];
    } elseif (!$keep_file && $resource['file_path']) {
        delete_uploaded_file($resource['file_path']);
        $filePath = null;
    }

    $coverUpload = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/covers',
        'uploads/covers',
        10 * 1024 * 1024
    );
    if ($coverUpload['error']) {
        log_error('Resource cover edit upload failed', ['resource_id' => $id, 'error' => $coverUpload['error']]);
        flash_message('error', $coverUpload['error']);
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $coverPath = $resource['cover_image_path'];
    if ($coverUpload['path']) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = $coverUpload['path'];
    } elseif ($remove_cover && $resource['cover_image_path']) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = null;
    }

    $stmt = $pdo->prepare("UPDATE resources
                           SET title = :title,
                               description = :description,
                               type = :type,
                               category_id = :category_id,
                               file_path = :file_path,
                               cover_image_path = :cover,
                               external_url = :external_url
                           WHERE id = :id");
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':type' => $type,
        ':category_id' => $category_id,
        ':file_path' => $filePath,
        ':cover' => $coverPath,
        ':external_url' => $external_url ?: null,
        ':id' => $id,
    ]);

    log_info('Resource updated', ['resource_id' => $id, 'title' => $title]);

    flash_message('success', 'Resource updated successfully!');
    header('Location: resources.php');
    exit;
}

$csrf = get_csrf_token();
include __DIR__ . '/../includes/header.php';
?>

<style>
    .page-header {
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(59, 130, 246, 0.05));
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(37, 99, 235, 0.1);
    }

    .form-card {
        background: white;
        border-radius: 16px;
        padding: 2.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid var(--border-color);
    }

    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-control, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 0.875rem 1.25rem;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .form-text {
        color: var(--text-secondary);
        font-size: 0.8rem;
        margin-top: 0.375rem;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .preview-box {
        background: rgba(37, 99, 235, 0.03);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 1.5rem;
        margin-top: 1rem;
    }

    .preview-title {
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .current-file-badge {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .preview-image {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        max-width: 100%;
    }

    .preview-image:hover {
        transform: scale(1.02);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .form-check {
        padding: 1rem;
        background: rgba(239, 68, 68, 0.05);
        border-radius: 8px;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .form-check-label {
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 2rem;
        border-top: 2px solid var(--border-color);
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        border-left: 4px solid #f59e0b;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }

    .alert-warning strong {
        color: #d97706;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
                <i class="fas fa-edit me-3"></i>Edit Resource
            </h2>
            <p class="text-muted mb-0">
                Update information for: <strong><?= h($resource['title']) ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="../viewer.php?id=<?= $id ?>" class="btn btn-outline-primary" target="_blank">
                <i class="fas fa-eye me-2"></i>Preview
            </a>
            <a href="resources.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>
</div>

<!-- Form Card -->
<div class="form-card">
    <div class="alert-warning">
        <strong><i class="fas fa-exclamation-triangle me-2"></i>Editing Resource</strong>
        <p class="mb-0 mt-1" style="color: var(--text-secondary);">
            Make changes carefully. Uploading new files will replace existing ones.
        </p>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        
        <!-- Basic Information Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-info-circle"></i>
                Basic Information
            </h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-heading"></i>
                        Title <span class="required">*</span>
                    </label>
                    <input type="text" name="title" class="form-control" required
                           value="<?= h($resource['title']) ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-tag"></i>
                        Resource Type <span class="required">*</span>
                    </label>
                    <select name="type" class="form-select" required>
                        <?php
                        $types = [
                            'pdf' => '📄 PDF Document',
                            'epub' => '📚 EPUB Book',
                            'video_file' => '🎥 Video File',
                            'video_link' => '🔗 Video Link',
                            'doc' => '📝 Word Document',
                            'ppt' => '📊 PowerPoint',
                            'xls' => '📈 Excel',
                            'link' => '🌐 Web Link'
                        ];
                        foreach ($types as $value => $label):
                        ?>
                            <option value="<?= $value ?>" <?= $resource['type'] === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-folder"></i>
                        Category
                    </label>
                    <select name="category_id" class="form-select">
                        <option value="">-- No Category --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $resource['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i>
                        Description
                    </label>
                    <textarea name="description" class="form-control" rows="4"><?= h($resource['description']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- File Management Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-file"></i>
                File Management
            </h5>
            
            <div class="row g-4">
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-upload"></i>
                        Replace Resource File
                    </label>
                    <input type="file" name="file" class="form-control">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        Leave empty to keep current file
                    </div>
                    
                    <?php if (!empty($resource['file_path'])): ?>
                        <div class="preview-box mt-3">
                            <div class="preview-title">
                                <i class="fas fa-file-alt"></i>Current File
                            </div>
                            <div class="current-file-badge">
                                <i class="fas fa-check-circle"></i>
                                <?= h(basename($resource['file_path'])) ?>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="keep_file" id="keep_file" checked>
                                <label class="form-check-label" for="keep_file">
                                    <i class="fas fa-save me-2"></i>Keep existing file
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-link"></i>
                        External URL
                    </label>
                    <input type="url" name="external_url" class="form-control" 
                           value="<?= h($resource['external_url']) ?>"
                           placeholder="https://example.com/resource">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        For video links or web resources
                    </div>
                </div>
            </div>
        </div>

        <!-- Cover Image Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-image"></i>
                Cover Image
            </h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-upload"></i>
                        Replace Cover Image
                    </label>
                    <input type="file" name="cover_image" class="form-control" accept="image/*">
                    <div class="form-text">
                        <i class="fas fa-check-circle"></i>
                        Accepted: JPG, PNG, GIF, WEBP (Max 10MB)
                    </div>
                </div>
                
                <?php if (!empty($resource['cover_image_path'])): ?>
                    <div class="col-md-6">
                        <div class="preview-title">Current Cover</div>
                        <?php $coverUrl = app_path($resource['cover_image_path']); ?>
                        <img src="<?= h($coverUrl) ?>" alt="Cover preview" class="preview-image">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="remove_cover" id="remove_cover">
                            <label class="form-check-label" for="remove_cover">
                                <i class="fas fa-trash-alt me-2"></i>Remove existing cover
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="resources.php" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i>Update Resource
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>