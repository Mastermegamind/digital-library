<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT r.*, c.name AS category_name, u.name AS creator_name
                       FROM resources r
                       LEFT JOIN categories c ON r.category_id = c.id
                       LEFT JOIN users u ON r.created_by = u.id
                       WHERE r.id = :id");
$stmt->execute([':id' => $id]);
$resource = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resource) {
    flash_message('error', 'Resource not found.');
    log_warning('Resource view requested for missing resource (viewer)', ['resource_id' => $id]);
    header('Location: index.php');
    exit;
}

$title = $resource['title'];
$type = $resource['type'];
$secureToken = null;
$secureUrl = null;
$publicFileUrl = !empty($resource['file_path']) ? app_path($resource['file_path']) : null;
$fileBasedTypes = ['pdf','epub','video_file','doc','ppt','xls'];
if (!in_array($type, ['link','video_link'], true) && !empty($resource['file_path'])) {
    $secureToken = issue_resource_token((int)$resource['id'], $resource['file_path']);
    $secureUrl = app_path('secure_file.php?token=' . urlencode($secureToken));
} elseif ($type !== 'link' && $type !== 'video_link' && empty($resource['file_path'])) {
    log_warning('Viewer attempted without file path', ['resource_id' => $resource['id'], 'type' => $type]);
}
$fileUrl = $secureUrl;
$externalUrl = $resource['external_url'];
$closeUrl = app_path('index.php');

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($title); ?> — Viewer</title>
  <style>
    html, body {
      height: 100%;
      margin: 0;
      background: #111;
      font-family: "Segoe UI", Arial, sans-serif;
      color: #fff;
    }
    .viewer-wrapper {
      position: relative;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    .viewer-content {
      width: 100%;
      height: 100%;
      background: #000;
    }
    iframe, video {
      width: 100%;
      height: 100%;
      border: 0;
      background: #000;
    }
    .close-button {
      position: fixed;
      top: 16px;
      left: 16px;
      z-index: 1000;
      background: rgba(0,0,0,0.6);
      color: #fff;
      padding: 10px 18px;
      text-decoration: none;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.3);
      transition: background 0.2s;
      backdrop-filter: blur(6px);
    }
    .close-button:hover {
      background: rgba(0,0,0,0.85);
    }
    .meta {
      position: fixed;
      bottom: 16px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0,0,0,0.55);
      border-radius: 999px;
      padding: 8px 18px;
      font-size: 0.95rem;
      border: 1px solid rgba(255,255,255,0.2);
      display: flex;
      align-items: center;
      gap: 16px;
      backdrop-filter: blur(6px);
    }
    .meta span {
      color: #ddd;
    }
    .meta .title {
      font-weight: 600;
      color: #fff;
    }
    .placeholder {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      flex-direction: column;
      text-align: center;
      gap: 12px;
    }
    .placeholder a {
      color: #0d6efd;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <a class="close-button" href="<?php echo h($closeUrl); ?>">&larr; Close</a>
  <div class="viewer-wrapper">
    <div class="viewer-content">
      <?php if ($type === 'pdf' && $secureToken): ?>
        <?php $pdfFrame = app_path('pdf_viewer.php?token=' . urlencode($secureToken)); ?>
        <iframe src="<?php echo h($pdfFrame); ?>" allowfullscreen sandbox="allow-scripts allow-same-origin"></iframe>
      <?php elseif ($type === 'epub' && $fileUrl): ?>
        <div id="epub-viewer" style="width:100%;height:100%;"></div>
      <?php elseif ($type === 'video_file' && $fileUrl): ?>
        <video controls controlsList="nodownload" oncontextmenu="return false;">
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
          <iframe src="<?php echo h($youtubeEmbed); ?>" allowfullscreen></iframe>
        <?php else: ?>
          <div class="placeholder">
            <p>Video link:</p>
            <a href="<?php echo h($externalUrl); ?>" target="_blank" rel="noopener">Open Video</a>
          </div>
        <?php endif; ?>
      <?php elseif (in_array($type, ['doc','ppt','xls'], true) && $fileUrl): ?>
        <iframe src="<?php echo h($fileUrl); ?>" sandbox="allow-same-origin allow-scripts"></iframe>
      <?php elseif ($type === 'link' && $externalUrl): ?>
        <iframe src="<?php echo h($externalUrl); ?>"></iframe>
      <?php else: ?>
        <div class="placeholder">
          <p>Preview not available for this resource.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="meta">
    <span class="title"><?php echo h($title); ?></span>
    <span><?php echo h(strtoupper($type)); ?></span>
    <?php if (!empty($resource['category_name'])): ?>
      <span><?php echo h($resource['category_name']); ?></span>
    <?php endif; ?>
    <?php if (!empty($resource['creator_name'])): ?>
      <span>By <?php echo h($resource['creator_name']); ?></span>
  <?php endif; ?>
</div>

<?php if ($type === 'epub' && $fileUrl): ?>
    <!-- Load JSZip first (required for EPUB) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <!-- Load epub.js -->
    <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>
    <script>
      (function() {
        const holder = document.getElementById('epub-viewer');
        const loading = document.getElementById('epub-loading');
        
        // Check if libraries loaded
        if (typeof ePub === 'undefined') {
          console.error('epub.js failed to load');
          if (holder) {
            holder.innerHTML = '<p style="text-align:center;color:#fff;padding:20px;">EPUB viewer unavailable. Libraries failed to load.</p>';
          }
          return;
        }
        
        if (typeof JSZip === 'undefined') {
          console.error('JSZip failed to load');
          if (holder) {
            holder.innerHTML = '<p style="text-align:center;color:#fff;padding:20px;">EPUB viewer unavailable. JSZip failed to load.</p>';
          }
          return;
        }

        // Fetch and load the EPUB file
        fetch("<?php echo h($fileUrl); ?>", { credentials: 'same-origin' })
          .then(function(res) {
            if (!res.ok) throw new Error('Failed to fetch EPUB file (HTTP ' + res.status + ')');
            return res.arrayBuffer();
          })
          .then(function(buffer) {
            // Initialize the book with the buffer
            const book = ePub(buffer);
            
            // Render to the container
            const rendition = book.renderTo("epub-viewer", {
              width: "100%",
              height: "100%",
              spread: "none",
              flow: "paginated"
            });
            
            // Display the first page
            return rendition.display().then(function() {
              if (loading) loading.style.display = 'none';
              
              // Add navigation with keyboard
              document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft') rendition.prev();
                if (e.key === 'ArrowRight') rendition.next();
              });
              
              // Add click navigation
              holder.addEventListener('click', function(e) {
                const rect = holder.getBoundingClientRect();
                const x = e.clientX - rect.left;
                if (x < rect.width / 2) {
                  rendition.prev();
                } else {
                  rendition.next();
                }
              });
              
              console.log('EPUB loaded successfully');
            });
          })
          .catch(function(err) {
            console.error('EPUB render error:', err);
            if (holder) {
              holder.innerHTML = '<p style="text-align:center;color:#fff;padding:20px;">Failed to display EPUB: ' + err.message + '</p>';
            }
          });
      })();
    </script>
  <?php endif; ?>
  <script>
    // document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
  </script>
</body>
</html>
