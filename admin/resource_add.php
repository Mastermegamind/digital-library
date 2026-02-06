<?php
require_once __DIR__ . '/../includes/auth.php';
redirect_legacy_php('admin/resource/add');
require_admin();

$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        log_warning('Resource add CSRF validation failed');
        flash_message('error', 'Invalid session token.');
        header('Location: ' . app_path('admin/resource/add'));
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $external_url = trim($_POST['external_url'] ?? '');
    $tagsInput = trim($_POST['tags'] ?? '');

    if ($title === '' || $type === '') {
        log_warning('Resource add validation failed', ['title' => $title, 'type' => $type]);
        flash_message('error', 'Title and type are required.');
        header('Location: ' . app_path('admin/resource/add'));
        exit;
    }

    $allowedExt = ['pdf','epub','mp4','webm','doc','docx','ppt','pptx','xls','xlsx'];
    $maxUploadBytes = 500 * 1024 * 1024; // 500MB
    $uploadResult = handle_file_upload($_FILES['file'] ?? null, $allowedExt, __DIR__ . '/../uploads', 'uploads', $maxUploadBytes);
    if ($uploadResult['error']) {
        log_error('Resource add upload failed', ['error' => $uploadResult['error']]);
        flash_message('error', $uploadResult['error']);
        header('Location: ' . app_path('admin/resource/add'));
        exit;
    }
    $filePath = $uploadResult['path'];

    $coverResult = handle_file_upload(
        $_FILES['cover_image'] ?? null,
        ['jpg','jpeg','png','gif','webp'],
        __DIR__ . '/../uploads/covers',
        'uploads/covers',
        10 * 1024 * 1024,
        ['max_width' => 1600, 'max_height' => 1600, 'max_pixels' => 12000000, 'quality' => 85]
    );
    if ($coverResult['error']) {
        log_error('Resource cover upload failed', ['error' => $coverResult['error']]);
        flash_message('error', $coverResult['error']);
        header('Location: ' . app_path('admin/resource/add'));
        exit;
    }
    $coverPath = $coverResult['path'];

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
        ':created_by' => current_user()['id'],
        ':status' => 'approved',
        ':approved_by' => current_user()['id'],
        ':approved_at' => date('Y-m-d H:i:s'),
    ]);

    $resourceId = (int)$pdo->lastInsertId();
    if ($tagsInput !== '') {
        $tags = parse_tag_list($tagsInput);
        set_resource_tags($resourceId, $tags);
    }
    notify_all_users(
        'resource_new',
        'New resource added',
        $title,
        app_path('resource/' . $resourceId),
        current_user()['id'] ?? null
    );

    $resourceLink = app_url('resource/' . $resourceId);
    $emailSubject = 'New resource added - ' . $APP_NAME;
    $emailHtml = '<p>A new resource has been added.</p>'
        . '<p><strong>' . h($title) . '</strong></p>'
        . '<p><a href="' . h($resourceLink) . '">View resource</a></p>';
    $emailText = "A new resource has been added.\n\n{$title}\n{$resourceLink}";
    notify_all_users_email($emailSubject, $emailHtml, $emailText, current_user()['id'] ?? null);

    log_info('Resource created', [
        'resource_id' => $resourceId,
        'title' => $title,
        'type' => $type,
    ]);

    flash_message('success', 'Resource added successfully!');
    header('Location: ' . app_path('admin/resource/add'));
    exit;
}

$csrf = get_csrf_token();
$meta_title = 'Add Resource - Admin | ' . $APP_NAME;
$meta_description = 'Upload a new resource to the ' . $APP_NAME . ' library.';
include __DIR__ . '/../includes/header.php';
?>



<!-- Page Header -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-2 page-title">
                <i class="fas fa-plus-circle me-3"></i>Add New Resource
            </h2>
            <p class="text-muted mb-0">
                Upload a new learning resource to CONS-UNTH E-LIBRARY
            </p>
        </div>
        <div>
            <a href="<?= h(app_path('admin/resources')) ?>" class="btn btn-outline-secondary">
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
            Make sure you have all necessary files ready. Supported formats include PDF, EPUB, Office documents, and video files up to 500MB. Cover images should be under 10MB.
        </p>
    </div>

    <form method="post" enctype="multipart/form-data" id="resourceForm">
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

                <div class="col-md-12">
                    <label class="form-label">
                        <i class="fas fa-tags"></i>
                        Tags
                    </label>
                    <input type="text" name="tags" class="form-control"
                           placeholder="e.g., nursing, pediatrics, pharmacology">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i>
                        Separate tags with commas.
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
                    <div class="file-upload-area" id="fileDropZone">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <p class="file-upload-text">
                            <strong>Drag & Drop</strong> your file here or <strong>click to browse</strong>
                        </p>
                        <small class="text-muted">PDF, EPUB, MP4, WebM, DOC, DOCX, PPT, PPTX, XLS, XLSX (Max 500MB)</small>
                    </div>
                    <input type="file" name="file" id="fileInput" class="hidden-input">
                    
                    <div class="file-preview" id="filePreview">
                        <i class="fas fa-file-alt file-preview-icon" id="fileIcon"></i>
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="fileName"></div>
                            <div class="file-preview-size" id="fileSize"></div>
                        </div>
                        <button type="button" class="file-preview-remove" id="removeFile">
                            <i class="fas fa-times me-1"></i>Remove
                        </button>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">
                        <i class="fas fa-image"></i>
                        Cover Image
                    </label>
                    <div class="file-upload-area" id="coverDropZone">
                        <i class="fas fa-image file-upload-icon"></i>
                        <p class="file-upload-text">
                            <strong>Drag & Drop</strong> your image here or <strong>click to browse</strong>
                        </p>
                        <small class="text-muted">JPG, PNG, GIF, WEBP (Max 10MB)</small>
                    </div>
                    <input type="file" name="cover_image" id="coverInput" class="hidden-input" accept="image/*">
                    
                    <div class="image-preview" id="imagePreview">
                        <img src="" alt="Cover preview" id="previewImg">
                        <button type="button" class="image-preview-remove" id="removeCover">
                            <i class="fas fa-times me-1"></i>Remove
                        </button>
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
            <a href="<?= h(app_path('admin/resources')) ?>" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-save me-2"></i>Add Resource
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload handlers
    const fileDropZone = document.getElementById('fileDropZone');
    const fileInput = document.getElementById('fileInput');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileIcon = document.getElementById('fileIcon');
    const removeFile = document.getElementById('removeFile');
    
    // Cover image handlers
    const coverDropZone = document.getElementById('coverDropZone');
    const coverInput = document.getElementById('coverInput');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const removeCover = document.getElementById('removeCover');
    
    // File icon mapping
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
    
    function getFileIcon(fileName) {
        const ext = fileName.split('.').pop().toLowerCase();
        return fileIcons[ext] || 'fa-file';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    // File upload drag and drop
    fileDropZone.addEventListener('click', () => fileInput.click());
    
    fileDropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileDropZone.classList.add('dragover');
    });
    
    fileDropZone.addEventListener('dragleave', () => {
        fileDropZone.classList.remove('dragover');
    });
    
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
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
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
    
    // Cover image drag and drop
    coverDropZone.addEventListener('click', () => coverInput.click());
    
    coverDropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        coverDropZone.classList.add('dragover');
    });
    
    coverDropZone.addEventListener('dragleave', () => {
        coverDropZone.classList.remove('dragover');
    });
    
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
        if (e.target.files.length > 0) {
            handleCoverSelect(e.target.files[0]);
        }
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
