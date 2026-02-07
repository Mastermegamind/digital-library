</div><!-- /.container -->
</div><!-- /.main-container -->

<footer class="mt-auto">
    <div class="container">
        <div class="row py-5">
            <!-- About Section -->
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= app_path('assets/logo.png') ?>" alt="Logo" height="48" onerror="this.style.display='none'">
                    <h5 class="fw-bold text-white mb-0"><?= h($APP_NAME) ?></h5>
                </div>
                <p class="text-white-50 mb-3">
                    Your gateway to knowledge and learning. Access thousands of educational resources anytime, anywhere.
                </p>
                <div class="d-flex gap-2">
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle footer-social-link">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle footer-social-link">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle footer-social-link">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle footer-social-link">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-4 mb-4 mb-lg-0">
                <h6 class="fw-bold text-white mb-3 text-uppercase">Quick Links</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="<?= h(app_path('')) ?>" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>Library
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                    <li class="mb-2">
                        <a href="<?= h(app_path('admin')) ?>" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>Admin Panel
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>About Us
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>Contact
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Resources -->
            <div class="col-lg-3 col-md-4 mb-4 mb-lg-0">
                <h6 class="fw-bold text-white mb-3 text-uppercase">Resources</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>Help Center
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>User Guide
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>FAQs
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>Privacy Policy
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2 footer-link-icon"></i>Terms of Service
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-lg-3 col-md-4">
                <h6 class="fw-bold text-white mb-3 text-uppercase">Contact Info</h6>
                <ul class="list-unstyled">
                    <li class="mb-3 d-flex align-items-start">
                        <i class="fas fa-map-marker-alt text-white-50 me-3 mt-1"></i>
                        <span class="text-white-50">College Of Nursing<br>Old Unth, Enugu</span>
                    </li>
                    <li class="mb-3 d-flex align-items-center">
                        <i class="fas fa-envelope text-white-50 me-3"></i>
                        <a href="mailto:library@consunth.edu.ng" class="text-white-50 text-decoration-none hover-link">
                            library@consunth.edu.ng
                        </a>
                    </li>
                    <li class="mb-3 d-flex align-items-center">
                        <i class="fas fa-phone text-white-50 me-3"></i>
                        <a href="tel:+2348039194681" class="text-white-50 text-decoration-none hover-link">
                            +234 803 919 4681
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="border-top border-secondary pt-4 pb-4">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0 text-white-50">
                        &copy; <?= date('Y') ?> <?= h($APP_NAME) ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-white-50">
                        Made with <i class="fas fa-heart text-danger"></i> by
                        <a href="https://megamindtecnologies.com" class="text-white-50 text-decoration-none hover-link" target="_blank" rel="noopener">
                            MegaMind Technologies LTD
                        </a>
                        for Education
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>

<?php
$showChatbot = false;
if (is_logged_in()) {
    require_once __DIR__ . '/ai.php';
    $showChatbot = function_exists('ai_is_configured') && ai_is_configured();
}
?>

<?php if (is_logged_in()): ?>
<!-- Add to Collection Modal -->
<div class="modal fade" id="collectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add to Collection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="collectionModalList" class="list-group"></div>
                <div class="text-muted small mt-3">
                    Need a new collection? <a href="<?= h(app_path('collections')) ?>">Create one</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($showChatbot): ?>
