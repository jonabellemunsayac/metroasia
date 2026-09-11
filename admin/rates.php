<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin_menu('admin-rates');
$pageTitle = 'Rates/Holiday Schedules';
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
        </div>

        <ul class="nav nav-tabs mt-3" id="adminRateTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="adminRateListTab" data-bs-toggle="tab" data-bs-target="#adminRateListPane" type="button" role="tab" aria-controls="adminRateListPane" aria-selected="true">Rate List</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adminHolidaySchedulesTab" data-bs-toggle="tab" data-bs-target="#adminHolidaySchedulesPane" type="button" role="tab" aria-controls="adminHolidaySchedulesPane" aria-selected="false">Holiday Schedules</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adminRateAuditTab" data-bs-toggle="tab" data-bs-target="#adminRateAuditPane" type="button" role="tab" aria-controls="adminRateAuditPane" aria-selected="false">Audit Trail</button>
            </li>
        </ul>

        <div class="tab-content pt-3" id="adminRateTabContent">
            <div class="tab-pane fade show active" id="adminRateListPane" role="tabpanel" aria-labelledby="adminRateListTab" tabindex="0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="row g-2 align-items-end flex-grow-1">
                        <label class="col-md-4 col-lg-3 small fw-bold">Filter by Sport
                            <select id="adminRateSportFilter" class="form-select">
                                <option value="">All sports</option>
                            </select>
                        </label>
                        <label class="col-md-4 col-lg-3 small fw-bold">Filter by Court
                            <select id="adminRateCourtFilter" class="form-select">
                                <option value="">All courts</option>
                            </select>
                        </label>
                        <div class="col-md-4 col-lg-3">
                            <button id="adminRateClearFilters" class="btn btn-outline-secondary btn-sm" type="button">Clear Filters</button>
                        </div>
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
                                <th>Effective Date</th>
                                <th class="text-end">Rate / hr</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="adminRateSummary" class="small fw-semibold">
                            <tr>
                                <td colspan="7" class="text-secondary">Loading rates...</td>
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
            </div>

            <div class="tab-pane fade" id="adminHolidaySchedulesPane" role="tabpanel" aria-labelledby="adminHolidaySchedulesTab" tabindex="0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <span class="section-kicker">Holidays</span>
                        <h3 class="mt-1 mb-0 fw-black">Holiday schedules</h3>
                    </div>
                    <button id="adminAddHolidaySchedule" class="btn btn-primary btn-sm" type="button">
                        <i data-lucide="plus" class="icon-sm"></i>Add Holiday
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr class="small text-secondary">
                                <th>Date</th>
                                <th>Holiday Name</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="adminHolidayScheduleRows" class="small fw-semibold">
                            <tr>
                                <td colspan="3" class="text-secondary">Loading holiday schedules...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="adminRateAuditPane" role="tabpanel" aria-labelledby="adminRateAuditTab" tabindex="0">
                <span class="section-kicker">Audit Trail</span>
                <h3 class="mt-1 mb-3 fw-black">Rate and holiday changes</h3>
                <div id="adminRateAudit" class="grid gap-2"></div>
            </div>
        </div>
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
                            <label class="col-md-4 small fw-bold">Sport
                                <select required name="sport" id="adminRateSport" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Court
                                <select required name="courtId" id="adminRateCourt" class="form-select"></select>
                            </label>
                            <label class="col-md-4 small fw-bold">Day of Week
                                <select required name="dayOfWeek" id="adminRateDayOfWeek" class="form-select">
                                    <option value="Any">Any day</option>
                                    <option value="Holiday">Holiday</option>
                                    <option value="Weekday">Weekday</option>
                                    <option value="Weekend">Weekend</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </label>
                            <label class="col-md-4 small fw-bold">Day Range From
                                <select name="dayRangeFrom" id="adminRateDayRangeFrom" class="form-select">
                                    <option value="">Use selected day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </label>
                            <label class="col-md-4 small fw-bold">Day Range To
                                <select name="dayRangeTo" id="adminRateDayRangeTo" class="form-select">
                                    <option value="">Use selected day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                            </label>
                            <input type="hidden" name="rateMode" id="adminRateMode" value="range">
                            <label id="adminRateTimeSlotWrap" class="col-md-4 small fw-bold" hidden>Time Slot
                                <select name="timeSlotId" id="adminRateTimeSlot" class="form-select"></select>
                            </label>
                            <div id="adminRateRangeWrap" class="col-md-8 row g-2 m-0 p-0">
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
                            <label class="col-md-4 small fw-bold">Effective Date
                                <input required type="date" name="effectiveDate" id="adminRateEffectiveFrom" class="form-input">
                            </label>
                            <p id="adminRateRangeHelp" class="col-12 small fw-semibold text-secondary mb-0">
                                The rate will be applied to every existing hourly slot fully inside the selected time range and selected day range starting on the effective date. Previous rate versions stay intact.
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

    <div id="adminRateAdvanceBookingModal" class="modal fade" tabindex="-1" aria-labelledby="adminRateAdvanceBookingTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="section-kicker">Advance Bookings</span>
                        <h2 id="adminRateAdvanceBookingTitle" class="modal-title fw-black">Rate change affects future bookings</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="adminRateAdvanceBookingSummary" class="small fw-semibold text-secondary">
                        There are existing advance bookings affected by this rate change. Do you want to update them to the new rate or keep their current rates?
                    </p>
                    <div id="adminRateAdvanceBookingRows" class="table-responsive"></div>
                </div>
                <div class="modal-footer">
                    <button id="adminRateAdvanceCancel" type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button id="adminRateAdvanceKeep" type="button" class="btn btn-outline-primary btn-sm">Keep Existing Rates</button>
                    <button id="adminRateAdvanceUpdate" type="button" class="btn btn-primary btn-sm">Update Advance Bookings</button>
                </div>
            </div>
        </div>
    </div>

    <div id="adminHolidayScheduleModal" class="modal fade" tabindex="-1" aria-labelledby="adminHolidayScheduleModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="adminHolidayScheduleForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Holiday Schedule</span>
                            <h2 id="adminHolidayScheduleModalTitle" class="modal-title fw-black">Add Holiday</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminHolidayScheduleId">
                        <input type="hidden" name="reason" value="Holiday schedule change">
                        <div class="row g-2">
                            <label class="col-md-5 small fw-bold">Date
                                <input required type="date" name="date" id="adminHolidayScheduleDate" class="form-input">
                            </label>
                            <label class="col-md-7 small fw-bold">Holiday Name
                                <input required type="text" maxlength="160" name="holidayName" id="adminHolidayScheduleName" class="form-input">
                            </label>
                        </div>
                        <div class="hidden rounded-md p-2 text-xs font-bold mt-3" data-holiday-schedule-message></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary btn-sm" type="submit">Save Holiday</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
