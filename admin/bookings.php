<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$pageTitle = 'Bookings | Multi-Sport Court Scheduling & Reservation';
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
                <p class="mb-1 small text-secondary fw-bold text-uppercase">Available</p>
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
                <div class="btn-group flex-wrap admin-filter-group" role="group" aria-label="Reservation filter">
                    <button class="btn btn-primary btn-sm" data-admin-filter="Pending Payment">Pending Payment</button>
                    <button class="btn btn-outline-secondary btn-sm" data-admin-filter="Held">Held</button>
                    <button class="btn btn-outline-secondary btn-sm" data-admin-filter="Booked">Booked</button>
                    <button class="btn btn-outline-secondary btn-sm" data-admin-filter="Available">Available</button>
                    <button class="btn btn-outline-secondary btn-sm" data-admin-filter="All">All</button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 admin-bookings-table">
                <thead>
                    <tr class="small text-secondary">
                        <th>Reservation</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Receipt</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="adminRows" class="small fw-semibold">
                    <tr>
                        <td colspan="6" class="text-secondary">Loading reservations...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
