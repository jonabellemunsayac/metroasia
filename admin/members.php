<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Users / Members';
$active = 'admin-members';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Users / Members</span>
                <h2 class="mt-1 mb-1 fw-black">Access and account management</h2>
                <p class="mb-0 small text-secondary fw-semibold">Manage registered member access and create or update staff/admin accounts.</p>
            </div>
            <span class="badge text-bg-success">Database managed</span>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-12">
            <div class="app-card h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <span class="section-kicker">Members</span>
                        <h2 class="mt-1 mb-0 fw-black">Registered member list</h2>
                    </div>
                    <span class="badge text-bg-light">Activate / deactivate</span>
                </div>
                <div id="adminMembers"></div>
            </div>
        </div>
        <div class="col-12">
            <div class="app-card h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <span class="section-kicker">Admin Users</span>
                        <h2 class="mt-1 mb-0 fw-black">Staff access</h2>
                    </div>
                    <span class="badge text-bg-primary">Admin / staff</span>
                </div>
                <div id="adminUsers" class="grid gap-3"></div>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
