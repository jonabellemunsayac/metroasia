<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Payment Setup | Multi-Sport Court Scheduling & Reservation';
$active = 'admin-payment';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Payment Setup</span>
                <h2 class="mt-1 mb-1 fw-black">GCash and BDO configuration</h2>
                <p class="mb-0 small text-secondary fw-semibold">Update the GCash QR, BDO bank details, BDO Pay QR, and payment instructions directly from the database-backed setup.</p>
            </div>
            <span class="badge text-bg-primary">GCash and BDO only</span>
        </div>
    </section>

    <section class="app-card">
        <div id="adminPaymentChannels" class="grid gap-3"></div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
