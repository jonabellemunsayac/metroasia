<?php
require_once __DIR__ . '/../includes/faqs.php';
$faqs = faqs_all(db());
$pageTitle = 'FAQs';
$active = 'faqs';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page"><section class="mx-auto grid max-w-[980px] gap-6">
    <h1 class="font-display">Frequently Asked Questions</h1>
    <?php if (!$faqs): ?><div class="public-card"><p>Have a question? Please <a href="<?php echo htmlspecialchars(app_url('ui/contact.php')); ?>">contact us</a>.</p></div><?php endif; ?>
    <?php foreach ($faqs as $faq): ?>
        <details class="public-card"><summary class="fw-bold"><?php echo htmlspecialchars($faq['question']); ?></summary><div class="privacy-policy-content mt-3"><?php echo page_content_sanitize_html($faq['answer_html']); ?></div></details>
    <?php endforeach; ?>
</section></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
