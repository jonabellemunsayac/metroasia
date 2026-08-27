<?php
$useAdminShell = $useAdminShell ?? false;
$assetVersion = $assetVersion ?? '3.0.20';

if ($useAdminShell):
?>
        </div>
    </div>

    <footer class="admin-footer">
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <span>Metro Asia Multi-Sport Court Scheduling &amp; Reservation.</span>
            </div>
        </div>
    </footer>

<?php else: ?>

    <footer class="metro-footer">
        <div class="metro-container">
            <div class="metro-footer-grid">

                <section class="metro-footer-intro">
                    <p>
                        Stay up to date with court schedules, announcements, events,
                        and the latest updates from MetroAsia Arena.
                    </p>

                    <!--
                        Presentation-only newsletter field for now.
                        Do not connect this to a database until newsletter subscription
                        functionality is intentionally implemented.
                    -->
                    <form class="metro-newsletter" action="#" method="post" onsubmit="return false;">
                        <input
                            type="email"
                            name="email"
                            placeholder="Email address"
                            aria-label="Email address"
                        >
                        <button type="submit">Join the Community</button>
                    </form>
                </section>

                <section class="metro-footer-links">
                    <h3>Quick Links</h3>
                    <a href="<?php echo htmlspecialchars($bookingCtaHref ?? app_url(member_login_path('ui/booking.php'))); ?>">Let's Play</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/index.php#gallery')); ?>">Gallery</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/rules.php')); ?>">Rules</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/index.php#contact-us')); ?>">Contact Us</a>
                </section>

                <section class="metro-footer-social">
                    <h3>Follow Us</h3>

                    <div class="metro-social">

                        <a href="#" aria-label="Facebook" title="Facebook">
                            <span class="social-text-icon">f</span>
                        </a>

                        <a href="#" aria-label="Instagram" title="Instagram">
                            <span class="instagram-icon" aria-hidden="true">
                                <span class="instagram-dot"></span>
                            </span>
                        </a>

                        <a href="#" aria-label="Contact MetroAsia Arena" title="Contact">
                            <i data-lucide="message-circle" class="icon-sm"></i>
                        </a>

                    </div>
                </section>

            </div>

            <div class="metro-copyright">
                Copyright © <span data-metro-year><?php echo date('Y'); ?></span>
                MetroAsia Arena | All rights reserved
            </div>
        </div>
    </footer>

    <div class="gallery-modal" data-gallery-modal hidden aria-hidden="true">
        <div class="gallery-modal-backdrop" data-gallery-modal-close></div>
        <div class="gallery-modal-dialog" role="dialog" aria-modal="true" aria-label="Gallery image viewer">
            <button class="gallery-modal-close" type="button" data-gallery-modal-close aria-label="Close gallery">
                <i data-lucide="x" class="icon-sm"></i>
            </button>

            <button class="gallery-modal-arrow gallery-modal-arrow-left" type="button" data-gallery-modal-prev aria-label="Previous image">
                <i data-lucide="chevron-left" class="icon-sm"></i>
            </button>

            <figure class="gallery-modal-frame">
                <img src="" alt="" data-gallery-modal-image>
                <figcaption class="gallery-modal-caption">
                    <strong data-gallery-modal-title></strong>
                    <span data-gallery-modal-count></span>
                </figcaption>
            </figure>

            <button class="gallery-modal-arrow gallery-modal-arrow-right" type="button" data-gallery-modal-next aria-label="Next image">
                <i data-lucide="chevron-right" class="icon-sm"></i>
            </button>
        </div>
    </div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    window.appConfig = {
        rootUrl: '<?php echo htmlspecialchars(app_url(''), ENT_QUOTES); ?>',
        apiUrl: '<?php echo htmlspecialchars(app_url('api.php'), ENT_QUOTES); ?>',
        adminLoginUrl: '<?php echo htmlspecialchars(app_url('login.php'), ENT_QUOTES); ?>'
    };
</script>

<!-- Existing application JavaScript retained. -->
<script
    src="<?php echo htmlspecialchars(app_url('assets/js/app.js')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
></script>

<?php if (($active ?? '') === 'admin-rates'): ?>
<script
    src="<?php echo htmlspecialchars(
        app_url('assets/js/admin-rates-pagination.js')
    ); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
></script>
<?php endif; ?>

<?php if (($active ?? '') === 'home'): ?>
<script src="<?php echo htmlspecialchars(app_url('assets/js/amenities-gallery.js')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"></script>
<?php endif; ?>

<?php if (($active ?? '') === 'booking'): ?>
<script
    src="<?php echo htmlspecialchars(app_url('assets/js/mobile-booking.js')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
></script>
<?php endif; ?>

<?php if (!$useAdminShell): ?>
    <!-- Mobile public navigation + Metro theme behavior. -->
    <script
        src="<?php echo htmlspecialchars(app_url('assets/js/metro-theme.js')); ?>?v=<?php echo htmlspecialchars($assetVersion); ?>"
    ></script>
<?php endif; ?>

<script>
    if (window.lucide) {
        window.lucide.createIcons();
    }
</script>

</body>
</html>
