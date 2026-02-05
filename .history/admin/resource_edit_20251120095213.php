<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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

    if ($title === '' || $type === '') {
        flash_message('error', 'Title and type are required.');
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    // Handle file replacement
    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 50 * 1024 * 1024;
    $uploadResult = handle_file_upload($_FILES['file'] ?? null, $allowedExt, __DIR__ . '/../uploads', 'uploads', $maxUploadBytes);
    if ($uploadResult['error']) {
        flash_message('error', $uploadResult['error']);
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $filePath = $resource['file_path'];
    if ($uploadResult['path']) {
        delete_uploaded_file($resource['file_path']);
        $filePath = $uploadResult['path'];
    }

    // Handle cover image
    $coverResult = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/covers',
        'uploads/covers',
        10 * 1024 * 1024
    );
    if ($coverResult['error']) {
        flash_message('error', $coverResult['error']);
        header('Location: resource_edit.php?id=' . $id);
        exit;
    }

    $coverPath = $resource['cover_image_path'];
    if ($coverResult['path']) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = $coverResult['path'];
    } elseif (isset($_POST['remove_cover'])) {
        delete_uploaded_file($resource['cover_image_path']);
        $coverPath = null;
    }

    $stmt = $pdo->prepare("UPDATE resources SET
        title = :title, description = :description, type = :type, category_id = :category_id,
        file_path = :file_path, cover_image_path = :cover, external_url = :external_url
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
    /* Same exact styles as resource_add.php */
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

    .form-label .required { color: #ef4444; }

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
        cursor: pointer;
        position: relative;
    }

    .file-upload-area:hover { border-color: var(--primary-color); background: rgba(37, 99, 235, 0.05); }
    .file-upload-area.dragover { border-color: var(--primary-color); background: rgba(37, 99, 235, 0.1); border-style: solid; }

    .file-upload-icon {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        opacity: 0.5;
        transition: all 0.3s ease;
    }

    .file-upload-area:hover .file-upload-icon { opacity: 0.8; transform: scale(1.1); }

    .file-upload-text strong { color: var(--primary-color); }

    .file-preview {
        margin-top: 1rem;
        padding: 1rem;
        background: rgba(16, 185, 129, 0.05);
        border: 2px solid rgba(16, 185, 129, 0.2);
        border-radius: 12px;
        display: none;
        align-items: center;
        gap: 1rem;
    }

    .file-preview.active { display: flex; }

    .file-preview-icon { font-size: 2.5rem; color: #059669; }

    .file-preview-remove {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
    }

    .file-preview-remove:hover { background: rgba(239, 68, 68, 0.2); }

    .current-file-info {
        background: rgba(16, 185, 129, 0.08);
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .image-preview {
        margin-top: 1rem;
        display: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    .image-preview.active { display: block; }

    .image-preview img { width: 100%; max-height: 300px; object-fit: cover; }

    .image-preview-remove {
        position: absolute;
        top: 10px; right: 10px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
    }

    .current-cover {
        margin-top: 1rem;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    .current-cover img { width: 100%; max-height: 300px; object-fit: cover; }

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

    .hidden-input { display: none; }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2" style="color: var(--primary-color);">
                <i class="fas fa-edit me-3"></i>Edit Resource
            </h2>
            <p class="text-muted mb-0">Modify details for: <strong><?= h($resource['title']) ?></strong></p>
        </div>
        <div>
            <a href="../viewer.php?id=<?= $id ?>" class="btn btn-outline-primary" target="_blank">
                <i class="fas fa-eye me-2"></i>Preview
            </a>
            <a href="resources.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Resources
            </a>
        </div>
    </div>
</div>

<!-- Form Card -->
<div class="form-card">
    <div class="info-box">
        <div class="info-box-title">
            <i class="fas fa-info-circle"></i>
            Editing Resource
        </div>
        <p class="info-box-text">
            You can update any field. Drag & drop new files to replace existing ones. Leave file fields empty to keep current files.
        </p>
    </div>

    <form method="post" enctype="multipart/form-data" id="resourceForm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <!-- Basic Information -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-heading"></i> Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" value="<?= h($resource['title']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-tag"></i> Resource Type <span class="required">*</span></label>
                    <select name="type" class="form-select" required>
                        <option value="">Select type...</option>
                        <option value="pdf" <?= $resource['type']=='pdf'?'selected':'' ?>>📄 PDF Document</option>
                        <option value="epub" <?= $resource['type']=='epub'?'selected':'' ?>>📚 EPUB Book</option>
                        <option value="video_file" <?= $resource['type']=='video_file'?'selected':'' ?>>🎥 Video File (MP4/WebM)</option>
                        <option value="video_link" <?= $resource['type']=='video_link'?'selected':'' ?>>🔗 Video Link (YouTube, etc.)</option>
                        <option value="doc" <?= $resource['type']=='doc'?'selected':'' ?>>📝 Word Document (DOC/DOCX)</option>
                        <option value="ppt" <?= $resource['type']=='ppt'?'selected':'' ?>>📊 PowerPoint (PPT/PPTX)</option>
                        <option value="xls" <?= $resource['type']=='xls'?'selected':'' ?>>📈 Excel (XLS/XLSX)</option>
                        <option value="link" <?= $resource['type']=='link'?'selected':'' ?>>🌐 Web Link</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-folder"></i> Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- No Category --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $resource['category_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= h($resource['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- File Upload Section -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-cloud-upload-alt"></i> File & Cover</h5>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-file"></i> Replace Resource File</label>
                    <div class="file-upload-area" id="fileDropZone">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <p class="file-upload-text"><strong>Drag & Drop</strong> new file or <strong>click to browse</strong></p>
                        <small class="text-muted">PDF, EPUB, Video, Office files (Max 50MB)</small>
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

                    <?php if (!empty($resource['file_path'])): ?>
                        <div class="current-file-info">
                            <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                            <div>
                                <strong>Current file:</strong> <?= h(basename($resource['file_path'])) ?><br>
                                <small class="text-success">Will be kept if no new file is uploaded</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-image"></i> Replace Cover Image</label>
                    <div class="file-upload-area" id="coverDropZone">
                        <i class="fas fa-image file-upload-icon"></i>
                        <p class="file-upload-text"><strong>Drag & Drop</strong> image or <strong>click</strong></p>
                        <small class="text-muted">JPG, PNG, GIF, WEBP (Max 10MB)</small>
                    </div>
                    <input type="file" name="cover_image" id="coverInput" class="hidden-input" accept="image/*">

                    <div class="image-preview" id="imagePreview">
                        <img src="" alt="New cover preview" id="previewImg">
                        <button type="button" class="image-preview-remove" id="removeCover">Remove</button>
                    </div>

                    <?php if (!empty($resource['cover_image_path'])): ?>
                        <div class="current-cover">
                            <img src="<?= h(app_path($resource['cover_image_path'])) ?>" alt="Current cover">
                            <button type="button" class="image-preview-remove" id="removeCurrentCover">Remove Current Cover</button>
                            <input type="hidden" name="remove_cover" id="removeCoverFlag" value="0">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-12">
                    <label class="form-label"><i class="fas fa-link"></i> External URL</label>
                    <input type="url" name="external_url" class="form-control" value="<?= h($resource['external_url'] ?? '') ?>" placeholder="https://example.com/resource">
                    <div class="form-text"><i class="fas fa-info-circle"></i> Optional link (e.g., YouTube, Google Drive)</div>
                </div>
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
    const removeCurrentCover = document.getElementById('removeCurrentCover');
    const removeCoverFlag = document.getElementById('removeCoverFlag');

    const fileIcons = { 'pdf': 'fa-file-pdf', 'epub': 'fa-book', 'mp4': 'fa-file-video', 'webm': 'fa-file-video',
        'doc': 'fa-file-word', 'docx': 'fa-file-word', 'ppt': 'fa-file-powerpoint', 'pptx': 'fa-file-powerpoint',
        'xls': 'fa-file-excel', 'xlsx': 'fa-file-excel', default: 'fa-file' };

    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        return fileIcons[ext] || fileIcons.default;
    }

    function formatFileSize(bytes) {
        if (!bytes) return '';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // === Resource File Handling ===
    fileDropZone.addEventListener('click', () => fileInput.click());
    ['dragover', 'dragenter'].forEach(e => fileDropZone.addEventListener(e, ev => { ev.preventDefault(); fileDropZone.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(e => fileDropZone.addEventListener(e, ev => { ev.preventDefault(); fileDropZone.classList.remove('dragover'); }));
    fileDropZone.addEventListener('drop', e => { if (e.dataTransfer.files[0]) { fileInput.files = e.dataTransfer.files; handleFileSelect(e.dataTransfer.files[0]); } });
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) handleFileSelect(fileInput.files[0]); });

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

    // === Cover Image Handling ===
    coverDropZone.addEventListener('click', () => coverInput.click());
    ['dragover', 'dragenter'].forEach(e => coverDropZone.addEventListener(e, ev => { ev.preventDefault(); coverDropZone.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach(e => coverDropZone.addEventListener(e, ev => { ev.preventDefault(); coverDropZone.classList.remove('dragover'); }));
    coverDropZone.addEventListener('drop', e => { const file = e.dataTransfer.files[0]; if (file && file.type.startsWith('image/')) { coverInput.files = e.dataTransfer.files; handleCoverSelect(file); } });
    coverInput.addEventListener('change', () => { if (coverInput.files[0]) handleCoverSelect(coverInput.files[0]); });

    function handleCoverSelect(file) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            imagePreview.classList.add('active');
            coverDropZone.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    if (removeCover) removeCover.addEventListener('click', () => { coverInput.value = ''; imagePreview.classList.remove('active'); previewImg.src = ''; coverDropZone.style.display = 'block'; });
    if (removeCurrentCover) {
        removeCurrentCover.addEventListener('click', () => {
            document.querySelector('.current-cover').remove();
            if (removeCoverFlag) removeCoverFlag.value = '1';
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>