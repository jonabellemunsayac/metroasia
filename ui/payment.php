<?php
$pageTitle = 'Payment | Multi-Sport Court Scheduling & Reservation';
$active = 'payment';
include __DIR__ . '/../includes/header.php';
$siteConfig = site_config();
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
?>
<main data-needs-state class="public-page">
    <section class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="grid content-start gap-5">
            <article class="venue-card">
                <img src="<?php echo htmlspecialchars(site_asset_url((string) $siteConfig['contact_image_path'])); ?>" alt="Covered pickleball courts" class="h-[220px] w-full object-cover">
                <div class="p-4">
                    <h1 class="text-xl font-black text-ink">Payment Channels</h1>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Pick a channel during checkout, pay using the shown QR or bank details, then upload your receipt.</p>
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="mt-4 inline-flex rounded-full bg-limevolt px-5 py-2.5 text-sm font-black text-ink transition hover:bg-sport">Book a Court</a>
                </div>
            </article>

            <article class="public-card p-4">
                <h2 class="text-sm font-black text-ink">After Payment</h2>
                <div class="mt-3 grid gap-3 text-sm font-semibold leading-6 text-muted">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="font-black text-ink">Non-members</p>
                        <p class="mt-1">
                            Send payment proof through
                            <?php if ($messengerUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($messengerUrl); ?>" target="_blank" rel="noopener" class="text-primary">Facebook Messenger</a>
                            <?php else: ?>
                                Facebook Messenger
                            <?php endif; ?>
                            with your reservation name, date, sport, court, and time.
                        </p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="font-black text-ink">Registered members</p>
                        <p class="mt-1">Upload payment proof directly to the website. Admin confirms payment after review.</p>
                    </div>
                </div>
            </article>
        </aside>

        <section class="booking-panel">
            <div class="flex items-center gap-3 border-b border-line px-4">
                <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="public-tab">Book</a>
                <a href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>" class="public-tab public-tab-active">Payment</a>
            </div>
            <div class="p-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50/60 px-4 py-3">
                    <p class="text-sm font-black text-primary">Receipt Upload Payment</p>
                    <p class="mt-1 text-sm font-semibold text-muted">Payment channels are configured in the database by admin, including QR images and bank details.</p>
                </div>
                <div id="paymentPageChannels" class="mt-4 grid gap-4 xl:grid-cols-2"></div>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
