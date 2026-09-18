<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin_menu('admin-court-blockings');
$pageTitle = 'Court Blockings';
$active = 'admin-court-blockings';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Court Blockings</span>
                <h2 class="mt-1 mb-1 fw-black">Operational availability control</h2>
                <p class="mb-0 small text-secondary fw-semibold">Block Miami, Wooden Courts, Lakers, or Pickleball Pro Courts for maintenance, events, tournaments, cleaning, construction, or club activity.</p>
            </div>
            <span class="badge text-bg-warning">Override protected</span>
        </div>
    </section>

    <section class="app-card mb-3">
        <div id="adminCourtBlocks" class="grid gap-3"></div>
        <div id="adminCourtBlockPagination" class="admin-rate-pagination" hidden>
            <div class="admin-rate-pagination-inner">
                <div id="adminCourtBlockPageInfo" class="admin-rate-pagination-meta">
                    Showing court blockings...
                </div>
                <div class="admin-rate-pagination-actions">
                    <select id="adminCourtBlockPageSize" class="admin-rate-page-size" aria-label="Court blockings per page">
                        <option value="10" selected>10 per page</option>
                        <option value="20">20 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                    <button id="adminCourtBlockPrev" class="btn btn-outline-secondary btn-sm" type="button">Previous</button>
                    <button id="adminCourtBlockNext" class="btn btn-primary btn-sm" type="button">Next</button>
                </div>
            </div>
        </div>
    </section>

    <section class="app-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <span class="section-kicker">Audit Trail</span>
                <h2 class="mt-1 mb-0 fw-black">Recent override log</h2>
            </div>
            <a href="<?php echo htmlspecialchars(app_url('admin/dashboard.php')); ?>" class="btn btn-outline-secondary btn-sm"><i data-lucide="table-2" class="icon-sm"></i>Back to Matrix</a>
        </div>
        <div id="adminOverrideLogs" class="grid gap-2"></div>
    </section>

    <div id="adminCourtBlockConflictModal" class="modal fade" tabindex="-1" aria-labelledby="adminCourtBlockConflictTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="section-kicker">Booked Slots Found</span>
                        <h2 id="adminCourtBlockConflictTitle" class="modal-title fw-black">Court blocking not allowed</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="adminCourtBlockConflictSummary" class="small fw-semibold text-secondary"></p>
                    <div id="adminCourtBlockConflictRows" class="table-responsive"></div>
                    <div id="adminCourtBlockConflictMessage" class="mt-3 rounded-md bg-amber-50 p-3 small fw-bold text-warning">
                        Existing bookings will not be overwritten, cancelled, modified, or bypassed. Choose another court, date, or time range.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button id="adminCourtBlockConflictProceed" type="button" class="btn btn-primary btn-sm" hidden>Proceed</button>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
