<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin_menu('admin-courts');
$pageTitle = 'Courts';
$active = 'admin-courts';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <span class="section-kicker">Courts</span>
                <h2 class="mt-1 mb-0 fw-black">Court management</h2>
                <p class="mb-0 small fw-semibold text-secondary">Manage the court reference list used by bookings and rates.</p>
            </div>
            <button id="adminAddCourt" class="btn btn-primary btn-sm" type="button">
                <i data-lucide="plus" class="icon-sm"></i>Add Court
            </button>
        </div>

        <div id="adminCourtManagement" class="grid gap-3">
            <div class="rounded-xl border border-dashed border-line bg-white p-4 text-sm fw-bold text-secondary">Loading courts...</div>
        </div>
    </section>

    <div id="adminCourtModal" class="modal fade" tabindex="-1" aria-labelledby="adminCourtModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="adminCourtForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Court</span>
                            <h2 id="adminCourtModalTitle" class="modal-title fw-black">Add Court</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminCourtId">
                        <div class="row g-3">
                            <label class="col-md-3 small fw-bold">Display Order
                                <input required type="number" min="1" step="1" name="displayNumber" id="adminCourtDisplayNumber" class="form-input">
                            </label>
                            <label class="col-md-5 small fw-bold">Court Name
                                <input required name="name" id="adminCourtName" class="form-input" maxlength="80" placeholder="Court 1">
                            </label>
                            <label class="col-md-4 small fw-bold">Court Type
                                <input required name="courtType" id="adminCourtType" class="form-input" maxlength="80" placeholder="Indoor">
                            </label>
                            <label class="col-md-4 small fw-bold">Surface Label
                                <input name="surfaceLabel" id="adminCourtSurface" class="form-input" maxlength="80" placeholder="Multi-sport">
                            </label>
                            <fieldset class="col-md-5 small fw-bold">
                                <legend class="small fw-bold mb-2">Supported Sports</legend>
                                <div class="d-flex flex-wrap gap-2">
                                    <label class="form-check form-check-inline fw-semibold">
                                        <input class="form-check-input" type="checkbox" name="sports[]" value="Pickleball"> Pickleball
                                    </label>
                                    <label class="form-check form-check-inline fw-semibold">
                                        <input class="form-check-input" type="checkbox" name="sports[]" value="Basketball"> Basketball
                                    </label>
                                    <label class="form-check form-check-inline fw-semibold">
                                        <input class="form-check-input" type="checkbox" name="sports[]" value="Volleyball"> Volleyball
                                    </label>
                                </div>
                            </fieldset>
                            <label class="col-md-3 small fw-bold d-flex align-items-end gap-2 pb-2">
                                <input type="checkbox" name="isActive" id="adminCourtActive" class="form-check-input" value="1" checked>
                                Active
                            </label>
                        </div>
                        <div class="hidden rounded-md p-2 text-xs font-bold mt-3" id="adminCourtFormMessage"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary btn-sm" type="submit">Save Court</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
