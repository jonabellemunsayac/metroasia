<?php $useAdminShell = $useAdminShell ?? false; ?>
<?php if ($useAdminShell): ?>
        </div>
    </div>
<?php endif; ?>
    <footer class="<?php echo $useAdminShell ? 'admin-footer' : 'public-footer'; ?>">
        <div class="<?php echo $useAdminShell ? 'container-fluid' : 'container-xl'; ?>">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <span>Metro Asia Multi-Sport Court Scheduling & Reservation.</span>
                <?php if (!$useAdminShell): ?>
                    <nav class="d-flex flex-wrap align-items-center gap-3 small fw-bold" aria-label="Secondary links">
                        <a href="<?php echo htmlspecialchars(app_url('ui/open-play.php')); ?>">Open Play</a>
                        <a href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>">Payment</a>
                        <a href="<?php echo htmlspecialchars(app_url('ui/rules.php')); ?>">Rules</a>
                        <a href="<?php echo htmlspecialchars(app_url('ui/contact.php')); ?>">Contact Admin</a>
                    </nav>
                <?php else: ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.appConfig = {
            rootUrl: '<?php echo htmlspecialchars(app_url(''), ENT_QUOTES); ?>',
            apiUrl: '<?php echo htmlspecialchars(app_url('api.php'), ENT_QUOTES); ?>',
            adminLoginUrl: '<?php echo htmlspecialchars(app_url('login.php'), ENT_QUOTES); ?>'
        };
    </script>
    <script src="<?php echo htmlspecialchars(app_url('assets/js/app.js')); ?>?v=<?php echo $assetVersion ?? '3.0.0'; ?>"></script>
</body>
</html>
