<?php
$pageTitle = 'Multi-Sport Court Scheduling & Reservation';
$active = 'home';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="grid gap-6 lg:grid-cols-[430px_minmax(0,1fr)]">
        <article class="venue-card">
            <div class="relative">
                <img src="<?php echo htmlspecialchars(app_url('assets/courts-preview.png')); ?>" alt="Metro Asia covered courts" class="h-[320px] w-full object-cover">
                <span class="absolute bottom-4 left-4 rounded-full bg-ink/85 px-4 py-2 text-sm font-black text-white">Indoor Courts</span>
            </div>
            <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-black leading-tight text-ink">Metro Asia</h1>
                        <p class="mt-2 flex gap-2 text-sm font-semibold leading-5 text-muted">
                            <i data-lucide="map-pin" class="mt-0.5 h-4 w-4 shrink-0"></i>
                            JV Ayala Avenue Prk 1B, Tagum City, Davao del Norte
                        </p>
                    </div>
                    <p class="shrink-0 text-2xl font-black text-primary">PHP 265<span class="text-sm font-semibold text-muted">/hr</span></p>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xl font-black">7</p>
                        <p class="text-xs font-bold text-muted">Courts</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xl font-black">3</p>
                        <p class="text-xs font-bold text-muted">Sports</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xl font-black">24h</p>
                        <p class="text-xs font-bold text-muted">Access</p>
                    </div>
                </div>
            </div>
        </article>

        <section class="grid content-start gap-5">
            <div class="public-card p-6">
                <p class="text-sm font-black uppercase tracking-[.14em] text-primary">Metro Asia</p>
                <h2 class="mt-3 max-w-2xl font-display text-4xl font-black leading-tight text-ink lg:text-5xl">Multi-Sport Court Scheduling & Reservation</h2>
                <p class="mt-4 max-w-2xl text-sm font-semibold leading-7 text-muted">
                    Choose your sport, pick a slot, select GCash or BDO, then send or upload your receipt for admin verification.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="rounded-full bg-limevolt px-6 py-3 text-sm font-black text-ink transition hover:bg-sport">Book a Court</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/open-play.php')); ?>" class="rounded-full border border-line px-6 py-3 text-sm font-black text-ink transition hover:border-primary hover:text-primary">Open Plays</a>
                    <a href="<?php echo htmlspecialchars(app_url('ui/register.php')); ?>" class="rounded-full border border-line px-6 py-3 text-sm font-black text-ink transition hover:border-primary hover:text-primary">Become a Member</a>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <article class="public-card p-4">
                    <i data-lucide="calendar-check" class="h-5 w-5 text-primary"></i>
                    <h3 class="mt-3 text-base font-black">Court Booking</h3>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Pick a sport, date, court, and time from one compact booking table.</p>
                </article>
                <article class="public-card p-4">
                    <i data-lucide="users" class="h-5 w-5 text-primary"></i>
                    <h3 class="mt-3 text-base font-black">Open Play</h3>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Join organized sessions and reserve your spot with receipt upload.</p>
                </article>
                <article class="public-card p-4">
                    <i data-lucide="wallet-cards" class="h-5 w-5 text-primary"></i>
                    <h3 class="mt-3 text-base font-black">Payment</h3>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Configurable QR and bank channels for production updates.</p>
                </article>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
