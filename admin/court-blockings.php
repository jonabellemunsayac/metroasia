<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
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
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
