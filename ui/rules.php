<?php
$pageTitle = 'Rules and Policies';
$active = 'rules';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
        <aside class="public-card p-5">
            <p class="section-kicker">Policies</p>
            <h1 class="mt-2 font-display text-3xl font-black leading-tight">Clear rules for booking, payment, and court use.</h1>
            <p class="mt-4 text-sm font-semibold leading-7 text-muted">
                These policies help keep reservations fair while admin staff reviews payment receipts and booking changes.
            </p>
            <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="btn btn-primary mt-5 w-full">Book a Court</a>
        </aside>

        <section class="grid gap-4">
            <article class="public-card p-5">
                <div class="flex items-start gap-3">
                    <i data-lucide="git-branch" class="mt-1 h-5 w-5 text-primary"></i>
                    <div>
                        <h2 class="text-xl font-black">Court Availability</h2>
                        <p class="mt-2 text-sm font-semibold leading-6 text-muted">
                            Each court listing has its own schedule. A booking only blocks the selected court for that time slot.
                        </p>
                    </div>
                </div>
            </article>

            <article class="public-card p-5">
                <div class="flex items-start gap-3">
                    <i data-lucide="credit-card" class="mt-1 h-5 w-5 text-primary"></i>
                    <div>
                        <h2 class="text-xl font-black">Payment Proof</h2>
                        <p class="mt-2 text-sm font-semibold leading-6 text-muted">
                            Use the selected payment channel instructions, then upload a receipt if you are signed in as a member. Non-members may send payment proof directly to the admin for manual verification.
                        </p>
                    </div>
                </div>
            </article>

            <article class="public-card p-5">
                <div class="flex items-start gap-3">
                    <i data-lucide="ban" class="mt-1 h-5 w-5 text-primary"></i>
                    <div>
                        <h2 class="text-xl font-black">Schedule Changes</h2>
                        <p class="mt-2 text-sm font-semibold leading-6 text-muted">
                            For schedule changes, contact the admin with your booking name, date, sport, and time slot so staff can review the request.
                        </p>
                    </div>
                </div>
            </article>
        </section>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
