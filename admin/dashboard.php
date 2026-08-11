<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$pageTitle = 'Admin | Multi-Sport Court Scheduling & Reservation';
$active = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<main id="dashboard" data-needs-state class="app-main admin-compact">
    <section class="admin-hero card border-0 mb-3">
        <div class="card-body p-3 p-lg-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <span class="section-kicker">Operations Dashboard</span>
                    <h2 class="mt-2 mb-2 fw-black">Multi-sport schedule board</h2>
                    <p class="mb-3 text-secondary fw-semibold">
                        Click any time slot in the court matrix to create a booking or override a conflict. Use dedicated admin pages for payments, cancellations, rates, users, and court blocking.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#schedule-dashboard" class="btn btn-light btn-sm"><i data-lucide="table-2" class="icon-sm"></i>Court Matrix</a>
                        <a href="<?php echo htmlspecialchars(app_url('admin/bookings.php')); ?>" class="btn btn-outline-light btn-sm"><i data-lucide="calendar-check" class="icon-sm"></i>Bookings</a>
                        <a href="<?php echo htmlspecialchars(app_url('admin/court-blockings.php')); ?>" class="btn btn-outline-light btn-sm"><i data-lucide="shield-alert" class="icon-sm"></i>Court Blockings</a>
                        <a href="<?php echo htmlspecialchars(app_url('admin/rates.php')); ?>" class="btn btn-outline-light btn-sm"><i data-lucide="badge-dollar-sign" class="icon-sm"></i>Rates</a>
                        <a href="<?php echo htmlspecialchars(app_url('admin/payment.php')); ?>" class="btn btn-outline-light btn-sm"><i data-lucide="credit-card" class="icon-sm"></i>Payment Setup</a>
                        <a href="<?php echo htmlspecialchars(app_url('admin/members.php')); ?>" class="btn btn-outline-light btn-sm"><i data-lucide="users" class="icon-sm"></i>Users / Members</a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="admin-profile-card">
                        <span class="admin-avatar"><?php echo htmlspecialchars(strtoupper(substr($admin['name'], 0, 1))); ?></span>
                        <div class="min-w-0">
                            <p class="mb-0 fw-black text-white"><?php echo htmlspecialchars($admin['name']); ?></p>
                            <p class="mb-0 small text-white-50 text-truncate"><?php echo htmlspecialchars($admin['email']); ?></p>
                        </div>
                        <a href="<?php echo htmlspecialchars(app_url('admin/logout.php')); ?>" class="btn btn-light btn-sm ms-auto">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <p class="mb-1 small text-secondary fw-bold text-uppercase">Needs Review</p>
                        <p id="adminPendingCount" class="mb-0 stat-number">0</p>
                    </div>
                    <span class="metric-icon bg-warning-subtle text-warning"><i data-lucide="timer" class="icon-sm"></i></span>
                </div>
                <p class="mb-0 mt-2 small text-secondary fw-semibold">Payment pending or under review</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <p class="mb-1 small text-secondary fw-bold text-uppercase">Confirmed</p>
                        <p id="adminBookedCount" class="mb-0 stat-number">0</p>
                    </div>
                    <span class="metric-icon bg-success-subtle text-success"><i data-lucide="badge-check" class="icon-sm"></i></span>
                </div>
                <p class="mb-0 mt-2 small text-secondary fw-semibold">Paid reservations</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <p class="mb-1 small text-secondary fw-bold text-uppercase">Cancelled</p>
                        <p id="adminCancelledCount" class="mb-0 stat-number">0</p>
                    </div>
                    <span class="metric-icon bg-danger-subtle text-danger"><i data-lucide="ban" class="icon-sm"></i></span>
                </div>
                <p class="mb-0 mt-2 small text-secondary fw-semibold">Staff cancellations</p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <p class="mb-1 small text-secondary fw-bold text-uppercase">System</p>
                        <p class="mb-0 stat-number">Live</p>
                    </div>
                    <span class="metric-icon bg-primary-subtle text-primary"><i data-lucide="activity" class="icon-sm"></i></span>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-success" style="width: 76%;"></div>
                </div>
            </div>
        </div>
    </section>

    <section id="schedule-dashboard" class="app-card p-0 mb-3">
        <div class="card-header bg-white border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
            <div>
                <span class="section-kicker">Multi-Sport Schedule Dashboard</span>
                <h2 class="mt-1 mb-1 fw-black">Daily court matrix</h2>
                <p class="mb-0 small text-secondary fw-semibold">Click a cell to book or override that slot. Miami cells explain Wooden Court conflicts.</p>
            </div>
            <div class="btn-group btn-group-sm">
                <button id="adminSchedulePrev" class="btn btn-outline-secondary" type="button" aria-label="Previous schedule date">
                    <i data-lucide="chevron-left" class="icon-sm"></i>
                </button>
                <span class="btn btn-light disabled text-dark">DATE: <span id="adminScheduleDateLabel"></span></span>
                <button id="adminScheduleNext" class="btn btn-outline-secondary" type="button" aria-label="Next schedule date">
                    <i data-lucide="chevron-right" class="icon-sm"></i>
                </button>
            </div>
        </div>
        <div class="table-responsive bg-light p-3">
            <div id="adminScheduleGrid" class="admin-schedule-grid grid rounded border bg-white"></div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3 border-top px-3 py-2 small fw-bold text-secondary">
            <span class="text-primary">Click any cell to book or override.</span>
            <span><span class="legend-dot bg-light border"></span>Available/Open</span>
            <span><span class="legend-dot bg-warning-subtle border border-warning-subtle"></span>Temporary block</span>
            <span><span class="legend-dot bg-success-subtle border border-success-subtle"></span>Confirmed</span>
            <span><span class="legend-dot bg-danger-subtle border border-danger-subtle"></span>Conflict/block</span>
        </div>
    </section>

    <div id="adminOverrideBookingModal" class="modal fade" tabindex="-1" aria-labelledby="adminOverrideBookingTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="adminOverrideBookingForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Calendar Booking</span>
                            <h2 id="adminOverrideBookingTitle" class="modal-title fw-black">Book selected slot</h2>
                            <p id="adminOverrideContext" class="mb-0 small text-secondary fw-semibold"></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input required type="hidden" name="date" id="adminOverrideDate">
                        <input required type="hidden" name="timeSlotId" id="adminOverrideTime">
                        <div class="row g-2">
                            <label class="col-md-6 small fw-bold">Court
                                <select required name="courtId" id="adminOverrideCourt" class="form-select"></select>
                            </label>
                            <label class="col-md-6 small fw-bold">Sport
                                <select required name="sport" id="adminOverrideSport" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Customer
                                <input required name="name" class="form-input" placeholder="Walk-in / event name">
                            </label>
                            <label class="col-md-4 small fw-bold">Phone
                                <input required name="phone" class="form-input" placeholder="09XX XXX XXXX">
                            </label>
                            <label class="col-md-4 small fw-bold">Email
                                <input type="email" name="email" class="form-input" placeholder="optional@email.com">
                            </label>
                            <label class="col-md-6 small fw-bold">Status
                                <select name="status" class="form-select">
                                    <option value="Confirmed">Confirmed</option>
                                    <option value="Held">Held</option>
                                    <option value="Payment Pending">Payment Pending</option>
                                    <option value="Under Review">Under Review</option>
                                </select>
                            </label>
                            <label class="col-md-6 small fw-bold">Payment
                                <input name="paymentMethod" class="form-input" value="Admin Override">
                            </label>
                            <label class="col-12 small fw-bold">Reason
                                <input required name="overrideReason" class="form-input" placeholder="Example: Walk-in booking approved by admin">
                            </label>
                        </div>
                        <div id="adminOverrideBookingMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-warning btn-sm" type="submit">Save Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
