<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin_menu('admin');
$pageTitle = 'Admin';
$active = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<main id="dashboard" data-needs-state class="app-main admin-compact">
    <section class="admin-hero card border-0 mb-3">
        <div class="card-body p-3 p-lg-4">
            <div class="row g-3 align-items-center">
                <div class="col-12">
                    <span class="section-kicker">Operations Dashboard</span>
                    <h2 class="mt-2 mb-2 fw-black">MetroAsia schedule board</h2>
                    <p class="mb-0 text-secondary fw-semibold">
                        Review daily court availability, reservations, and court blocks in the same rhythm as the player booking calendar.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 mb-3">
        <div class="col-8 col-lg-4">
            <a class="stat-card h-100 d-block text-decoration-none text-reset" href="<?php echo htmlspecialchars(app_url('admin/bookings.php?status=Held')); ?>">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <p class="mb-1 small text-secondary fw-bold text-uppercase">Needs Review</p>
                        <p id="adminPendingCount" class="mb-0 stat-number">0</p>
                    </div>
                    <span class="metric-icon bg-warning-subtle text-warning"><i data-lucide="timer" class="icon-sm"></i></span>
                </div>
                        <p class="mb-0 mt-2 small text-secondary fw-semibold">Submitted reservations</p>
            </a>
        </div>
        <div class="col-8 col-lg-4">
            <a class="stat-card h-100 d-block text-decoration-none text-reset" href="<?php echo htmlspecialchars(app_url('admin/bookings.php?status=Booked')); ?>">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <p class="mb-1 small text-secondary fw-bold text-uppercase">Booked</p>
                        <p id="adminBookedCount" class="mb-0 stat-number">0</p>
                    </div>
                    <span class="metric-icon bg-success-subtle text-success"><i data-lucide="badge-check" class="icon-sm"></i></span>
                </div>
                <p class="mb-0 mt-2 small text-secondary fw-semibold">Paid reservations</p>
            </a>
        </div>
        <div class="col-8 col-lg-4">
            <a class="stat-card h-100 d-block text-decoration-none text-reset" href="<?php echo htmlspecialchars(app_url('admin/bookings.php?status=Cancelled')); ?>">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <p class="mb-1 small text-secondary fw-bold text-uppercase">Cancelled</p>
                        <p id="adminCancelledCount" class="mb-0 stat-number">0</p>
                    </div>
                    <span class="metric-icon bg-danger-subtle text-danger"><i data-lucide="ban" class="icon-sm"></i></span>
                </div>
                        <p class="mb-0 mt-2 small text-secondary fw-semibold">Released reservations</p>
            </a>
        </div>
        <!-- <div class="col-6 col-lg-3">
            <a class="stat-card h-100 d-block text-decoration-none text-reset" href="<?php echo htmlspecialchars(app_url('admin/site-config.php')); ?>">
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
            </a>
        </div> -->
    </section>

    <section id="schedule-dashboard" class="app-card p-0 mb-3">
        <div class="card-header bg-white border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3 p-3">
            <div>
                <span class="section-kicker">Multi-Sport Schedule Dashboard</span>
                <h2 class="mt-1 mb-1 fw-black">Daily court matrix</h2>
                <p class="mb-0 small text-secondary fw-semibold">Available cells can be booked by Admin or Super Admin. Occupied cells open details.</p>
            </div>
            <div class="d-flex flex-wrap align-items-end gap-2">
                <label class="small fw-bold text-secondary">Sport
                    <select id="adminScheduleSportFilter" class="form-select form-select-sm">
                        <option value="">All sports</option>
                        <option value="Pickleball">Pickleball</option>
                        <option value="Basketball">Basketball</option>
                        <option value="Volleyball">Volleyball</option>
                    </select>
                </label>
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
        </div>
        <div class="table-responsive bg-light p-3">
            <div id="adminScheduleGrid" class="admin-schedule-grid grid rounded border bg-white"></div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3 border-top px-3 py-2 small fw-bold text-secondary">
            <span class="text-primary">Click occupied cells for details.</span>
            <span><span class="legend-dot legend-dot-available"></span>Available / Open</span>
            <span><span class="legend-dot legend-dot-held"></span>Held</span>
            <span><span class="legend-dot legend-dot-booked"></span>Booked</span>
            <span><span class="legend-dot legend-dot-blocked"></span>Blocked / Past unavailable</span>
        </div>
    </section>

    <div id="adminOverrideBookingModal" class="modal fade" tabindex="-1" aria-labelledby="adminOverrideBookingTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable admin-override-dialog">
            <div class="modal-content admin-override-modal-content">
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
                        <div class="row g-3 admin-override-single-column">
                            <label class="col-12 small fw-bold">Court
                                <select required name="courtId" id="adminOverrideCourt" class="form-select"></select>
                            </label>
                            <label class="col-12 small fw-bold">Sport
                                <select required name="sport" id="adminOverrideSport" class="form-select"></select>
                            </label>
                            <label class="col-12 small fw-bold">Member
                                <select required name="memberId" id="adminOverrideCustomer" class="form-select"></select>
                            </label>
                            <div class="col-12 small text-secondary fw-semibold" id="adminOverrideCustomerHelp">Choose an active member. Contact details will be filled automatically.</div>
                            <label class="col-12 small fw-bold">Customer name
                                <input required readonly name="name" id="adminOverrideName" class="form-input" placeholder="Selected member name">
                            </label>
                            <label class="col-12 small fw-bold">Phone
                                <input required readonly name="phone" id="adminOverridePhone" class="form-input" placeholder="Selected member phone">
                            </label>
                            <label class="col-12 small fw-bold">Email
                                <input readonly type="email" name="email" id="adminOverrideEmail" class="form-input" placeholder="Selected member email">
                            </label>
                            <label class="col-12 small fw-bold">Status
                                <select name="status" class="form-select">
                                    <option value="Booked">Booked</option>
                                    <option value="Held">Held</option>
                                </select>
                            </label>
                            <label class="col-12 small fw-bold">Payment
                                <input name="paymentMethod" class="form-input" value="Admin Override">
                            </label>
                            <label class="col-12 small fw-bold">Reason
                                <input required name="overrideReason" class="form-input" placeholder="Example: Member booking approved by admin">
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

    <div id="adminCalendarDetailModal" class="modal fade" tabindex="-1" aria-labelledby="adminCalendarDetailTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="section-kicker">Calendar Details</span>
                        <h2 id="adminCalendarDetailTitle" class="modal-title fw-black">Schedule details</h2>
                        <p id="adminCalendarDetailMeta" class="mb-0 small text-secondary fw-semibold"></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="adminCalendarDetailBody" class="modal-body"></div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
