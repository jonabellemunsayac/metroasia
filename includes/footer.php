<?php
$useAdminShell = $useAdminShell ?? false;
$assetVersion = $assetVersion ?? '3.0.21';
$appJsPath = dirname(__DIR__) . '/assets/js/app.js';
$appJsVersion = $assetVersion . (is_file($appJsPath) ? '.' . filemtime($appJsPath) : '');

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

<?php else:
    $footerConfig = site_config();
    $footerSocialUrl = static function (string $key) use ($footerConfig): string {
        $url = trim((string) ($footerConfig[$key] ?? ''));
        return filter_var($url, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : '';
    };
    $footerFacebookUrl = $footerSocialUrl('facebook_url');
    $footerMessengerUrl = $footerSocialUrl('messenger_url');
?>

    <footer class="metro-footer">
        <div class="metro-container">
            <div class="metro-footer-grid">

                <section class="metro-footer-intro">
                    <p>
                        Stay up to date with court schedules, announcements, events,
                        and the latest updates from MetroAsia Sports Center.
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
                    <a href="<?php echo htmlspecialchars(app_url('ui/rules.php')); ?>">Rules</a>
                    <span aria-disabled="true">Terms of Service</span>
                    <span aria-disabled="true">Privacy Policy</span>
                    <span aria-disabled="true">FAQs</span>
                    <a href="<?php echo htmlspecialchars(app_url('ui/index.php#about')); ?>">About</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/index.php#contact-us')); ?>">Contact Us</a>
                </section>

                <section class="metro-footer-links">
                    <h3>Community</h3>
                    <span aria-disabled="true">Be an OP Host</span>
                    <span aria-disabled="true">List your Club</span>
                    <span aria-disabled="true">Tournament</span>
                    <!-- <a href="<?php //echo htmlspecialchars(app_url('ui/index.php#about')); ?>">About</a> -->
                </section>

                <section class="metro-footer-social">
                    <h3>Follow Us</h3>

                    <div class="metro-social">

                        <?php if ($footerFacebookUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($footerFacebookUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook" title="Facebook">
                            <span class="social-text-icon">f</span>
                        </a>
                        <?php endif; ?>

                        <!-- <a href="#" aria-label="Instagram" title="Instagram">
                            <span class="instagram-icon" aria-hidden="true">
                                <span class="instagram-dot"></span>
                            </span>
                        </a> -->

                        <?php if ($footerMessengerUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($footerMessengerUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Contact MetroAsia Sports Center" title="Contact">
                            <i data-lucide="message-circle" class="icon-sm"></i>
                        </a>
                        <?php endif; ?>

                    </div>
                </section>

            </div>

            <div class="metro-copyright">
                Copyright © <span data-metro-year><?php echo date('Y'); ?></span>
                MetroAsia Sports Center | All rights reserved
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
    src="<?php echo htmlspecialchars(app_url('assets/js/app.js')); ?>?v=<?php echo htmlspecialchars($appJsVersion); ?>"
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
        src="<?php echo htmlspecialchars(app_url('assets/js/metro-theme.js')); ?>?v=<?php echo htmlspecialchars(hash_file('sha256', __DIR__ . '/../assets/js/metro-theme.js') ?: $assetVersion); ?>"
    ></script>
<?php endif; ?>

<script>
    if (window.lucide) {
        window.lucide.createIcons();
    }
</script>

</body>
</html>
