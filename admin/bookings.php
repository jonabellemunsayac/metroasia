<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$pageTitle = 'Bookings';
$active = 'admin-bookings';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Bookings</span>
                <h2 class="mt-1 mb-1 fw-black">Reservation queue</h2>
                <p class="mb-0 small text-secondary fw-semibold">Review payment submissions, confirm paid bookings, and cancel or reject problem reservations.</p>
            </div>
            <a href="<?php echo htmlspecialchars(app_url('admin/dashboard.php')); ?>" class="btn btn-outline-secondary btn-sm"><i data-lucide="table-2" class="icon-sm"></i>Court Matrix</a>
        </div>
    </section>

    <section class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Needs Review</p>
                <p id="adminPendingCount" class="mb-0 stat-number">0</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Booked</p>
                <p id="adminBookedCount" class="mb-0 stat-number">0</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Cancelled</p>
                <p id="adminCancelledCount" class="mb-0 stat-number">0</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Default View</p>
                <p class="mb-0 stat-number">Queue</p>
            </div>
        </div>
    </section>

    <section class="app-card p-0">
        <div class="card-header bg-white border-bottom p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="section-kicker">Approvals</span>
                    <h2 class="mt-1 mb-0 fw-black">Payments and booking actions</h2>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <label class="mb-0 small fw-bold">
                        <span class="visually-hidden">Search by Reference Number</span>
                        <input id="adminReferenceSearch" class="form-input form-input-sm" placeholder="Search by Reference Number">
                    </label>
                    <div class="btn-group flex-wrap admin-filter-group" role="group" aria-label="Reservation filter">
                        <button class="btn btn-primary btn-sm" data-admin-filter="Held">Held</button>
                        <button class="btn btn-outline-secondary btn-sm" data-admin-filter="Booked">Booked</button>
                        <button class="btn btn-outline-secondary btn-sm" data-admin-filter="Cancelled">Cancelled</button>
                        <button class="btn btn-outline-secondary btn-sm" data-admin-filter="All">All</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-bookings-table">
                <thead>
                    <tr class="small text-secondary">
                        <th>Reservation</th>
                        <th>Reference Number</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Receipt</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="adminRows" class="small fw-semibold">
                    <tr>
                        <td colspan="7" class="text-secondary">Loading reservations...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div id="adminCancelReservationModal" class="modal fade" tabindex="-1" aria-labelledby="adminCancelReservationTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="adminCancelReservationForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Cancel Reservation</span>
                            <h2 id="adminCancelReservationTitle" class="modal-title fw-black">Reason required</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminCancelReservationId">
                        <p id="adminCancelReservationSummary" class="small fw-semibold text-secondary"></p>
                        <label class="small fw-bold w-100">Reason for cancellation
                            <textarea required name="reason" id="adminCancelReservationReason" class="form-input mt-2" rows="4" placeholder="Explain why this reservation is being cancelled."></textarea>
                        </label>
                        <div id="adminCancelReservationMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-outline-danger btn-sm">Cancel Reservation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="adminReceiptUploadModal" class="modal fade" tabindex="-1" aria-labelledby="adminReceiptUploadTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="adminReceiptUploadForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Receipt</span>
                            <h2 id="adminReceiptUploadTitle" class="modal-title fw-black">Upload payment proof</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminReceiptReservationId">
                        <p id="adminReceiptUploadSummary" class="small fw-semibold text-secondary"></p>
                        <label class="small fw-bold w-100">Receipt file
                            <input required type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-input mt-2">
                        </label>
                        <p class="mt-2 mb-0 text-xs fw-semibold text-secondary">JPG, PNG, WEBP, or PDF. Max 5MB.</p>
                        <div id="adminReceiptUploadMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
