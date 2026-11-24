<?php
require_once __DIR__ . '/../includes/rules.php';
$policy = rules_active_policy();
$pageTitle = 'Rules';
$active = 'rules';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="mx-auto grid max-w-[980px] gap-6">
        <div>
            <p class="section-kicker">Rules</p>
            <h1 class="mt-2 font-display text-4xl font-black leading-tight">Rules</h1>
            <p class="mt-3 max-w-3xl text-sm font-semibold leading-7 text-muted">
                Please review the venue booking policy, house rules, and liability reminders before reserving a court.
            </p>
        </div>

        <article class="public-card p-5 privacy-policy-content"><?php echo rules_sanitize_html($policy['contentHtml']); ?></article>

        <div>
            <a href="<?php echo htmlspecialchars($bookingCtaHref); ?>" class="btn btn-primary">Let's Play</a>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
