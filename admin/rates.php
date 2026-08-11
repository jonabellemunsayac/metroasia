<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Rates | Multi-Sport Court Scheduling & Reservation';
$active = 'admin-rates';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Rates</span>
                <h2 class="mt-1 mb-1 fw-black">Pricing rules</h2>
                <p class="mb-0 small text-secondary fw-semibold">Configure pricing by court, sport, day of the week, time, duration, and member or non-member status.</p>
            </div>
            <span class="badge text-bg-primary">Audited changes</span>
        </div>
    </section>

    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div>
                <span class="section-kicker">Configured Rates</span>
                <h2 class="mt-1 mb-0 fw-black">Rate list</h2>
            </div>
            <button id="adminAddRate" class="btn btn-primary btn-sm" type="button">
                <i data-lucide="plus" class="icon-sm"></i>Add Rate
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr class="small text-secondary">
                        <th>Court</th>
                        <th>Sport</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Edit</th>
                    </tr>
                </thead>
                <tbody id="adminRateSummary" class="small fw-semibold">
                    <tr>
                        <td colspan="6" class="text-secondary">Loading active rates...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="app-card">
        <span class="section-kicker">Rate Audit</span>
        <h2 class="mt-1 mb-3 fw-black">Recent pricing changes</h2>
        <div id="adminRateAudit" class="grid gap-2"></div>
    </section>

    <div id="adminRateModal" class="modal fade" tabindex="-1" aria-labelledby="adminRateModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="adminRateForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Rate Rule</span>
                            <h2 id="adminRateModalTitle" class="modal-title fw-black">Add Rate</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminRateId">
                        <div class="row g-2">
                            <label class="col-md-4 small fw-bold">Court
                                <select name="courtId" id="adminRateCourt" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Sport
                                <select name="sport" id="adminRateSport" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Day
                                <select name="dayPattern" id="adminRateDay" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Start
                                <input required type="time" name="startsAt" id="adminRateStart" class="form-input">
                            </label>
                            <label class="col-md-4 small fw-bold">End
                                <input required type="time" name="endsAt" id="adminRateEnd" class="form-input">
                            </label>
                            <label class="col-md-4 small fw-bold">Duration
                                <select required name="durationMinutes" id="adminRateDuration" class="form-select"></select>
                            </label>
                            <label class="col-md-6 small fw-bold">Rule name
                                <input required name="name" id="adminRateName" class="form-input" placeholder="Miami Basketball Saturday">
                            </label>
                            <label class="col-md-3 small fw-bold">Non-member / hr
                                <input required type="number" min="1" step="1" name="pricePerHour" id="adminRatePrice" class="form-input">
                            </label>
                            <label class="col-md-3 small fw-bold">Member / hr
                                <input type="number" min="1" step="1" name="memberPricePerHour" id="adminRateMemberPrice" class="form-input" placeholder="Optional">
                            </label>
                            <label class="col-md-3 small fw-bold">Priority
                                <input type="number" name="priority" id="adminRatePriority" class="form-input">
                            </label>
                            <label class="col-md-5 small fw-bold">Change reason
                                <select name="reason" id="adminRateReason" class="form-select"></select>
                            </label>
                            <label class="col-md-2 small fw-bold d-flex align-items-end gap-2 pb-1">
                                <input type="checkbox" name="isActive" id="adminRateActive" value="1">
                                Enabled
                            </label>
                            <label class="col-md-6 small fw-bold">Effective from
                                <input type="date" name="effectiveFrom" id="adminRateEffectiveFrom" class="form-input">
                            </label>
                            <label class="col-md-6 small fw-bold">Effective to
                                <input type="date" name="effectiveTo" id="adminRateEffectiveTo" class="form-input">
                            </label>
                        </div>
                        <div class="hidden rounded-md p-2 text-xs font-bold mt-3" data-rate-rule-message></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary btn-sm" type="submit">Save Rate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
