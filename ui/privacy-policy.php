<?php
require_once __DIR__ . '/../includes/data-privacy.php';
$policy = data_privacy_active_policy();
$pageTitle = 'Privacy Policy';
$active = 'privacy';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page"><section class="mx-auto grid max-w-[980px] gap-6">
<h1 class="font-display">Privacy Policy</h1>
<article class="public-card privacy-policy-content"><?php echo data_privacy_sanitize_html($policy['contentHtml']); ?></article>
</section></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
