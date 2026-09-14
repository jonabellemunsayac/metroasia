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
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button id="adminAddTimeSlot" class="btn btn-outline-primary btn-sm" type="button">
                    <i data-lucide="plus" class="icon-sm"></i>Add Time Slot
                </button>
                <button form="adminSportSlotForm" class="btn btn-primary btn-sm" type="submit">
                    <i data-lucide="save" class="icon-sm"></i>Save Availability
                </button>
            </div>
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

    <div id="adminTimeSlotModal" class="modal fade" tabindex="-1" aria-labelledby="adminTimeSlotModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="adminTimeSlotForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Time Slot</span>
                            <h2 id="adminTimeSlotModalTitle" class="modal-title fw-black">Add Time Slot</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminTimeSlotId">
                        <div class="row g-2">
                            <label class="col-md-6 small fw-bold">Start Time
                                <input required type="time" name="startsAt" id="adminTimeSlotStartsAt" class="form-input">
                            </label>
                            <label class="col-md-6 small fw-bold">End Time
                                <input required type="time" name="endsAt" id="adminTimeSlotEndsAt" class="form-input">
                            </label>
                            <label class="col-md-6 small fw-bold">Period
                                <input name="period" id="adminTimeSlotPeriod" class="form-input" placeholder="Morning">
                            </label>
                            <label class="col-md-3 small fw-bold">Price
                                <input required type="number" min="0" step="1" name="price" id="adminTimeSlotPrice" class="form-input">
                            </label>
                            <label class="col-md-3 small fw-bold">Sort
                                <input required type="number" step="1" name="sortOrder" id="adminTimeSlotSortOrder" class="form-input">
                            </label>
                        </div>
                        <div id="adminTimeSlotFormMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary btn-sm" type="submit">Save Time Slot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