<style>
  .chatbot-widget { position: fixed; right: 20px; bottom: 20px; z-index: 1050; }
  .chatbot-toggle { border: none; background: #1f2937; color: #fff; padding: 12px 16px; border-radius: 999px; box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
  .chatbot-panel { position: fixed; right: 20px; bottom: 80px; width: 320px; max-height: 420px; background: #fff; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); display: none; flex-direction: column; overflow: hidden; }
  .chatbot-panel.open { display: flex; }
  .chatbot-header { padding: 12px 14px; background: #1f2937; color: #fff; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
  .chatbot-messages { padding: 12px; overflow-y: auto; flex: 1; background: #f8fafc; }
  .chatbot-message { margin-bottom: 10px; padding: 8px 10px; border-radius: 10px; max-width: 85%; font-size: 0.9rem; }
  .chatbot-message.user { background: #2563eb; color: #fff; margin-left: auto; }
  .chatbot-message.assistant { background: #e2e8f0; color: #111827; }
  .chatbot-input { border-top: 1px solid #e5e7eb; padding: 8px; display: flex; gap: 8px; }
  .chatbot-input textarea { flex: 1; resize: none; border: 1px solid #e5e7eb; border-radius: 8px; padding: 6px 8px; font-size: 0.9rem; }
  .chatbot-input button { border: none; background: #2563eb; color: #fff; padding: 8px 12px; border-radius: 8px; }
  @media (max-width: 576px) {
    .chatbot-panel { right: 10px; left: 10px; width: auto; }
  }
</style>
<div class="chatbot-widget">
    <button class="chatbot-toggle" id="chatbotToggle">
        <i class="fas fa-robot me-2"></i>Study Assistant
    </button>
    <div class="chatbot-panel" id="chatbotPanel">
        <div class="chatbot-header">
            <span>Study Assistant</span>
            <button type="button" class="btn btn-sm btn-light" id="chatbotClose">Close</button>
        </div>
        <div class="chatbot-messages" id="chatbotMessages"></div>
        <form class="chatbot-input" id="chatbotForm">
            <textarea rows="2" id="chatbotInput" placeholder="Ask me anything..."></textarea>
            <button type="submit">Send</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?php echo h(app_path('assets/js/bootstrap.bundle.min.js')); ?>"></script>

<!-- App Configuration -->
<script>
    const appPath = '<?= h(app_base_path_prefix()) ?>/';
    const csrfToken = '<?= h(get_csrf_token()) ?>';
    const isLoggedIn = <?= is_logged_in() ? 'true' : 'false' ?>;
</script>

<!-- Core App JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Add smooth scrolling to all links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // =============================================
    // DARK MODE FUNCTIONALITY
    // =============================================
    const DARK_MODE_KEY = 'darkMode';
    const toggle = document.getElementById('darkModeToggle');
    const icon = document.getElementById('darkModeIcon');

    function setTheme(dark) {
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        localStorage.setItem(DARK_MODE_KEY, dark ? 'true' : 'false');

        if (icon) {
            icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Sync with server if logged in
        if (isLoggedIn) {
            fetch(appPath + 'api/settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(csrfToken)}&dark_mode=${dark ? 1 : 0}`
            }).catch(() => {});
        }
    }

    // Initialize theme from localStorage (overrides server preference for immediate response)
    const savedTheme = localStorage.getItem(DARK_MODE_KEY);
    if (savedTheme !== null) {
        const isDark = savedTheme === 'true';
        if (isDark !== (document.documentElement.getAttribute('data-theme') === 'dark')) {
            setTheme(isDark);
        }
    }

    // Toggle handler
    if (toggle) {
        toggle.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            setTheme(!isDark);
        });
    }

    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (localStorage.getItem(DARK_MODE_KEY) === null) {
            setTheme(e.matches);
        }
    });

    // =============================================
    // BOOKMARK FUNCTIONALITY
    // =============================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.bookmark-btn');
        if (!btn || !isLoggedIn) return;

        e.preventDefault();
        e.stopPropagation();

        const resourceId = btn.dataset.resourceId;
        const isBookmarked = btn.dataset.bookmarked === '1';

        // Optimistic UI update
        btn.classList.toggle('bookmarked', !isBookmarked);
        btn.dataset.bookmarked = isBookmarked ? '0' : '1';
        const iconEl = btn.querySelector('i');
        if (iconEl) {
            iconEl.className = isBookmarked ? 'far fa-bookmark' : 'fas fa-bookmark';
        }

        // Send request
        fetch(appPath + 'api/bookmark', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&resource_id=${resourceId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                // Revert on error
                btn.classList.toggle('bookmarked', isBookmarked);
                btn.dataset.bookmarked = isBookmarked ? '1' : '0';
                if (iconEl) {
                    iconEl.className = isBookmarked ? 'fas fa-bookmark' : 'far fa-bookmark';
                }
                showToast(data.error, 'error');
            } else {
                showToast(data.message || (data.bookmarked ? 'Bookmark added' : 'Bookmark removed'), 'success');
            }
        })
        .catch(() => {
            // Revert on error
            btn.classList.toggle('bookmarked', isBookmarked);
            btn.dataset.bookmarked = isBookmarked ? '1' : '0';
            if (iconEl) {
                iconEl.className = isBookmarked ? 'fas fa-bookmark' : 'far fa-bookmark';
            }
            showToast('Failed to update bookmark', 'error');
        });
    });

    // =============================================
    // IMAGE FALLBACK (fontawesome book icon)
    // =============================================
    const applyImageFallback = (img, force = false) => {
        if (!img) return;
        if (img.dataset.fallbackApplied === '1') return;
        if (!force && img.dataset.fallback !== '1') return;
        img.dataset.fallbackApplied = '1';

        const wrapper = img.closest('.resource-image-wrapper, .position-relative') || img.parentElement;
        if (!wrapper) return;

        const style = window.getComputedStyle(wrapper);
        if (style.position === 'static') {
            wrapper.style.position = 'relative';
        }

        const existing = wrapper.querySelector('.image-fallback');
        if (!existing) {
            const fallback = document.createElement('div');
            fallback.className = 'image-fallback';
            fallback.innerHTML = '<i class="fas fa-book"></i>';

            const imgRect = img.getBoundingClientRect();
            const wrapperRect = wrapper.getBoundingClientRect();
            if (imgRect.width && imgRect.height && wrapperRect.width && wrapperRect.height) {
                fallback.style.width = imgRect.width + 'px';
                fallback.style.height = imgRect.height + 'px';
                fallback.style.left = (imgRect.left - wrapperRect.left) + 'px';
                fallback.style.top = (imgRect.top - wrapperRect.top) + 'px';
                fallback.style.right = 'auto';
                fallback.style.bottom = 'auto';
            }

            wrapper.appendChild(fallback);
        }
        img.style.display = 'none';
    };

    const attachImageFallback = (img) => {
        if (!img || img.dataset.fallbackBound === '1') return;
        img.dataset.fallbackBound = '1';
        img.addEventListener('error', () => {
            applyImageFallback(img, true);
        });
        if (img.dataset.fallback === '1') {
            applyImageFallback(img, true);
        }
    };

    document.querySelectorAll(
        'img[data-resource-image="1"], img.resource-image, img.resource-thumb, img.resource-cover-thumb, img.resource-cover-mini, img.card-img-top, img.resource-cover'
    ).forEach(attachImageFallback);

    // =============================================
    // LAZY LOADING WITH INTERSECTION OBSERVER
    // =============================================
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    img.classList.remove('lazy-image');
                    attachImageFallback(img);
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            img.classList.add('lazy-image');
            imageObserver.observe(img);
        });
    }
});

