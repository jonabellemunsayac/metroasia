<?php
$pageTitle = 'Open Play | Multi-Sport Court Scheduling & Reservation';
$active = 'open-play';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="public-page">
    <section class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="grid content-start gap-5">
            <article class="venue-card">
                <img src="<?php echo htmlspecialchars(app_url('assets/courts-preview.png')); ?>" alt="Metro Asia indoor courts" class="h-[220px] w-full object-cover">
                <div class="p-4">
                    <h1 class="text-xl font-black text-ink">Open Play Sessions</h1>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted">Join hosted sessions, rotate with other players, and upload your payment receipt to reserve a spot.</p>
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="mt-4 inline-flex rounded-full border border-line px-5 py-2.5 text-sm font-black text-ink transition hover:border-primary hover:text-primary">Book Private Court</a>
                </div>
            </article>

            <article class="public-card p-4">
                <h2 class="text-sm font-black text-ink">Session Notes</h2>
                <div class="mt-3 grid gap-3 text-sm font-semibold leading-6 text-muted">
                    <p>Open play reservations use the same payment channels as court bookings.</p>
                    <p>Slots are pending until staff verify the uploaded receipt.</p>
                    <p>Capacity is updated from the live reservation database.</p>
                </div>
            </article>
        </aside>

        <section class="booking-panel">
            <div class="flex items-center gap-3 border-b border-line px-4">
                <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="public-tab">Book</a>
                <a href="<?php echo htmlspecialchars(app_url('ui/open-play.php')); ?>" class="public-tab public-tab-active">Open Plays</a>
                <a href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>" class="public-tab">Payment</a>
            </div>
            <div class="p-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50/60 px-4 py-3">
                    <p class="text-sm font-black text-primary">Find a Group Game</p>
                    <p class="mt-1 text-sm font-semibold text-muted">Choose a session, submit your details, and upload your receipt for admin confirmation.</p>
                </div>
                <div id="openPlayCards" class="mt-4 grid gap-4 xl:grid-cols-2"></div>
            </div>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/payment-modal.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
