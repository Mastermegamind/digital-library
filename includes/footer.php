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

</body>
</html>
