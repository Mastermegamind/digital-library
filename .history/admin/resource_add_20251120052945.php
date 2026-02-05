<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Resource add CSRF validation failed');
        flash_message('error', 'Invalid session token.');
        header('Location: resource_add.php');
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $external_url = trim($_POST['external_url'] ?? '');

    if ($title === '' || $type === '') {
        log_warning('Resource add validation failed', ['title' => $title, 'type' => $type]);
        flash_message('error', 'Title and type are required.');
        header('Location: resource_add.php');
        exit;
    }

    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 50 * 1024 * 1024; // 50MB
    $uploadResult = handle_file_upload($_FILES['file'] ?? null, $allowedExt, __DIR__ . '/../uploads', 'uploads', $maxUploadBytes);
    if ($uploadResult['error']) {
        log_error('Resource add upload failed', ['error' => $uploadResult['error']]);
        flash_message('error', $uploadResult['error']);
        header('Location: resource_add.php');
        exit;
    }
    $filePath = $uploadResult['path'];

    $coverResult = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/covers',
        'uploads/covers',
        10 * 1024 * 1024
    );
    if ($coverResult['error']) {
        log_error('Resource cover upload failed', ['error' => $coverResult['error']]);
        flash_message('error', $coverResult['error']);
        header('Location: resource_add.php');
        exit;
    }
    $coverPath = $coverResult['path'];

    $stmt = $pdo->prepare("INSERT INTO resources
        (title, description, type, category_id, file_path, cover_image_path, external_url, created_by)
        VALUES (:title, :description, :type, :category_id, :file_path, :cover, :external_url, :created_by)");
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':type' => $type,
        ':category_id' => $category_id,
        ':file_path' => $filePath,
        ':cover' => $coverPath,
        ':external_url' => $external_url ?: null,
        ':created_by' => current_user()['id'],
    ]);

    log_info('Resource created', [
        'resource_id' => $pdo->lastInsertId(),
        'title' => $title,
        'type' => $type,
    ]);

    flash_message('success', 'Resource added successfully!');
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

    .file-upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        background: rgba(37, 99, 235, 0.02);
    }

    .file-upload-area:hover {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }

    .file-upload-icon {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        padding-top: 2rem;
        border-top: 2px solid var(--border-color);
    }

    .info-box {
        background: rgba(37, 99, 235, 0.05);
        border-left: 4px solid var(--primary-color);
        border-radius: 8px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }

    .info-box-title {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-box-text {
        color: var(--text-secondary);
        font-size: 0.875rem;
        margin: 0;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
                <i class="fas fa-plus-circle me-3"></i>Add New Resource
            </h2>
            <p class="text-muted mb-0">
                Upload a new learning resource to CONS-UNTH E-LIBRARY
            </p>
        </div>
        <div>
            <a href="resources.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Resources
            </a>
        </div>
    </div>
</div>

<!-- Form Card -->
<div class="form-card">
    <!-- Info Box -->
    <div class="info-box">
        <div class="info-box-title">
            <i class="fas fa-info-circle"></i>
            Before You Start
        </div>
        <p class="info-box-text">
            Make sure you have all necessary files ready. Supported formats include PDF, EPUB, Office documents, and video files up to 50MB. Cover images should be under 10MB.
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
                           placeholder="Enter resource title...">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-tag"></i>
                        Resource Type <span class="required">*</span>
                    </label>
                    <select name="type" class="form-select" required>
                        <option value="">Select type...</option>
                        <option value="pdf">📄 PDF Document</option>
                        <option value="epub">📚 EPUB Book</option>
                        <option value="video_file">🎥 Video File (MP4/WebM)</option>
                        <option value="video_link">🔗 Video Link (YouTube, etc.)</option>
                        <option value="doc">📝 Word Document (DOC/DOCX)</option>
                        <option value="ppt">📊 PowerPoint (PPT/PPTX)</option>
                        <option value="xls">📈 Excel (XLS/XLSX)</option>
                        <option value="link">🌐 Web Link</option>
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
                            <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        <i class="fas fa-lightbulb"></i>
                        Organize your resource by selecting a category
                    </div>
                </div>
                
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i>
                        Description
                    </label>
                    <textarea name="description" class="form-control" rows="4"
                              placeholder="Enter a brief description of this resource..."></textarea>
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        Help users understand what this resource contains
                    </div>
                </div>
            </div>
        </div>

        <!-- File Upload Section -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-cloud-upload-alt"></i>
                File Upload
            </h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-file"></i>
                        Resource File
                    </label>
                    <input type="file" name="file" class="form-control">
                    <div class="form-text">
                        <i class="fas fa-check-circle"></i>
                        Supported: PDF, EPUB, MP4, WebM, DOC, DOCX, PPT, PPTX, XLS, XLSX (Max 50MB)
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-image"></i>
                        Cover Image
                    </label>
                    <input type="file" name="cover_image" class="form-control" accept="image/*">
                    <div class="form-text">
                        <i class="fas fa-check-circle"></i>
                        Accepted: JPG, PNG, GIF, WEBP (Max 10MB)
                    </div>
                </div>
                
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-link"></i>
                        External URL
                    </label>
                    <input type="url" name="external_url" class="form-control" 
                           placeholder="https://example.com/resource">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        For video links or web resources, paste the URL here
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="resources.php" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-save me-2"></i>Add Resource
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>