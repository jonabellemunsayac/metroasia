<?php
require_once __DIR__ . '/../includes/terms-conditions.php';
$policy = terms_conditions_active_policy();
$pageTitle = 'Terms of Service';
$active = 'terms';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page"><section class="mx-auto grid max-w-[980px] gap-6">
<h1 class="font-display">Terms of Service</h1>
<article class="public-card privacy-policy-content"><?php echo terms_conditions_sanitize_html($policy['contentHtml']); ?></article>
</section></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
