<?php
require_once __DIR__ . '/includes/auth.php';
redirect_legacy_php('submit');
require_login();

$user = current_user();
$isAdmin = is_admin();

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Resource submission CSRF validation failed');
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('submit'));
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $external_url = trim($_POST['external_url'] ?? '');

    if ($title === '' || $type === '') {
        flash_message('error', 'Title and type are required.');
        header('Location: ' . app_path('submit'));
        exit;
    }

    $requiresFile = !in_array($type, ['link', 'video_link'], true);
    $requiresUrl = in_array($type, ['link', 'video_link'], true);
    if ($requiresUrl && $external_url === '') {
        flash_message('error', 'External URL is required for links and video links.');
        header('Location: ' . app_path('submit'));
        exit;
    }

    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 200 * 1024 * 1024; // 200MB
    $uploadResult = handle_file_upload(
        $_FILES['file'] ?? null,
        $allowedExt,
        $USER_RESOURCE_UPLOAD_DIR,
        $USER_RESOURCE_UPLOAD_PREFIX,
        $maxUploadBytes
    );
    if ($uploadResult['error']) {
        log_error('Resource submission upload failed', ['error' => $uploadResult['error']]);
        flash_message('error', $uploadResult['error']);
        header('Location: ' . app_path('submit'));
        exit;
    }
    $filePath = $uploadResult['path'];

    if ($requiresFile && empty($filePath)) {
        flash_message('error', 'Please upload a resource file.');
        header('Location: ' . app_path('submit'));
        exit;
    }

    $coverResult = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        $USER_RESOURCE_COVER_DIR,
        $USER_RESOURCE_COVER_PREFIX,
        10 * 1024 * 1024,
        ['max_width' => 1600, 'max_height' => 1600, 'max_pixels' => 12000000, 'quality' => 85]
    );
    if ($coverResult['error']) {
        log_error('Resource cover upload failed', ['error' => $coverResult['error']]);
        flash_message('error', $coverResult['error']);
        header('Location: ' . app_path('submit'));
        exit;
    }
    $coverPath = $coverResult['path'];

    $status = (!$isAdmin && !empty($RESOURCE_SUBMISSION_REQUIRES_APPROVAL)) ? 'pending' : 'approved';
    $approvedBy = $status === 'approved' ? $user['id'] : null;

    $stmt = $pdo->prepare("INSERT INTO resources
        (title, description, type, category_id, file_path, cover_image_path, external_url, created_by, status, approved_by, approved_at)
        VALUES (:title, :description, :type, :category_id, :file_path, :cover, :external_url, :created_by, :status, :approved_by, :approved_at)");
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':type' => $type,
        ':category_id' => $category_id,
        ':file_path' => $filePath,
        ':cover' => $coverPath,
        ':external_url' => $external_url ?: null,
        ':created_by' => $user['id'],
        ':status' => $status,
        ':approved_by' => $approvedBy,
        ':approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
    ]);

    log_info('Resource submitted', [
        'resource_id' => $pdo->lastInsertId(),
        'title' => $title,
        'status' => $status,
        'submitter_id' => $user['id'],
    ]);

    if ($status === 'pending' && !empty($MAIL_ADMIN_ADDRESS) && mailer_is_configured()) {
        $subject = 'New Resource Submission - ' . $APP_NAME;
        $body = '<p>A new resource has been submitted for review.</p>'
              . '<p><strong>Title:</strong> ' . h($title) . '</p>'
              . '<p><strong>Submitted by:</strong> ' . h($user['name']) . '</p>';
        send_app_mail($MAIL_ADMIN_ADDRESS, $subject, $body);
    }

    $message = $status === 'approved'
        ? 'Resource added successfully!'
        : 'Resource submitted. It will be visible after admin approval.';
    flash_message('success', $message);
    header('Location: ' . app_path('my-submissions'));
    exit;
}

$csrf = get_csrf_token();
$meta_title = 'Submit Resource - ' . $APP_NAME;
$meta_description = 'Submit a resource for review in ' . $APP_NAME . '.';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-upload me-2"></i>Submit a Resource
            </h2>
            <p class="text-muted mb-0">Your submission will be reviewed before it appears in the library.</p>
        </div>
        <a href="<?= h(app_path('my-submissions')) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-list me-2"></i>My Submissions
        </a>
    </div>
</div>

