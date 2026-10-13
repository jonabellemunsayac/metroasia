<?php
require_once __DIR__.'/announcements.php';
$announcement=announcement_settings();
if (announcement_is_active($announcement)):
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/announcement.css')); ?>?v=<?php echo hash_file('sha256',__DIR__.'/../assets/themes/metro/announcement.css'); ?>">
<dialog id="announcementPopup" aria-label="Announcement">
    <button type="button" class="announcement-close" aria-label="Close announcement" autofocus>&times;</button>
    <img src="<?php echo htmlspecialchars(app_url($announcement['image'])); ?>" alt="MetroAsia Sports Center announcement">
</dialog>
<script src="<?php echo htmlspecialchars(app_url('assets/js/announcement.js')); ?>?v=<?php echo hash_file('sha256',__DIR__.'/../assets/js/announcement.js'); ?>" defer></script>
<?php endif; ?>
