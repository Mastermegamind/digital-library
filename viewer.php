<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ai.php';
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
$showChat = in_array($type, ['pdf','epub'], true) && function_exists('ai_is_configured') && ai_is_configured();
$fileSizeLabel = can_view_resource_file_size() ? get_resource_file_size_label($resource) : null;
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
  <?php if ($showChat): ?>
  <style>
    .viewer-wrapper.with-chat { display: flex; }
    .viewer-content { flex: 1; }
    .viewer-chat { width: 320px; border-left: 1px solid #e5e7eb; background: #ffffff; display: none; flex-direction: column; }
    .viewer-chat.open { display: flex; }
    .viewer-chat-header { padding: 10px 12px; background: #f3f4f6; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
    .viewer-chat-messages { flex: 1; overflow-y: auto; padding: 10px; background: #f8fafc; }
    .viewer-chat-message { margin-bottom: 8px; padding: 8px 10px; border-radius: 8px; max-width: 90%; font-size: 0.9rem; }
    .viewer-chat-message.user { background: #2563eb; color: #fff; margin-left: auto; }
    .viewer-chat-message.assistant { background: #e2e8f0; color: #111827; }
    .viewer-chat-input { border-top: 1px solid #e5e7eb; padding: 8px; display: flex; gap: 8px; }
    .viewer-chat-input textarea { flex: 1; resize: none; border: 1px solid #e5e7eb; border-radius: 6px; padding: 6px; font-size: 0.9rem; }
    .viewer-chat-input button { border: none; background: #2563eb; color: #fff; border-radius: 6px; padding: 6px 10px; }
    @media (max-width: 992px) {
      .viewer-chat { position: fixed; top: 60px; right: 0; bottom: 0; transform: translateX(100%); transition: transform 0.2s ease; z-index: 999; display: flex; }
      .viewer-chat.open { transform: translateX(0); }
    }
  </style>
  <?php endif; ?>
</head>
<body class="viewer-page">
  <!-- Progress tracking configuration -->
  <script>
    const resourceId = <?= (int)$id ?>;
    const savedPosition = <?= $savedProgress ? (int)$savedProgress['last_position'] : 0 ?>;
    const savedPercent = <?= $savedProgress ? (float)$savedProgress['progress_percent'] : 0 ?>;
    const appPath = '<?= h(app_base_path_prefix()) ?>/';
    const csrfToken = '<?= h(get_csrf_token()) ?>';
    const viewerType = '<?= h($type) ?>';
  </script>
  <!-- Viewer Toolbar -->
  <div class="viewer-toolbar">
    <a class="close-button" href="<?php echo h($closeUrl); ?>">&larr; Close</a>

    <div class="toolbar-spacer"></div>

    <!-- Zoom Controls -->
    <div class="zoom-controls" id="zoom-controls">
      <button type="button" id="zoom-out" class="toolbar-btn" title="Zoom Out (-)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          <line x1="8" y1="11" x2="14" y2="11"/>
        </svg>
      </button>
      <span id="zoom-level" class="zoom-level">100%</span>
      <button type="button" id="zoom-in" class="toolbar-btn" title="Zoom In (+)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          <line x1="11" y1="8" x2="11" y2="14"/>
          <line x1="8" y1="11" x2="14" y2="11"/>
        </svg>
      </button>
      <button type="button" id="zoom-reset" class="toolbar-btn" title="Reset Zoom">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
          <path d="M3 3v5h5"/>
        </svg>
      </button>
      <button type="button" id="zoom-fit" class="toolbar-btn" title="Fit to Width">Fit</button>
    </div>
    <?php if ($showChat): ?>
      <button type="button" id="chat-toggle" class="toolbar-btn" title="Chat">Chat</button>
    <?php endif; ?>
  </div>

  <div class="viewer-wrapper<?= $showChat ? ' with-chat' : '' ?>">
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
    <?php if ($showChat): ?>
      <aside class="viewer-chat" id="viewerChat">
        <div class="viewer-chat-header">
          <span>Study Helper</span>
          <button type="button" class="toolbar-btn" id="chat-close">Close</button>
        </div>
        <div class="viewer-chat-messages" id="chatMessages"></div>
        <form class="viewer-chat-input" id="chatForm">
          <textarea rows="2" id="chatInput" placeholder="Ask about this document..."></textarea>
          <button type="submit">Send</button>
        </form>
      </aside>
    <?php endif; ?>
  </div>
  <div class="meta">
    <span class="title"><?php echo h($title); ?></span>
    <span><?php echo h(strtoupper($type)); ?></span>
    <?php if ($fileSizeLabel): ?>
      <span>Size: <?php echo h($fileSizeLabel); ?></span>
    <?php endif; ?>
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
        
        // Keyboard navigation (use PageUp/PageDown to avoid stealing arrow keys)
        document.addEventListener('keydown', function(e) {
          if (e.defaultPrevented) return;
          if (e.key === 'PageUp') {
            e.preventDefault();
            if (rendition) rendition.prev();
          } else if (e.key === 'PageDown') {
            e.preventDefault();
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

        window.epubZoomIn = function() {
          currentZoom = Math.min(currentZoom + 10, 200);
          applyZoom();
        };

        window.epubZoomOut = function() {
          currentZoom = Math.max(currentZoom - 10, 50);
          applyZoom();
        };

        window.epubScrollLeft = function() {
          const iframe = holder.querySelector('iframe');
          if (iframe && iframe.contentWindow) {
            iframe.contentWindow.scrollBy({ left: -200, top: 0, behavior: 'smooth' });
          } else if (holder) {
            holder.scrollBy({ left: -200, top: 0, behavior: 'smooth' });
          }
        };

        window.epubScrollRight = function() {
          const iframe = holder.querySelector('iframe');
          if (iframe && iframe.contentWindow) {
            iframe.contentWindow.scrollBy({ left: 200, top: 0, behavior: 'smooth' });
          } else if (holder) {
            holder.scrollBy({ left: 200, top: 0, behavior: 'smooth' });
          }
        };
        
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
      // When PDF iframe signals it's ready, restore saved position
      if (e.data && e.data.type === 'pdfReady' && savedPosition > 0) {
        setTimeout(() => {
          const iframe = document.querySelector('.viewer-content iframe');
          if (iframe && iframe.contentWindow) {
            iframe.contentWindow.postMessage({ type: 'restorePosition', page: savedPosition }, '*');
          }
        }, 1500);
      }
    });

    // =============================================
    // KEYBOARD NAVIGATION
    // =============================================
    document.addEventListener('keydown', function(e) {
      // Don't capture if user is typing in an input
      const target = e.target;
      if (!target) return;
      if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable) return;

      const video = document.querySelector('video');
      const iframe = document.querySelector('.viewer-content iframe');
      const viewerContent = document.querySelector('.viewer-content');
      const isVideoFocused = video && document.activeElement === video;
      const isPdf = viewerType === 'pdf';
      const isEpub = viewerType === 'epub';

      const scrollByAmount = (dx, dy) => {
        if (viewerType === 'pdf' && iframe && iframe.contentWindow) {
          iframe.contentWindow.postMessage({ type: 'scroll', dx: dx, dy: dy }, '*');
        }
        if (viewerContent) {
          viewerContent.scrollBy({ left: dx, top: dy, behavior: 'smooth' });
        } else {
          window.scrollBy({ left: dx, top: dy, behavior: 'smooth' });
        }
      };

      switch(e.key) {
        case 'ArrowLeft':
          e.preventDefault();
          if (isPdf && typeof window.viewerZoomOut === 'function') {
            window.viewerZoomOut();
          } else if (isEpub && typeof window.epubScrollLeft === 'function') {
            window.epubScrollLeft();
          } else if (isVideoFocused) {
            video.currentTime = Math.max(0, video.currentTime - 10);
          } else {
            scrollByAmount(-200, 0);
          }
          break;

        case 'ArrowRight':
          e.preventDefault();
          if (isPdf && typeof window.viewerZoomIn === 'function') {
            window.viewerZoomIn();
          } else if (isEpub && typeof window.epubScrollRight === 'function') {
            window.epubScrollRight();
          } else if (isVideoFocused) {
            video.currentTime = Math.min(video.duration || 0, video.currentTime + 10);
          } else {
            scrollByAmount(200, 0);
          }
          break;

        case 'ArrowUp':
          e.preventDefault();
          if (isEpub && typeof window.epubZoomIn === 'function') {
            window.epubZoomIn();
          } else if (isVideoFocused) {
            video.volume = Math.min(1, video.volume + 0.1);
          } else {
            scrollByAmount(0, -200);
          }
          break;

        case 'ArrowDown':
          e.preventDefault();
          if (isEpub && typeof window.epubZoomOut === 'function') {
            window.epubZoomOut();
          } else if (isVideoFocused) {
            video.volume = Math.max(0, video.volume - 0.1);
          } else {
            scrollByAmount(0, 200);
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
      hint.innerHTML = ' <small style="opacity:0.7;font-size:0.75rem;">(Esc to close, arrows to scroll)</small>';
      closeBtn.appendChild(hint);
    }

    // =============================================
    // ZOOM CONTROLS
    // =============================================
    (function() {
      const zoomInBtn = document.getElementById('zoom-in');
      const zoomOutBtn = document.getElementById('zoom-out');
      const zoomResetBtn = document.getElementById('zoom-reset');
      const zoomFitBtn = document.getElementById('zoom-fit');
      const zoomLevelDisplay = document.getElementById('zoom-level');
      const iframe = document.querySelector('.viewer-content iframe');

      let currentZoom = 100;
      const minZoom = 25;
      const maxZoom = 300;
      const zoomStep = 25;

      function updateZoomDisplay() {
        if (zoomLevelDisplay) {
          zoomLevelDisplay.textContent = currentZoom + '%';
        }
      }

      function sendZoomToIframe(zoom, fitWidth = false) {
        if (iframe && iframe.contentWindow) {
          iframe.contentWindow.postMessage({
            type: 'zoom',
            level: zoom,
            fitWidth: fitWidth
          }, '*');
        }
      }

      function zoomIn() {
        currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
        updateZoomDisplay();
        sendZoomToIframe(currentZoom);
      }

      function zoomOut() {
        currentZoom = Math.max(currentZoom - zoomStep, minZoom);
        updateZoomDisplay();
        sendZoomToIframe(currentZoom);
      }

      function zoomReset() {
        currentZoom = 100;
        updateZoomDisplay();
        sendZoomToIframe(currentZoom);
      }

      function zoomFit() {
        sendZoomToIframe(currentZoom, true);
      }

      if (zoomInBtn) zoomInBtn.addEventListener('click', zoomIn);
      if (zoomOutBtn) zoomOutBtn.addEventListener('click', zoomOut);
      if (zoomResetBtn) zoomResetBtn.addEventListener('click', zoomReset);
      if (zoomFitBtn) zoomFitBtn.addEventListener('click', zoomFit);

      window.viewerZoomIn = zoomIn;
      window.viewerZoomOut = zoomOut;
      window.viewerZoomReset = zoomReset;
      window.viewerZoomFit = zoomFit;

      // Keyboard shortcuts for zoom
      document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        if ((e.ctrlKey || e.metaKey) && (e.key === '=' || e.key === '+')) {
          e.preventDefault();
          zoomIn();
        } else if ((e.ctrlKey || e.metaKey) && e.key === '-') {
          e.preventDefault();
          zoomOut();
        } else if ((e.ctrlKey || e.metaKey) && e.key === '0') {
          e.preventDefault();
          zoomReset();
        }
      });

      // Mouse wheel zoom with Ctrl
      document.querySelector('.viewer-content')?.addEventListener('wheel', function(e) {
        if (e.ctrlKey || e.metaKey) {
          e.preventDefault();
          if (e.deltaY < 0) {
            zoomIn();
          } else {
            zoomOut();
          }
        }
      }, { passive: false });

      // Listen for zoom level updates from iframe
      window.addEventListener('message', function(e) {
        if (e.data && e.data.type === 'zoomUpdate') {
          currentZoom = e.data.level;
          updateZoomDisplay();
        }
      });
    })();

    <?php if ($showChat): ?>
    // =============================================
    // AI CHAT SIDEBAR
    // =============================================
    (function() {
      const chatToggle = document.getElementById('chat-toggle');
      const chatClose = document.getElementById('chat-close');
      const chatPanel = document.getElementById('viewerChat');
      const chatMessages = document.getElementById('chatMessages');
      const chatForm = document.getElementById('chatForm');
      const chatInput = document.getElementById('chatInput');
      let historyLoaded = false;

      function appendMessage(role, text) {
        if (!chatMessages) return;
        const div = document.createElement('div');
        div.className = 'viewer-chat-message ' + role;
        div.textContent = text;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
      }

      function loadHistory() {
        if (historyLoaded) return;
        historyLoaded = true;
        fetch(appPath + 'api/chat?resource_id=' + resourceId)
          .then(res => res.json())
          .then(data => {
            (data.messages || []).forEach(msg => appendMessage(msg.role, msg.content));
          })
          .catch(() => {});
      }

      function toggleChat(show) {
        if (!chatPanel) return;
        chatPanel.classList.toggle('open', show);
        if (show) loadHistory();
      }

      if (chatToggle) chatToggle.addEventListener('click', () => toggleChat(!chatPanel.classList.contains('open')));
      if (chatClose) chatClose.addEventListener('click', () => toggleChat(false));

      if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const message = chatInput ? chatInput.value.trim() : '';
          if (!message) return;
          appendMessage('user', message);
          if (chatInput) chatInput.value = '';

          fetch(appPath + 'api/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              csrf_token: csrfToken,
              resource_id: resourceId,
              message: message
            })
          })
          .then(res => res.json())
          .then(data => {
            if (data.error) {
              appendMessage('assistant', 'Sorry, I could not respond.');
              return;
            }
            appendMessage('assistant', data.reply || '');
          })
          .catch(() => appendMessage('assistant', 'Sorry, I could not respond.'));
        });
      }
    })();
    <?php endif; ?>
  </script>
</body>
</html>