<div class="form-card">
    <form method="post" enctype="multipart/form-data" id="resourceForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-info-circle"></i>Resource Details
            </h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-heading"></i>
                        Title <span class="required">*</span>
                    </label>
                    <input type="text" name="title" class="form-control" required placeholder="Enter resource title">
                </div>
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-tag"></i>
                        Resource Type <span class="required">*</span>
                    </label>
                    <select name="type" class="form-select" required>
                        <option value="">Select type...</option>
                        <option value="pdf">PDF Document</option>
                        <option value="epub">EPUB Book</option>
                        <option value="video_file">Video File (MP4/WebM)</option>
                        <option value="video_link">Video Link (YouTube, etc.)</option>
                        <option value="doc">Word Document (DOC/DOCX)</option>
                        <option value="ppt">PowerPoint (PPT/PPTX)</option>
                        <option value="xls">Excel (XLS/XLSX)</option>
                        <option value="link">Web Link</option>
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
                </div>
                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i>
                        Description
                    </label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe this resource"></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-cloud-upload-alt"></i>File Upload</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-file"></i>Resource File</label>
                    <div class="file-upload-area" id="fileDropZone">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <p class="file-upload-text"><strong>Drag and drop</strong> file or <strong>click to browse</strong></p>
                        <small class="text-muted">PDF, EPUB, MP4, WebM, DOC, PPT, XLS (Max 200MB)</small>
                    </div>
                    <input type="file" name="file" id="fileInput" class="hidden-input">

                    <div class="file-preview" id="filePreview">
                        <i class="fas fa-file-alt file-preview-icon" id="fileIcon"></i>
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="fileName"></div>
                            <div class="file-preview-size" id="fileSize"></div>
                        </div>
                        <button type="button" class="file-preview-remove" id="removeFile">Remove</button>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-image"></i>Cover Image</label>
                    <div class="file-upload-area" id="coverDropZone">
                        <i class="fas fa-image file-upload-icon"></i>
                        <p class="file-upload-text"><strong>Drag and drop</strong> image or <strong>click to browse</strong></p>
                        <small class="text-muted">JPG, PNG, GIF, WEBP (Max 10MB)</small>
                    </div>
                    <input type="file" name="cover_image" id="coverInput" class="hidden-input" accept="image/*">

                    <div class="image-preview" id="imagePreview">
                        <img src="" alt="Cover preview" id="previewImg">
                        <button type="button" class="image-preview-remove" id="removeCover">Remove</button>
                    </div>
                </div>

                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-link"></i>External URL</label>
                    <input type="url" name="external_url" class="form-control" placeholder="https://example.com/resource">
                    <div class="form-text"><i class="fas fa-info-circle"></i>Required for link or video link types.</div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="<?= h(app_path('')) ?>" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-paper-plane me-2"></i>Submit for Review
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileDropZone = document.getElementById('fileDropZone');
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileIcon = document.getElementById('fileIcon');
    const removeFile = document.getElementById('removeFile');

    const coverDropZone = document.getElementById('coverDropZone');
    const coverInput = document.getElementById('coverInput');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const removeCover = document.getElementById('removeCover');

    const fileIcons = {
        'pdf': 'fa-file-pdf',
        'epub': 'fa-book',
        'mp4': 'fa-file-video',
        'webm': 'fa-file-video',
        'doc': 'fa-file-word',
        'docx': 'fa-file-word',
        'ppt': 'fa-file-powerpoint',
        'pptx': 'fa-file-powerpoint',
        'xls': 'fa-file-excel',
        'xlsx': 'fa-file-excel'
    };

    function getFileIcon(name) {
        const ext = name.split('.').pop().toLowerCase();
        return fileIcons[ext] || 'fa-file';
    }

    function formatFileSize(bytes) {
        if (!bytes) return '';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    fileDropZone.addEventListener('click', () => fileInput.click());
    fileDropZone.addEventListener('dragover', (e) => { e.preventDefault(); fileDropZone.classList.add('dragover'); });
    fileDropZone.addEventListener('dragleave', () => fileDropZone.classList.remove('dragover'));
    fileDropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        fileDropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFileSelect(files[0]);
        }
    });
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handleFileSelect(e.target.files[0]);
    });

    function handleFileSelect(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        fileIcon.className = 'fas ' + getFileIcon(file.name) + ' file-preview-icon';
        filePreview.classList.add('active');
        fileDropZone.style.display = 'none';
    }

    removeFile.addEventListener('click', () => {
        fileInput.value = '';
        filePreview.classList.remove('active');
        fileDropZone.style.display = 'block';
    });

    coverDropZone.addEventListener('click', () => coverInput.click());
    coverDropZone.addEventListener('dragover', (e) => { e.preventDefault(); coverDropZone.classList.add('dragover'); });
    coverDropZone.addEventListener('dragleave', () => coverDropZone.classList.remove('dragover'));
    coverDropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        coverDropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('image/')) {
            coverInput.files = files;
            handleCoverSelect(files[0]);
        }
    });
    coverInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handleCoverSelect(e.target.files[0]);
    });

    function handleCoverSelect(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            imagePreview.classList.add('active');
            coverDropZone.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    removeCover.addEventListener('click', () => {
        coverInput.value = '';
        imagePreview.classList.remove('active');
        previewImg.src = '';
        coverDropZone.style.display = 'block';
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
