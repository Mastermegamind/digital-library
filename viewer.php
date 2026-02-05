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
    header('Location: ' . app_path(''));
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
    $secureUrl = app_path('secure/' . urlencode($secureToken));
} elseif ($type !== 'link' && $type !== 'video_link' && empty($resource['file_path'])) {
    log_warning('Viewer attempted without file path', ['resource_id' => $resource['id'], 'type' => $type]);
}
$fileUrl = $secureUrl;
$externalUrl = $resource['external_url'];
$closeUrl = app_path('');

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($title); ?> — Viewer</title>
  <style>
html, body, #epub-container {
  height: 100vh !important;
  width: 100vw !important;
  margin: 0;
  padding: 0;
  overflow: hidden;
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
        <?php $pdfFrame = app_path('pdf/' . urlencode($secureToken)); ?>
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
        <div id="epub-container" style="width:100%;height:100%;background:#2a2a2a;position:relative;">
          <!-- Controls Bar -->
          <div id="epub-controls" style="position:absolute;top:0;left:0;right:0;background:rgba(0,0,0,0.9);padding:12px 20px;z-index:1000;display:flex;align-items:center;gap:15px;backdrop-filter:blur(10px);border-bottom:1px solid rgba(255,255,255,0.1);">
            <button id="epub-prev" style="background:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;" title="Previous Page">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
              </svg>
            </button>
            <button id="epub-next" style="background:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;" title="Next Page">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
              </svg>
            </button>
            <span id="epub-page-info" style="color:#fff;font-size:14px;margin:0 10px;">Loading...</span>
            
            <div style="flex:1;"></div>
            
            <!-- Zoom Controls -->
            <button id="epub-zoom-out" style="background:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;" title="Zoom Out">-</button>
            <span id="epub-zoom-level" style="color:#fff;font-size:14px;min-width:50px;text-align:center;">100%</span>
            <button id="epub-zoom-in" style="background:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;" title="Zoom In">+</button>
            <button id="epub-zoom-reset" style="background:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;" title="Reset Zoom">Reset</button>
            
            <!-- Font Size Controls -->
            <div style="border-left:1px solid rgba(255,255,255,0.3);height:24px;margin:0 10px;"></div>
            <button id="epub-font-decrease" style="background:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;" title="Decrease Font">A-</button>
            <button id="epub-font-increase" style="background:#fff;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;" title="Increase Font">A+</button>
          </div>
          
          <!-- EPUB Reader Area -->
          <div id="epub-viewer" style="width:100%;height:100%;background:#fff;overflow:hidden;"></div>
          
          <!-- Loading Indicator -->
          <div id="epub-loading" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;color:#fff;">
            <p>Loading EPUB...</p>
          </div>
        </div>

          <?php endif; ?>

  <?php if ($type === 'epub' && $fileUrl): ?>
    <!-- Load JSZip first (required for EPUB) -->
    <script src="<?php echo h(app_path('assets/pdfjs/jszip.min.js')); ?>"></script>
    <!-- Load epub.js -->
    <script src="<?php echo h(app_path('assets/pdfjs/epub.min.js')); ?>"></script>
    <script>
      (function() {
        const holder = document.getElementById('epub-viewer');
        const loading = document.getElementById('epub-loading');
        const pageInfo = document.getElementById('epub-page-info');
        const zoomLevel = document.getElementById('epub-zoom-level');
        
        let currentZoom = 100;
        let currentFontSize = 100;
        let book, rendition;
        
        // Check if libraries loaded
        if (typeof ePub === 'undefined' || typeof JSZip === 'undefined') {
          console.error('Required libraries failed to load');
          if (holder) {
            holder.innerHTML = '<p style="text-align:center;color:#000;padding:20px;">EPUB viewer unavailable. Libraries failed to load.</p>';
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
            book = ePub(buffer);
            
            // Render to the container
            rendition = book.renderTo("epub-viewer", {
              width: "100%",
              height: "100%",
              spread: "none",
              flow: "paginated"
            });
            
            // Display the first page
            return rendition.display().then(function() {
              if (loading) loading.style.display = 'none';
              updatePageInfo();
              console.log('EPUB loaded successfully');
            });
          })
          .then(function() {
            // Update page info on location change
            rendition.on('relocated', function(location) {
              updatePageInfo();
            });
          })
          .catch(function(err) {
            console.error('EPUB render error:', err);
            if (holder) {
              holder.innerHTML = '<p style="text-align:center;color:#000;padding:20px;">Failed to display EPUB: ' + err.message + '</p>';
            }
          });
        
        function updatePageInfo() {
          const location = rendition.currentLocation();
          if (location && location.start) {
            const current = location.start.displayed.page;
            const total = location.start.displayed.total;
            pageInfo.textContent = 'Page ' + current + ' of ' + total;
          }
        }
        
        // Navigation buttons
        document.getElementById('epub-prev').addEventListener('click', function() {
          if (rendition) rendition.prev();
        });
        
        document.getElementById('epub-next').addEventListener('click', function() {
          if (rendition) rendition.next();
        });
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
          if (e.key === 'ArrowLeft') {
            if (rendition) rendition.prev();
          } else if (e.key === 'ArrowRight') {
            if (rendition) rendition.next();
          }
        });
        
        // Zoom controls
        document.getElementById('epub-zoom-in').addEventListener('click', function() {
          currentZoom = Math.min(currentZoom + 10, 200);
          applyZoom();
        });
        
        document.getElementById('epub-zoom-out').addEventListener('click', function() {
          currentZoom = Math.max(currentZoom - 10, 50);
          applyZoom();
        });
        
        document.getElementById('epub-zoom-reset').addEventListener('click', function() {
          currentZoom = 100;
          applyZoom();
        });
        
        function applyZoom() {
          const iframe = holder.querySelector('iframe');
          if (iframe && iframe.contentDocument) {
            const body = iframe.contentDocument.body;
            if (body) {
              body.style.zoom = currentZoom + '%';
            }
          }
          zoomLevel.textContent = currentZoom + '%';
        }
        
        // Font size controls
        document.getElementById('epub-font-increase').addEventListener('click', function() {
          currentFontSize = Math.min(currentFontSize + 10, 200);
          applyFontSize();
        });
        
        document.getElementById('epub-font-decrease').addEventListener('click', function() {
          currentFontSize = Math.max(currentFontSize - 10, 50);
          applyFontSize();
        });
        
        function applyFontSize() {
          if (rendition) {
            rendition.themes.fontSize(currentFontSize + '%');
          }
        }
        
        // Mouse wheel zoom (Ctrl + scroll)
        holder.addEventListener('wheel', function(e) {
          if (e.ctrlKey) {
            e.preventDefault();
            if (e.deltaY < 0) {
              currentZoom = Math.min(currentZoom + 5, 200);
            } else {
              currentZoom = Math.max(currentZoom - 5, 50);
            }
            applyZoom();
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
