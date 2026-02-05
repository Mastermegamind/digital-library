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
                        <a href="<?= h(app_path('index.php')) ?>" class="text-white-50 text-decoration-none hover-link">
                            <i class="fas fa-chevron-right me-2" style="font-size: 0.75rem;"></i>Library
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                    <li class="mb-2">
                        <a href="<?= h(app_path('admin/index.php')) ?>" class="text-white-50 text-decoration-none hover-link">
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
                        <span class="text-white-50">123 Library Street<br>Port Harcourt, Rivers State</span>
                    </li>
                    <li class="mb-3 d-flex align-items-center">
                        <i class="fas fa-envelope text-white-50 me-3"></i>
                        <a href="mailto:info@library.edu" class="text-white-50 text-decoration-none hover-link">
                            info@library.edu
                        </a>
                    </li>
                    <li class="mb-3 d-flex align-items-center">
                        <i class="fas fa-phone text-white-50 me-3"></i>
                        <a href="tel:+2341234567890" class="text-white-50 text-decoration-none hover-link">
                            +234 123 456 7890
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
                        Made with <i class="fas fa-heart text-danger"></i> for Education
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