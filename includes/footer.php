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
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>">Book a Court</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/index.php#gallery')); ?>">Gallery</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/rules.php')); ?>">Court Rules</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>">Payment</a>
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