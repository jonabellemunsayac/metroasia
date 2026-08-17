<?php
$pageTitle = 'Contact Admin';
$active = 'contact';
include __DIR__ . '/../includes/header.php';
$siteConfig = site_config();
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
?>
<main class="public-page">
    <section class="grid gap-6 lg:grid-cols-[420px_minmax(0,1fr)]">
        <article class="venue-card">
            <img src="<?php echo htmlspecialchars(site_asset_url((string) $siteConfig['contact_image_path'])); ?>" alt="Metro Asia covered courts" class="h-[270px] w-full object-cover">
            <div class="p-5">
                <p class="section-kicker">Contact Admin</p>
                <h1 class="mt-2 font-display text-3xl font-black leading-tight">Need help with a booking or payment?</h1>
                <p class="mt-4 text-sm font-semibold leading-7 text-muted">
                    Send your reservation name, sport, date, time, and payment reference so staff can review it quickly.
                </p>
                <div class="mt-5 grid gap-3 text-sm font-semibold">
                    <p class="flex items-center gap-2"><i data-lucide="map-pin" class="h-4 w-4 text-primary"></i><?php echo htmlspecialchars((string) $siteConfig['address']); ?></p>
                    <p class="flex items-center gap-2"><i data-lucide="phone" class="h-4 w-4 text-primary"></i>Admin desk: <?php echo htmlspecialchars((string) $siteConfig['contact_phone']); ?></p>
                    <p class="flex items-center gap-2"><i data-lucide="mail" class="h-4 w-4 text-primary"></i><?php echo htmlspecialchars((string) $siteConfig['contact_email']); ?></p>
                    <?php if ($messengerUrl !== ''): ?>
                        <p class="flex items-center gap-2"><i data-lucide="message-circle" class="h-4 w-4 text-primary"></i><a href="<?php echo htmlspecialchars($messengerUrl); ?>" target="_blank" rel="noopener">Facebook Messenger</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <section class="grid content-start gap-5">
            <form class="public-card p-6" action="mailto:<?php echo htmlspecialchars((string) $siteConfig['contact_email']); ?>" method="post" enctype="text/plain">
                <p class="section-kicker">Message</p>
                <h2 class="mt-2 font-display text-3xl font-black">Send booking details</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm font-bold">Name
                        <input name="name" required class="form-input" placeholder="Your full name">
                    </label>
                    <label class="grid gap-2 text-sm font-bold">Phone
                        <input name="phone" required class="form-input" placeholder="09XX XXX XXXX">
                    </label>
                </div>
                <label class="mt-4 grid gap-2 text-sm font-bold">Reservation concern
                    <select name="concern" class="form-select">
                        <option>Payment verification</option>
                        <option>Booking change</option>
                        <option>Cancellation request</option>
                        <option>Membership help</option>
                    </select>
                </label>
                <label class="mt-4 grid gap-2 text-sm font-bold">Details
                    <textarea name="details" rows="6" required class="form-textarea" placeholder="Include date, time, sport, court, payment channel, and reference number."></textarea>
                </label>
                <button class="btn btn-primary mt-5">Send Message</button>
            </form>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="public-card p-4">
                    <p class="text-sm font-black text-primary">Payments</p>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Use the active GCash or BDO details shown on the payment guide.</p>
                </div>
                <div class="public-card p-4">
                    <p class="text-sm font-black text-primary">Members</p>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Upload payment proof from My Bookings after signing in.</p>
                </div>
                <div class="public-card p-4">
                    <p class="text-sm font-black text-primary">Admin Review</p>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Staff reviews receipts and reservation details from the admin area.</p>
                </div>
            </div>

            <div class="landing-map">
                <iframe
                    title="Metro Asia Arena map"
                    src="<?php echo htmlspecialchars((string) $siteConfig['map_embed_url']); ?>"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen>
                </iframe>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
