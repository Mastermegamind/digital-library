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
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-light rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
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
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>Library
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                    <li class="mb-2">
                        <a href="<?= h(app_path('admin')) ?>" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>Admin Panel
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>About Us
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>Contact
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
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>Help Center
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>User Guide
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>FAQs
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>Privacy Policy
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="#" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>Terms of Service
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

<style>
    .hover-link {
        transition: all 0.3s ease;
    }
    
    .hover-link:hover {
        color: white !important;
        padding-left: 5px;
    }

    footer {
        background: linear-gradient(135deg, #1e293b, #334155);
        position: relative;
        overflow: hidden;
    }

    footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
        background-size: 200% 100%;
        animation: gradient 3s ease infinite;
    }

    @keyframes gradient {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    @media (max-width: 1366px), (max-height: 820px) {
        .hero-section,
        .page-header,
        .welcome-card {
            padding: clamp(1rem, 2.2vw, 1.75rem) !important;
            margin-bottom: 1rem !important;
        }

        .search-container,
        .toolbar,
        .quick-stats,
        .stats-bar,
        .pagination-container,
        .resources-table-card,
        .form-card,
        .quick-actions-card,
        .recent-resources-card,
        .section-card,
        .stat-card {
            padding: clamp(0.85rem, 1.8vw, 1.35rem) !important;
        }

        .resource-body,
        .card .card-body {
            padding: clamp(0.8rem, 1.6vw, 1.2rem) !important;
        }

        .display-4 {
            font-size: clamp(1.65rem, 3vw, 2.35rem) !important;
        }

        .display-5 {
            font-size: clamp(1.35rem, 2.4vw, 2rem) !important;
        }

        .section-title,
        .resource-title {
            font-size: clamp(1rem, 1.9vw, 1.25rem) !important;
        }

        .resource-image-wrapper,
        .cover-img {
            height: clamp(180px, 30vh, 240px) !important;
        }

        .resource-cover-thumb,
        .resource-cover-mini {
            width: 58px !important;
            height: 58px !important;
        }

        .section-icon,
        .stat-icon,
        .role-icon {
            width: 56px !important;
            height: 56px !important;
            font-size: 1.45rem !important;
        }

        .stat-number,
        .stat-value {
            font-size: clamp(1.35rem, 2.2vw, 1.85rem) !important;
        }

        .table thead th,
        .table tbody td {
            padding: 0.7rem 0.65rem !important;
        }

        .btn,
        .btn-action,
        .quick-action-btn {
            padding: 0.55rem 0.9rem !important;
        }

        .form-control,
        .form-select {
            padding: 0.65rem 0.85rem !important;
            font-size: 0.95rem !important;
        }

        .avatar-preview {
            width: 160px !important;
            height: 160px !important;
        }

        .avatar-upload-icon {
            font-size: 3rem !important;
        }
    }

    @media (max-width: 1024px) {
        .role-selector {
            grid-template-columns: 1fr 1fr !important;
        }

        .action-buttons {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 768px) {
        .role-selector {
            grid-template-columns: 1fr !important;
        }

        .action-buttons {
            flex-direction: column !important;
            align-items: stretch !important;
        }
    }
</style>

<script src="<?php echo h(app_path('assets/js/bootstrap.bundle.min.js')); ?>"></script>

<!-- Smooth Scroll -->
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
});
</script>

</body>
</html>
