<?php
$pageTitle = 'About Us | Multi-Sport Court Scheduling & Reservation';
$active = 'about';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="grid gap-6 lg:grid-cols-[420px_minmax(0,1fr)]">
        <article class="venue-card">
            <div class="relative">
                <img src="<?php echo htmlspecialchars(app_url('assets/hero-pickleball.png')); ?>" alt="Players at Metro Asia" class="h-[320px] w-full object-cover">
                <span class="absolute bottom-4 left-4 rounded-full bg-ink/85 px-4 py-2 text-sm font-black text-white">Pasig City</span>
            </div>
            <div class="p-5">
                <p class="section-kicker">About Us</p>
                <h1 class="mt-2 font-display text-3xl font-black leading-tight">A compact indoor sports venue built for reliable play.</h1>
                <p class="mt-4 text-sm font-semibold leading-7 text-muted">
                    Metro Asia gives players a bright, covered, reservation-ready venue for pickleball, basketball, and volleyball. The platform keeps court selection clear before anyone pays.
                </p>
            </div>
        </article>

        <section class="grid content-start gap-5">
            <div class="public-card p-6">
                <p class="section-kicker">Facility</p>
                <h2 class="mt-2 font-display text-3xl font-black">Clear court listings for every sport.</h2>
                <p class="mt-4 max-w-3xl text-sm font-semibold leading-7 text-muted">
                    Lakers and Miami support full-size basketball and volleyball reservations. Miami, Pickleball Pro Courts, and Wooden Courts are listed independently for pickleball booking.
                </p>
                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-sm font-black text-primary">Lakers</p>
                        <p class="mt-2 text-sm font-semibold leading-6 text-muted">Full-size basketball and volleyball court.</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-sm font-black text-primary">Miami</p>
                        <p class="mt-2 text-sm font-semibold leading-6 text-muted">Full-size court for basketball, volleyball, and pickleball reservations.</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-sm font-black text-primary">Wooden Courts</p>
                        <p class="mt-2 text-sm font-semibold leading-6 text-muted">Courts 5-7 are listed with their own pickleball availability.</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="public-card p-5">
                    <i data-lucide="calendar-check" class="h-5 w-5 text-primary"></i>
                    <h3 class="mt-3 text-lg font-black">Simple Reservations</h3>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Players see a clean court schedule while admins manage reservation review and conflicts in the back office.</p>
                </article>
                <article class="public-card p-5">
                    <i data-lucide="wallet-cards" class="h-5 w-5 text-primary"></i>
                    <h3 class="mt-3 text-lg font-black">Receipt-Based Payment</h3>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Players can choose GCash or BDO, then upload proof as a member or send proof to the admin for review.</p>
                </article>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
