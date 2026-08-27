<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin_menu('admin-sport-time-slots');
if (($admin['role'] ?? '') !== 'super_admin') {
    redirect_to('admin/dashboard.php');
}
$pageTitle = 'Sport Time Slots';
$active = 'admin-sport-time-slots';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <span class="section-kicker">Super Admin</span>
                <h2 class="mt-1 mb-1 fw-black">Sport time-slot availability</h2>
                <p class="mb-0 small fw-semibold text-secondary">
                    Choose which hourly slots can be booked per sport. Pickleball starts at 7 AM by default; basketball and volleyball start at 5 AM.
                </p>
            </div>
            <button form="adminSportSlotForm" class="btn btn-primary btn-sm" type="submit">
                <i data-lucide="save" class="icon-sm"></i>Save Availability
            </button>
        </div>
    </section>

    <section class="app-card">
        <form id="adminSportSlotForm">
            <div id="adminSportSlotAvailability" class="grid gap-3">
                <div class="rounded-xl border border-dashed border-line bg-white p-4 text-sm fw-bold text-secondary">Loading sport availability...</div>
            </div>
            <div id="adminSportSlotMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
        </form>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
