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
                        <th>Day</th>
                        <th>Time</th>
                        <th class="text-end">Rate / hr</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="adminRateSummary" class="small fw-semibold">
                    <tr>
                        <td colspan="6" class="text-secondary">Loading rates...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="adminRatePagination" class="admin-rate-pagination" hidden>
            <div class="admin-rate-pagination-inner">
                <div id="adminRatePageInfo" class="admin-rate-pagination-meta">
                    Showing rates...
                </div>

                <div class="admin-rate-pagination-actions">
                    <select
                        id="adminRatePageSize"
                        class="admin-rate-page-size"
                        aria-label="Rates per page"
                    >
                        <option value="10" selected>10 per page</option>
                        <option value="20">20 per page</option>
                        <option value="50">50 per page</option>
                    </select>

                    <button
                        id="adminRatePrev"
                        class="btn btn-outline-secondary btn-sm"
                        type="button"
                    >
                        Previous
                    </button>

                    <button
                        id="adminRateNext"
                        class="btn btn-primary btn-sm"
                        type="button"
                    >
                        Next
                    </button>
                </div>
            </div>
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
                            <label class="col-md-4 small fw-bold">Day of Week
                                <select required name="dayOfWeek" id="adminRateDayOfWeek" class="form-select">
                                    <option value="Any">Any day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </label>
                            <label class="col-md-4 small fw-bold">Apply Rate To
                                <select required name="rateMode" id="adminRateMode" class="form-select">
                                    <option value="single">Single time slot</option>
                                    <option value="range">Time range</option>
                                </select>
                            </label>
                            <label id="adminRateTimeSlotWrap" class="col-md-4 small fw-bold">Time Slot
                                <select required name="timeSlotId" id="adminRateTimeSlot" class="form-select"></select>
                            </label>
                            <div id="adminRateRangeWrap" class="col-md-8 row g-2 m-0 p-0" hidden>
                                <label class="col-md-6 small fw-bold">Start Time
                                    <select name="rangeStart" id="adminRateRangeStart" class="form-select"></select>
                                </label>
                                <label class="col-md-6 small fw-bold">End Time
                                    <select name="rangeEnd" id="adminRateRangeEnd" class="form-select"></select>
                                </label>
                            </div>
                            <label class="col-md-4 small fw-bold">Rate / hr
                                <input required type="number" min="1" step="1" name="pricePerHour" id="adminRatePrice" class="form-input">
                            </label>
                            <p id="adminRateRangeHelp" class="col-12 small fw-semibold text-secondary mb-0" hidden>
                                The rate will be applied to every existing hourly slot fully inside the selected range. Existing matching rates will be updated.
                            </p>
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
