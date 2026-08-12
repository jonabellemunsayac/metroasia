<?php
$pageTitle = 'Book a Court | Multi-Sport Court Scheduling & Reservation';
$active = 'booking';
include __DIR__ . '/../includes/header.php';
$siteConfig = site_config();
?>
<main data-needs-state class="public-page">
    <section class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="grid content-start gap-5">
            <article class="venue-card">
                <div class="relative">
                    <img src="<?php echo htmlspecialchars(site_asset_url((string) $siteConfig['contact_image_path'])); ?>" alt="Covered Metro Asia courts" class="h-[270px] w-full object-cover">
                    <span class="absolute bottom-4 left-4 rounded-full bg-ink/85 px-4 py-2 text-sm font-black text-white">2 / 4</span>
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h1 class="text-xl font-black leading-tight text-ink">Metro Asia</h1>
                            <p class="mt-2 flex gap-2 text-sm font-semibold leading-5 text-muted">
                                <i data-lucide="map-pin" class="mt-0.5 h-4 w-4 shrink-0"></i>
                                <?php echo htmlspecialchars((string) $siteConfig['address']); ?>
                            </p>
                        </div>
                        <p class="shrink-0 text-xl font-black text-primary">PHP 265<span class="text-sm font-semibold text-muted">/hr</span></p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button class="inline-flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm font-black">
                            <i data-lucide="heart" class="h-4 w-4"></i>174
                        </button>
                        <button class="inline-flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm font-black">
                            <i data-lucide="share-2" class="h-4 w-4"></i>Share
                        </button>
                        <button class="inline-flex items-center gap-2 rounded-md border border-line px-3 py-2 text-sm font-black">
                            <i data-lucide="flag" class="h-4 w-4"></i>Support
                        </button>
                    </div>
                </div>
            </article>

            <article class="public-card p-4">
                <h2 class="text-sm font-black text-ink">About</h2>
                <p class="mt-3 text-sm font-semibold leading-6 text-muted">
                    Metro Asia brings premium indoor pickleball, basketball, and volleyball to Pasig with bright covered courts, smooth gameplay, and simple receipt-based reservations.
                </p>
                    <p class="mt-4 text-sm font-semibold leading-6 text-muted">
                    Each listed court is scheduled independently. Lakers and Miami handle full-size sports, while Pickleball Pro and Wooden courts have their own pickleball availability.
                </p>
            </article>

        </aside>

        <section class="booking-panel">
            <div class="flex items-center gap-3 border-b border-line px-4">
                <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="public-tab public-tab-active">Book</a>
                <a href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>" class="public-tab">Payment</a>
            </div>

            <div class="p-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50/60 px-4 py-3">
                    <p class="text-sm font-black text-primary">Multi-Day Booking Enabled</p>
                    <p class="mt-1 text-sm font-semibold text-muted">Book across multiple dates in a single reservation.</p>
                </div>

                <div class="mt-5 grid gap-3 rounded-lg border border-line bg-white p-3 sm:grid-cols-[1fr_auto] sm:items-center">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.14em] text-muted">Activity</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <button data-sport="Pickleball" class="sport-option-active btn w-100 justify-content-start">Pickleball</button>
                            <button data-sport="Basketball" class="sport-option btn w-100 justify-content-start">Basketball</button>
                            <button data-sport="Volleyball" class="sport-option btn w-100 justify-content-start">Volleyball</button>
                        </div>
                    </div>
                    <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold leading-5 text-amber-900 sm:max-w-[240px]">
                        Past dates and times cannot be booked.
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <i data-lucide="calendar-days" class="h-5 w-5 text-primary"></i>
                        <div class="flex items-center gap-2">
                            <p id="bookingDateLabel" class="text-lg font-black text-ink"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="prevDate" class="grid h-9 w-9 place-items-center rounded-md border border-line text-muted transition hover:text-primary" aria-label="Previous date">
                            <i data-lucide="chevron-left" class="h-4 w-4"></i>
                        </button>
                        <button class="grid h-9 w-9 place-items-center rounded-md border border-line text-muted" aria-label="Calendar">
                            <i data-lucide="calendar" class="h-4 w-4"></i>
                        </button>
                        <button id="nextDate" class="grid h-9 w-9 place-items-center rounded-md border border-line text-muted transition hover:text-primary" aria-label="Next date">
                            <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-line bg-slate-50 p-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-sm font-black text-muted">Rates:</span>
                        <div id="rateCards" class="flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto rounded-lg border border-line bg-white">
                    <div id="bookingGrid" class="booking-grid grid"></div>
                </div>

                <div id="bookingSelectionBar" class="booking-selection-bar hidden">
                    <div>
                        <p class="booking-selection-title">Selected slots</p>
                        <p id="bookingSelectionSummary" class="booking-selection-summary"></p>
                    </div>
                    <button id="bookingSelectionBookNow" type="button" class="btn btn-primary">Book Now</button>
                </div>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/payment-modal.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
