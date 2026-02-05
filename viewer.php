<?php
require_once __DIR__ . '/includes/auth.php';
$legacyId = (int)($_GET['id'] ?? 0);
if ($legacyId > 0) {
    redirect_legacy_php('viewer/' . $legacyId, ['id' => null]);
}
require_login();

$id = $legacyId;
$stmt = $pdo->prepare("SELECT r.*, COALESCE(r.status, 'approved') AS status, c.name AS category_name, u.name AS creator_name
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

$viewerUser = current_user();
if (!resource_is_visible($resource, $viewerUser)) {
    flash_message('error', 'This resource is not yet available.');
    log_warning('Viewer access blocked', ['resource_id' => $id, 'status' => $resource['status'] ?? null]);
    header('Location: ' . app_path(''));
    exit;
}

// Record view and get saved progress
$currentUser = current_user();
$savedProgress = null;
if ($currentUser) {
    record_resource_view($currentUser['id'], $id);
    $savedProgress = get_resource_progress($currentUser['id'], $id);
}

// Get dark mode preference
$viewerDarkMode = $currentUser ? get_user_dark_mode($currentUser['id']) : false;

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
<html lang="en" class="viewer-page" data-theme="<?= $viewerDarkMode ? 'dark' : 'light' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo h($title); ?> — Viewer</title>
  <link rel="stylesheet" href="<?php echo h(app_path('assets/css/components.css')); ?>">
</head>
<body class="viewer-page">
  <!-- Progress tracking configuration -->
  <script>
    const resourceId = <?= (int)$id ?>;
    const savedPosition = <?= $savedProgress ? (int)$savedProgress['last_position'] : 0 ?>;
    const savedPercent = <?= $savedProgress ? (float)$savedProgress['progress_percent'] : 0 ?>;
    const appPath = '<?= h(app_base_path_prefix()) ?>/';
    const csrfToken = '<?= h(get_csrf_token()) ?>';
  </script>
  <a class="close-button" href="<?php echo h($closeUrl); ?>">&larr; Close</a>
  <div class="viewer-wrapper">
    <div class="viewer-content">
      <?php if ($type === 'pdf' && $secureToken): ?>
        <?php $pdfFrame = app_path('pdf/' . urlencode($secureToken)); ?>
        <iframe src="<?php echo h($pdfFrame); ?>" allowfullscreen sandbox="allow-scripts allow-same-origin"></iframe>
      <?php elseif ($type === 'epub' && $fileUrl): ?>
        <div id="epub-viewer" class="epub-viewer"></div>
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
        <div id="epub-container" class="epub-container">
          <!-- Controls Bar -->
          <div id="epub-controls" class="epub-controls">
            <button id="epub-prev" class="epub-control-btn" title="Previous Page">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
              </svg>
            </button>
            <button id="epub-next" class="epub-control-btn" title="Next Page">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
              </svg>
            </button>
            <span id="epub-page-info" class="epub-page-info">Loading...</span>
            
            <div class="epub-spacer"></div>
            
            <!-- Zoom Controls -->
            <button id="epub-zoom-out" class="epub-control-btn" title="Zoom Out">-</button>
            <span id="epub-zoom-level" class="epub-zoom-level">100%</span>
            <button id="epub-zoom-in" class="epub-control-btn" title="Zoom In">+</button>
            <button id="epub-zoom-reset" class="epub-control-btn" title="Reset Zoom">Reset</button>
            
            <!-- Font Size Controls -->
            <div class="epub-divider"></div>
            <button id="epub-font-decrease" class="epub-control-btn" title="Decrease Font">A-</button>
            <button id="epub-font-increase" class="epub-control-btn" title="Increase Font">A+</button>
          </div>
          
          <!-- EPUB Reader Area -->
          <div id="epub-viewer" class="epub-viewer"></div>
          
          <!-- Loading Indicator -->
          <div id="epub-loading" class="epub-loading">
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
              holder.innerHTML = '<p class="viewer-error">EPUB viewer unavailable. Libraries failed to load.</p>';
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
            // Update page info on location change and save progress
            rendition.on('relocated', function(location) {
              updatePageInfo();
              // Save reading progress
              if (location && location.start && location.start.displayed) {
                const current = location.start.displayed.page;
                const total = location.start.displayed.total;
                const percent = (current / total) * 100;
                saveProgress(current, percent, total);
              }
            });

            // Restore saved position
            if (savedPosition > 0 && book.spine) {
              setTimeout(function() {
                try {
                  // Try to go to saved page
                  const spineItem = book.spine.get(savedPosition - 1);
                  if (spineItem) {
                    rendition.display(spineItem.href);
                  }
                } catch (e) {
                  console.log('Could not restore position:', e);
                }
              }, 500);
            }
          })
          .catch(function(err) {
            console.error('EPUB render error:', err);
            if (holder) {
              holder.innerHTML = '<p class="viewer-error">Failed to display EPUB: ' + err.message + '</p>';
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

    // Progress tracking helper
    function saveProgress(position, percent, totalPages) {
      const body = new URLSearchParams({
        csrf_token: csrfToken,
        resource_id: resourceId,
        position: position,
        percent: Math.min(100, Math.max(0, percent))
      });
      if (totalPages) {
        body.append('total_pages', totalPages);
      }

      fetch(appPath + 'api/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      }).catch(() => {});
    }

    // Debounce helper
    function debounce(func, wait) {
      let timeout;
      return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
      };
    }

    // Track video progress
    const video = document.querySelector('video');
    if (video) {
      // Restore saved position
      if (savedPosition > 0) {
        video.currentTime = savedPosition;
      }

      // Save progress periodically
      const saveVideoProgress = debounce(function() {
        const percent = (video.currentTime / video.duration) * 100;
        saveProgress(Math.floor(video.currentTime), percent, Math.floor(video.duration));
      }, 2000);

      video.addEventListener('timeupdate', saveVideoProgress);
      video.addEventListener('pause', function() {
        const percent = (video.currentTime / video.duration) * 100;
        saveProgress(Math.floor(video.currentTime), percent, Math.floor(video.duration));
      });
      video.addEventListener('ended', function() {
        saveProgress(Math.floor(video.duration), 100, Math.floor(video.duration));
      });
    }

    // Track PDF progress via postMessage from pdf_viewer.php iframe
    window.addEventListener('message', function(e) {
      if (e.data && e.data.type === 'pdfProgress') {
        saveProgress(e.data.page, e.data.percent, e.data.totalPages);
      }
    });

    // =============================================
    // KEYBOARD NAVIGATION
    // =============================================
    document.addEventListener('keydown', function(e) {
      // Don't capture if user is typing in an input
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

      const video = document.querySelector('video');
      const iframe = document.querySelector('.viewer-content iframe');
      const viewerContent = document.querySelector('.viewer-content');

      switch(e.key) {
        case 'ArrowLeft':
          e.preventDefault();
          if (video) {
            // Seek back 10 seconds for video
            video.currentTime = Math.max(0, video.currentTime - 10);
          } else if (iframe) {
            // Send scroll/page command to PDF iframe
            iframe.contentWindow.postMessage({ type: 'scroll', direction: 'up' }, '*');
            // Also try scrolling the iframe content
            try {
              if (iframe.contentWindow) {
                iframe.contentWindow.scrollBy({ top: -300, behavior: 'smooth' });
              }
            } catch(err) {}
          }
          break;

        case 'ArrowRight':
          e.preventDefault();
          if (video) {
            // Seek forward 10 seconds for video
            video.currentTime = Math.min(video.duration, video.currentTime + 10);
          } else if (iframe) {
            // Send scroll/page command to PDF iframe
            iframe.contentWindow.postMessage({ type: 'scroll', direction: 'down' }, '*');
            try {
              if (iframe.contentWindow) {
                iframe.contentWindow.scrollBy({ top: 300, behavior: 'smooth' });
              }
            } catch(err) {}
          }
          break;

        case 'ArrowUp':
          e.preventDefault();
          if (video) {
            // Increase volume
            video.volume = Math.min(1, video.volume + 0.1);
          } else if (viewerContent) {
            // Scroll up in viewer
            viewerContent.scrollBy({ top: -200, behavior: 'smooth' });
          }
          break;

        case 'ArrowDown':
          e.preventDefault();
          if (video) {
            // Decrease volume
            video.volume = Math.max(0, video.volume - 0.1);
          } else if (viewerContent) {
            // Scroll down in viewer
            viewerContent.scrollBy({ top: 200, behavior: 'smooth' });
          }
          break;

        case ' ': // Spacebar
          e.preventDefault();
          if (video) {
            // Play/pause video
            if (video.paused) {
              video.play();
            } else {
              video.pause();
            }
          } else if (viewerContent) {
            // Page down
            viewerContent.scrollBy({ top: viewerContent.clientHeight * 0.9, behavior: 'smooth' });
          }
          break;

        case 'Home':
          e.preventDefault();
          if (video) {
            video.currentTime = 0;
          } else if (viewerContent) {
            viewerContent.scrollTo({ top: 0, behavior: 'smooth' });
          }
          break;

        case 'End':
          e.preventDefault();
          if (video) {
            video.currentTime = video.duration;
          } else if (viewerContent) {
            viewerContent.scrollTo({ top: viewerContent.scrollHeight, behavior: 'smooth' });
          }
          break;

        case 'Escape':
          // Close viewer and go back
          window.location.href = '<?= h($closeUrl) ?>';
          break;

        case 'f':
        case 'F':
          // Toggle fullscreen for video
          if (video) {
            if (document.fullscreenElement) {
              document.exitFullscreen();
            } else {
              video.requestFullscreen();
            }
          }
          break;

        case 'm':
        case 'M':
          // Mute/unmute video
          if (video) {
            video.muted = !video.muted;
          }
          break;
      }
    });

    // Show keyboard shortcuts hint
    const closeBtn = document.querySelector('.close-button');
    if (closeBtn) {
      const hint = document.createElement('span');
      hint.className = 'keyboard-hint';
      hint.innerHTML = ' <small style="opacity:0.7;font-size:0.75rem;">(Esc to close, arrows to navigate)</small>';
      closeBtn.appendChild(hint);
    }
  </script>
</body>
</html>
