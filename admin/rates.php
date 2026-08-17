<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Rates';
$active = 'admin-rates';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div>
                <span class="section-kicker">Rates</span>
                <h2 class="mt-1 mb-0 fw-black">Court pricing</h2>
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
                        <th>Time</th>
                        <th class="text-end">Rate / hr</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="adminRateSummary" class="small fw-semibold">
                    <tr>
                        <td colspan="5" class="text-secondary">Loading rates...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="app-card">
        <span class="section-kicker">Rate Audit</span>
        <h2 class="mt-1 mb-3 fw-black">Recent rate changes</h2>
        <div id="adminRateAudit" class="grid gap-2"></div>
    </section>

    <div id="adminRateModal" class="modal fade" tabindex="-1" aria-labelledby="adminRateModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="adminRateForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Rate</span>
                            <h2 id="adminRateModalTitle" class="modal-title fw-black">Add Rate</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminRateId">
                        <input type="hidden" name="reason" id="adminRateReason" value="Regular rate">
                        <div class="row g-2">
                            <label class="col-md-4 small fw-bold">Court
                                <select required name="courtId" id="adminRateCourt" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Sport
                                <select required name="sport" id="adminRateSport" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Time Slot
                                <select required name="timeSlotId" id="adminRateTimeSlot" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Rate / hr
                                <input required type="number" min="1" step="1" name="pricePerHour" id="adminRatePrice" class="form-input">
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