// =============================================
// TOAST NOTIFICATION HELPER
// =============================================
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toast);

    const bsToast = new bootstrap.Toast(toast, { autohide: true, delay: 3000 });
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', () => toast.remove());
}

// =============================================
// PROGRESS TRACKING HELPER (used in viewer.php)
// =============================================
function saveReadingProgress(resourceId, position, percent, totalPages = null) {
    if (!isLoggedIn) return Promise.resolve();

    const body = new URLSearchParams({
        csrf_token: csrfToken,
        resource_id: resourceId,
        position: position,
        percent: percent
    });
    if (totalPages !== null) {
        body.append('total_pages', totalPages);
    }

    return fetch(appPath + 'api/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    }).then(res => res.json()).catch(() => {});
}
</script>

<?php if (is_logged_in()): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const collectionModalEl = document.getElementById('collectionModal');
    const collectionList = document.getElementById('collectionModalList');
    let currentResourceId = null;
    let collectionModal = null;

    if (collectionModalEl) {
        collectionModal = new bootstrap.Modal(collectionModalEl);
    }

    function renderCollections(collections) {
        if (!collectionList) return;
        collectionList.innerHTML = '';
        if (!collections || collections.length === 0) {
            collectionList.innerHTML = '<div class="text-muted">No collections found.</div>';
            return;
        }
        collections.forEach(col => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.textContent = col.name + ' (' + (col.item_count || 0) + ')';
            btn.addEventListener('click', function() {
                if (!currentResourceId) return;
                fetch(appPath + 'api/collection', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: csrfToken,
                        action: 'add_item',
                        collection_id: col.id,
                        resource_id: currentResourceId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        showToast(data.error, 'error');
                    } else {
                        showToast('Added to collection', 'success');
                        if (collectionModal) collectionModal.hide();
                    }
                })
                .catch(() => showToast('Failed to add to collection', 'error'));
            });
            collectionList.appendChild(btn);
        });
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.add-to-collection-btn');
        if (!btn) return;
        currentResourceId = btn.dataset.resourceId;
        if (!collectionModal || !collectionList) return;
        collectionList.innerHTML = '<div class="text-muted">Loading collections...</div>';
        fetch(appPath + 'api/collection')
            .then(res => res.json())
            .then(data => renderCollections(data.collections || []))
            .catch(() => { collectionList.innerHTML = '<div class="text-danger">Failed to load collections.</div>'; });
        collectionModal.show();
    });
});
</script>
<?php endif; ?>

<?php if ($showChatbot): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('chatbotToggle');
    const panel = document.getElementById('chatbotPanel');
    const closeBtn = document.getElementById('chatbotClose');
    const messagesEl = document.getElementById('chatbotMessages');
    const form = document.getElementById('chatbotForm');
    const input = document.getElementById('chatbotInput');
    let historyLoaded = false;

    function appendMessage(role, text) {
        if (!messagesEl) return;
        const div = document.createElement('div');
        div.className = 'chatbot-message ' + role;
        div.textContent = text;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function loadHistory() {
        if (historyLoaded) return;
        historyLoaded = true;
        fetch(appPath + 'api/chatbot')
            .then(res => res.json())
            .then(data => {
                (data.messages || []).forEach(msg => appendMessage(msg.role, msg.content));
            })
            .catch(() => {});
    }

    function togglePanel(show) {
        if (!panel) return;
        panel.classList.toggle('open', show);
        if (show) loadHistory();
    }

    if (toggle) toggle.addEventListener('click', () => togglePanel(!panel.classList.contains('open')));
    if (closeBtn) closeBtn.addEventListener('click', () => togglePanel(false));

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = input ? input.value.trim() : '';
            if (message === '') return;
            appendMessage('user', message);
            if (input) input.value = '';

            fetch(appPath + 'api/chatbot', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrfToken, message })
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }
                appendMessage('assistant', data.reply || '');
            })
            .catch(() => showToast('Chatbot request failed', 'error'));
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>
