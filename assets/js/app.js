const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
const api = window.appConfig?.apiUrl || 'api.php';
const adminLoginUrl = window.appConfig?.adminLoginUrl || 'login.php';
const rootUrl = window.appConfig?.rootUrl || '';
const metroTimeZone = 'Asia/Manila';

let state = null;
let selectedDate = new Date();
let adminScheduleDate = new Date();
let adminScheduleSportFilter = 'Pickleball';
const adminReservationFilters = ['Held', 'Booked', 'Cancelled', 'All'];
function normalizeAdminFilter(value) {
    return adminReservationFilters.includes(value) ? value : 'Held';
}
const pageParams = new URLSearchParams(window.location.search);
const todayIso = isoDate(new Date());
let adminFilter = normalizeAdminFilter(pageParams.get('status'));
let adminReferenceSearch = '';
let adminBookingStartDate = pageParams.has('from') ? pageParams.get('from') : todayIso;
let adminBookingEndDate = pageParams.has('to') ? pageParams.get('to') : todayIso;
let adminBookingSort = pageParams.get('sort') || 'created-desc';
let adminScheduleCalendarMonth = new Date(adminScheduleDate.getFullYear(), adminScheduleDate.getMonth(), 1);
let adminMemberSearch = '';
let adminRateSportFilter = '';
let adminRateCourtFilter = '';
let adminQrStream = null;
const supportedBookingSports = ['Pickleball', 'Basketball', 'Volleyball'];
function normalizeBookingSport(value) {
    const requested = String(value || '').trim().toLowerCase();
    return supportedBookingSports.find(sport => sport.toLowerCase() === requested) || 'Pickleball';
}
let selectedSport = normalizeBookingSport(window.metroBookingSport || new URLSearchParams(window.location.search).get('sport'));
let selectedBookingSlots = [];
let activeBookingSlots = [];
let bookingModalStep = 0;
let bookingModalCloseUnlocked = false;
const bookingPaymentWindowMs = 15 * 60 * 1000;
let bookingPaymentDeadline = 0;
let bookingPaymentTimerId = null;
let bookingPaymentMethod = '';

const els = {
    rates: document.getElementById('rateCards'),
    grid: document.getElementById('bookingGrid'),
    dateLabel: document.getElementById('bookingDateLabel'),
    datePicker: document.getElementById('bookingDatePicker'),
    admin: document.getElementById('adminRows'),
    modal: document.getElementById('bookingModal'),
    modalTitle: document.getElementById('modalTitle'),
    modalMeta: document.getElementById('modalMeta'),
    modalKicker: document.getElementById('modalKicker'),
    form: document.getElementById('paymentForm'),
    formMessage: document.getElementById('formMessage'),
    bookingReviewSummary: document.getElementById('bookingReviewSummary'),
    bookingReferencePanel: document.getElementById('bookingReferencePanel'),
    bookingStepPanels: document.querySelectorAll('[data-booking-step-panel]'),
    bookingStepItems: document.querySelectorAll('[data-booking-step]'),
    modalBackButton: document.getElementById('modalBackButton'),
    modalNextButton: document.getElementById('modalNextButton'),
    modalSubmitButton: document.getElementById('modalSubmitButton'),
    paymentMethod: document.getElementById('paymentMethodSelect'),
    paymentInstructions: document.getElementById('paymentInstructions'),
    bookingSelectionBar: document.getElementById('bookingSelectionBar'),
    bookingSelectionSummary: document.getElementById('bookingSelectionSummary'),
    bookingSelectionBookNow: document.getElementById('bookingSelectionBookNow'),
    inlineReservation: document.getElementById('bookingInlineReservation'),
    inlineEmpty: document.getElementById('bookingInlineEmpty'),
    bookingSummaryDateLabel: document.getElementById('bookingSummaryDateLabel'),
    paymentPageChannels: document.getElementById('paymentPageChannels'),
    adminPaymentChannels: document.getElementById('adminPaymentChannels'),
    adminRateSummary: document.getElementById('adminRateSummary'),
    adminRateSportFilter: document.getElementById('adminRateSportFilter'),
    adminRateCourtFilter: document.getElementById('adminRateCourtFilter'),
    adminRateClearFilters: document.getElementById('adminRateClearFilters'),
    adminAddRate: document.getElementById('adminAddRate'),
    adminRateModal: document.getElementById('adminRateModal'),
    adminRateForm: document.getElementById('adminRateForm'),
    adminRateModalTitle: document.getElementById('adminRateModalTitle'),
    adminRateId: document.getElementById('adminRateId'),
    adminRateCourt: document.getElementById('adminRateCourt'),
    adminRateSport: document.getElementById('adminRateSport'),
    adminRateDayOfWeek: document.getElementById('adminRateDayOfWeek'),
    adminRateMode: document.getElementById('adminRateMode'),
    adminRateTimeSlotWrap: document.getElementById('adminRateTimeSlotWrap'),
    adminRateTimeSlot: document.getElementById('adminRateTimeSlot'),
    adminRateRangeWrap: document.getElementById('adminRateRangeWrap'),
    adminRateRangeStart: document.getElementById('adminRateRangeStart'),
    adminRateRangeEnd: document.getElementById('adminRateRangeEnd'),
    adminRateRangeHelp: document.getElementById('adminRateRangeHelp'),
    adminRateDay: document.getElementById('adminRateDay'),
    adminRateStart: document.getElementById('adminRateStart'),
    adminRateEnd: document.getElementById('adminRateEnd'),
    adminRateDuration: document.getElementById('adminRateDuration'),
    adminRateName: document.getElementById('adminRateName'),
    adminRatePrice: document.getElementById('adminRatePrice'),
    adminRateMemberPrice: document.getElementById('adminRateMemberPrice'),
    adminRatePriority: document.getElementById('adminRatePriority'),
    adminRateReason: document.getElementById('adminRateReason'),
    adminRateActive: document.getElementById('adminRateActive'),
    adminRateEffectiveFrom: document.getElementById('adminRateEffectiveFrom'),
    adminRateEffectiveTo: document.getElementById('adminRateEffectiveTo'),
    adminRateAudit: document.getElementById('adminRateAudit'),
    adminCourtManagement: document.getElementById('adminCourtManagement'),
    adminAddCourt: document.getElementById('adminAddCourt'),
    adminCourtModal: document.getElementById('adminCourtModal'),
    adminCourtForm: document.getElementById('adminCourtForm'),
    adminCourtModalTitle: document.getElementById('adminCourtModalTitle'),
    adminCourtId: document.getElementById('adminCourtId'),
    adminCourtDisplayNumber: document.getElementById('adminCourtDisplayNumber'),
    adminCourtName: document.getElementById('adminCourtName'),
    adminCourtType: document.getElementById('adminCourtType'),
    adminCourtSurface: document.getElementById('adminCourtSurface'),
    adminCourtActive: document.getElementById('adminCourtActive'),
    adminCourtBlocks: document.getElementById('adminCourtBlocks'),
    adminOverrideLogs: document.getElementById('adminOverrideLogs'),
    adminMembers: document.getElementById('adminMembers'),
    adminUsers: document.getElementById('adminUsers'),
    adminRolePermissions: document.getElementById('adminRolePermissions'),
    adminScheduleGrid: document.getElementById('adminScheduleGrid'),
    adminScheduleDateLabel: document.getElementById('adminScheduleDateLabel'),
    adminScheduleSportFilter: document.getElementById('adminScheduleSportFilter'),
    adminScheduleCalendarOpen: document.getElementById('adminScheduleCalendarOpen'),
    adminScheduleDatePickerModal: document.getElementById('adminScheduleDatePickerModal'),
    adminScheduleCalendarTitle: document.getElementById('adminScheduleCalendarTitle'),
    adminScheduleCalendarGrid: document.getElementById('adminScheduleCalendarGrid'),
    adminScheduleCalendarHelp: document.getElementById('adminScheduleCalendarHelp'),
    adminScheduleCalendarPrev: document.getElementById('adminScheduleCalendarPrev'),
    adminScheduleCalendarNext: document.getElementById('adminScheduleCalendarNext'),
    adminScheduleCalendarToday: document.getElementById('adminScheduleCalendarToday'),
    superAdminRangeOverride: document.getElementById('superAdminRangeOverride'),
    superAdminRangeSport: document.getElementById('superAdminRangeSport'),
    superAdminRangeCourt: document.getElementById('superAdminRangeCourt'),
    superAdminRangeStart: document.getElementById('superAdminRangeStart'),
    superAdminRangeEnd: document.getElementById('superAdminRangeEnd'),
    superAdminRangeOverrideButton: document.getElementById('superAdminRangeOverrideButton'),
    superAdminRangeOverrideHelp: document.getElementById('superAdminRangeOverrideHelp'),
    adminOverrideBookingForm: document.getElementById('adminOverrideBookingForm'),
    adminOverrideBookingMessage: document.getElementById('adminOverrideBookingMessage'),
    adminOverrideBookingId: document.getElementById('adminOverrideBookingId'),
    adminOverrideTimeSlotIds: document.getElementById('adminOverrideTimeSlotIds'),
    adminOverrideDate: document.getElementById('adminOverrideDate'),
    adminOverrideTime: document.getElementById('adminOverrideTime'),
    adminOverrideCourt: document.getElementById('adminOverrideCourt'),
    adminOverrideSport: document.getElementById('adminOverrideSport'),
    adminOverrideCustomer: document.getElementById('adminOverrideCustomer'),
    adminOverrideContext: document.getElementById('adminOverrideContext'),
    adminOverrideBookingModal: document.getElementById('adminOverrideBookingModal'),
    adminCalendarDetailModal: document.getElementById('adminCalendarDetailModal'),
    adminCalendarDetailTitle: document.getElementById('adminCalendarDetailTitle'),
    adminCalendarDetailMeta: document.getElementById('adminCalendarDetailMeta'),
    adminCalendarDetailBody: document.getElementById('adminCalendarDetailBody'),
    adminReferenceSearch: document.getElementById('adminReferenceSearch'),
    adminBookingStartDate: document.getElementById('adminBookingStartDate'),
    adminBookingEndDate: document.getElementById('adminBookingEndDate'),
    adminBookingDateClear: document.getElementById('adminBookingDateClear'),
    adminBookingSort: document.getElementById('adminBookingSort'),
    adminCancelReservationModal: document.getElementById('adminCancelReservationModal'),
    adminCancelReservationForm: document.getElementById('adminCancelReservationForm'),
    adminCancelReservationId: document.getElementById('adminCancelReservationId'),
    adminCancelReservationSummary: document.getElementById('adminCancelReservationSummary'),
    adminCancelReservationMessage: document.getElementById('adminCancelReservationMessage'),
    adminReceiptUploadModal: document.getElementById('adminReceiptUploadModal'),
    adminReceiptUploadForm: document.getElementById('adminReceiptUploadForm'),
    adminReceiptReservationId: document.getElementById('adminReceiptReservationId'),
    adminReceiptUploadSummary: document.getElementById('adminReceiptUploadSummary'),
    adminReceiptUploadMessage: document.getElementById('adminReceiptUploadMessage'),
    adminMemberSearch: document.getElementById('adminMemberSearch'),
    adminAddMember: document.getElementById('adminAddMember'),
    adminMemberModal: document.getElementById('adminMemberModal'),
    adminMemberForm: document.getElementById('adminMemberForm'),
    adminMemberModalTitle: document.getElementById('adminMemberModalTitle'),
    termsConditionsModal: document.getElementById('termsConditionsModal'),
    adminPrivacyPolicyModal: document.getElementById('adminPrivacyPolicyModal'),
    adminMemberQrModal: document.getElementById('adminMemberQrModal'),
    adminMemberQrTitle: document.getElementById('adminMemberQrTitle'),
    adminMemberQrBody: document.getElementById('adminMemberQrBody'),
    adminQrScanModal: document.getElementById('adminQrScanModal'),
    adminQrScanForm: document.getElementById('adminQrScanForm'),
    adminQrPayload: document.getElementById('adminQrPayload'),
    adminQrScanMessage: document.getElementById('adminQrScanMessage'),
    adminQrVideo: document.getElementById('adminQrVideo'),
    adminStartQrCamera: document.getElementById('adminStartQrCamera'),
    adminEntranceFeeModal: document.getElementById('adminEntranceFeeModal'),
    adminEntranceFeeForm: document.getElementById('adminEntranceFeeForm'),
    adminEntranceMemberId: document.getElementById('adminEntranceMemberId'),
    adminEntranceMemberSummary: document.getElementById('adminEntranceMemberSummary'),
    adminEntranceFeeMessage: document.getElementById('adminEntranceFeeMessage')
};

function isoDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function niceDate(iso) {
    return new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric'
    });
}

function bookingMaxDateIso() {
    const raw = String(state?.siteConfig?.booking_max_date || '').trim();
    return /^\d{4}-\d{2}-\d{2}$/.test(raw) ? raw : '';
}

function compactTime(label) {
    return label.replace(/\b0(\d:)/g, '$1');
}

function compactTimeHeader(label) {
    const slot = state?.slotDetails?.[label];
    if (!slot?.startsAt || !slot?.endsAt) return compactTime(label).replace(/\s+/g, '');
    const token = time => {
        const [hour] = String(time).split(':').map(Number);
        if (hour === 0) {
            return { displayHour: 12, suffix: 'MN' };
        }
        const suffix = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return { displayHour, suffix };
    };
    const start = token(slot.startsAt);
    const end = token(slot.endsAt);
    if (start.suffix === end.suffix) {
        return `${start.displayHour}-${end.displayHour}${end.suffix}`;
    }
    return `${start.displayHour}${start.suffix}-${end.displayHour}${end.suffix}`;
}

async function loadState() {
    try {
        const response = await fetch(`${api}?action=state`);
        const payload = await response.json();
        if (!payload.ok) {
            showLoadError(payload.message || 'Could not load booking data.');
            return;
        }
        state = payload.state;
        renderAll();
    } catch (error) {
        console.error('Could not load application state.', error);
        showLoadError('Could not load the latest data. Please refresh the page.');
    }
}

function showLoadError(message) {
    const target = els.grid || els.admin || els.adminMembers;
    if (target) {
        target.innerHTML = `<div class="rounded-lg border border-rose-200 bg-rose-50 p-5 text-sm font-bold text-rose-700">${message}</div>`;
    }
}

function showAdminToast(message = 'Saved successfully.', ok = true) {
    if (!document.body.classList.contains('admin-body')) return;
    let container = document.getElementById('adminToastStack');
    if (!container) {
        container = document.createElement('div');
        container.id = 'adminToastStack';
        container.className = 'admin-toast-stack';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `admin-toast ${ok ? 'admin-toast-success' : 'admin-toast-error'}`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `
        <i data-lucide="${ok ? 'check-circle-2' : 'circle-alert'}" class="icon-sm"></i>
        <span>${escapeHtml(message)}</span>
    `;
    container.appendChild(toast);
    if (window.lucide) lucide.createIcons();

    window.setTimeout(() => {
        toast.classList.add('is-hiding');
        window.setTimeout(() => toast.remove(), 220);
    }, 3200);
}

function showAdminServerFlashToast() {
    if (!document.body.classList.contains('admin-body')) return;
    const flash = document.querySelector('.app-main .alert.alert-primary, .admin-content .alert.alert-primary');
    const message = String(flash?.textContent || '').trim();
    if (message) showAdminToast(message);
}

function normalizePhoneValue(value) {
    let phone = String(value || '').trim().replace(/[\s().-]+/g, '');
    if (phone.startsWith('+63')) {
        phone = `0${phone.slice(3)}`;
    } else if (phone.startsWith('63') && phone.length === 12) {
        phone = `0${phone.slice(2)}`;
    }
    return phone;
}

function isValidPhoneValue(value) {
    return /^09\d{9}$/.test(normalizePhoneValue(value));
}

function isValidContactPhoneValue(value) {
    const normalized = normalizePhoneValue(value);
    const raw = String(value || '').trim().replace(/[^\d+]/g, '');
    return normalized !== '' && (
        /^09\d{9}$/.test(normalized)
        || /^0\d{7,10}$/.test(normalized)
        || /^\+63\d{8,11}$/.test(raw)
        || /^\d{7,8}$/.test(normalized)
    );
}

function phoneValidationMessage() {
    return 'Use a valid Philippine mobile number, e.g. 0917 123 4567 or +63 917 123 4567.';
}

function contactPhoneValidationMessage() {
    return 'Use a valid mobile or landline number, e.g. 0917 123 4567, (02) 8123 4567, or +63 2 8123 4567.';
}

function phoneInputs(root = document) {
    return [...root.querySelectorAll('input')].filter(input => {
        const name = String(input.name || '').toLowerCase();
        const id = String(input.id || '').toLowerCase();
        return name.includes('phone') || id.includes('phone');
    });
}

function validatePhoneInput(input) {
    const value = String(input.value || '').trim();
    if (value === '') {
        input.setCustomValidity(input.required ? 'Phone is required.' : '');
        return !input.required;
    }
    const contactMode = input.dataset.phoneMode === 'contact';
    const valid = contactMode ? isValidContactPhoneValue(value) : isValidPhoneValue(value);
    input.setCustomValidity(valid ? '' : (contactMode ? contactPhoneValidationMessage() : phoneValidationMessage()));
    return valid;
}

function enhancePhoneInputs(root = document) {
    phoneInputs(root).forEach(input => {
        input.type = 'tel';
        input.inputMode = 'tel';
        const contactMode = input.dataset.phoneMode === 'contact';
        input.placeholder = input.placeholder || (contactMode ? '0917 123 4567 or (02) 8123 4567' : '09xx xxx xxxx');
        input.title = contactMode ? contactPhoneValidationMessage() : phoneValidationMessage();
        input.dataset.phoneValidation = '1';
        validatePhoneInput(input);
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function resourceUrl(path) {
    const value = String(path || '').trim();

    if (!value) {
        return '';
    }

    // Already an absolute URL or data URL
    if (
        /^(https?:)?\/\//.test(value) ||
        value.startsWith('data:')
    ) {
        return value;
    }

    const cleanRoot = String(rootUrl || '').replace(/\/+$/, '');
    const cleanPath = value.replace(/^\/+/, '');

    return `${cleanRoot}/${cleanPath}`;
}

function isActiveReservation(status) {
    return ['Held', 'Booked'].includes(status);
}

function statusTone(status) {
    if (status === 'Booked') return 'booked';
    if (status === 'Cancelled') return 'cancelled';
    if (status === 'Blocked') return 'blocked';
    return 'pending';
}

function compactStatusLabel(status) {
    return {
        Available: 'Available',
        Booked: 'Booked',
        Held: 'Held',
        Cancelled: 'Cancelled',
        Blocked: 'Blocked'
    }[status] || status;
}

function publicSlotLabel(status, booking = null) {
    if (status === 'Booked' || status === 'Held') {
        const nickname = String(booking?.playerNickname || '').trim();
        return nickname ? `${status} - ${nickname}` : status;
    }
    return compactStatusLabel(status);
}

function renderAll() {
    [
        ['Admin permissions', renderAdminPermissionsUI],
        ['Rates', renderRates],
        ['Booking grid', renderBookingGrid],
        ['Admin bookings', renderAdmin],
        ['Admin schedule', renderAdminSchedule],
        ['Super Admin range override', renderSuperAdminRangeOverride],
        ['Admin override form', renderAdminOverrideBookingForm],
        ['Payment options', renderPaymentOptions],
        ['Payment page', renderPaymentPage],
        ['Admin payment channels', renderAdminPaymentChannels],
        ['Admin rate summary', renderAdminRateSummary],
        ['Admin rate audit', renderAdminRateAudit],
        ['Admin courts', renderAdminCourts],
        ['Admin court blocks', renderAdminCourtBlocks],
        ['Admin override logs', renderAdminOverrideLogs],
        ['Admin members', renderAdminMembers],
        ['Admin users', renderAdminUsers],
        ['Admin role permissions', renderAdminRolePermissions],
        ['Phone validation', enhancePhoneInputs],
    ].forEach(([label, renderer]) => safeRender(label, renderer));
    if (window.lucide) lucide.createIcons();
}

function safeRender(label, renderer) {
    try {
        renderer();
    } catch (error) {
        console.error(`${label} render failed.`, error);
        if (label === 'Admin members' && els.adminMembers) {
            els.adminMembers.innerHTML = '<div class="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm font-bold text-rose-700">Could not display members. Please refresh the page.</div>';
        }
    }
}

function renderAdminPermissionsUI() {
    const canManage = adminCanManageOperations();
    const canManageMembers = adminCanManageMembers();
    document.querySelectorAll('[data-admin-requires-manage]').forEach(element => {
        element.hidden = !canManage;
    });
    if (els.adminAddMember) els.adminAddMember.hidden = !canManageMembers;
    if (els.adminAddCourt) els.adminAddCourt.hidden = !canManage;
    const scanButton = document.getElementById('adminScanMemberQr');
    if (scanButton) scanButton.hidden = !canManageMembers;
    if (els.adminOverrideBookingForm) {
        els.adminOverrideBookingForm.querySelectorAll('input, select, textarea, button').forEach(field => {
            field.disabled = !canManage;
        });
    }
}

function defaultRateRule() {
    const firstCourtInfo = state?.courts?.[0] || null;
    const firstSlot = Object.values(state?.slotDetails || {})[0]?.id || '';
    return {
        id: '',
        name: '',
        courtId: 'all',
        sport: firstCourtInfo?.sports?.[0] || 'Pickleball',
        dayOfWeek: 'Any',
        timeSlotId: firstSlot,
        pricePerHour: 400,
        memberPricePerHour: '',
        isActive: true,
        changeReason: 'Regular rate'
    };
}

function rateReasonList(selected = 'Regular rate') {
    const reasons = [
        'Regular rate',
        'Special events',
        'Promotions',
        'VIP customers',
        'Corporate bookings',
        'Private arrangements',
        'Negotiated rates',
        'Temporary discounts',
        'Complimentary bookings',
        'Emergency pricing adjustments',
        'Special club activities'
    ];
    const current = selected || 'Regular rate';
    return reasons.includes(current) ? reasons : [current, ...reasons];
}

function setSelectOptions(select, options, selected) {
    if (!select) return;
    select.innerHTML = options.map(option => {
        const value = Array.isArray(option) ? option[0] : option;
        const label = Array.isArray(option) ? option[1] : option;
        return `<option value="${escapeHtml(value)}" ${String(value) === String(selected ?? '') ? 'selected' : ''}>${escapeHtml(label)}</option>`;
    }).join('');
}

function sortedRateSlots() {
    return Object.values(state?.slotDetails || {}).sort((a, b) => timeToMinutes(a.startsAt) - timeToMinutes(b.startsAt));
}

function sortedCourtBlockSlots() {
    return Object.values(state?.slotDetails || {}).sort((a, b) => timeToMinutes(a.startsAt) - timeToMinutes(b.startsAt));
}

function populateAdminRateRangeOptions(rule) {
    const slots = sortedRateSlots();
    setSelectOptions(els.adminRateRangeStart, slots.map(slot => [
        slot.startsAt,
        formatRuleTime(slot.startsAt)
    ]), rule.startsAt || slots[0]?.startsAt || '');
    setSelectOptions(els.adminRateRangeEnd, slots.map(slot => [
        slot.endsAt,
        formatRuleTime(slot.endsAt)
    ]), rule.endsAt || slots[slots.length - 1]?.endsAt || '');
}

function setAdminRateMode(mode, editing = false) {
    const isRange = mode === 'range' && !editing;
    if (els.adminRateMode) {
        els.adminRateMode.value = isRange ? 'range' : 'single';
        els.adminRateMode.disabled = editing;
    }
    if (els.adminRateTimeSlotWrap) els.adminRateTimeSlotWrap.hidden = isRange;
    if (els.adminRateRangeWrap) els.adminRateRangeWrap.hidden = !isRange;
    if (els.adminRateRangeHelp) els.adminRateRangeHelp.hidden = !isRange;
    if (els.adminRateTimeSlot) {
        els.adminRateTimeSlot.disabled = isRange;
        els.adminRateTimeSlot.required = !isRange;
    }
    [els.adminRateRangeStart, els.adminRateRangeEnd].forEach(select => {
        if (!select) return;
        select.disabled = !isRange;
        select.required = isRange;
    });
}

function populateAdminRateOptions(rule) {
    setSelectOptions(els.adminRateCourt, [
        ['all', 'All courts'],
        ...(state?.courts || []).map(court => [court.id, court.name])
    ], rule.courtId ?? '');
    setSelectOptions(els.adminRateSport, [
        ['Pickleball', 'Pickleball'],
        ['Basketball', 'Basketball'],
        ['Volleyball', 'Volleyball']
    ], rule.sport ?? '');
    setSelectOptions(els.adminRateDayOfWeek, [
        ['Any', 'Any day'],
        ['Weekday', 'Weekday'],
        ['Weekend', 'Weekend'],
        ['Monday', 'Monday'],
        ['Tuesday', 'Tuesday'],
        ['Wednesday', 'Wednesday'],
        ['Thursday', 'Thursday'],
        ['Friday', 'Friday'],
        ['Saturday', 'Saturday'],
        ['Sunday', 'Sunday']
    ], rule.dayOfWeek || rule.dayPattern || 'Any');
    setSelectOptions(els.adminRateTimeSlot, Object.values(state?.slotDetails || {}).map(slot => [
        slot.id,
        compactTime(slot.label)
    ]), rule.timeSlotId ?? '');
    populateAdminRateRangeOptions(rule);
    if (els.adminRateReason) els.adminRateReason.value = rule.changeReason || 'Regular rate';
}

function currentRateRuleName() {
    const court = els.adminRateCourt?.selectedOptions?.[0]?.textContent || 'All courts';
    const sport = els.adminRateSport?.value || 'All sports';
    const day = els.adminRateDayOfWeek?.selectedOptions?.[0]?.textContent || 'Any day';
    if (els.adminRateMode?.value === 'range') {
        const start = els.adminRateRangeStart?.selectedOptions?.[0]?.textContent || '';
        const end = els.adminRateRangeEnd?.selectedOptions?.[0]?.textContent || '';
        return `${court} ${sport} ${day} ${start}-${end}`.trim();
    }
    const slot = els.adminRateTimeSlot?.selectedOptions?.[0]?.textContent || '';
    return `${court} ${sport} ${day} ${slot}`.trim();
}

function simpleRateDay(value) {
    const day = String(value || '');
    if (day === 'Monday-Friday') return 'Monday - Friday';
    if (day === 'Saturday-Sunday') return 'Saturday - Sunday';
    if (day === 'Any') return 'All days';
    return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].includes(day) ? day : 'All days';
}

function rateDaySortValue(value) {
    return ['Any', 'Weekday', 'Weekend', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].indexOf(value || 'Any');
}

function expandedRateDays(selection) {
    if (selection === 'Monday-Friday') return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    if (selection === 'Saturday-Sunday') return ['Saturday', 'Sunday'];
    return [selection || 'Monday'];
}

function matchingRateRuleId(formData, day, fallbackId = '') {
    const courtId = String(formData.get('courtId') || '');
    const sport = String(formData.get('sport') || '');
    const dayOfWeek = String(formData.get('dayOfWeek') || 'Any');
    const timeSlotId = String(formData.get('timeSlotId') || '');
    const match = (state?.adminRateRules || []).find(rule =>
        String(rule.courtId ?? '') === courtId &&
        String(rule.sport ?? '') === sport &&
        String(rule.dayOfWeek || rule.dayPattern || 'Any') === dayOfWeek &&
        String(rule.timeSlotId || '') === timeSlotId
    );

    return match?.id || fallbackId || '';
}

function openAdminRateModal(ruleId = '') {
    if (!els.adminRateForm || !state) return;
    const existing = ruleId ? (state.adminRateRules || []).find(rule => String(rule.id) === String(ruleId)) : null;
    const rule = { ...defaultRateRule(), ...(existing || {}) };

    populateAdminRateOptions(rule);
    if (els.adminRateModalTitle) els.adminRateModalTitle.textContent = existing ? 'Edit Rate' : 'Add Rate';
    setAdminRateMode(existing ? 'single' : (els.adminRateMode?.value || 'single'), Boolean(existing));
    if (els.adminRateId) els.adminRateId.value = rule.id || '';
    if (els.adminRateDayOfWeek) els.adminRateDayOfWeek.value = rule.dayOfWeek || rule.dayPattern || 'Any';
    if (els.adminRateName) els.adminRateName.value = rule.name || '';
    if (els.adminRateStart) els.adminRateStart.value = rule.startsAt || '08:00';
    if (els.adminRateEnd) els.adminRateEnd.value = rule.endsAt || '17:00';
    if (els.adminRateDuration) els.adminRateDuration.value = '60';
    if (els.adminRateTimeSlot) els.adminRateTimeSlot.value = rule.timeSlotId || '';
    if (els.adminRatePrice) els.adminRatePrice.value = rule.pricePerHour || '';
    if (els.adminRateMemberPrice) els.adminRateMemberPrice.value = rule.memberPricePerHour || '';
    if (els.adminRatePriority) els.adminRatePriority.value = rule.priority || 0;
    if (els.adminRateActive) els.adminRateActive.checked = Boolean(rule.isActive);
    if (els.adminRateEffectiveFrom) els.adminRateEffectiveFrom.value = rule.effectiveFrom || '';
    if (els.adminRateEffectiveTo) els.adminRateEffectiveTo.value = rule.effectiveTo || '';

    const message = els.adminRateForm.querySelector('[data-rate-rule-message]');
    if (message) {
        message.textContent = '';
        message.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }

    if (window.bootstrap && els.adminRateModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminRateModal).show();
    }
}

function bindAdminRateForm() {
    if (els.adminAddRate && !els.adminAddRate.dataset.bound) {
        els.adminAddRate.addEventListener('click', () => openAdminRateModal());
        els.adminAddRate.dataset.bound = '1';
    }
    if (els.adminRateForm && !els.adminRateForm.dataset.bound) {
        els.adminRateForm.addEventListener('submit', submitRateRule);
        els.adminRateForm.dataset.bound = '1';
    }
    if (els.adminRateMode && !els.adminRateMode.dataset.bound) {
        els.adminRateMode.addEventListener('change', event => setAdminRateMode(event.target.value, Boolean(els.adminRateId?.value)));
        els.adminRateMode.dataset.bound = '1';
    }
}

function formatPaymentCountdown(ms) {
    const totalSeconds = Math.max(0, Math.ceil(ms / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function stopBookingPaymentTimer() {
    if (bookingPaymentTimerId) {
        clearInterval(bookingPaymentTimerId);
        bookingPaymentTimerId = null;
    }
    bookingPaymentDeadline = 0;
    bookingPaymentMethod = '';
}

function bookingPaymentTimerMarkup() {
    const remaining = bookingPaymentDeadline ? bookingPaymentDeadline - Date.now() : bookingPaymentWindowMs;
    const expired = remaining <= 0;
    const proofAction = state?.member ? 'upload your receipt' : 'send your payment proof to our team';
    return `
        <div id="bookingPaymentTimer" class="metro-payment-timer ${expired ? 'is-expired' : ''}">
            <div>
                <span class="metro-payment-timer-label">${expired ? 'Payment time expired' : 'Payment window'}</span>
                <strong id="bookingPaymentCountdown">${formatPaymentCountdown(remaining)}</strong>
            </div>
            <p id="bookingPaymentTimerNote">${expired
                ? 'Select a payment channel again to restart the 15-minute payment window before confirming.'
                : `Please pay and ${proofAction} within 15 minutes to secure the selected slot.`}</p>
        </div>
    `;
}

function updateBookingPaymentTimerDisplay() {
    if (!bookingPaymentDeadline) return;
    const remaining = bookingPaymentDeadline - Date.now();
    const expired = remaining <= 0;
    const timer = document.getElementById('bookingPaymentTimer');
    const countdown = document.getElementById('bookingPaymentCountdown');
    const note = document.getElementById('bookingPaymentTimerNote');
    const label = timer?.querySelector('.metro-payment-timer-label');

    timer?.classList.toggle('is-expired', expired);
    if (countdown) countdown.textContent = formatPaymentCountdown(remaining);
    if (label) label.textContent = expired ? 'Payment time expired' : 'Payment window';
    if (note) {
        const proofAction = state?.member ? 'upload your receipt' : 'send your payment proof to our team';
        note.textContent = expired
            ? 'Select a payment channel again to restart the 15-minute payment window before confirming.'
            : `Please pay and ${proofAction} within 15 minutes to secure the selected slot.`;
    }
    if (els.modalSubmitButton && els.modalSubmitButton.dataset.done !== '1') {
        els.modalSubmitButton.disabled = expired || (isInlineBookingForm() && selectedBookingSlots.length === 0);
    }
}

function startBookingPaymentTimer(method) {
    if (!method) {
        stopBookingPaymentTimer();
        renderPaymentInstructions('');
        return;
    }

    if (bookingPaymentMethod !== method || !bookingPaymentDeadline || bookingPaymentDeadline <= Date.now()) {
        bookingPaymentMethod = method;
        bookingPaymentDeadline = Date.now() + bookingPaymentWindowMs;
    }
    renderPaymentInstructions(method);
    updateBookingPaymentTimerDisplay();
    if (bookingPaymentTimerId) clearInterval(bookingPaymentTimerId);
    bookingPaymentTimerId = setInterval(updateBookingPaymentTimerDisplay, 1000);
}

function bookingPaymentWindowExpired() {
    return Boolean(bookingPaymentDeadline && bookingPaymentDeadline <= Date.now());
}

function renderPaymentInstructions(method) {
    if (!els.paymentInstructions) return;
    const details = (state?.paymentChannels || []).find(channel => channel.code === method);

    if (!details) {
        els.paymentInstructions.classList.add('hidden');
        els.paymentInstructions.innerHTML = '';
        return;
    }

    els.paymentInstructions.classList.remove('hidden');
    const messengerUrl = String(state?.siteConfig?.messenger_url || '').trim();
    const messengerText = messengerUrl
        ? `<a href="${escapeHtml(messengerUrl)}" target="_blank" rel="noopener" class="text-primary">Facebook Messenger</a>`
        : 'Facebook Messenger';
    const proofNote = state?.member
        ? 'Registered members can upload payment proof directly on the website. Admin confirms payment after review.'
        : `Create or sign in to a player account before booking. If staff requests supporting proof, send it through ${messengerText} with your reservation reference.`;
    const qrImage = details.qrPath
        ? `<img src="${escapeHtml(resourceUrl(details.qrPath))}" alt="${escapeHtml(details.name)} payment QR code" class="metro-payment-qr-image" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"><div class="hidden metro-payment-qr-placeholder">QR image not found</div>`
        : '<div class="metro-payment-qr-placeholder">No QR uploaded</div>';
    const downloadLink = details.qrPath
        ? `<a href="${escapeHtml(resourceUrl(details.qrPath))}" download class="metro-payment-download">Download QR</a>`
        : '';

    if (details.type === 'qr') {
        els.paymentInstructions.innerHTML = `
            ${bookingPaymentTimerMarkup()}
            <div class="metro-payment-display">
                <div class="metro-payment-qr-block">
                    ${qrImage}
                    ${downloadLink}
                </div>
                <div class="metro-payment-detail-block">
                    <p class="metro-payment-channel-label">${escapeHtml(details.name)} QR</p>
                    <h4>Send payment via ${escapeHtml(details.name)}</h4>
                    <dl>
                        <div><dt>Account name</dt><dd>${escapeHtml(details.accountName || '-')}</dd></div>
                        <div><dt>Account no.</dt><dd>${escapeHtml(details.accountNumber || '-')}</dd></div>
                    </dl>
                    <p class="metro-payment-instruction-text">${escapeHtml(details.instructions || 'Complete the transfer, then upload your receipt.')}</p>
                    <p class="metro-payment-proof-note">${proofNote}</p>
                </div>
            </div>
        `;
        return;
    }

    els.paymentInstructions.innerHTML = `
        ${bookingPaymentTimerMarkup()}
        <div class="metro-payment-display">
            <div class="metro-payment-qr-block">
                ${qrImage}
                ${downloadLink}
            </div>
            <div class="metro-payment-detail-block">
                <p class="metro-payment-channel-label">${escapeHtml(details.bankName || details.name)}</p>
                <h4>${escapeHtml(details.name)} transfer</h4>
                <dl>
                    <div><dt>Bank information</dt><dd>${escapeHtml(details.bankName || details.name)}</dd></div>
                    <div><dt>Account name</dt><dd>${escapeHtml(details.accountName || '-')}</dd></div>
                    <div><dt>Account no.</dt><dd>${escapeHtml(details.accountNumber || '-')}</dd></div>
                </dl>
                <p class="metro-payment-instruction-text">${escapeHtml(details.instructions || 'Upload the deposit or transfer receipt before submitting.')}</p>
                <p class="metro-payment-proof-note">${proofNote}</p>
            </div>
        </div>
    `;
}

function renderPaymentOptions() {
    if (!els.paymentMethod || !state) return;
    els.paymentMethod.innerHTML = '<option value="">Select payment channel</option>' + state.paymentChannels.map(channel => (
        `<option value="${channel.code}">${channel.name}</option>`
    )).join('');
}

function paymentChannelCard(channel) {
    const qrPanel = channel.qrPath
        ? `<div class="metro-payment-qr-block"><img src="${escapeHtml(resourceUrl(channel.qrPath))}" alt="${escapeHtml(channel.name)} payment QR code" class="metro-payment-qr-image" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"><div class="hidden metro-payment-qr-placeholder">QR image not found</div><a href="${escapeHtml(resourceUrl(channel.qrPath))}" download class="metro-payment-download">Download QR</a></div>`
        : '<div class="metro-payment-qr-block"><div class="metro-payment-qr-placeholder">No QR uploaded</div></div>';

    if (channel.type === 'qr') {
        return `
            <article class="public-card p-4">
                <p class="text-xs font-black uppercase tracking-[.12em] text-muted">QR / Wallet</p>
                <h2 class="mt-2 text-2xl font-black">${escapeHtml(channel.name)}</h2>
                <div class="metro-payment-display mt-4">
                    ${qrPanel}
                    <div class="metro-payment-detail-block">
                        <dl>
                            <div><dt>Account name</dt><dd>${escapeHtml(channel.accountName || '-')}</dd></div>
                            <div><dt>Account no.</dt><dd>${escapeHtml(channel.accountNumber || '-')}</dd></div>
                        </dl>
                        <p class="metro-payment-instruction-text">${escapeHtml(channel.instructions || 'Scan to pay, then upload the receipt in the booking form.')}</p>
                    </div>
                </div>
            </article>
        `;
    }

    return `
        <article class="public-card p-4">
            <p class="text-xs font-black uppercase tracking-[.12em] text-muted">Bank</p>
            <h2 class="mt-2 text-2xl font-black">${escapeHtml(channel.name)}</h2>
            <div class="metro-payment-display mt-4">
                ${qrPanel}
                <div class="metro-payment-detail-block">
                    <dl>
                        <div><dt>Bank information</dt><dd>${escapeHtml(channel.bankName || channel.name)}</dd></div>
                        <div><dt>Account name</dt><dd>${escapeHtml(channel.accountName || '-')}</dd></div>
                        <div><dt>Account no.</dt><dd>${escapeHtml(channel.accountNumber || '-')}</dd></div>
                    </dl>
                    <p class="metro-payment-instruction-text">${escapeHtml(channel.instructions || 'Upload a deposit or transfer receipt for admin review.')}</p>
                </div>
            </div>
        </article>
    `;
}

function renderPaymentPage() {
    if (!els.paymentPageChannels || !state) return;
    els.paymentPageChannels.innerHTML = state.paymentChannels.map(paymentChannelCard).join('');
}

function renderRates() {
    if (!els.rates || !state) return;
    const date = isoDate(selectedDate);
    const rates = bookingRateCardsForDate(date);
    els.rates.innerHTML = rates.map(rate => `
        <div class="metro-rate-card">
            <strong>${peso.format(Number(rate.price))}<span>/hr</span></strong>
            <small>${escapeHtml(rate.time)}</small>
        </div>
    `).join('') || '<div class="metro-rate-card"><strong>Rates<span>/hr</span></strong><small>Select a date</small></div>';
}

function bookingRateCardsForDate(date) {
    const rules = (state?.rateRules || [])
        .filter(rule =>
            rule.sport === selectedSport &&
            dayPatternMatches(rule.dayPattern || rule.dayOfWeek || 'Any', date)
        );

    if (rules.length === 0) {
        return state.rates || [];
    }

    const uniqueSegments = new Map();
    rules.forEach(rule => {
        const price = Number(rule.pricePerHour || 0);
        if (!price) return;
        const start = rule.startsAt || '';
        const end = rule.endsAt || '';
        if (!start || !end) return;
        const key = `${price}|${start}|${end}`;
        if (!uniqueSegments.has(key)) {
            uniqueSegments.set(key, {
                price,
                start,
                end,
                sort: timeToMinutes(start || '00:00')
            });
        }
    });

    const merged = [];
    [...uniqueSegments.values()]
        .sort((a, b) => a.sort - b.sort || timeToMinutes(a.end) - timeToMinutes(b.end) || a.price - b.price)
        .forEach(segment => {
            const previous = [...merged].reverse().find(item =>
                item.price === segment.price &&
                timeToMinutes(item.end) === timeToMinutes(segment.start)
            );
            if (previous && previous.price === segment.price && timeToMinutes(previous.end) === timeToMinutes(segment.start)) {
                previous.end = segment.end;
                return;
            }
            merged.push({ ...segment });
        });

    return merged
        .sort((a, b) => timeToMinutes(a.start) - timeToMinutes(b.start) || a.price - b.price)
        .map(group => ({
            price: group.price,
            time: `${formatRuleTime(group.start)} - ${formatRuleTime(group.end)}`
        }));
}

function bookingFor(date, time, court) {
    return Object.values(state.bookings).find(item => item.date === date && item.time === time && Number(item.court) === Number(court));
}

function courtDisplayName(courtInfo) {
    return courtInfo.labels?.[selectedSport] || courtInfo.name;
}

function courtsForSelectedSport() {
    if (!state?.courts) return [];
    return state.courts.filter(court => {
        if (!court.sports.includes(selectedSport)) return false;
        return !(selectedSport === 'Pickleball' && Number(court.id) === 2);
    });
}

function relatedConflictFor(date, time, courtInfo) {
    const court = Number(courtInfo.id);
    const direct = bookingFor(date, time, court);
    if (direct) {
        return {
            id: direct.id,
            status: direct.status,
            sport: direct.sport,
            playerNickname: direct.playerNickname || '',
            shortLabel: compactStatusLabel(direct.status),
            message: `${courtDisplayName(courtInfo)} is already ${direct.status} for ${direct.sport} during ${time}.`
        };
    }

    const block = blockConflictFor(date, time, court, selectedSport);
    if (block) return block;

    return null;
}

function courtBlockApplies(blockCourtId, blockSport, courtId, sport) {
    const blockCourt = blockCourtId === null || blockCourtId === undefined || blockCourtId === '' ? null : Number(blockCourtId);
    const court = Number(courtId);

    if (blockCourt === null) return true;

    if (blockCourt === court) {
        return !blockSport || blockSport === sport || [1, 2].includes(court);
    }

    return false;
}

function blockConflictFor(date, time, courtId, sport) {
    const block = (state?.courtBlocks || []).find(item =>
        item.status === 'Active'
        && item.date === date
        && item.time === time
        && courtBlockApplies(item.courtId, item.sport, courtId, sport)
    );
    if (!block) return null;

    const scope = block.sport ? `${block.courtName} ${block.sport}` : block.courtName;
    return {
        status: 'Blocked',
        sport: block.sport || sport,
        shortLabel: 'Blocked',
        message: `${scope} is blocked for ${block.reason}${block.notes ? `: ${block.notes}` : ''}.`
    };
}

function bookingsAt(date, time) {
    return Object.values(state?.bookings || {}).filter(item => item.date === date && item.time === time && isActiveReservation(item.status));
}

function directBookingAt(date, time, courtId) {
    return bookingsAt(date, time).find(item => Number(item.court) === Number(courtId));
}

function adminScheduleColumns() {
    return (state?.courts || []).flatMap(court => {
        const sports = court.sports || [];
        return sports.map(sport => ({
            key: `${court.id}-${sport}`,
            label: (court.labels?.[sport] || court.name || `Court ${court.id}`).toUpperCase(),
            court: court.id,
            sport,
            openLabel: sport === 'Pickleball' ? 'OPEN' : 'AVAILABLE'
        }));
    }).filter(column => !adminScheduleSportFilter || column.sport === adminScheduleSportFilter);
}

function adminScheduleDateText(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric'
    });
}

function adminTimeStart(label) {
    const start = String(label).split(' - ')[0] || label;
    return start.replace(/^0/, '').replace(':00 ', ' ');
}

function adminBookingLabel(booking, pickleballLabel = 'BOOKED') {
    if (booking.sport === 'Basketball' || booking.sport === 'Volleyball') {
        return booking.sport.toUpperCase();
    }
    if (booking.status === 'Booked') return pickleballLabel;
    return compactStatusLabel(booking.status).toUpperCase();
}

function adminCanManageOperations() {
    return Boolean(state?.currentAdmin?.canManageOperations);
}

function adminCanManageMembers() {
    return Boolean(state?.currentAdmin?.canManageMembers);
}

function adminCanManageStaff() {
    return Boolean(state?.currentAdmin?.canManageStaff);
}

function adminIsSuperAdmin() {
    return state?.currentAdmin?.role === 'super_admin';
}

function reservationById(id) {
    return (state?.adminGroupedReservations || []).find(item => item.id === id)
        || (state?.adminReservations || []).find(item => item.id === id)
        || Object.values(state?.bookings || {}).find(item => item.id === id)
        || null;
}

function bookingBySlot(date, time, courtId, sport = '') {
    return Object.values(state?.bookings || {}).find(item =>
        item.date === date &&
        item.time === time &&
        Number(item.court) === Number(courtId) &&
        (!sport || item.sport === sport)
    ) || null;
}

function qrImageUrl(payload) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(payload)}`;
}

function memberSearchText(member) {
    return [member.name, member.nickname, member.phone, member.email, member.lookupToken]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
}

function scheduleCell(label, status = 'Available', sub = '', title = '', meta = {}) {
    return { label, status, sub, title: title || sub || label, ...meta };
}

function adminColumnSelection(column, cell) {
    return { courtId: Number(column.court), sport: column.sport || 'Pickleball' };
}

function courtById(courtId) {
    return (state?.courts || []).find(court => Number(court.id) === Number(courtId));
}

function adminCourtOptionLabel(court) {
    const labels = [];
    if (court.sports.includes('Basketball') || court.sports.includes('Volleyball')) labels.push(court.name);
    if (court.sports.includes('Pickleball')) labels.push(court.labels?.Pickleball || court.name);
    return [...new Set(labels)].join(' / ');
}

function blockCell(date, time, courtId, sport) {
    const block = (state?.courtBlocks || []).find(item =>
        item.status === 'Active'
        && item.date === date
        && item.time === time
        && courtBlockApplies(item.courtId, item.sport, courtId, sport)
    );
    if (!block) return null;
    const scope = block.sport ? `${block.courtName} ${block.sport}` : block.courtName;
    const message = `${scope} is blocked for ${block.reason}${block.notes ? `: ${block.notes}` : ''}.`;
    return scheduleCell('BLOCKED', 'Blocked', 'Court Blocking', message, { blockId: block.id });
}

function adminBookingCustomerDisplay(booking) {
    return String(
        booking.playerNickname
        || booking.memberName
        || booking.customerName
        || booking.customerPhone
        || 'Customer'
    ).trim();
}

function adminScheduleCell(date, time, column) {
    const direct = directBookingAt(date, time, column.court);
    if (direct) {
        const customerName = adminBookingCustomerDisplay(direct);
        const label = compactStatusLabel(direct.status).toUpperCase();
        const status = direct.status;
        const sub = customerName;
        const title = `${column.label}: ${direct.sport} ${direct.status} - ${customerName}`;
        return scheduleCell(label, status, sub, title, { reservationId: direct.id });
    }

    const block = blockCell(date, time, column.court, column.sport);
    if (block) return block;

    return scheduleCell(column.openLabel || 'AVAILABLE', 'Available', '', `${column.label} is available`);
}

function adminScheduleCellClass(status) {
    const tone = statusTone(status);
    if (tone === 'booked') return 'admin-slot-booked';
    if (tone === 'pending') return 'admin-slot-held';
    if (tone === 'blocked' || tone === 'cancelled') return 'admin-slot-blocked';
    return 'admin-slot-available';
}

function renderAdminSchedule() {
    if (!els.adminScheduleGrid || !state) return;
    const date = isoDate(adminScheduleDate);
    const columns = adminScheduleColumns();
    const slots = Object.values(state.timeSlots || {}).flat();
    if (els.adminScheduleDateLabel) els.adminScheduleDateLabel.textContent = adminScheduleDateText(date);
    renderAdminScheduleCalendar();
    if (columns.length === 0) {
        els.adminScheduleGrid.style.gridTemplateColumns = '1fr';
        els.adminScheduleGrid.innerHTML = `<div class="p-4 text-sm fw-bold text-secondary">No active courts found${adminScheduleSportFilter ? ` for ${escapeHtml(adminScheduleSportFilter)}` : ''}.</div>`;
        return;
    }
    els.adminScheduleGrid.style.gridTemplateColumns = `minmax(92px, 120px) repeat(${columns.length}, minmax(90px, 1fr))`;

    const header = ['TIME', ...columns.map(column => column.label)].map((label, index) => `
        <div class="admin-schedule-head ${index === 0 ? 'admin-schedule-head-time' : ''}">${label}</div>
    `).join('');

    const rows = slots.map(time => {
        const timeCell = `<div class="admin-schedule-time">${compactTimeHeader(time)}</div>`;
        const cells = columns.map(column => {
            const cell = adminScheduleCell(date, time, column);
            const selection = adminColumnSelection(column, cell);
            const slot = state.slotDetails?.[time] || {};
            const isPastAvailable = cell.status === 'Available' && slotIsPast(date, time);
            const cellStatus = isPastAvailable ? 'Blocked' : cell.status;
            const cellLabel = isPastAvailable ? '' : cell.label;
            const cellSub = isPastAvailable ? '' : cell.sub;
            const cellTitle = isPastAvailable ? 'Past dates and time slots cannot be overridden.' : cell.title;
            const actionable = cell.status === 'Available' ? (adminCanManageOperations() && !isPastAvailable) : true;
            return `
                <div class="admin-schedule-cell">
                    <button type="button"
                        title="${escapeHtml(cellTitle)}"
                        class="admin-schedule-action ${adminScheduleCellClass(cellStatus)}"
                        ${actionable ? '' : 'disabled'}
                        data-admin-calendar-booking
                        data-date="${escapeHtml(date)}"
                        data-time="${escapeHtml(time)}"
                        data-time-slot-id="${escapeHtml(slot.id || '')}"
                        data-court-id="${escapeHtml(selection.courtId)}"
                        data-sport="${escapeHtml(selection.sport)}"
                        data-cell-label="${escapeHtml(cellLabel)}"
                        data-cell-status="${escapeHtml(cellStatus)}"
                        data-cell-title="${escapeHtml(cellTitle)}"
                        data-reservation-id="${escapeHtml(cell.reservationId || '')}"
                        data-block-id="${escapeHtml(cell.blockId || '')}">
                        <span>${cellStatus === 'Available' ? '' : escapeHtml(cellLabel)}</span>
                        ${cellSub ? `<span class="mt-0.5 block text-[9px] font-bold opacity-75">${escapeHtml(cellSub)}</span>` : ''}
                    </button>
                </div>
            `;
        }).join('');
        return timeCell + cells;
    }).join('');

    els.adminScheduleGrid.innerHTML = header + rows;
    syncSuperAdminRangeOverride();
}

function renderAdminOverrideBookingForm() {
    if (!els.adminOverrideBookingForm || !state) return;

    if (els.adminOverrideTime) {
        els.adminOverrideTime.innerHTML = sortedCourtBlockSlots().map(slot => `
            <option value="${slot.id}">${escapeHtml(compactTimeHeader(slot.label))}</option>
        `).join('');
    }
    updateAdminOverrideSportAndCourts('Pickleball');
    renderAdminOverrideCustomerOptions();
}

function renderAdminOverrideCustomerOptions(selectedId = '') {
    if (!els.adminOverrideCustomer) return;
    const members = state?.adminMembers || [];
    const options = [
        '<option value="">Walk-in / non-member</option>',
        ...members.map(member => {
            const label = `${member.name}${member.nickname ? ` (${member.nickname})` : ''}${member.phone ? ` - ${member.phone}` : ''}`;
            return `<option value="${escapeHtml(member.id)}" ${String(selectedId) === String(member.id) ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        })
    ];
    els.adminOverrideCustomer.innerHTML = options.join('');
}

function applyAdminOverrideCustomer() {
    if (!els.adminOverrideBookingForm || !els.adminOverrideCustomer) return;
    const form = els.adminOverrideBookingForm;
    const member = findAdminMember(els.adminOverrideCustomer.value);
    const nameField = form.querySelector('[name="name"]');
    const phoneField = form.querySelector('[name="phone"]');
    const emailField = form.querySelector('[name="email"]');
    if (member) {
        if (nameField) nameField.value = member.name || '';
        if (phoneField) phoneField.value = member.phone || '';
        if (emailField) emailField.value = member.email || '';
        [nameField, phoneField, emailField].forEach(field => {
            if (field) field.readOnly = true;
        });
    } else {
        if (nameField) nameField.value = '';
        if (phoneField) phoneField.value = '';
        if (emailField) emailField.value = '';
        [nameField, phoneField, emailField].forEach(field => {
            if (field) field.readOnly = false;
        });
    }
}

function adminOverrideSports() {
    const sports = new Set();
    (state?.courts || []).forEach(court => {
        (court.sports || []).forEach(sport => sports.add(sport));
    });
    const ordered = supportedBookingSports.filter(sport => sports.has(sport));
    return ordered.length > 0 ? ordered : supportedBookingSports;
}

function updateAdminOverrideSportAndCourts(preferredSport = '', preferredCourtId = '') {
    if (!els.adminOverrideCourt || !els.adminOverrideSport || !state) return;
    const sports = adminOverrideSports();
    const currentSport = els.adminOverrideSport.value;
    const selectedSport = sports.includes(preferredSport)
        ? preferredSport
        : (sports.includes(currentSport) ? currentSport : sports[0]);

    els.adminOverrideSport.innerHTML = sports.map(sport => `
        <option value="${escapeHtml(sport)}" ${sport === selectedSport ? 'selected' : ''}>${escapeHtml(sport)}</option>
    `).join('');

    const currentCourtId = els.adminOverrideCourt.value;
    const courts = (state.courts || []).filter(court => (court.sports || []).includes(selectedSport));
    const selectedCourtId = courts.some(court => String(court.id) === String(preferredCourtId))
        ? preferredCourtId
        : (courts.some(court => String(court.id) === String(currentCourtId)) ? currentCourtId : (courts[0]?.id || ''));

    els.adminOverrideCourt.innerHTML = courts.map(court => {
        const label = court.labels?.[selectedSport] || court.name || `Court ${court.id}`;
        return `<option value="${escapeHtml(court.id)}" ${String(court.id) === String(selectedCourtId) ? 'selected' : ''}>${escapeHtml(label)}</option>`;
    }).join('');
}

function superAdminRangeCourtsForSport(sport) {
    return (state?.courts || []).filter(court =>
        court.isActive !== false && (court.sports || []).includes(sport)
    );
}

function superAdminRangeSlotOptions() {
    const slots = sortedCourtBlockSlots();
    const starts = slots.map(slot => [slot.startsAt, formatRuleTime(slot.startsAt)]);
    const endMap = new Map();
    slots.forEach(slot => {
        endMap.set(slot.endsAt, formatRuleTime(slot.endsAt));
    });
    return {
        slots,
        starts,
        ends: [...endMap.entries()]
    };
}

function selectedSuperAdminRangeSlots() {
    if (!adminIsSuperAdmin()) return [];
    const start = els.superAdminRangeStart?.value || '';
    const end = els.superAdminRangeEnd?.value || '';
    if (!start || !end) return [];

    const startMinutes = timeToMinutes(start);
    const endMinutes = String(end).startsWith('00:00') ? 1440 : timeToMinutes(end);
    if (endMinutes <= startMinutes) return [];

    const slots = sortedCourtBlockSlots()
        .filter(slot => {
            const slotStart = timeToMinutes(slot.startsAt);
            const slotEnd = String(slot.endsAt).startsWith('00:00') ? 1440 : timeToMinutes(slot.endsAt);
            return slotStart >= startMinutes && slotEnd <= endMinutes;
        })
        .sort((a, b) => timeToMinutes(a.startsAt) - timeToMinutes(b.startsAt));

    if (slots.length === 0) return [];
    if (timeToMinutes(slots[0].startsAt) !== startMinutes) return [];
    const lastEnd = String(slots[slots.length - 1].endsAt).startsWith('00:00') ? 1440 : timeToMinutes(slots[slots.length - 1].endsAt);
    if (lastEnd !== endMinutes) return [];

    for (let index = 1; index < slots.length; index += 1) {
        const previousEnd = String(slots[index - 1].endsAt).startsWith('00:00') ? 1440 : timeToMinutes(slots[index - 1].endsAt);
        if (previousEnd !== timeToMinutes(slots[index].startsAt)) {
            return [];
        }
    }

    return slots;
}

function renderSuperAdminRangeOverride() {
    if (!els.superAdminRangeOverride) return;
    const isAllowed = adminIsSuperAdmin();
    els.superAdminRangeOverride.hidden = !isAllowed;
    if (!isAllowed || !state) return;

    const currentSport = els.superAdminRangeSport?.value || adminScheduleSportFilter || 'Pickleball';
    const sports = adminOverrideSports();
    const sport = sports.includes(currentSport) ? currentSport : 'Pickleball';
    setSelectOptions(els.superAdminRangeSport, sports.map(item => [item, item]), sport);

    const courts = superAdminRangeCourtsForSport(sport);
    const currentCourt = els.superAdminRangeCourt?.value || '';
    const selectedCourt = courts.some(court => String(court.id) === String(currentCourt)) ? currentCourt : (courts[0]?.id || '');
    setSelectOptions(els.superAdminRangeCourt, courts.map(court => [
        court.id,
        court.labels?.[sport] || court.name || `Court ${court.id}`
    ]), selectedCourt);

    const { slots, starts, ends } = superAdminRangeSlotOptions();
    const selectedStart = els.superAdminRangeStart?.value || slots[0]?.startsAt || '';
    const selectedEnd = els.superAdminRangeEnd?.value || slots[Math.min(2, slots.length - 1)]?.endsAt || slots[0]?.endsAt || '';
    setSelectOptions(els.superAdminRangeStart, starts, selectedStart);
    setSelectOptions(els.superAdminRangeEnd, ends, selectedEnd);

    syncSuperAdminRangeOverride();
}

function syncSuperAdminRangeOverride() {
    if (!els.superAdminRangeOverride || !adminIsSuperAdmin()) return;
    const slots = selectedSuperAdminRangeSlots();
    const date = isoDate(adminScheduleDate);
    const start = els.superAdminRangeStart?.value || '';
    const end = els.superAdminRangeEnd?.value || '';
    const courtId = els.superAdminRangeCourt?.value || '';
    const sport = els.superAdminRangeSport?.value || '';
    const valid = Boolean(courtId && sport && slots.length > 0);

    if (els.superAdminRangeOverrideButton) {
        els.superAdminRangeOverrideButton.classList.toggle('hidden', !valid);
        els.superAdminRangeOverrideButton.disabled = !valid;
    }
    if (els.superAdminRangeOverrideHelp) {
        if (!courtId || !sport) {
            els.superAdminRangeOverrideHelp.textContent = 'Choose a sport and court.';
        } else if (!start || !end || slots.length === 0) {
            els.superAdminRangeOverrideHelp.textContent = 'Choose a continuous start and end time range.';
        } else {
            els.superAdminRangeOverrideHelp.textContent = `${niceDate(date)} | ${formatRuleTime(start)} - ${formatRuleTime(end)} | ${slots.length} slot${slots.length === 1 ? '' : 's'} selected.`;
        }
    }
}

function openSuperAdminRangeOverride() {
    if (!els.adminOverrideBookingForm || !adminIsSuperAdmin()) return;
    const slots = selectedSuperAdminRangeSlots();
    if (slots.length === 0) return;

    const date = isoDate(adminScheduleDate);
    const sport = els.superAdminRangeSport?.value || 'Pickleball';
    const courtId = els.superAdminRangeCourt?.value || '';
    const court = courtById(courtId);
    const courtLabel = court?.labels?.[sport] || court?.name || `Court ${courtId}`;
    const start = els.superAdminRangeStart?.value || slots[0].startsAt;
    const end = els.superAdminRangeEnd?.value || slots[slots.length - 1].endsAt;
    const form = els.adminOverrideBookingForm;

    form.reset();
    if (els.adminOverrideBookingId) els.adminOverrideBookingId.value = '';
    if (els.adminOverrideTimeSlotIds) els.adminOverrideTimeSlotIds.value = slots.map(slot => slot.id).join(',');
    if (els.adminOverrideDate) els.adminOverrideDate.value = date;
    if (els.adminOverrideTime) {
        els.adminOverrideTime.innerHTML = `<option value="${escapeHtml(slots[0].id)}">${escapeHtml(`${formatRuleTime(start)} - ${formatRuleTime(end)}`)}</option>`;
        els.adminOverrideTime.value = slots[0].id;
    }
    updateAdminOverrideSportAndCourts(sport, courtId);
    renderAdminOverrideCustomerOptions();
    applyAdminOverrideCustomer();

    const titleEl = document.getElementById('adminOverrideBookingTitle');
    if (titleEl) titleEl.textContent = 'Book selected range';
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.textContent = 'Save Booking';
    const statusField = form.querySelector('[name="status"]');
    if (statusField) {
        statusField.disabled = false;
        statusField.value = 'Held';
    }
    form.querySelector('[name="paymentMethod"]').value = 'Admin Override';
    form.querySelector('[name="overrideReason"]').value = 'Super Admin range override';

    if (els.adminOverrideContext) {
        els.adminOverrideContext.textContent = `${niceDate(date)} | ${formatRuleTime(start)} - ${formatRuleTime(end)} | ${courtLabel} | ${sport}`;
    }
    if (els.adminOverrideBookingMessage) {
        els.adminOverrideBookingMessage.textContent = '';
        els.adminOverrideBookingMessage.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (window.bootstrap && els.adminOverrideBookingModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminOverrideBookingModal).show();
    }
}

function openAdminCalendarBooking(button) {
    if (!els.adminOverrideBookingForm || !state) return;
    const status = button.dataset.cellStatus || 'Available';
    if (status !== 'Available') {
        openAdminCalendarDetails(button);
        return;
    }
    if (!adminCanManageOperations()) {
        return;
    }
    const form = els.adminOverrideBookingForm;
    const date = button.dataset.date || isoDate(adminScheduleDate);
    const time = button.dataset.time || '';
    const courtId = button.dataset.courtId || '';
    const sport = button.dataset.sport || '';
    const label = button.dataset.cellLabel || 'AVAILABLE';
    const title = button.dataset.cellTitle || '';
    const court = courtById(courtId);
    const courtLabel = court ? adminCourtOptionLabel(court) : `Court ${courtId}`;

    form.reset();
    if (els.adminOverrideBookingId) els.adminOverrideBookingId.value = '';
    if (els.adminOverrideTimeSlotIds) els.adminOverrideTimeSlotIds.value = '';
    const titleEl = document.getElementById('adminOverrideBookingTitle');
    if (titleEl) titleEl.textContent = 'Book selected slot';
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.textContent = 'Save Booking';
    const statusField = form.querySelector('[name="status"]');
    if (statusField) statusField.disabled = false;
    renderAdminOverrideCustomerOptions();
    applyAdminOverrideCustomer();
    if (els.adminOverrideBookingMessage) {
        els.adminOverrideBookingMessage.textContent = '';
        els.adminOverrideBookingMessage.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (els.adminOverrideDate) els.adminOverrideDate.value = date;
    if (els.adminOverrideTime) els.adminOverrideTime.value = button.dataset.timeSlotId || '';
    updateAdminOverrideSportAndCourts(sport, courtId);
    if (statusField) statusField.value = 'Held';
    form.querySelector('[name="paymentMethod"]').value = 'Admin Override';
    form.querySelector('[name="overrideReason"]').value = status === 'Available'
        ? 'Admin calendar booking'
        : `Admin calendar override for ${label}`;
    if (els.adminOverrideContext) {
        els.adminOverrideContext.textContent = `${niceDate(date)} | ${compactTime(time)} | ${courtLabel} | ${sport}${title ? ` | ${title}` : ''}`;
    }

    if (window.bootstrap && els.adminOverrideBookingModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminOverrideBookingModal).show();
    }
}

function openAdminCalendarBookingEdit(reservation) {
    if (!els.adminOverrideBookingForm || !reservation || reservation.type !== 'court') return;
    const form = els.adminOverrideBookingForm;
    const bookingId = String(reservation.id || '').replace(/^court:/, '');
    const titleEl = document.getElementById('adminOverrideBookingTitle');
    const submitButton = form.querySelector('button[type="submit"]');
    const statusField = form.querySelector('[name="status"]');

    form.reset();
    if (els.adminOverrideBookingId) els.adminOverrideBookingId.value = bookingId;
    if (els.adminOverrideTimeSlotIds) els.adminOverrideTimeSlotIds.value = '';
    if (titleEl) titleEl.textContent = 'Edit booking';
    if (submitButton) submitButton.textContent = 'Update Booking';
    if (els.adminOverrideDate) els.adminOverrideDate.value = reservation.date || '';
    if (els.adminOverrideTime) els.adminOverrideTime.value = reservation.timeSlotId || '';
    updateAdminOverrideSportAndCourts(reservation.sport || '', reservation.court || '');
    if (statusField) {
        statusField.value = reservation.status || 'Held';
        statusField.disabled = true;
    }
    form.querySelector('[name="paymentMethod"]').value = reservation.paymentMethod || 'Admin Override';
    form.querySelector('[name="overrideReason"]').value = 'Admin dashboard booking edit';
    renderAdminOverrideCustomerOptions(reservation.memberId || '');

    const member = reservation.memberId ? findAdminMember(reservation.memberId) : null;
    const nameField = form.querySelector('[name="name"]');
    const phoneField = form.querySelector('[name="phone"]');
    const emailField = form.querySelector('[name="email"]');
    if (member) {
        applyAdminOverrideCustomer();
    } else {
        if (nameField) {
            nameField.value = reservation.customerName || '';
            nameField.readOnly = false;
        }
        if (phoneField) {
            phoneField.value = reservation.customerPhone || '';
            phoneField.readOnly = false;
        }
        if (emailField) {
            emailField.value = reservation.customerEmail || '';
            emailField.readOnly = false;
        }
    }
    if (els.adminOverrideBookingMessage) {
        els.adminOverrideBookingMessage.textContent = '';
        els.adminOverrideBookingMessage.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    const court = courtById(reservation.court);
    const courtLabel = court ? adminCourtOptionLabel(court) : (reservation.courtName || `Court ${reservation.court}`);
    if (els.adminOverrideContext) {
        els.adminOverrideContext.textContent = `${niceDate(reservation.date)} | ${compactTime(reservation.time)} | ${courtLabel} | ${reservation.sport || ''} | ${reservation.bookingReference || 'No reference'}`;
    }
    if (window.bootstrap && els.adminCalendarDetailModal) {
        bootstrap.Modal.getInstance(els.adminCalendarDetailModal)?.hide();
    }
    if (window.bootstrap && els.adminOverrideBookingModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminOverrideBookingModal).show();
    }
}

function openAdminCalendarDetails(button) {
    if (!els.adminCalendarDetailBody) return;
    const date = button.dataset.date || isoDate(adminScheduleDate);
    const time = button.dataset.time || '';
    const courtId = button.dataset.courtId || '';
    const sport = button.dataset.sport || '';
    const status = button.dataset.cellStatus || '';
    const reservation = reservationById(button.dataset.reservationId || '') || bookingBySlot(date, time, courtId, sport);
    const block = (state?.adminCourtBlocks || state?.courtBlocks || []).find(item => String(item.id) === String(button.dataset.blockId || ''));
    const court = courtById(courtId);
    const courtLabel = court ? adminCourtOptionLabel(court) : `Court ${courtId}`;

    if (els.adminCalendarDetailTitle) {
        els.adminCalendarDetailTitle.textContent = status === 'Blocked' ? 'Court Blocking' : 'Reservation Details';
    }
    if (els.adminCalendarDetailMeta) {
        els.adminCalendarDetailMeta.textContent = `${niceDate(date)} | ${compactTime(time)} | ${courtLabel}`;
    }

    if (reservation) {
        els.adminCalendarDetailBody.innerHTML = `
            <div class="admin-detail-grid">
                <section class="admin-detail-card">
                    <span class="section-kicker">Player Information</span>
                    <dl class="admin-detail-list">
                        <div><dt>Name</dt><dd>${escapeHtml(reservation.customerName || 'N/A')}</dd></div>
                        <div><dt>Nickname</dt><dd>${escapeHtml(reservation.playerNickname || 'N/A')}</dd></div>
                        <div><dt>Phone</dt><dd>${escapeHtml(reservation.customerPhone || 'N/A')}</dd></div>
                        <div><dt>Email</dt><dd>${escapeHtml(reservation.customerEmail || 'N/A')}</dd></div>
                    </dl>
                </section>
                <section class="admin-detail-card">
                    <span class="section-kicker">Booking Summary</span>
                    <dl class="admin-detail-list">
                        <div><dt>Sport</dt><dd>${escapeHtml(reservation.sport || sport)}</dd></div>
                        <div><dt>Court</dt><dd>${escapeHtml(courtLabel)}</dd></div>
                        <div><dt>Date</dt><dd>${escapeHtml(niceDate(reservation.date || date))}</dd></div>
                        <div><dt>Time</dt><dd>${escapeHtml(compactTime(reservation.time || time))}</dd></div>
                        <div><dt>Status</dt><dd><span class="status-badge ${adminStatusClass(reservation.status)}">${escapeHtml(reservation.status)}</span></dd></div>
                        ${reservation.status === 'Held' ? '<div><dt>Admin Step</dt><dd><span class="status-badge status-badge-review">Review Payment</span></dd></div>' : ''}
                        <div><dt>Reference Number</dt><dd><strong>${escapeHtml(reservation.bookingReference || 'N/A')}</strong></dd></div>
                    </dl>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        ${reservation.receipt ? `<a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="${escapeHtml(resourceUrl(reservation.receipt))}">View Receipt</a>` : ''}
                        ${adminCanManageOperations() && reservation.type === 'court' && reservation.status !== 'Cancelled'
                            ? `<button type="button" class="btn btn-primary btn-sm" data-admin-calendar-edit-booking="${escapeHtml(reservation.id)}">Edit Booking</button>`
                            : ''}
                    </div>
                </section>
            </div>
        `;
        els.adminCalendarDetailBody.querySelector('[data-admin-calendar-edit-booking]')?.addEventListener('click', event => {
            const item = reservationById(event.currentTarget.dataset.adminCalendarEditBooking || '');
            openAdminCalendarBookingEdit(item || reservation);
        });
    } else if (block) {
        els.adminCalendarDetailBody.innerHTML = `
            <section class="admin-detail-card">
                <span class="section-kicker">Court Blocking</span>
                <dl class="admin-detail-list">
                    <div><dt>Scope</dt><dd>${escapeHtml(block.courtName || courtLabel)}</dd></div>
                    <div><dt>Sport</dt><dd>${escapeHtml(block.sport || 'All supported sports')}</dd></div>
                    <div><dt>Date</dt><dd>${escapeHtml(niceDate(block.date || date))}</dd></div>
                    <div><dt>Time</dt><dd>${escapeHtml(compactTime(block.time || time))}</dd></div>
                    <div><dt>Reason</dt><dd>${escapeHtml(block.reason || 'Court Blocking')}</dd></div>
                    <div><dt>Notes</dt><dd>${escapeHtml(block.notes || 'N/A')}</dd></div>
                    <div><dt>Status</dt><dd>${escapeHtml(block.status || 'Active')}</dd></div>
                </dl>
            </section>
        `;
    } else {
        els.adminCalendarDetailBody.innerHTML = '<p class="small fw-semibold text-secondary">No reservation details were found for this slot.</p>';
    }

    if (window.bootstrap && els.adminCalendarDetailModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminCalendarDetailModal).show();
    }
}

function dayTypeForDate(date) {
    const day = new Date(`${date}T00:00:00`).getDay();
    return day === 0 || day === 6 ? 'Weekend' : 'Weekday';
}

function dayNameForDate(date) {
    return new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { weekday: 'long' });
}

function dayPatternMatches(pattern, date) {
    const value = String(pattern || 'Any').trim();
    if (!value || value === 'Any') return true;
    const dayName = dayNameForDate(date);
    const dayType = dayTypeForDate(date);
    if (value === dayType || value === dayName) return true;
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const dayIndex = days.indexOf(dayName);

    return value.split(',').map(part => part.trim()).some(part => {
        if (!part) return false;
        if (part === dayName || part === dayType) return true;
        if (!part.includes('-')) return false;
        const [start, end] = part.split('-').map(item => item.trim());
        const startIndex = days.indexOf(start);
        const endIndex = days.indexOf(end);
        if (startIndex < 0 || endIndex < 0) return false;
        if (startIndex <= endIndex) return dayIndex >= startIndex && dayIndex <= endIndex;
        return dayIndex >= startIndex || dayIndex <= endIndex;
    });
}

function timeToMinutes(value) {
    const [hours, minutes] = String(value || '00:00').split(':').map(Number);
    return (hours * 60) + minutes;
}

function slotDuration(slot) {
    const start = timeToMinutes(slot?.startsAt);
    let end = timeToMinutes(slot?.endsAt);
    if (end <= start) end += 1440;
    return Math.max(0.25, (end - start) / 60);
}

function slotIsPast(date, time) {
    const slot = state?.slotDetails?.[time];
    if (!slot?.startsAt) return date < isoDate(new Date());
    return new Date(`${date}T${slot.startsAt}:00`) <= new Date();
}

function rateForSlot(time, courtId = null, sport = selectedSport, date = isoDate(selectedDate)) {
    const slot = state?.slotDetails?.[time];
    const rules = state?.rateRules || [];
    const selectedCourtId = Number(courtId || courtsForSelectedSport()[0]?.id || 0);
    const duration = slotDuration(slot);
    const dayName = dayNameForDate(date);
    const rule = rules
        .filter(item =>
            Number(item.courtId) === selectedCourtId &&
            item.sport === sport &&
            Number(item.timeSlotId) === Number(slot?.id) &&
            dayPatternMatches(item.dayPattern || item.dayOfWeek || 'Any', date)
        )
        .sort((a, b) => {
            const aDay = a.dayOfWeek || a.dayPattern || 'Any';
            const bDay = b.dayOfWeek || b.dayPattern || 'Any';
            if (aDay === dayName && bDay !== dayName) return -1;
            if (bDay === dayName && aDay !== dayName) return 1;
            if (aDay !== 'Any' && bDay === 'Any') return -1;
            if (bDay !== 'Any' && aDay === 'Any') return 1;
            return 0;
        })[0];
    if (rule) {
        const hourly = Number(rule.pricePerHour);
        return {
            amount: hourly * duration,
            hourly,
            ruleName: rule.name,
            duration
        };
    }

    const fallback = Number(slot?.price || 265);
    return {
        amount: fallback * duration,
        hourly: fallback,
        ruleName: 'Time slot fallback',
        duration
    };
}

function slotPrice(time, courtId = null, sport = selectedSport, date = isoDate(selectedDate)) {
    return rateForSlot(time, courtId, sport, date).amount;
}

function bookingSlotKey(slot) {
    return `${slot.date}|${slot.time}|${slot.court}|${slot.sport}`;
}

function selectedBookingTotal() {
    return selectedBookingSlots.reduce((total, slot) => total + slotPrice(slot.time, slot.court, slot.sport, slot.date), 0);
}

function clearBookingSelection() {
    selectedBookingSlots = [];
    renderBookingSelectionBar();
}

function toggleBookingSelection(data) {
    const slot = {
        date: data.bookDate,
        time: data.bookTime,
        court: Number(data.bookCourt),
        courtName: data.bookCourtName || `Court ${data.bookCourt}`,
        sport: data.bookSport || selectedSport
    };
    const key = bookingSlotKey(slot);
    const index = selectedBookingSlots.findIndex(item => bookingSlotKey(item) === key);
    if (index >= 0) {
        selectedBookingSlots.splice(index, 1);
    } else {
        selectedBookingSlots.push(slot);
    }
    renderBookingGrid();
}

function renderBookingSelectionBar() {
    renderInlineReservationPanel();
    if (!els.bookingSelectionBar || !els.bookingSelectionSummary) return;
    if (selectedBookingSlots.length === 0) {
        els.bookingSelectionBar.classList.add('hidden');
        els.bookingSelectionSummary.textContent = '';
        return;
    }

    els.bookingSelectionBar.classList.remove('hidden');
    const sorted = [...selectedBookingSlots].sort((a, b) =>
        `${a.date} ${a.time} ${a.court}`.localeCompare(`${b.date} ${b.time} ${b.court}`)
    );
    const slotsText = sorted.map(slot => `${compactTime(slot.time)} ${slot.courtName}`).join(', ');
    els.bookingSelectionSummary.textContent = `${selectedBookingSlots.length} slot${selectedBookingSlots.length === 1 ? '' : 's'} selected - ${slotsText} - Total ${peso.format(selectedBookingTotal())}`;
}

function isInlineBookingForm() {
    return els.form?.dataset.bookingInline === '1';
}

function renderInlineReservationPanel() {
    if (!isInlineBookingForm()) return;
    const slots = [...selectedBookingSlots].sort((a, b) =>
        `${a.date} ${a.time} ${a.court}`.localeCompare(`${b.date} ${b.time} ${b.court}`)
    );
    activeBookingSlots = slots;
    const hasSlots = slots.length > 0;
    const first = slots[0];

    if (els.inlineReservation) {
        els.inlineReservation.classList.toggle('has-selection', hasSlots);
    }
    if (els.inlineEmpty) {
        els.inlineEmpty.textContent = hasSlots
            ? `${slots.length} slot${slots.length === 1 ? '' : 's'} selected. Review details and confirm below.`
            : 'Select one or more available slots to complete your reservation.';
    }
    if (els.bookingSummaryDateLabel) {
        els.bookingSummaryDateLabel.textContent = hasSlots ? niceDate(first.date) : '';
    }
    if (els.bookingReviewSummary) {
        els.bookingReviewSummary.innerHTML = hasSlots
            ? inlineReservationSummary(slots)
            : '<div class="metro-inline-empty-summary">No selected slots yet.</div>';
    }
    if (els.modalSubmitButton) {
        els.modalSubmitButton.disabled = !hasSlots;
        els.modalSubmitButton.type = 'submit';
        els.modalSubmitButton.textContent = 'Confirm Reservation';
        delete els.modalSubmitButton.dataset.done;
    }
    if (hasSlots) {
        document.getElementById('actionType').value = 'book';
        document.getElementById('formDate').value = first.date;
        document.getElementById('formTime').value = first.time;
        document.getElementById('formCourt').value = first.court;
        document.getElementById('formSport').value = first.sport;
        document.getElementById('formSessionId').value = '';
        if (els.bookingReferencePanel) {
            els.bookingReferencePanel.classList.add('hidden');
            els.bookingReferencePanel.innerHTML = '';
        }
        showFormMessage('', true, true);
    }
}

function openSelectedBookingModal() {
    if (selectedBookingSlots.length === 0) return;
    const first = selectedBookingSlots[0];
    openCourtModal({
        bookDate: first.date,
        bookTime: first.time,
        bookCourt: first.court,
        bookCourtName: first.courtName,
        bookSport: first.sport
    });
}

function bookingModalSteps() {
    return state?.member ? ['review', 'payment', 'proof'] : ['info', 'review', 'payment', 'proof'];
}

function bookingModalStepTitle(key) {
    return {
        info: 'Booking Details',
        review: 'Review Booking',
        payment: 'Review / Payment',
        proof: 'Reservation Confirmation'
    }[key] || key;
}

function bookingSlotSummaryRows(slots = activeBookingSlots) {
    return slots.map(slot => {
        const amount = slotPrice(slot.time, slot.court, slot.sport, slot.date);
        return `
            <tr>
                <td>${escapeHtml(niceDate(slot.date))}</td>
                <td>${escapeHtml(compactTime(slot.time))}</td>
                <td>${escapeHtml(slot.courtName || `Court ${slot.court}`)}</td>
                <td>${escapeHtml(slot.sport)}</td>
                <td class="text-end">${peso.format(amount)}</td>
            </tr>
        `;
    }).join('');
}

function bookingDetailsTable(slots = activeBookingSlots, compact = false) {
    const total = slots.reduce((sum, slot) => sum + slotPrice(slot.time, slot.court, slot.sport, slot.date), 0);
    return `
        <div class="booking-review-card">
            <table class="booking-review-table ${compact ? 'booking-review-table-compact' : ''}">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Court</th>
                        <th>Sport</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>${bookingSlotSummaryRows(slots)}</tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Overall Total</td>
                        <td class="text-end">${peso.format(total)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;
}

function inlineReservationSummary(slots = activeBookingSlots) {
    const sorted = [...slots].sort((a, b) => {
        if (a.date !== b.date) return a.date.localeCompare(b.date);
        if (Number(a.court) !== Number(b.court)) return Number(a.court) - Number(b.court);
        return slotStartMinutes(a) - slotStartMinutes(b);
    });
    const dateGroups = new Map();
    sorted.forEach(slot => {
        const dateKey = slot.date;
        if (!dateGroups.has(dateKey)) dateGroups.set(dateKey, []);
        dateGroups.get(dateKey).push(slot);
    });
    const subtotal = sorted.reduce((sum, slot) => sum + slotPrice(slot.time, slot.court, slot.sport, slot.date), 0);
    const surcharge = 0;
    const total = subtotal + surcharge;
    const scheduleHtml = [...dateGroups.entries()].map(([date, dateSlots]) => {
        const courtGroups = new Map();
        dateSlots.forEach(slot => {
            const courtLabel = slot.courtName || `Court ${slot.court}`;
            if (!courtGroups.has(courtLabel)) courtGroups.set(courtLabel, []);
            courtGroups.get(courtLabel).push(slot);
        });
        const courtsHtml = [...courtGroups.entries()].map(([courtLabel, courtSlots]) => `
            <div class="metro-summary-timeslot-group">
                <strong>${escapeHtml(courtLabel)}</strong>
                ${courtSlots.map(slot => `<span>${escapeHtml(compactTimeHeader(slot.time))}</span>`).join('')}
            </div>
        `).join('');
        return `
            <section class="metro-summary-date-group">
                <span class="metro-summary-kicker">Schedule Date</span>
                <h4>${escapeHtml(new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }))}</h4>
                <span class="metro-summary-kicker">Allocated Timeslots</span>
                <div class="metro-summary-timeslots">${courtsHtml}</div>
            </section>
        `;
    }).join('');

    return `
        <div class="metro-reservation-summary">
            ${scheduleHtml}
            <dl class="metro-summary-totals">
                <div><dt>Rate Subtotal</dt><dd>${peso.format(subtotal)}</dd></div>
                <div><dt>Convenience Surcharge</dt><dd>${peso.format(surcharge)}</dd></div>
                <div class="metro-summary-total"><dt>Total Due</dt><dd>${peso.format(total)}</dd></div>
            </dl>
        </div>
    `;
}

function slotStartMinutes(slot) {
    return timeToMinutes(state?.slotDetails?.[slot.time]?.startsAt || '00:00');
}

function slotEndMinutes(slot) {
    const details = state?.slotDetails?.[slot.time];
    if (!details) return slotStartMinutes(slot);
    let end = timeToMinutes(details.endsAt);
    const start = timeToMinutes(details.startsAt);
    if (end <= start) end += 1440;
    return end;
}

function minutesToDisplay(minutes) {
    const normalized = ((minutes % 1440) + 1440) % 1440;
    const hours24 = Math.floor(normalized / 60);
    const mins = normalized % 60;
    const suffix = hours24 >= 12 ? 'PM' : 'AM';
    const hours12 = hours24 % 12 || 12;
    return `${hours12}:${String(mins).padStart(2, '0')} ${suffix}`;
}

function formatHours(hours) {
    const value = Number(hours);
    const label = Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    return `${label} hour${value === 1 ? '' : 's'}`;
}

function bookingReferencePrefix(date = new Date()) {
    const year = String(date.getFullYear()).slice(-2);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    const random = Math.random().toString(36).slice(2, 6).toUpperCase().padEnd(4, '0');
    return `MA${year}${month}${day}-${hours}${minutes}${seconds}-${random}`;
}

function bookingSummaryBreakdown(slots = activeBookingSlots) {
    const sorted = [...slots].sort((a, b) => {
        if (a.date !== b.date) return a.date.localeCompare(b.date);
        if (slotStartMinutes(a) !== slotStartMinutes(b)) return slotStartMinutes(a) - slotStartMinutes(b);
        return Number(a.court) - Number(b.court);
    });
    const dates = [...new Set(sorted.map(slot => slot.date))];
    const sports = [...new Set(sorted.map(slot => slot.sport))];
    const courts = [...new Set(sorted.map(slot => slot.courtName || `Court ${slot.court}`))];
    const totalDuration = sorted.reduce((sum, slot) => sum + slotDuration(state?.slotDetails?.[slot.time]), 0);
    const subtotal = sorted.reduce((sum, slot) => sum + slotPrice(slot.time, slot.court, slot.sport, slot.date), 0);
    const discount = 0;
    const total = subtotal - discount;
    const hourlyRates = sorted.map(slot => rateForSlot(slot.time, slot.court, slot.sport, slot.date).hourly);
    const uniqueRates = [...new Set(hourlyRates.map(rate => Number(rate)))];
    const rateLabel = uniqueRates.length === 1
        ? `${peso.format(uniqueRates[0])}/hour`
        : `${peso.format(subtotal / Math.max(totalDuration, 1))}/hour average`;
    const dateLabel = dates.length === 1
        ? new Date(`${dates[0]}T00:00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        : dates.map(date => niceDate(date)).join(', ');
    const start = Math.min(...sorted.map(slotStartMinutes));
    const end = Math.max(...sorted.map(slotEndMinutes));
    const times = [`${minutesToDisplay(start)}-${minutesToDisplay(end)}`];

    return `
        <div class="booking-summary-list">
            <article class="booking-summary-card">
                <h5>Booking Summary</h5>
                <dl class="booking-summary-meta">
                    <div><dt>Court:</dt><dd>${escapeHtml(courts.join(', '))}</dd></div>
                    <div><dt>Sport:</dt><dd>${escapeHtml(sports.join(', '))}</dd></div>
                    <div><dt>Date:</dt><dd>${escapeHtml(dateLabel)}</dd></div>
                    <div><dt>Time:</dt><dd>${escapeHtml(times.join(', '))}</dd></div>
                    <div><dt>Duration:</dt><dd>${formatHours(totalDuration)}</dd></div>
                </dl>
                <table class="booking-breakdown-table">
                    <thead>
                        <tr><th>Description</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Court Rate</td><td class="text-end">${escapeHtml(rateLabel)}</td></tr>
                        <tr><td>Duration</td><td class="text-end">${formatHours(totalDuration)}</td></tr>
                        <tr><td>Subtotal</td><td class="text-end">${peso.format(subtotal)}</td></tr>
                        <tr><td>Discount</td><td class="text-end">${peso.format(discount)}</td></tr>
                    </tbody>
                    <tfoot>
                        <tr><td>Overall Total</td><td class="text-end">${peso.format(total)}</td></tr>
                    </tfoot>
                </table>
            </article>
        </div>
    `;
}

function renderBookingReviewSummary() {
    if (!els.bookingReviewSummary) return;
    els.bookingReviewSummary.innerHTML = bookingSummaryBreakdown(activeBookingSlots);
}

function setBookingModalStep(nextStep) {
    const steps = bookingModalSteps();
    bookingModalStep = Math.max(0, Math.min(nextStep, steps.length - 1));
    const currentKey = steps[bookingModalStep];
    const isFinalStep = bookingModalStep === steps.length - 1;

    els.bookingStepPanels?.forEach(panel => {
        panel.classList.toggle('hidden', panel.dataset.bookingStepPanel !== currentKey);
    });

    els.bookingStepItems?.forEach(item => {
        const itemIndex = steps.indexOf(item.dataset.bookingStep);
        item.classList.toggle('hidden', itemIndex < 0);
        item.classList.toggle('is-active', itemIndex === bookingModalStep);
        item.classList.toggle('is-complete', itemIndex >= 0 && itemIndex < bookingModalStep);
    });

    if (els.modalBackButton) els.modalBackButton.classList.toggle('hidden', bookingModalStep === 0 || isFinalStep);
    if (els.modalNextButton) els.modalNextButton.classList.toggle('hidden', isFinalStep);
    if (els.modalSubmitButton) els.modalSubmitButton.classList.toggle('hidden', !isFinalStep);
    // const closeButton = document.getElementById('closeModal');
    // if (closeButton) {
    //     const closeLocked = !bookingModalCloseUnlocked || Boolean(els.modalSubmitButton?.dataset.done === '1');
    //     closeButton.disabled = closeLocked;
    //     closeButton.classList.toggle('is-locked', closeLocked);
    //     closeButton.title = closeLocked ? 'Click Done to finish this booking process' : 'Close payment form';
    // }
    const closeButton = document.getElementById('closeModal');
    if (closeButton) {
        closeButton.disabled = false;
        closeButton.classList.remove('is-locked');
        closeButton.title = 'Cancel booking';
        closeButton.setAttribute('aria-label', 'Cancel booking');
    }
    if (els.modalKicker) els.modalKicker.textContent = bookingModalStepTitle(currentKey);
}

function validateModalStep() {
    const key = bookingModalSteps()[bookingModalStep];
    if (key === 'info') {
        const fields = [els.form?.querySelector('[name="name"]'), els.form?.querySelector('[name="phone"]')].filter(Boolean);
        const invalid = fields.find(field => !field.checkValidity());
        if (invalid) {
            invalid.reportValidity();
            return false;
        }
    }
    if (key === 'payment' && !els.paymentMethod?.value) {
        showFormMessage('Choose GCash or BDO Online to continue.', false);
        els.paymentMethod?.focus();
        return false;
    }
    showFormMessage('', true, true);
    return true;
}

function proceedBookingModal() {
    if (!validateModalStep()) return;
    bookingModalCloseUnlocked = true;
    setBookingModalStep(bookingModalStep + 1);
}

function backBookingModal() {
    setBookingModalStep(bookingModalStep - 1);
}

function renderBookingGrid() {
    if (!els.grid || !state) return;
    let date = isoDate(selectedDate);
    const today = isoDate(new Date());
    const maxDate = bookingMaxDateIso();
    const noEnabledBookingDates = Boolean(maxDate && today > maxDate);
    if (maxDate && date > maxDate) {
        selectedDate = new Date(`${noEnabledBookingDates ? today : maxDate}T00:00:00`);
        date = noEnabledBookingDates ? today : maxDate;
        clearBookingSelection();
    }
    const courts = courtsForSelectedSport();
    const allSlots = Object.values(state.timeSlots || {}).flat();
    if (els.dateLabel) {
        els.dateLabel.textContent = noEnabledBookingDates
            ? 'No booking dates are currently enabled.'
            : `${niceDate(date)}${date === today ? ' - Today' : ''}`;
    }
    syncBookingDatePicker(date, today, maxDate, noEnabledBookingDates);

    if (noEnabledBookingDates) {
        els.grid.innerHTML = '<div class="rounded-lg border border-dashed border-line bg-white p-5 text-sm fw-bold text-secondary">No booking dates are currently enabled. Please contact MetroAsia Arena.</div>';
        renderBookingSelectionBar();
        return;
    }

    els.grid.style.gridTemplateColumns = `minmax(112px, 150px) repeat(${allSlots.length}, minmax(76px, 1fr))`;
    els.grid.style.minWidth = `${150 + allSlots.length * 76}px`;

    const header = ['Court', ...allSlots];
    const headerHtml = header.map((item, index) => `
        <div class="metro-schedule-head ${index === 0 ? 'metro-schedule-head-court' : ''}">
            ${index === 0 ? item : compactTimeHeader(item)}
        </div>
    `).join('');

    const rows = courts.map((courtInfo, index) => {
        const court = courtInfo.id;
        const courtLabel = courtDisplayName(courtInfo);
        const courtNumber = courtInfo.number || index + 1;
        const first = `
            <div class="metro-schedule-court-cell">
                <small>Court</small>
                <strong>${String(courtNumber).padStart(2, '0')}</strong>
                <span>${escapeHtml(courtLabel)}</span>
            </div>
        `;
        const cells = allSlots.map(time => {
            const conflict = relatedConflictFor(date, time, courtInfo);
            const status = conflict?.status || 'Available';
            const isPast = slotIsPast(date, time);
            const tone = statusTone(status);
            const slotData = {
                date,
                time,
                court,
                courtName: courtLabel,
                sport: selectedSport
            };
            const isSelected = selectedBookingSlots.some(item => bookingSlotKey(item) === bookingSlotKey(slotData));
            const css = isSelected
                ? 'booking-slot-selected'
                : isPast
                ? 'booking-slot-available booking-slot-past'
                : status === 'Held'
                ? 'booking-slot-held'
                : status === 'Booked'
                ? 'booking-slot-booked'
                : tone === 'blocked'
                ? 'booking-slot-unavailable'
                : 'booking-slot-available';
            const disabled = status !== 'Available' || isPast ? 'disabled' : '';
            const label = isSelected
                ? 'Selected'
                : status === 'Available' && !isPast
                ? ''
                : tone === 'blocked' || isPast
                ? 'Unavailable'
                : publicSlotLabel(status, conflict);
            const help = isPast
                ? 'Past dates and time slots cannot be booked.'
                : status === 'Available'
                ? `Select ${courtLabel} ${selectedSport} slot`
                : `${courtLabel} is unavailable for this time.`;
            return `
                <button ${disabled}
                    data-book-date="${date}"
                    data-book-time="${time}"
                    data-book-court="${court}"
                    data-book-court-name="${escapeHtml(courtLabel)}"
                    data-book-sport="${selectedSport}"
                    title="${escapeHtml(help)}"
                    class="slot booking-slot metro-schedule-slot ${css}">
                    <span class="slot-label">${escapeHtml(label)}</span>
                </button>
            `;
        }).join('');
        return first + cells;
    }).join('');

    els.grid.innerHTML = headerHtml + rows;
    els.grid.querySelectorAll('[data-book-date]').forEach(button => {
        button.addEventListener('click', () => toggleBookingSelection(button.dataset));
    });
    renderBookingSelectionBar();
}

function syncBookingDatePicker(date, today, maxDate, noEnabledBookingDates) {
    if (!els.datePicker) return;

    els.datePicker.min = today;
    els.datePicker.max = maxDate || '';
    els.datePicker.value = date;
    els.datePicker.disabled = noEnabledBookingDates;
}

function adminReservationGroupKey(item) {
    return item.bookingReference || item.id;
}

function adminReservationSlotStart(item) {
    return timeToMinutes(state?.slotDetails?.[item.time]?.startsAt || '00:00');
}

function adminReservationSlotEnd(item) {
    const details = state?.slotDetails?.[item.time];
    if (!details) return adminReservationSlotStart(item);
    let end = timeToMinutes(details.endsAt);
    const start = timeToMinutes(details.startsAt);
    if (end <= start) end += 1440;
    return end;
}

function adminReservationSortValue(item) {
    const firstSlot = item.items?.[0] || item;
    return `${firstSlot.date || ''} ${String(adminReservationSlotStart(firstSlot)).padStart(4, '0')}`;
}

function adminReservationCreatedText(item) {
    const created = new Date(item.createdAt);
    if (Number.isNaN(created.getTime())) return 'N/A';
    const datePart = created.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: metroTimeZone
    });
    const timePart = created.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
        timeZone: metroTimeZone
    }).replace(/\s/g, '');
    return `${datePart} ${timePart}`;
}

function groupedReservationStatus(items) {
    const statuses = [...new Set(items.map(item => item.status))];
    if (statuses.length === 1) return statuses[0];
    if (statuses.includes('Held')) return 'Held';
    if (statuses.includes('Booked')) return 'Booked';
    return statuses[0] || 'Cancelled';
}

function groupedReservationReceipt(items) {
    return items.find(item => item.receipt)?.receipt || '';
}

function groupedReservationReviewedBy(items) {
    return [...new Set(items.map(item => item.reviewedByName).filter(Boolean))].join(', ');
}

function groupedReservationCancelReason(items) {
    return [...new Set(items.map(item => item.cancelReason).filter(Boolean))].join('; ');
}

function adminReservationTimeRanges(items) {
    const byDate = new Map();
    [...items].sort((a, b) => {
        if (a.date !== b.date) return a.date.localeCompare(b.date);
        return adminReservationSlotStart(a) - adminReservationSlotStart(b);
    }).forEach(item => {
        if (!byDate.has(item.date)) byDate.set(item.date, []);
        byDate.get(item.date).push(item);
    });

    return [...byDate.entries()].map(([date, slots]) => {
        const ranges = [];
        slots.forEach(slot => {
            const start = adminReservationSlotStart(slot);
            const end = adminReservationSlotEnd(slot);
            const last = ranges[ranges.length - 1];
            if (last && last.end === start) {
                last.end = end;
                return;
            }
            ranges.push({ start, end });
        });
        return {
            date,
            label: `${niceDate(date)} - ${ranges.map(range => `${minutesToDisplay(range.start)} - ${minutesToDisplay(range.end)}`).join(', ')}`
        };
    });
}

function adminReservationCourtSummaries(items) {
    const groups = new Map();
    items.forEach(item => {
        const courtName = item.courtName || `Court ${item.court}`;
        const key = `${item.date}|${courtName}`;
        if (!groups.has(key)) {
            groups.set(key, {
                courtName,
                date: item.date,
                slots: []
            });
        }
        groups.get(key).slots.push(item);
    });

    const hasMultipleDates = new Set(items.map(item => item.date)).size > 1;
    return [...groups.values()].map(group => {
        const ranges = [];
        [...group.slots].sort((a, b) => adminReservationSlotStart(a) - adminReservationSlotStart(b)).forEach(slot => {
            const start = adminReservationSlotStart(slot);
            const end = adminReservationSlotEnd(slot);
            const last = ranges[ranges.length - 1];
            if (last && last.end === start) {
                last.end = end;
                return;
            }
            ranges.push({ start, end });
        });
        const rangeLabel = ranges.map(range => `${minutesToDisplay(range.start)} - ${minutesToDisplay(range.end)}`).join(', ');
        return {
            courtName: group.courtName,
            date: group.date,
            ranges,
            label: `${group.courtName} - ${hasMultipleDates ? `${niceDate(group.date)} ` : ''}${rangeLabel}`
        };
    });
}

function groupAdminReservations(rows) {
    const groups = new Map();
    rows.forEach(item => {
        const key = adminReservationGroupKey(item);
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(item);
    });

    return [...groups.values()].map(items => {
        const sorted = [...items].sort((a, b) => {
            if (a.date !== b.date) return a.date.localeCompare(b.date);
            return adminReservationSlotStart(a) - adminReservationSlotStart(b);
        });
        const first = sorted[0];
        const ids = sorted.map(item => String(item.id).replace(/^court:/, ''));
        const timeRanges = adminReservationTimeRanges(sorted);
        const courtSummaries = adminReservationCourtSummaries(sorted);
        const status = groupedReservationStatus(sorted);
        const total = sorted.reduce((sum, item) => sum + Number(item.finalAmount || 0), 0);
        const courtNames = [...new Set(sorted.map(item => item.courtName || `Court ${item.court}`).filter(Boolean))];
        const sports = [...new Set(sorted.map(item => item.sport).filter(Boolean))];
        return {
            ...first,
            id: `court:${ids.join(',')}`,
            items: sorted,
            childIds: sorted.map(item => item.id),
            slotCount: sorted.length,
            courtName: courtNames.join(', '),
            sport: sports.join(', '),
            status,
            receipt: groupedReservationReceipt(sorted),
            reviewedByName: groupedReservationReviewedBy(sorted),
            cancelReason: groupedReservationCancelReason(sorted),
            finalAmount: total,
            timeRanges,
            courtSummaries,
            date: timeRanges[0]?.date || first.date,
            time: timeRanges.map(range => range.label.replace(`${niceDate(range.date)} - `, '')).join('; '),
            createdAt: sorted.reduce((earliest, item) => item.createdAt < earliest ? item.createdAt : earliest, first.createdAt)
        };
    });
}

function adminReservationMatchesDateRange(item) {
    if (!adminBookingStartDate && !adminBookingEndDate) return true;

    const { start, end } = adminBookingDateRange();
    const dates = (item.items && item.items.length ? item.items : [item])
        .map(slot => slot.date)
        .filter(Boolean);

    return dates.some(date => {
        if (start && date < start) return false;
        if (end && date > end) return false;
        return true;
    });
}

function adminBookingDateRange() {
    if (adminBookingStartDate && adminBookingEndDate && adminBookingStartDate > adminBookingEndDate) {
        return { start: adminBookingEndDate, end: adminBookingStartDate };
    }

    return { start: adminBookingStartDate, end: adminBookingEndDate };
}

function syncAdminBookingDateInputs() {
    if (els.adminBookingStartDate) els.adminBookingStartDate.value = adminBookingStartDate;
    if (els.adminBookingEndDate) els.adminBookingEndDate.value = adminBookingEndDate;
}

function setAdminBookingDateRange(start, end = start) {
    adminBookingStartDate = start || '';
    adminBookingEndDate = end || '';
    syncAdminBookingDateInputs();
    renderAdmin();
}

function syncAdminBookingSortInput() {
    if (els.adminBookingSort) els.adminBookingSort.value = adminBookingSort;
}

function sortAdminReservations(rows) {
    return [...rows].sort((a, b) => {
        if (adminBookingSort === 'reservation-asc') {
            return adminReservationSortValue(a).localeCompare(adminReservationSortValue(b))
                || String(a.createdAt || '').localeCompare(String(b.createdAt || ''));
        }

        if (adminBookingSort === 'reservation-desc') {
            return adminReservationSortValue(b).localeCompare(adminReservationSortValue(a))
                || String(b.createdAt || '').localeCompare(String(a.createdAt || ''));
        }

        return String(b.createdAt || '').localeCompare(String(a.createdAt || ''));
    });
}

function setAdminScheduleCalendarMonth(dateValue) {
    const date = new Date(`${dateValue || todayIso}T00:00:00`);
    if (!Number.isNaN(date.getTime())) {
        adminScheduleCalendarMonth = new Date(date.getFullYear(), date.getMonth(), 1);
    }
}

function setAdminScheduleDate(dateIso) {
    const date = new Date(`${dateIso || todayIso}T00:00:00`);
    if (Number.isNaN(date.getTime())) return;
    adminScheduleDate = date;
    setAdminScheduleCalendarMonth(dateIso);
    renderAdminSchedule();
    renderAdminScheduleCalendar();
}

function openAdminScheduleCalendar() {
    setAdminScheduleCalendarMonth(isoDate(adminScheduleDate));
    renderAdminScheduleCalendar();
    if (window.bootstrap && els.adminScheduleDatePickerModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminScheduleDatePickerModal).show();
    }
}

function renderAdminScheduleCalendar() {
    if (!els.adminScheduleCalendarGrid || !els.adminScheduleCalendarTitle) return;

    const monthStart = new Date(adminScheduleCalendarMonth.getFullYear(), adminScheduleCalendarMonth.getMonth(), 1);
    const gridStart = new Date(monthStart);
    gridStart.setDate(gridStart.getDate() - gridStart.getDay());
    const selected = isoDate(adminScheduleDate);
    const monthLabel = monthStart.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    els.adminScheduleCalendarTitle.textContent = monthLabel;

    const days = [];
    for (let index = 0; index < 42; index += 1) {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);
        const dateIso = isoDate(date);
        const isMuted = date.getMonth() !== monthStart.getMonth();
        const classes = [
            'admin-date-calendar-day',
            isMuted ? 'is-muted' : '',
            dateIso === todayIso ? 'is-today' : '',
            dateIso === selected ? 'is-selected' : '',
        ].filter(Boolean).join(' ');
        days.push(`
            <button type="button" class="${classes}" data-admin-schedule-calendar-date="${dateIso}">
                <span>${date.getDate()}</span>
            </button>
        `);
    }

    els.adminScheduleCalendarGrid.innerHTML = days.join('');
    if (els.adminScheduleCalendarHelp) {
        els.adminScheduleCalendarHelp.textContent = `Dashboard is showing ${niceDate(selected)}. Click a date to update the court matrix.`;
    }
    if (window.lucide) lucide.createIcons();
}

function renderAdmin() {
    if (!state) return;
    const allRows = groupAdminReservations((state.adminReservations || []).filter(item => item.type === 'court'));
    state.adminGroupedReservations = allRows;
    renderAdminStats(allRows);
    renderAdminFilterButtons();
    if (!els.admin) return;
    const referenceNeedle = adminReferenceSearch.trim().toLowerCase();
    const rows = allRows.filter(item => {
        const matchesStatus = adminFilter === 'All' || item.status === adminFilter;
        const matchesReference = referenceNeedle === '' || String(item.bookingReference || '').toLowerCase().includes(referenceNeedle);
        const matchesDateRange = adminReservationMatchesDateRange(item);
        return matchesStatus && matchesReference && matchesDateRange;
    });

    if (rows.length === 0) {
        els.admin.innerHTML = '<tr><td colspan="8" class="text-secondary">No reservations match the current filter.</td></tr>';
        return;
    }

    els.admin.innerHTML = sortAdminReservations(rows).map(item => `
        <tr>
            <td>
                <p class="mb-1 fw-black text-ink">${escapeHtml((item.timeRanges || [{ date: item.date }]).map(range => niceDate(range.date || item.date)).join(', '))}</p>
                ${(item.courtSummaries || [{ label: `${item.courtName || `Court ${item.court}`} - ${item.slotCount || 1} time slot${(item.slotCount || 1) === 1 ? '' : 's'}` }]).map(summary => `
                    <p class="mb-0 text-xs text-secondary">${escapeHtml(summary.label)}</p>
                `).join('')}
                <p class="mb-0 text-xs text-secondary">${escapeHtml(item.sport)}</p>
            </td>
            <td>
                <p class="mb-0 fw-black text-ink">${escapeHtml(item.bookingReference || 'N/A')}</p>
            </td>
            <td>
                <p class="mb-1 fw-black">${escapeHtml(item.customerName)}</p>
                ${item.playerNickname ? `<p class="mb-0 text-xs text-secondary">Nickname: ${escapeHtml(item.playerNickname)}</p>` : ''}
                <p class="mb-0 text-xs text-secondary">${escapeHtml(item.customerPhone || 'No phone')}</p>
                <p class="mb-0 text-xs text-secondary">${escapeHtml(item.customerEmail || 'No email')}</p>
                ${item.memberName ? `<p class="mb-0 text-xs fw-black text-primary">Member: ${escapeHtml(item.memberName)}</p>` : ''}
            </td>
            <td>
                <p class="mb-0 text-xs fw-black text-ink">${escapeHtml(adminReservationCreatedText(item))}</p>
            </td>
            <td>
                <p class="mb-1 fw-black">${escapeHtml(item.paymentMethod || 'N/A')}</p>
                <p class="mb-0 text-xs text-secondary">${peso.format(Number(item.finalAmount || 0))}</p>
            </td>
            <td>
                <span class="status-badge ${adminStatusClass(item.status)}">${escapeHtml(item.status)}</span>
                ${item.status === 'Held' ? '<p class="mb-0 mt-1 text-xs fw-black text-warning">Review Payment</p>' : ''}
                ${item.reviewedByName ? `<p class="mb-0 mt-1 text-xs text-secondary">By ${escapeHtml(item.reviewedByName)}</p>` : ''}
                ${item.cancelReason ? `<p class="mb-0 mt-1 text-xs text-warning">Reason: ${escapeHtml(item.cancelReason)}</p>` : ''}
            </td>
            <td>
                ${item.receipt
                    ? `<a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="${escapeHtml(resourceUrl(item.receipt))}">View Receipt</a>
                       ${adminCanManageOperations() ? `<button type="button" class="btn btn-outline-secondary btn-sm mt-1" data-admin-receipt-upload="${item.id}">Replace</button>` : ''}`
                    : (adminCanManageOperations() ? `<button type="button" class="btn btn-outline-primary btn-sm" data-admin-receipt-upload="${item.id}">Upload</button>` : '<span class="text-xs text-secondary">None</span>')}
            </td>
            <td class="text-end">
                ${adminReservationActions(item)}
            </td>
        </tr>
    `).join('');

    els.admin.querySelectorAll('[data-admin-id]').forEach(button => {
        button.addEventListener('click', () => {
            if (button.dataset.status === 'Cancelled') {
                openCancelReservationModal(button.dataset.adminId);
            } else {
                updateStatus(button.dataset.adminId, button.dataset.status);
            }
        });
    });
    els.admin.querySelectorAll('[data-admin-receipt-upload]').forEach(button => {
        button.addEventListener('click', () => openReceiptUploadModal(button.dataset.adminReceiptUpload));
    });
}

function renderAdminFilterButtons() {
    document.querySelectorAll('[data-admin-filter]').forEach(button => {
        button.className = button.dataset.adminFilter === adminFilter
            ? 'btn btn-primary btn-sm'
            : 'btn btn-outline-secondary btn-sm';
    });
}

function adminReservationActions(item) {
    if (!adminCanManageOperations()) {
        return '<span class="text-xs text-secondary">View only</span>';
    }
    if (item.status === 'Held') {
        return `
            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                <button data-admin-id="${item.id}" data-status="Booked" class="btn btn-success btn-sm">Verify / Confirm</button>
                <button data-admin-id="${item.id}" data-status="Cancelled" class="btn btn-outline-danger btn-sm">Cancel</button>
            </div>
        `;
    }

    if (item.status === 'Booked') {
        return `
            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                <button data-admin-id="${item.id}" data-status="Cancelled" class="btn btn-outline-danger btn-sm">Cancel</button>
            </div>
        `;
    }

    return '<span class="text-xs text-secondary">No actions</span>';
}

function adminStatusClass(status) {
    if (status === 'Cancelled') return 'status-badge-cancelled';
    if (statusTone(status) === 'pending') return 'status-badge-pending';
    return 'status-badge-booked';
}

function renderAdminStats(rows) {
    const counts = rows.reduce((memo, item) => {
        memo[item.status] = (memo[item.status] || 0) + 1;
        return memo;
    }, {});
    const pending = document.getElementById('adminPendingCount');
    const booked = document.getElementById('adminBookedCount');
    const cancelled = document.getElementById('adminCancelledCount');
    if (pending) pending.textContent = counts.Held || 0;
    if (booked) booked.textContent = counts.Booked || 0;
    if (cancelled) cancelled.textContent = counts.Cancelled || 0;
}

function renderAdminPaymentChannels() {
    if (!els.adminPaymentChannels || !state) return;
    const channels = state.adminPaymentChannels || [];

    els.adminPaymentChannels.innerHTML = channels.map((channel, index) => {
        const fixedType = channel.code === 'GCash' ? 'qr' : 'bank';
        const typeLabel = channel.code === 'GCash' ? 'QR / Wallet' : 'Bank details + BDO Pay QR';
        return `
        <form class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-soft" data-payment-channel-form>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-black uppercase tracking-[.14em] text-court">Payment Channel</p>
                    <h3 class="mt-1 text-2xl font-black">${escapeHtml(channel.name)}</h3>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                    <span class="status-badge bg-blue-100 text-primary">${typeLabel}</span>
                    <span class="status-badge ${channel.isActive ? 'status-badge-booked' : 'status-badge-cancelled'}">${channel.isActive ? 'Active' : 'Inactive'}</span>
                </div>
            </div>
            <input type="hidden" name="id" value="${channel.id || ''}">
            <input type="hidden" name="channelType" value="${fixedType}">
            <div class="grid gap-3 md:grid-cols-4">
                <label class="grid gap-2 text-sm font-bold">Code
                    <input required readonly name="code" value="${escapeHtml(channel.code)}" class="rounded-md border border-slate-300 bg-slate-50 px-3 py-2">
                </label>
                <label class="grid gap-2 text-sm font-bold">Display name
                    <input required name="name" value="${escapeHtml(channel.name)}" class="rounded-md border border-slate-300 px-3 py-2" placeholder="GCash">
                </label>
                <label class="grid gap-2 text-sm font-bold">Type
                    <input readonly value="${typeLabel}" class="rounded-md border border-slate-300 bg-slate-50 px-3 py-2">
                </label>
                <label class="grid gap-2 text-sm font-bold">Sort
                    <input name="sortOrder" type="number" value="${channel.sortOrder || index + 1}" class="rounded-md border border-slate-300 px-3 py-2">
                </label>
            </div>
            <label class="d-inline-flex align-items-center gap-2 text-sm font-bold">
                <input type="checkbox" name="isActive" value="1" ${channel.isActive ? 'checked' : ''}>
                <span>Active payment option</span>
            </label>
            <div class="grid gap-3 md:grid-cols-3">
                <label class="grid gap-2 text-sm font-bold">Account name
                    <input name="accountName" value="${escapeHtml(channel.accountName)}" class="rounded-md border border-slate-300 px-3 py-2" placeholder="Metro Asia">
                </label>
                <label class="grid gap-2 text-sm font-bold">Account number
                    <input name="accountNumber" value="${escapeHtml(channel.accountNumber)}" class="rounded-md border border-slate-300 px-3 py-2" placeholder="0000-0000-0000">
                </label>
                <label class="grid gap-2 text-sm font-bold">Bank name
                    <input name="bankName" value="${escapeHtml(channel.bankName)}" class="rounded-md border border-slate-300 px-3 py-2" placeholder="BDO">
                </label>
            </div>
            <label class="grid gap-2 text-sm font-bold">Payment instructions
                <textarea name="instructions" rows="3" class="rounded-md border border-slate-300 px-3 py-2" placeholder="Tell customers how to pay and what receipt to upload.">${escapeHtml(channel.instructions)}</textarea>
            </label>
            <div class="grid gap-3 md:grid-cols-[120px_1fr_auto] md:items-end">
                <div>
                    ${channel.qrPath ? `<img src="${escapeHtml(resourceUrl(channel.qrPath))}" alt="${escapeHtml(channel.name)} QR" class="h-24 w-24 rounded-md border border-slate-200 bg-slate-50 object-contain p-2">` : '<div class="grid h-24 w-24 place-items-center rounded-md border border-dashed border-slate-300 bg-slate-50 text-center text-xs font-bold text-slate-500">No QR</div>'}
                </div>
                <label class="grid gap-2 text-sm font-bold">Upload QR image
                    <input type="file" name="qrFile" accept=".jpg,.jpeg,.png,.webp,.svg" class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-sm">
                </label>
                <button class="rounded-full bg-limevolt px-6 py-3 text-sm font-black text-ink">Save</button>
            </div>
            <div class="hidden rounded-md p-3 text-sm font-bold" data-payment-channel-message></div>
        </form>
    `;
    }).join('');

    els.adminPaymentChannels.querySelectorAll('[data-payment-channel-form]').forEach(form => {
        form.addEventListener('submit', submitPaymentChannel);
    });
}

function formatRuleTime(value) {
    if (String(value || '').startsWith('00:00')) return '12 MN';
    const [hourRaw, minuteRaw = '00'] = String(value || '00:00').split(':');
    let hour = Number(hourRaw);
    const minute = Number(minuteRaw);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}${minute ? `:${String(minute).padStart(2, '0')}` : ''} ${suffix}`;
}

function populateAdminRateFilters(rules) {
    const sports = [...new Set(rules.map(rule => rule.sport).filter(Boolean))].sort();
    const courts = [...new Map(rules.map(rule => [
        String(rule.courtId),
        rule.courtName || `Court ${rule.courtId}`
    ])).entries()].sort((a, b) => String(a[1]).localeCompare(String(b[1])));

    if (els.adminRateSportFilter) {
        const current = adminRateSportFilter;
        setSelectOptions(els.adminRateSportFilter, [
            ['', 'All sports'],
            ...sports.map(sport => [sport, sport])
        ], current);
        if (current && !sports.includes(current)) {
            adminRateSportFilter = '';
            els.adminRateSportFilter.value = '';
        }
    }

    if (els.adminRateCourtFilter) {
        const current = adminRateCourtFilter;
        setSelectOptions(els.adminRateCourtFilter, [
            ['', 'All courts'],
            ...courts
        ], current);
        if (current && !courts.some(([id]) => id === current)) {
            adminRateCourtFilter = '';
            els.adminRateCourtFilter.value = '';
        }
    }
}

function renderAdminRateSummary() {
    if (!els.adminRateSummary || !state) return;
    bindAdminRateForm();
    const allRules = state.adminRateRules || [];
    populateAdminRateFilters(allRules);
    const rows = allRules
        .filter(rule =>
            (!adminRateSportFilter || rule.sport === adminRateSportFilter) &&
            (!adminRateCourtFilter || String(rule.courtId) === String(adminRateCourtFilter))
        )
        .sort((a, b) => {
            const courtCompare = String(a.courtName || 'All courts').localeCompare(String(b.courtName || 'All courts'));
            if (courtCompare) return courtCompare;
            const sportCompare = String(a.sport || 'All sports').localeCompare(String(b.sport || 'All sports'));
            if (sportCompare) return sportCompare;
            const dayCompare = rateDaySortValue(a.dayOfWeek || a.dayPattern || 'Any') - rateDaySortValue(b.dayOfWeek || b.dayPattern || 'Any');
            if (dayCompare) return dayCompare;
            return timeToMinutes(a.startsAt) - timeToMinutes(b.startsAt);
        });

    if (rows.length === 0) {
        els.adminRateSummary.innerHTML = `<tr><td colspan="6" class="text-secondary">${allRules.length === 0 ? 'No rates configured.' : 'No rates match the selected filters.'}</td></tr>`;
        return;
    }

    els.adminRateSummary.innerHTML = rows.map(rule => `
        <tr>
            <td>${escapeHtml(rule.courtName || 'All courts')}</td>
            <td>${escapeHtml(rule.sport || 'All sports')}</td>
            <td>${escapeHtml(rule.dayOfWeek === 'Any' || !rule.dayOfWeek ? 'Any day' : rule.dayOfWeek)}</td>
            <td>${formatRuleTime(rule.startsAt)}-${formatRuleTime(rule.endsAt)}</td>
            <td class="text-end">${peso.format(Number(rule.pricePerHour || 0))}</td>
            <td class="text-end">
                <div class="d-inline-flex gap-1">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-admin-rate-edit="${rule.id}">
                        Edit
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-admin-rate-delete="${rule.id}" data-admin-rate-label="${escapeHtml(`${rule.courtName || 'All courts'} ${rule.sport || 'All sports'} ${rule.dayOfWeek || 'Any'} ${formatRuleTime(rule.startsAt)}-${formatRuleTime(rule.endsAt)}`)}">
                        Delete
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    els.adminRateSummary.querySelectorAll('[data-admin-rate-edit]').forEach(button => {
        button.addEventListener('click', () => openAdminRateModal(button.dataset.adminRateEdit));
    });
    els.adminRateSummary.querySelectorAll('[data-admin-rate-delete]').forEach(button => {
        button.addEventListener('click', () => deleteRateRule(button));
    });
}

function renderAdminRateAudit() {
    if (!els.adminRateAudit || !state) return;
    const rows = state.adminRateAudit || [];
    if (rows.length === 0) {
        els.adminRateAudit.innerHTML = '<p class="text-sm font-bold text-muted">No rate changes logged yet.</p>';
        return;
    }

    els.adminRateAudit.innerHTML = rows.map(row => `
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm">
            <div>
                <p class="font-black">${escapeHtml(row.ruleName)} <span class="text-muted">was ${escapeHtml(row.action)}</span></p>
                <p class="text-xs font-semibold text-muted">${escapeHtml(row.reason || 'No reason provided')}</p>
            </div>
            <p class="text-xs font-bold text-muted">${new Date(row.createdAt).toLocaleString()} by ${escapeHtml(row.adminName)}</p>
        </div>
    `).join('');
}

function findAdminCourt(id) {
    return (state?.adminCourts || state?.courts || []).find(court => Number(court.id) === Number(id)) || null;
}

function renderAdminCourts() {
    if (!els.adminCourtManagement || !state) return;
    const courts = state.adminCourts || state.courts || [];
    const canManage = adminCanManageOperations();
    if (courts.length === 0) {
        els.adminCourtManagement.innerHTML = '<div class="rounded-xl border border-dashed border-line bg-white p-4 text-sm fw-bold text-secondary">No courts configured yet.</div>';
        return;
    }

    els.adminCourtManagement.innerHTML = `
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr class="small text-secondary">
                        <th>Order</th>
                        <th>Court</th>
                        <th>Sports</th>
                        <th class="text-center">Rates</th>
                        <th class="text-center">Active Bookings</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${courts.map(court => `
                        <tr>
                            <td class="fw-black">${court.number}</td>
                            <td>
                                <div class="fw-black text-primary">${escapeHtml(court.name)}</div>
                                <div class="text-xs fw-semibold text-muted">${escapeHtml(court.type || 'Court')}${court.surface ? ` | ${escapeHtml(court.surface)}` : ''}</div>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    ${(court.sports || []).map(sport => `<span class="status-badge bg-slate-100 text-primary">${escapeHtml(sport)}</span>`).join('')}
                                </div>
                            </td>
                            <td class="text-center fw-black">${court.rateCount ?? 0}</td>
                            <td class="text-center fw-black">${court.activeBookingCount ?? 0}</td>
                            <td><span class="status-badge ${court.isActive ? 'status-badge-booked' : 'status-badge-cancelled'}">${court.isActive ? 'Active' : 'Inactive'}</span></td>
                            <td class="text-end">
                                ${canManage ? `<button type="button" class="btn btn-outline-primary btn-sm" data-admin-court-edit="${court.id}">Edit</button>` : '<span class="text-xs fw-bold text-muted">View only</span>'}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    els.adminCourtManagement.querySelectorAll('[data-admin-court-edit]').forEach(button => {
        button.addEventListener('click', () => openAdminCourtModal(button.dataset.adminCourtEdit));
    });
}

function openAdminCourtModal(id = '') {
    if (!els.adminCourtForm) return;
    const court = id ? findAdminCourt(id) : null;
    els.adminCourtForm.reset();
    if (els.adminCourtModalTitle) els.adminCourtModalTitle.textContent = court ? 'Edit Court' : 'Add Court';
    if (els.adminCourtId) els.adminCourtId.value = court?.id || '';
    if (els.adminCourtDisplayNumber) els.adminCourtDisplayNumber.value = court?.number || ((state?.adminCourts || state?.courts || []).length + 1);
    if (els.adminCourtName) els.adminCourtName.value = court?.name || '';
    if (els.adminCourtType) els.adminCourtType.value = court?.type || 'Indoor';
    if (els.adminCourtSurface) els.adminCourtSurface.value = court?.surface || '';
    if (els.adminCourtActive) els.adminCourtActive.checked = court ? Boolean(court.isActive) : true;
    const sports = court?.sports || ['Pickleball'];
    els.adminCourtForm.querySelectorAll('input[name="sports[]"]').forEach(input => {
        input.checked = sports.includes(input.value);
    });
    const message = document.getElementById('adminCourtFormMessage');
    if (message) {
        message.textContent = '';
        message.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (window.bootstrap && els.adminCourtModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminCourtModal).show();
    }
}

async function submitAdminCourtForm(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const message = document.getElementById('adminCourtFormMessage');
    const formData = new FormData(form);
    if (!formData.has('isActive')) formData.set('isActive', '0');

    const response = await fetch(`${api}?action=admin-court-save`, { method: 'POST', body: formData });
    const payload = await response.json();
    if (message) {
        message.textContent = payload.message || 'Saved.';
        message.className = `rounded-md p-2 text-xs font-bold mt-3 ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }
    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Court saved successfully.');
        if (window.bootstrap && els.adminCourtModal) {
            bootstrap.Modal.getInstance(els.adminCourtModal)?.hide();
        }
    }
}

function renderAdminCourtBlocks() {
    if (!els.adminCourtBlocks || !state) return;
    const blocks = state.adminCourtBlocks || [];
    const slots = sortedCourtBlockSlots();
    const newBlock = {
        id: '',
        date: isoDate(new Date()),
        timeSlotId: slots[0]?.id || '',
        startsAt: slots[0]?.startsAt || '',
        endsAt: slots[0]?.endsAt || '',
        courtId: '',
        sport: '',
        reason: 'Maintenance',
        notes: '',
        status: 'Active'
    };

    const blockEndMinutes = value => String(value || '').startsWith('00:00') ? 1440 : timeToMinutes(value || '00:00');
    const groupedBlocks = Object.values(blocks.reduce((groups, block) => {
        const key = [
            block.date,
            block.courtId ?? 'all',
            block.sport || '',
            block.status || 'Active'
        ].join('|');
        if (!groups[key]) {
            groups[key] = {
                ...block,
                ids: [],
                reasons: new Set(),
                notesList: new Set(),
                startMinutes: timeToMinutes(block.startsAt || '00:00'),
                endMinutes: blockEndMinutes(block.endsAt),
                startsAt: block.startsAt,
                endsAt: block.endsAt
            };
        }
        groups[key].ids.push(block.id);
        if (block.reason) groups[key].reasons.add(block.reason);
        if (block.notes) groups[key].notesList.add(block.notes);
        const startMinutes = timeToMinutes(block.startsAt || '00:00');
        const endMinutes = blockEndMinutes(block.endsAt);
        if (startMinutes < groups[key].startMinutes) {
            groups[key].startMinutes = startMinutes;
            groups[key].startsAt = block.startsAt;
        }
        if (endMinutes > groups[key].endMinutes) {
            groups[key].endMinutes = endMinutes;
            groups[key].endsAt = block.endsAt;
        }
        return groups;
    }, {})).sort((a, b) =>
        String(b.date).localeCompare(String(a.date))
        || a.startMinutes - b.startMinutes
        || String(a.courtName || '').localeCompare(String(b.courtName || ''))
    );

    const blockScopeOptions = selected => {
        const value = blockScopeValue(selected);
        return [
            ['1|', 'Lakers'],
            ['2|', 'Miami'],
            ['3|Pickleball', 'Pickleball Pro Court 1'],
            ['4|Pickleball', 'Pickleball Pro Court 2'],
            ['5|Pickleball', 'Pickleball Pro Court 3'],
            ['6|Pickleball', 'Pickleball Pro Court 4'],
            ['7|Pickleball', 'Wooden Court 5'],
            ['8|Pickleball', 'Wooden Court 6'],
            ['9|Pickleball', 'Wooden Court 7']
        ].map(([scope, label]) => `<option value="${scope}" ${scope === value ? 'selected' : ''}>${label}</option>`).join('');
    };
    const startOptions = selected => slots.map(slot =>
        `<option value="${escapeHtml(slot.startsAt)}" ${String(selected || '') === String(slot.startsAt) ? 'selected' : ''}>${escapeHtml(formatRuleTime(slot.startsAt))}</option>`
    ).join('');
    const endOptions = selected => slots.map(slot =>
        `<option value="${escapeHtml(slot.endsAt)}" ${String(selected || '') === String(slot.endsAt) ? 'selected' : ''}>${escapeHtml(formatRuleTime(slot.endsAt))}</option>`
    ).join('');
    const blockStart = block => block.startsAt || state?.slotDetails?.[block.time]?.startsAt || newBlock.startsAt;
    const blockEnd = block => block.endsAt || state?.slotDetails?.[block.time]?.endsAt || newBlock.endsAt;

    const formFor = block => `
        <form class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" data-court-block-form>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-black uppercase tracking-[.14em] text-court">${block.id ? 'Court Block' : 'Add Court Block'}</p>
                    <h3 class="mt-1 text-2xl font-black">${escapeHtml(block.id ? `${block.courtName} - ${compactTime(block.time)}` : 'Block availability')}</h3>
                    ${block.id ? `<p class="mt-1 text-xs font-bold text-muted">Created ${new Date(block.createdAt).toLocaleString()} by ${escapeHtml(block.createdByName)}</p>` : ''}
                </div>
                <label class="flex items-center gap-2 text-sm font-black">
                    <input type="checkbox" name="isActive" value="1" ${block.status !== 'Cancelled' ? 'checked' : ''}>
                    Active
                </label>
            </div>
            <input type="hidden" name="id" value="${block.id || ''}">
            <div class="grid gap-3 md:grid-cols-4">
                <label class="grid gap-2 text-sm font-bold">Date
                    <input required type="date" name="blockDate" value="${block.date || newBlock.date}" class="form-input">
                </label>
                <input type="hidden" name="timeSlotId" value="${block.timeSlotId || newBlock.timeSlotId}">
                <label class="grid gap-2 text-sm font-bold">Start time
                    <select required name="startTime" class="form-select">${startOptions(blockStart(block))}</select>
                </label>
                <label class="grid gap-2 text-sm font-bold">End time
                    <select required name="endTime" class="form-select">${endOptions(blockEnd(block))}</select>
                </label>
                <label class="grid gap-2 text-sm font-bold">Block scope
                    <select name="blockScope" class="form-select">${blockScopeOptions(block)}</select>
                </label>
                <label class="grid gap-2 text-sm font-bold">Reason
                    <select name="reason" class="form-select">
                        ${['Maintenance', 'Private event', 'Tournament', 'Cleaning', 'Construction', 'Club activity'].map(reason => `<option value="${reason}" ${block.reason === reason ? 'selected' : ''}>${reason}</option>`).join('')}
                    </select>
                </label>
            </div>
            <label class="grid gap-2 text-sm font-bold">Notes
                <input name="notes" value="${escapeHtml(block.notes || '')}" class="form-input" placeholder="Optional operations note">
            </label>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="${block.status === 'Cancelled' ? 'status-badge-cancelled' : 'status-badge-pending'}">${escapeHtml(block.status || 'Active')}</span>
                <button class="btn btn-primary">${block.id ? 'Save Block' : 'Create Block'}</button>
            </div>
            <div class="hidden rounded-md p-3 text-sm font-bold" data-court-block-message></div>
        </form>
    `;

    const table = groupedBlocks.length === 0
        ? '<div class="rounded-lg border border-dashed border-line bg-white p-5 text-sm fw-bold text-secondary">No court blockings have been added yet.</div>'
        : `
            <div class="table-responsive">
                <table class="table table-hover align-middle admin-members-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Court</th>
                            <th scope="col">Time Range</th>
                            <th scope="col">Reason</th>
                            <th scope="col">Notes</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created By</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${groupedBlocks.map(block => {
                            const active = block.status !== 'Cancelled';
                            return `
                            <tr>
                                <td>
                                    <div class="fw-black text-ink">${escapeHtml(niceDate(block.date))}</div>
                                    <div class="text-xs fw-bold text-muted">${escapeHtml(block.date)}</div>
                                </td>
                                <td>
                                    <div class="fw-black text-ink">${escapeHtml(block.courtName || 'All courts')}</div>
                                    <div class="text-xs fw-bold text-muted">${escapeHtml(block.sport || 'All sports')}</div>
                                </td>
                                <td class="fw-black">${escapeHtml(formatRuleTime(block.startsAt))} - ${escapeHtml(formatRuleTime(block.endsAt))}</td>
                                <td>${escapeHtml([...block.reasons].join(', ') || 'Court Blocking')}</td>
                                <td class="text-secondary fw-semibold">${escapeHtml([...block.notesList].join('; ') || '-')}</td>
                                <td><span class="${active ? 'status-badge-pending' : 'status-badge-cancelled'}">${active ? 'Active' : 'Inactive'}</span></td>
                                <td>
                                    <div class="fw-bold">${escapeHtml(block.createdByName || 'System')}</div>
                                    <div class="text-xs fw-bold text-muted">${block.ids.length} slot${block.ids.length === 1 ? '' : 's'}</div>
                                </td>
                                <td class="text-end">
                                    <button type="button"
                                        class="btn ${active ? 'btn-outline-secondary' : 'btn-primary'} btn-sm"
                                        data-court-block-status="${block.ids.join(',')}"
                                        data-is-active="${active ? '0' : '1'}">
                                        ${active ? 'Set Inactive' : 'Set Active'}
                                    </button>
                                </td>
                            </tr>
                        `}).join('')}
                    </tbody>
                </table>
            </div>
        `;

    els.adminCourtBlocks.innerHTML = `${formFor(newBlock)}${table}`;
    els.adminCourtBlocks.querySelectorAll('[data-court-block-form]').forEach(form => {
        const syncFallbackSlot = () => {
            const start = form.querySelector('[name="startTime"]')?.value || '';
            const fallback = slots.find(slot => slot.startsAt === start) || slots[0];
            const input = form.querySelector('[name="timeSlotId"]');
            if (input && fallback) input.value = fallback.id;
        };
        form.querySelector('[name="startTime"]')?.addEventListener('change', syncFallbackSlot);
        syncFallbackSlot();
        form.addEventListener('submit', submitCourtBlock);
    });
    els.adminCourtBlocks.querySelectorAll('[data-court-block-status]').forEach(button => {
        button.addEventListener('click', () => submitCourtBlockStatus(button));
    });
}

function blockScopeValue(block) {
    if (!block || block.courtId === null || block.courtId === undefined || block.courtId === '') return '2|';
    return `${Number(block.courtId)}|${block.sport || ''}`;
}

function renderAdminOverrideLogs() {
    if (!els.adminOverrideLogs || !state) return;
    const rows = state.adminOverrideLogs || [];
    if (rows.length === 0) {
        els.adminOverrideLogs.innerHTML = '<p class="text-sm font-bold text-muted">No override decisions logged yet.</p>';
        return;
    }

    els.adminOverrideLogs.innerHTML = rows.map(row => `
        <div class="rounded-lg bg-white px-3 py-2 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-black">${escapeHtml(row.action)} <span class="text-muted">#${escapeHtml(row.targetId || '')}</span></p>
                <p class="text-xs font-bold text-muted">${new Date(row.createdAt).toLocaleString()} by ${escapeHtml(row.adminName)}</p>
            </div>
            <p class="mt-1 text-xs font-semibold text-muted">${escapeHtml(row.conflictSummary || 'No conflict details')}</p>
        </div>
    `).join('');
}

function openCourtModal(data) {
    if (!els.modal) return;
    const slots = selectedBookingSlots.length > 0 ? selectedBookingSlots : [{
        date: data.bookDate,
        time: data.bookTime,
        court: Number(data.bookCourt),
        courtName: data.bookCourtName || `Court ${data.bookCourt}`,
        sport: data.bookSport || selectedSport
    }];
    activeBookingSlots = [...slots];
    const first = slots[0];
    bookingModalCloseUnlocked = false;
    els.modalKicker.textContent = 'Court Booking Payment';
    els.modalTitle.textContent = slots.length === 1
        ? `${first.courtName} - ${first.sport}`
        : `${slots.length} selected court slots`;
    els.modalMeta.innerHTML = bookingDetailsTable(slots, true);
    showModal();
    document.getElementById('actionType').value = 'book';
    document.getElementById('formDate').value = first.date;
    document.getElementById('formTime').value = first.time;
    document.getElementById('formCourt').value = first.court;
    document.getElementById('formSport').value = first.sport;
    document.getElementById('formSessionId').value = '';
    renderBookingReviewSummary();
    setBookingModalStep(0);
}

function showModal() {
    els.form.reset();
    stopBookingPaymentTimer();
    if (els.modalSubmitButton) {
        els.modalSubmitButton.disabled = false;
        els.modalSubmitButton.type = 'submit';
        els.modalSubmitButton.textContent = 'Submit Reservation';
        delete els.modalSubmitButton.dataset.done;
    }
    renderPaymentInstructions('');
    if (els.bookingReferencePanel) {
        els.bookingReferencePanel.classList.add('hidden');
        els.bookingReferencePanel.innerHTML = '';
    }
    showFormMessage('', true, true);
    els.modal.classList.remove('hidden');
    els.modal.classList.add('flex');
    document.body.classList.add('modal-open');
}

function closeModal() {
    if (els.modalSubmitButton?.dataset.done === '1') {
        showFormMessage('Please click Done to finish this booking process.', false);
        return;
    }
    if (!bookingModalCloseUnlocked) {
        showFormMessage('Please click Proceed before closing this booking process.', false);
        return;
    }
    els.modal.classList.add('hidden');
    els.modal.classList.remove('flex');
    document.body.classList.remove('modal-open');
    stopBookingPaymentTimer();
    activeBookingSlots = [];
    bookingModalCloseUnlocked = false;
}

function showFormMessage(message, ok, hide = false) {
    if (!els.formMessage) return;
    if (hide || !message) {
        els.formMessage.textContent = '';
        els.formMessage.className = 'hidden modal-message';
        return;
    }
    els.formMessage.textContent = message;
    els.formMessage.className = `modal-message ${ok ? 'modal-message-ok' : 'modal-message-error'}`;
}

async function submitPayment(event) {
    event.preventDefault();
    const inlineForm = isInlineBookingForm();
    if (inlineForm) {
        if (selectedBookingSlots.length === 0) {
            showFormMessage('Select at least one available slot before confirming.', false);
            return;
        }
        activeBookingSlots = [...selectedBookingSlots];
        if (!els.form.checkValidity()) {
            els.form.reportValidity();
            return;
        }
    } else if (!validateModalStep()) {
        return;
    }
    if (bookingPaymentWindowExpired()) {
        showFormMessage('The 15-minute payment window has expired. Select the payment channel again to restart the timer before confirming.', false);
        if (els.modalSubmitButton) els.modalSubmitButton.disabled = true;
        updateBookingPaymentTimerDisplay();
        return;
    }
    if (els.modalSubmitButton) els.modalSubmitButton.disabled = true;
    const endpoint = 'book';
    const slots = activeBookingSlots.length > 0 ? [...activeBookingSlots] : [{
        date: document.getElementById('formDate').value,
        time: document.getElementById('formTime').value,
        court: Number(document.getElementById('formCourt').value),
        sport: document.getElementById('formSport').value
    }];
    let saved = 0;
    let lastPayload = null;
    const references = [];
    const reservationReference = bookingReferencePrefix();

    for (const [index, slot] of slots.entries()) {
        const formData = new FormData(els.form);
        formData.set('action', endpoint);
        formData.set('date', slot.date);
        formData.set('time', slot.time);
        formData.set('court', slot.court);
        formData.set('sport', slot.sport);
        formData.set('bookingReference', reservationReference);

        const response = await fetch(`${api}?action=${endpoint}`, { method: 'POST', body: formData });
        const payload = await response.json();
        lastPayload = payload;
        if (!payload.ok) {
            showFormMessage(`${saved} of ${slots.length} saved. ${payload.message || 'Could not save one of the selected slots.'}`, false);
            if (payload.state) state = payload.state;
            renderAll();
            if (els.modalSubmitButton) els.modalSubmitButton.disabled = false;
            return;
        }
        saved += 1;
        if (payload.bookingReference && !references.includes(payload.bookingReference)) references.push(payload.bookingReference);
        state = payload.state;
    }

    if (els.bookingReferencePanel && references.length > 0) {
        if (!inlineForm) {
            bookingModalCloseUnlocked = true;
            setBookingModalStep(bookingModalStep);
        }
        const messengerUrl = String(state?.siteConfig?.messenger_url || '').trim();
        const messengerText = messengerUrl
            ? `<a href="${escapeHtml(messengerUrl)}" target="_blank" rel="noopener" class="text-primary">Facebook Messenger</a>`
            : 'Facebook Messenger';
        els.bookingReferencePanel.classList.remove('hidden');
        els.bookingReferencePanel.innerHTML = `
            <p class="modal-info-title">Booking reference${references.length === 1 ? '' : 's'} generated</p>
            <ul class="booking-reference-list">
                ${references.map(reference => `<li><strong>${escapeHtml(reference)}</strong></li>`).join('')}
            </ul>
            ${state?.member ? '<p>Admin will verify your uploaded proof and confirm the booking.</p>' : `<p>Send these references with your payment proof through ${messengerText}.</p>`}
        `;
    }
    showFormMessage(slots.length > 1 ? `${saved} slots submitted. Admin will review the reservation details.` : (lastPayload?.message || 'Saved.'), true);

    if (saved === slots.length) {
        stopBookingPaymentTimer();
        clearBookingSelection();
        renderAll();
        if (inlineForm) {
            if (els.bookingReferencePanel && references.length > 0) {
                els.bookingReferencePanel.classList.remove('hidden');
                els.bookingReferencePanel.innerHTML = `
                    <p class="modal-info-title">Booking reference${references.length === 1 ? '' : 's'} generated</p>
                    <ul class="booking-reference-list">
                        ${references.map(reference => `<li><strong>${escapeHtml(reference)}</strong></li>`).join('')}
                    </ul>
                    <p>${state?.member ? 'Admin will verify your uploaded proof and confirm the booking.' : 'Send these references with your payment proof.'}</p>
                `;
            }
            if (els.modalSubmitButton) {
                els.modalSubmitButton.disabled = true;
                els.modalSubmitButton.type = 'submit';
                els.modalSubmitButton.textContent = 'Confirm Reservation';
            }
            return;
        }
        if (els.modalSubmitButton) {
            els.modalSubmitButton.disabled = false;
            els.modalSubmitButton.type = 'button';
            els.modalSubmitButton.textContent = 'Done';
            els.modalSubmitButton.dataset.done = '1';
            setBookingModalStep(bookingModalStep);
        }
    }
}

function renderAdminMembers() {
    if (!els.adminMembers || !state) return;
    const query = adminMemberSearch.trim().toLowerCase();
    const members = (state.adminMembers || []).filter(member => !query || memberSearchText(member).includes(query));
    const canManage = adminCanManageMembers();

    if (members.length === 0) {
        els.adminMembers.innerHTML = '<div class="rounded-xl border border-dashed border-line bg-white p-5 text-sm font-bold text-muted">No members match the current search.</div>';
        return;
    }

    els.adminMembers.innerHTML = `
        <div class="table-responsive">
            <table class="table table-hover align-middle admin-members-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Member</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Skill / Consent</th>
                        <th scope="col">Activity</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${members.map(member => `
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="admin-member-avatar">
                                        ${member.profilePicture
                                            ? `<img src="${escapeHtml(resourceUrl(member.profilePicture))}" alt="">`
                                            : escapeHtml((member.nickname || member.name || 'P').charAt(0).toUpperCase())}
                                    </div>
                                    <div>
                                        <div class="fw-black text-ink">${escapeHtml(member.name)}</div>
                                        <div class="text-xs font-semibold text-muted">${escapeHtml(member.nickname || 'No nickname')} | Member #${member.id}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold">${escapeHtml(member.email)}</div>
                                <div class="text-xs font-semibold text-muted">${escapeHtml(member.phone || 'No phone')}</div>
                            </td>
                            <td>
                                <div class="fw-bold">${escapeHtml(member.skillLabel || 'No level')}</div>
                                <div class="text-xs font-semibold ${member.termsConditionsAgree ? 'text-success' : 'text-danger'}">${member.termsConditionsAgree ? 'Terms accepted' : 'Terms missing'}</div>
                                <div class="text-xs font-semibold ${member.dataPrivacyActAgree ? 'text-success' : 'text-danger'}">${member.dataPrivacyActAgree ? 'Privacy consent recorded' : 'Privacy consent missing'}</div>
                            </td>
                            <td class="text-sm fw-semibold text-secondary">
                                ${member.lastLoginAt ? `Last login ${new Date(member.lastLoginAt).toLocaleDateString()}` : `Joined ${new Date(member.createdAt).toLocaleDateString()}`}
                            </td>
                            <td>
                                <span class="status-badge ${member.isActive ? 'status-badge-booked' : 'status-badge-cancelled'}">${member.isActive ? 'Active' : 'Inactive'}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    ${canManage ? `
                                        <button type="button" data-admin-member-edit="${member.id}" class="btn btn-outline-primary btn-sm">Edit</button>
                                        <button type="button" data-admin-member-qr="${member.id}" class="btn btn-outline-secondary btn-sm">QR</button>
                                        <button type="button" data-admin-member-fee="${member.id}" class="btn btn-primary btn-sm">Pay Entrance Fee</button>
                                        <button type="button" data-admin-member-id="${member.id}" data-is-active="${member.isActive ? '0' : '1'}" class="btn ${member.isActive ? 'btn-outline-danger' : 'btn-success'} btn-sm">
                                            ${member.isActive ? 'Deactivate' : 'Activate'}
                                        </button>
                                    ` : '<span class="text-xs text-secondary">View only</span>'}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    els.adminMembers.querySelectorAll('[data-admin-member-id]').forEach(button => {
        button.addEventListener('click', () => submitAdminMemberStatus(button));
    });
    els.adminMembers.querySelectorAll('[data-admin-member-edit]').forEach(button => {
        button.addEventListener('click', () => openAdminMemberModal(button.dataset.adminMemberEdit));
    });
    els.adminMembers.querySelectorAll('[data-admin-member-qr]').forEach(button => {
        button.addEventListener('click', () => openAdminMemberQr(button.dataset.adminMemberQr));
    });
    els.adminMembers.querySelectorAll('[data-admin-member-fee]').forEach(button => {
        button.addEventListener('click', () => openEntranceFeeModal(button.dataset.adminMemberFee));
    });
}

function findAdminMember(id) {
    return (state?.adminMembers || []).find(member => Number(member.id) === Number(id)) || null;
}

function openAdminMemberModal(id = '') {
    if (!els.adminMemberForm) return;
    const member = id ? findAdminMember(id) : null;
    els.adminMemberForm.reset();
    if (els.adminMemberModalTitle) {
        els.adminMemberModalTitle.textContent = member ? 'Edit Member' : 'Add Member';
    }
    const set = (name, value) => {
        const field = els.adminMemberForm.querySelector(`[name="${name}"]`);
        if (field) field.value = value ?? '';
    };
    set('id', member?.id || '');
    set('name', member?.name || '');
    set('nickname', member?.nickname || '');
    set('phone', member?.phone || '');
    set('email', member?.email || '');
    set('birthMonth', member?.birthMonth || '');
    set('birthYear', member?.birthYear || '');
    set('skillLevel', member?.skillLevel || '');
    set('password', '');
    set('confirmPassword', '');
    const password = els.adminMemberForm.querySelector('[name="password"]');
    if (password) password.required = !member;
    const confirmPassword = els.adminMemberForm.querySelector('[name="confirmPassword"]');
    if (confirmPassword) confirmPassword.required = !member;
    const preview = document.getElementById('adminMemberProfilePreview');
    if (preview) {
        if (member?.profilePicture) {
            preview.innerHTML = `<img src="${escapeHtml(resourceUrl(member.profilePicture))}" alt="">`;
        } else {
            preview.textContent = (member?.nickname || member?.name || 'P').charAt(0).toUpperCase();
        }
    }
    const pictureInput = document.getElementById('adminMemberProfilePicture');
    if (pictureInput) pictureInput.value = '';
    const active = els.adminMemberForm.querySelector('[name="isActive"]');
    if (active) active.checked = member ? Boolean(member.isActive) : true;
    const terms = els.adminMemberForm.querySelector('[name="termsConditionsAgree"]');
    if (terms) terms.checked = member ? Boolean(member.termsConditionsAgree) : false;
    const consent = els.adminMemberForm.querySelector('[name="dataPrivacyActAgree"]');
    if (consent) consent.checked = member ? Boolean(member.dataPrivacyActAgree) : false;
    const message = document.getElementById('adminMemberFormMessage');
    if (message) {
        message.textContent = '';
        message.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (window.bootstrap && els.adminMemberModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminMemberModal).show();
    }
}

async function submitAdminMemberForm(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const message = document.getElementById('adminMemberFormMessage');
    const formData = new FormData(form);
    const password = form.querySelector('[name="password"]')?.value || '';
    const confirmPassword = form.querySelector('[name="confirmPassword"]')?.value || '';
    if (password && password !== confirmPassword) {
        if (message) {
            message.textContent = 'Password confirmation does not match.';
            message.className = 'rounded-md p-2 text-xs font-bold mt-3 bg-rose-50 text-rose-700';
        }
        return;
    }
    formData.set('isActive', form.querySelector('[name="isActive"]').checked ? '1' : '0');
    formData.set('termsConditionsAgree', form.querySelector('[name="termsConditionsAgree"]').checked ? '1' : '0');
    formData.set('dataPrivacyActAgree', form.querySelector('[name="dataPrivacyActAgree"]').checked ? '1' : '0');

    const response = await fetch(`${api}?action=admin-member-save`, { method: 'POST', body: formData });
    const payload = await response.json();
    if (message) {
        message.textContent = payload.message || 'Saved.';
        message.className = `rounded-md p-2 text-xs font-bold mt-3 ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }
    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Member saved successfully.');
        if (window.bootstrap && els.adminMemberModal) {
            bootstrap.Modal.getInstance(els.adminMemberModal)?.hide();
        }
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    }
}

function openAdminMemberQr(id) {
    const member = findAdminMember(id);
    if (!member || !els.adminMemberQrBody) return;
    const payload = member.qrPayload || `member=${member.lookupToken}`;
    if (els.adminMemberQrTitle) {
        els.adminMemberQrTitle.textContent = `${member.name} QR Code`;
    }
    els.adminMemberQrBody.innerHTML = `
        <div class="admin-member-qr-panel">
            <img src="${escapeHtml(qrImageUrl(payload))}" alt="${escapeHtml(member.name)} member QR code">
            <dl class="admin-detail-list">
                <div><dt>Name</dt><dd>${escapeHtml(member.name)}</dd></div>
                <div><dt>Nickname</dt><dd>${escapeHtml(member.nickname || 'N/A')}</dd></div>
                <div><dt>Phone</dt><dd>${escapeHtml(member.phone || 'N/A')}</dd></div>
                <div><dt>Email</dt><dd>${escapeHtml(member.email || 'N/A')}</dd></div>
            </dl>
            <p class="text-xs fw-bold text-secondary mb-0">Payload: ${escapeHtml(payload)}</p>
        </div>
    `;
    if (window.bootstrap && els.adminMemberQrModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminMemberQrModal).show();
    }
}

function openEntranceFeeModal(id) {
    const member = findAdminMember(id);
    if (!member || !els.adminEntranceFeeForm) return;
    els.adminEntranceFeeForm.reset();
    if (els.adminEntranceMemberId) els.adminEntranceMemberId.value = member.id;
    if (els.adminEntranceMemberSummary) {
        els.adminEntranceMemberSummary.textContent = `${member.name} | ${member.phone || member.email}`;
    }
    const now = new Date();
    const date = isoDate(now);
    const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    els.adminEntranceFeeForm.querySelector('[name="amount"]').value = '50.00';
    els.adminEntranceFeeForm.querySelector('[name="paymentDate"]').value = date;
    els.adminEntranceFeeForm.querySelector('[name="paymentTime"]').value = time;
    const message = els.adminEntranceFeeMessage;
    if (message) {
        message.textContent = '';
        message.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (window.bootstrap && els.adminEntranceFeeModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminEntranceFeeModal).show();
    }
}

async function submitEntranceFee(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const response = await fetch(`${api}?action=admin-entrance-fee`, { method: 'POST', body: new FormData(form) });
    const payload = await response.json();
    if (els.adminEntranceFeeMessage) {
        els.adminEntranceFeeMessage.textContent = payload.message || 'Saved.';
        els.adminEntranceFeeMessage.className = `rounded-md p-2 text-xs font-bold mt-3 ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }
    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Entrance fee payment recorded successfully.');
        if (window.bootstrap && els.adminEntranceFeeModal) {
            bootstrap.Modal.getInstance(els.adminEntranceFeeModal)?.hide();
        }
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    }
}

async function submitQrLookup(event) {
    event.preventDefault();
    const payloadValue = String(els.adminQrPayload?.value || '').trim();
    if (!payloadValue) {
        els.adminQrPayload?.focus();
        return;
    }
    const formData = new FormData();
    formData.set('qrPayload', payloadValue);
    const response = await fetch(`${api}?action=admin-member-lookup`, { method: 'POST', body: formData });
    const payload = await response.json();
    if (els.adminQrScanMessage) {
        els.adminQrScanMessage.textContent = payload.message || 'Lookup complete.';
        els.adminQrScanMessage.className = `rounded-md p-2 text-xs font-bold mt-3 ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }
    if (payload.ok) {
        state = payload.state;
        adminMemberSearch = String(findAdminMember(payload.memberId)?.lookupToken || payloadValue);
        if (els.adminMemberSearch) els.adminMemberSearch.value = adminMemberSearch;
        renderAll();
        showAdminToast(payload.message || 'Member lookup submitted successfully.');
        if (window.bootstrap && els.adminQrScanModal) {
            bootstrap.Modal.getInstance(els.adminQrScanModal)?.hide();
        }
    }
}

async function startQrCamera() {
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia || !els.adminQrVideo) {
        alert('Camera QR scanning is not available in this browser. Paste the QR payload instead.');
        return;
    }
    adminQrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    els.adminQrVideo.srcObject = adminQrStream;
    els.adminQrVideo.hidden = false;
    await els.adminQrVideo.play();
    const detector = new BarcodeDetector({ formats: ['qr_code'] });
    const scan = async () => {
        if (!adminQrStream) return;
        const codes = await detector.detect(els.adminQrVideo).catch(() => []);
        if (codes.length > 0) {
            els.adminQrPayload.value = codes[0].rawValue || '';
            stopQrCamera();
            return;
        }
        requestAnimationFrame(scan);
    };
    scan();
}

function stopQrCamera() {
    adminQrStream?.getTracks().forEach(track => track.stop());
    adminQrStream = null;
    if (els.adminQrVideo) {
        els.adminQrVideo.hidden = true;
        els.adminQrVideo.srcObject = null;
    }
}

function adminUserTableRow(user = null) {
    const isNew = !user;
    const row = user || {
        id: '',
        name: '',
        email: '',
        role: 'reception',
        isActive: true,
        lastLoginAt: null,
        createdAt: new Date().toISOString()
    };
    const roleOptions = state?.adminRoleOptions || { super_admin: 'Super Admin', admin: 'Admin', reception: 'Reception', executive: 'Executive' };
    const isSuperAdmin = row.role === 'super_admin';
    const roleField = isSuperAdmin
        ? `<input type="hidden" name="role" value="super_admin"><span class="status-badge status-badge-booked">Super Admin</span>`
        : `<select name="role" class="form-select">
            ${Object.entries(roleOptions).map(([value, label]) => `<option value="${escapeHtml(value)}" ${row.role === value ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('')}
        </select>`;

    return `
        <tr data-admin-user-row>
            <td>
                <input type="hidden" name="id" value="${row.id || ''}">
                <div class="fw-black text-ink">${isNew ? 'New user' : `Admin #${row.id}`}</div>
                <div class="text-xs font-semibold text-muted">${isNew ? 'Create staff/admin access' : (row.lastLoginAt ? `Last login ${new Date(row.lastLoginAt).toLocaleDateString()}` : `Created ${new Date(row.createdAt).toLocaleDateString()}`)}</div>
            </td>
            <td><input required name="name" value="${escapeHtml(row.name)}" class="form-input" placeholder="Staff name"></td>
            <td><input required type="email" name="email" value="${escapeHtml(row.email)}" class="form-input" placeholder="staff@example.com"></td>
            <td>${roleField}</td>
            <td><input ${isNew ? 'required' : ''} type="password" name="password" class="form-input" minlength="8" placeholder="${isNew ? 'Required' : 'Leave blank'}"></td>
            <td class="text-center">
                <label class="d-inline-flex align-items-center justify-content-center gap-2 fw-black mb-0">
                    <input type="checkbox" name="isActive" value="1" ${row.isActive ? 'checked' : ''}>
                    <span class="status-badge ${row.isActive ? 'status-badge-booked' : 'status-badge-cancelled'}">${row.isActive ? 'Active' : 'Inactive'}</span>
                </label>
            </td>
            <td class="text-end">
                <button class="btn btn-primary btn-sm" type="button" data-admin-user-save>${isNew ? 'Create' : 'Save'}</button>
                <div class="hidden mt-1 rounded-md p-1 text-xs font-bold text-start" data-admin-user-message></div>
            </td>
        </tr>
    `;
}

function renderAdminUsers() {
    if (!els.adminUsers || !state) return;
    if (!adminCanManageStaff()) {
        els.adminUsers.innerHTML = '';
        return;
    }
    const users = state.adminUsers || [];
    els.adminUsers.innerHTML = `
        <div class="table-responsive">
            <table class="table table-hover align-middle admin-members-table admin-users-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Account</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Password</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${adminUserTableRow(null)}
                    ${users.map(user => adminUserTableRow(user)).join('')}
                </tbody>
            </table>
        </div>
    `;
    els.adminUsers.querySelectorAll('[data-admin-user-save]').forEach(button => {
        button.addEventListener('click', () => submitAdminUserRow(button));
    });
}

function renderAdminRolePermissions() {
    if (!els.adminRolePermissions || !state) return;
    if (!adminCanManageStaff()) {
        els.adminRolePermissions.innerHTML = '';
        return;
    }

    const roles = Object.fromEntries(Object.entries(state.adminRoleOptions || {}).filter(([role]) => role !== 'super_admin'));
    const menus = state.adminMenuCatalog || [];
    const permissions = state.adminRoleMenuPermissions || {};
    els.adminRolePermissions.innerHTML = Object.entries(roles).map(([role, label]) => {
        const rolePermissions = permissions[role] || {};
        const menuItems = menus.map(menu => {
            const forced = menu.key === 'admin'
                || (role === 'reception' && menu.key === 'admin-members')
                || (role === 'admin' && menu.key === 'admin-members')
                || (role === 'executive' && ['admin-members', 'admin-reports'].includes(menu.key));
            const checked = menu.key === 'admin'
                || (role === 'reception' && menu.key === 'admin-members')
                || (role === 'admin' && menu.key === 'admin-members')
                || (role === 'executive' && menu.key === 'admin-reports')
                ? true
                : Boolean(rolePermissions[menu.key]);
            return `
                <label class="admin-role-menu-item ${forced ? 'is-locked' : ''}">
                    <input type="checkbox"
                        name="menus[]"
                        value="${escapeHtml(menu.key)}"
                        ${checked ? 'checked' : ''}
                        ${forced ? 'disabled' : ''}>
                    <span>
                        <strong>${escapeHtml(menu.label)}</strong>
                        <small>${escapeHtml(role === 'executive' && menu.key === 'admin-members'
                            ? 'Executive role has no Users / Members access.'
                            : role === 'executive' && menu.key === 'admin-reports'
                                ? 'Executive role always has report access.'
                            : role === 'reception' && menu.key === 'admin-members'
                                ? 'Reception always has full member management access.'
                            : role === 'admin' && menu.key === 'admin-members'
                                ? 'Admin always has member management access.'
                                : (menu.sub || ''))}</small>
                    </span>
                </label>
            `;
        }).join('');

        return `
            <form class="admin-role-permission-card" data-admin-role-permission-form>
                <input type="hidden" name="role" value="${escapeHtml(role)}">
                <div class="admin-role-permission-head">
                    <div>
                        <h3>${escapeHtml(label)}</h3>
                        <p>${role === 'reception'
                            ? 'Reception can view members when Users / Members is enabled.'
                            : role === 'executive'
                                ? 'Executive can be given operational menus, excluding Users / Members.'
                                : 'Admin can manage members and operational menus, but not staff access.'}</p>
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                </div>
                <div class="admin-role-menu-grid">${menuItems}</div>
                <div class="hidden mt-2 rounded-md p-2 text-xs font-bold" data-admin-role-permission-message></div>
            </form>
        `;
    }).join('');

    els.adminRolePermissions.querySelectorAll('[data-admin-role-permission-form]').forEach(form => {
        form.addEventListener('submit', submitAdminRolePermissions);
    });
}

async function submitAdminMemberStatus(button) {
    const formData = new FormData();
    formData.append('id', button.dataset.adminMemberId);
    formData.append('isActive', button.dataset.isActive);

    button.disabled = true;
    const response = await fetch(`${api}?action=admin-member-status`, { method: 'POST', body: formData });
    const payload = await response.json();
    button.disabled = false;

    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Member status updated successfully.');
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    } else {
        alert(payload.message || 'Could not update member.');
    }
}

async function submitAdminUserRow(button) {
    const row = button.closest('[data-admin-user-row]');
    const message = row.querySelector('[data-admin-user-message]');
    const fields = row.querySelectorAll('input, select');

    for (const field of fields) {
        if (!field.reportValidity()) {
            return;
        }
    }

    const formData = new FormData();
    ['id', 'name', 'email', 'role', 'password'].forEach(name => {
        formData.set(name, row.querySelector(`[name="${name}"]`)?.value || '');
    });
    formData.set('isActive', row.querySelector('[name="isActive"]').checked ? '1' : '0');

    button.disabled = true;
    const response = await fetch(`${api}?action=admin-user-save`, { method: 'POST', body: formData });
    const payload = await response.json();
    button.disabled = false;

    message.textContent = payload.message || 'Saved.';
    message.className = `mt-1 rounded-md p-1 text-xs font-bold text-start ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;

    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Staff access saved successfully.');
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    }
}

async function submitAdminRolePermissions(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('[data-admin-role-permission-message]');
    const button = form.querySelector('button[type="submit"]');
    const formData = new FormData(form);

    button.disabled = true;
    const response = await fetch(`${api}?action=admin-role-menu-permissions`, { method: 'POST', body: formData });
    const payload = await response.json();
    button.disabled = false;

    if (message) {
        message.textContent = payload.message || (payload.ok ? 'Menu access saved.' : 'Could not save menu access.');
        message.className = `mt-2 rounded-md p-2 text-xs font-bold ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }

    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Menu access saved successfully.');
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    }
}

async function updateStatus(id, status) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', status);

    const response = await fetch(`${api}?action=admin-status`, { method: 'POST', body: formData });
    const payload = await response.json();
    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Reservation status updated successfully.');
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    } else {
        alert(payload.message || 'Could not update status.');
    }
}

function openCancelReservationModal(id) {
    const item = reservationById(id);
    if (!item || !els.adminCancelReservationForm) return;
    els.adminCancelReservationForm.reset();
    els.adminCancelReservationId.value = id;
    if (els.adminCancelReservationSummary) {
        const schedule = (item.timeRanges || [{ label: `${niceDate(item.date)} - ${compactTime(item.time)}` }]).map(range => range.label).join('; ');
        els.adminCancelReservationSummary.textContent = `${item.bookingReference || 'No reference'} | ${schedule} | ${item.courtName || `Court ${item.court}`} | ${item.status}`;
    }
    if (els.adminCancelReservationMessage) {
        els.adminCancelReservationMessage.textContent = '';
        els.adminCancelReservationMessage.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (window.bootstrap && els.adminCancelReservationModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminCancelReservationModal).show();
    }
}

async function submitCancelReservation(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const reason = String(form.querySelector('[name="reason"]')?.value || '').trim();
    if (!reason) {
        form.reportValidity();
        return;
    }
    const formData = new FormData(form);
    formData.set('status', 'Cancelled');

    const response = await fetch(`${api}?action=admin-status`, { method: 'POST', body: formData });
    const payload = await response.json();
    if (els.adminCancelReservationMessage) {
        els.adminCancelReservationMessage.textContent = payload.message || 'Saved.';
        els.adminCancelReservationMessage.className = `rounded-md p-2 text-xs font-bold mt-3 ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }
    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Reservation cancellation submitted successfully.');
        if (window.bootstrap && els.adminCancelReservationModal) {
            bootstrap.Modal.getInstance(els.adminCancelReservationModal)?.hide();
        }
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    }
}

function openReceiptUploadModal(id) {
    const item = reservationById(id);
    if (!item || !els.adminReceiptUploadForm) return;
    els.adminReceiptUploadForm.reset();
    els.adminReceiptReservationId.value = id;
    if (els.adminReceiptUploadSummary) {
        const schedule = (item.timeRanges || [{ label: `${niceDate(item.date)} - ${compactTime(item.time)}` }]).map(range => range.label).join('; ');
        els.adminReceiptUploadSummary.textContent = `${item.bookingReference || 'No reference'} | ${item.customerName || ''} | ${schedule}`;
    }
    if (els.adminReceiptUploadMessage) {
        els.adminReceiptUploadMessage.textContent = '';
        els.adminReceiptUploadMessage.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (window.bootstrap && els.adminReceiptUploadModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminReceiptUploadModal).show();
    }
}

async function submitReceiptUpload(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity()) return;
    const formData = new FormData(form);
    const response = await fetch(`${api}?action=admin-receipt-upload`, { method: 'POST', body: formData });
    const payload = await response.json();
    if (els.adminReceiptUploadMessage) {
        els.adminReceiptUploadMessage.textContent = payload.message || 'Saved.';
        els.adminReceiptUploadMessage.className = `rounded-md p-2 text-xs font-bold mt-3 ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }
    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Receipt uploaded successfully.');
        if (window.bootstrap && els.adminReceiptUploadModal) {
            bootstrap.Modal.getInstance(els.adminReceiptUploadModal)?.hide();
        }
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    }
}

async function submitPaymentChannel(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('[data-payment-channel-message]');
    const formData = new FormData(form);
    formData.set('isActive', form.querySelector('[name="isActive"]').checked ? '1' : '0');

    const response = await fetch(`${api}?action=admin-payment-channel`, { method: 'POST', body: formData });
    const payload = await response.json();
    message.textContent = payload.message || 'Saved.';
    message.className = `rounded-md p-3 text-sm font-bold ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;

    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Payment channel saved successfully.');
    }
}

async function submitRateRule(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('[data-rate-rule-message]');
    const formData = new FormData(form);
    formData.set('reason', formData.get('reason') || 'Regular rate');
    formData.set('name', currentRateRuleName());

    const response = await fetch(`${api}?action=admin-rate-rule`, { method: 'POST', body: formData });
    const payload = await response.json();

    message.textContent = payload?.ok ? 'Rate saved.' : (payload?.message || 'Could not save rate.');
    message.className = `rounded-md p-3 text-sm font-bold ${payload?.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;

    if (payload?.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Rate saved successfully.');
        if (window.bootstrap && els.adminRateModal) {
            bootstrap.Modal.getInstance(els.adminRateModal)?.hide();
        }
    }
}

async function deleteRateRule(button) {
    const id = button.dataset.adminRateDelete;
    const label = button.dataset.adminRateLabel || 'this rate';
    if (!id) return;
    if (!window.confirm(`Delete ${label}? This cannot be undone.`)) return;

    const formData = new FormData();
    formData.set('id', id);
    const response = await fetch(`${api}?action=admin-rate-delete`, { method: 'POST', body: formData });
    const payload = await response.json();

    if (!payload.ok) {
        window.alert(payload.message || 'Could not delete rate.');
        return;
    }

    state = payload.state;
    renderAll();
    showAdminToast(payload.message || 'Rate deleted successfully.');
}

async function submitCourtBlock(event) {
    event.preventDefault();
    await saveCourtBlock(event.currentTarget, false);
}

async function submitCourtBlockStatus(button) {
    const isActive = button.dataset.isActive === '1';
    if (!isActive && !window.confirm('Set this court blocking inactive? The affected slots will become available again.')) {
        return;
    }

    const formData = new FormData();
    formData.set('ids', button.dataset.courtBlockStatus || '');
    formData.set('isActive', isActive ? '1' : '0');

    button.disabled = true;
    const response = await fetch(`${api}?action=admin-court-block-status`, { method: 'POST', body: formData });
    const payload = await response.json();
    button.disabled = false;

    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Court blocking status updated successfully.');
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    } else {
        alert(payload.message || 'Could not update court blocking status.');
    }
}

async function saveCourtBlock(form, confirmedOverride = false) {
    const message = form.querySelector('[data-court-block-message]');
    const formData = new FormData(form);
    const [courtId, sport = ''] = String(formData.get('blockScope') || '2|').split('|');
    formData.set('courtId', courtId);
    formData.set('sport', sport);
    formData.set('isActive', form.querySelector('[name="isActive"]').checked ? '1' : '0');
    formData.delete('blockScope');
    if (confirmedOverride) formData.set('overrideConfirm', '1');

    const response = await fetch(`${api}?action=admin-court-block`, { method: 'POST', body: formData });
    const payload = await response.json();

    if (response.status === 409 && payload.requiresOverride) {
        const confirmed = window.confirm(`${payload.message || 'This block overlaps active reservations.'}\n\nCreate the block and log an admin override?`);
        if (confirmed) {
            await saveCourtBlock(form, true);
        }
        return;
    }

    message.textContent = payload.message || 'Saved.';
    message.className = `rounded-md p-3 text-sm font-bold ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;

    if (payload.ok) {
        state = payload.state;
        renderAll();
        showAdminToast(payload.message || 'Court block saved successfully.');
    }
}

async function submitAdminOverrideBooking(event) {
    event.preventDefault();
    await saveAdminOverrideBooking(event.currentTarget, false);
}

async function saveAdminOverrideBooking(form, confirmedOverride = false) {
    const message = els.adminOverrideBookingMessage || form.querySelector('[data-admin-override-message]');
    const formData = new FormData(form);
    if (confirmedOverride) formData.set('overrideConfirm', '1');
    const endpoint = formData.get('bookingId') ? 'admin-booking-update' : 'admin-override-booking';

    const response = await fetch(`${api}?action=${endpoint}`, { method: 'POST', body: formData });
    const payload = await response.json();

    if (response.status === 409 && payload.requiresOverride) {
        const confirmed = window.confirm(payload.message || 'Resource Conflict\n\nCancel conflicting reservation and continue?');
        if (confirmed) {
            await saveAdminOverrideBooking(form, true);
        }
        return;
    }

    if (message) {
        message.textContent = payload.message || 'Saved.';
        message.className = `rounded-md p-2 text-xs font-bold ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
    }

    if (payload.ok) {
        state = payload.state;
        const dateValue = form.querySelector('[name="date"]')?.value;
        if (dateValue) {
            adminScheduleDate = new Date(`${dateValue}T00:00:00`);
            setAdminScheduleCalendarMonth(dateValue);
        }
        if (window.bootstrap && els.adminOverrideBookingModal) {
            bootstrap.Modal.getInstance(els.adminOverrideBookingModal)?.hide();
        }
        form.reset();
        renderAll();
        showAdminToast(payload.message || (endpoint === 'admin-booking-update' ? 'Booking updated successfully.' : 'Override booking saved successfully.'));
    }
}

els.datePicker?.addEventListener('click', () => {
    if (typeof els.datePicker.showPicker !== 'function' || els.datePicker.disabled) return;
    try {
        els.datePicker.showPicker();
    } catch (error) {
        // Browser can still open the native picker from the input itself.
    }
});

els.datePicker?.addEventListener('change', () => {
    const value = els.datePicker.value;
    if (!value) return;

    const today = isoDate(new Date());
    const maxDate = bookingMaxDateIso();
    let nextDateValue = value;
    if (nextDateValue < today) nextDateValue = today;
    if (maxDate && nextDateValue > maxDate) nextDateValue = maxDate;

    selectedDate = new Date(`${nextDateValue}T00:00:00`);
    clearBookingSelection();
    renderRates();
    renderBookingGrid();
});

els.adminScheduleCalendarOpen?.addEventListener('click', openAdminScheduleCalendar);
els.adminScheduleCalendarGrid?.addEventListener('click', event => {
    const button = event.target.closest('[data-admin-schedule-calendar-date]');
    if (!button) return;
    setAdminScheduleDate(button.dataset.adminScheduleCalendarDate);
    if (window.bootstrap && els.adminScheduleDatePickerModal) {
        bootstrap.Modal.getInstance(els.adminScheduleDatePickerModal)?.hide();
    }
});
els.adminScheduleCalendarPrev?.addEventListener('click', () => {
    adminScheduleCalendarMonth.setMonth(adminScheduleCalendarMonth.getMonth() - 1);
    renderAdminScheduleCalendar();
});
els.adminScheduleCalendarNext?.addEventListener('click', () => {
    adminScheduleCalendarMonth.setMonth(adminScheduleCalendarMonth.getMonth() + 1);
    renderAdminScheduleCalendar();
});
els.adminScheduleCalendarToday?.addEventListener('click', () => {
    setAdminScheduleDate(todayIso);
});
els.adminScheduleSportFilter?.addEventListener('change', event => {
    adminScheduleSportFilter = event.target.value;
    if (els.superAdminRangeSport) {
        els.superAdminRangeSport.value = adminScheduleSportFilter;
    }
    renderAdminSchedule();
    renderSuperAdminRangeOverride();
});
els.superAdminRangeSport?.addEventListener('change', event => {
    adminScheduleSportFilter = event.target.value || 'Pickleball';
    if (els.adminScheduleSportFilter) {
        els.adminScheduleSportFilter.value = adminScheduleSportFilter;
    }
    renderAdminSchedule();
    renderSuperAdminRangeOverride();
});
els.superAdminRangeCourt?.addEventListener('change', syncSuperAdminRangeOverride);
els.superAdminRangeStart?.addEventListener('change', syncSuperAdminRangeOverride);
els.superAdminRangeEnd?.addEventListener('change', syncSuperAdminRangeOverride);
els.superAdminRangeOverrideButton?.addEventListener('click', openSuperAdminRangeOverride);
els.adminOverrideBookingModal?.addEventListener('hidden.bs.modal', () => {
    if (els.adminOverrideTimeSlotIds) els.adminOverrideTimeSlotIds.value = '';
    renderAdminOverrideBookingForm();
});

// document.getElementById('closeModal')?.addEventListener('click', closeModal);
document.getElementById('closeModal')?.addEventListener('click', () => {
    const confirmed = window.confirm(
        'Are you sure you want to cancel this booking? Your current booking progress will be lost.'
    );

    if (!confirmed) {
        return;
    }

    // Allow closeModal() to perform its existing reset/cleanup.
    bookingModalCloseUnlocked = true;

    closeModal();
});

els.modal?.addEventListener('click', event => {
    if (event.target === els.modal) closeModal();
});
els.modalBackButton?.addEventListener('click', backBookingModal);
els.modalNextButton?.addEventListener('click', proceedBookingModal);
els.modalSubmitButton?.addEventListener('click', () => {
    if (els.modalSubmitButton?.dataset.done === '1') {
        delete els.modalSubmitButton.dataset.done;
        bookingModalCloseUnlocked = true;
        closeModal();
    }
});
els.form?.addEventListener('submit', submitPayment);
els.adminOverrideBookingForm?.addEventListener('submit', submitAdminOverrideBooking);
els.adminCancelReservationForm?.addEventListener('submit', submitCancelReservation);
els.adminReceiptUploadForm?.addEventListener('submit', submitReceiptUpload);
els.adminCourtForm?.addEventListener('submit', submitAdminCourtForm);
els.adminMemberForm?.addEventListener('submit', submitAdminMemberForm);
els.adminEntranceFeeForm?.addEventListener('submit', submitEntranceFee);
els.adminQrScanForm?.addEventListener('submit', submitQrLookup);
els.adminOverrideSport?.addEventListener('change', event => updateAdminOverrideSportAndCourts(event.target.value));
els.adminOverrideCustomer?.addEventListener('change', applyAdminOverrideCustomer);
els.adminScheduleGrid?.addEventListener('click', event => {
    const button = event.target.closest('[data-admin-calendar-booking]');
    if (button) openAdminCalendarBooking(button);
});
els.adminReferenceSearch?.addEventListener('input', event => {
    adminReferenceSearch = event.target.value;
    renderAdmin();
});
syncAdminBookingDateInputs();
syncAdminBookingSortInput();
els.adminBookingStartDate?.addEventListener('change', event => {
    setAdminBookingDateRange(event.target.value, adminBookingEndDate);
});
els.adminBookingEndDate?.addEventListener('change', event => {
    setAdminBookingDateRange(adminBookingStartDate, event.target.value);
});
els.adminBookingDateClear?.addEventListener('click', () => {
    setAdminBookingDateRange('', '');
});
els.adminBookingSort?.addEventListener('change', event => {
    adminBookingSort = event.target.value || 'created-desc';
    renderAdmin();
});
els.adminMemberSearch?.addEventListener('input', event => {
    adminMemberSearch = event.target.value;
    renderAdminMembers();
});
els.adminRateSportFilter?.addEventListener('change', event => {
    adminRateSportFilter = event.target.value;
    renderAdminRateSummary();
    document.dispatchEvent(new CustomEvent('admin-rates-filtered'));
});
els.adminRateCourtFilter?.addEventListener('change', event => {
    adminRateCourtFilter = event.target.value;
    renderAdminRateSummary();
    document.dispatchEvent(new CustomEvent('admin-rates-filtered'));
});
els.adminRateClearFilters?.addEventListener('click', () => {
    adminRateSportFilter = '';
    adminRateCourtFilter = '';
    if (els.adminRateSportFilter) els.adminRateSportFilter.value = '';
    if (els.adminRateCourtFilter) els.adminRateCourtFilter.value = '';
    renderAdminRateSummary();
    document.dispatchEvent(new CustomEvent('admin-rates-filtered'));
});
els.adminAddMember?.addEventListener('click', () => openAdminMemberModal());
document.getElementById('adminMemberProfilePicture')?.addEventListener('change', event => {
    const file = event.target.files?.[0];
    const preview = document.getElementById('adminMemberProfilePreview');
    if (!file || !preview) return;
    preview.innerHTML = `<img src="${escapeHtml(URL.createObjectURL(file))}" alt="">`;
});
document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle || '');
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}" class="icon-sm"></i>`;
        if (window.lucide) lucide.createIcons();
    });
});
els.adminAddCourt?.addEventListener('click', () => openAdminCourtModal());
document.querySelectorAll('[data-open-terms-conditions]').forEach(button => {
    button.addEventListener('click', () => {
        if (window.bootstrap && els.termsConditionsModal) {
            bootstrap.Modal.getOrCreateInstance(els.termsConditionsModal).show();
        }
    });
});
document.querySelectorAll('[data-open-privacy-policy]').forEach(button => {
    button.addEventListener('click', () => {
        if (window.bootstrap && els.adminPrivacyPolicyModal) {
            bootstrap.Modal.getOrCreateInstance(els.adminPrivacyPolicyModal).show();
        }
    });
});
document.getElementById('adminScanMemberQr')?.addEventListener('click', () => {
    if (els.adminQrScanForm) els.adminQrScanForm.reset();
    if (els.adminQrScanMessage) {
        els.adminQrScanMessage.textContent = '';
        els.adminQrScanMessage.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (window.bootstrap && els.adminQrScanModal) {
        bootstrap.Modal.getOrCreateInstance(els.adminQrScanModal).show();
    }
});
els.adminStartQrCamera?.addEventListener('click', startQrCamera);
els.adminQrScanModal?.addEventListener('hidden.bs.modal', stopQrCamera);
els.paymentMethod?.addEventListener('change', event => startBookingPaymentTimer(event.target.value));
els.bookingSelectionBookNow?.addEventListener('click', openSelectedBookingModal);

document.querySelectorAll('[data-admin-filter]').forEach(button => {
    button.addEventListener('click', () => {
        adminFilter = button.dataset.adminFilter;
        document.querySelectorAll('[data-admin-filter]').forEach(item => {
            item.className = 'btn btn-outline-secondary btn-sm';
        });
        button.className = 'btn btn-primary btn-sm';
        renderAdmin();
    });
});

document.querySelectorAll('[data-sport]').forEach(button => {
    button.addEventListener('click', () => {
        selectedSport = button.dataset.sport;
        clearBookingSelection();
        document.querySelectorAll('[data-sport]').forEach(item => {
            item.className = 'sport-option btn w-100 justify-content-start';
        });
        button.className = 'sport-option-active btn w-100 justify-content-start';
        renderRates();
        renderBookingGrid();
    });
});

const sidebar = document.getElementById('appSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const openSidebarButton = document.getElementById('sidebarOpen');
const closeSidebarButton = document.getElementById('sidebarClose');

function setSidebar(open) {
    sidebar?.classList.toggle('is-open', open);
    sidebarOverlay?.classList.toggle('hidden', !open);
}

openSidebarButton?.addEventListener('click', () => setSidebar(true));
closeSidebarButton?.addEventListener('click', () => setSidebar(false));
sidebarOverlay?.addEventListener('click', () => setSidebar(false));

document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', () => {
        if (link.classList.contains('admin-nav-link')) {
            document.querySelectorAll('.admin-nav-link').forEach(item => item.classList.remove('active'));
            link.classList.add('active');
        }
        const target = document.querySelector(link.getAttribute('href'));
        if (target?.tagName === 'DETAILS') target.open = true;
        setSidebar(false);
    });
});

document.addEventListener('input', event => {
    if (event.target?.matches?.('input[data-phone-validation="1"]')) {
        validatePhoneInput(event.target);
    }
});

document.addEventListener('change', event => {
    if (event.target?.matches?.('input[data-phone-validation="1"]')) {
        validatePhoneInput(event.target);
    }
});

document.addEventListener('submit', event => {
    const invalidPhone = phoneInputs(event.target).find(input => !validatePhoneInput(input));
    if (invalidPhone) {
        event.preventDefault();
        event.stopImmediatePropagation();
        invalidPhone.reportValidity();
    }
}, true);

enhancePhoneInputs();
showAdminServerFlashToast();

if (document.querySelector('[data-needs-state]')) {
    loadState();
} else if (window.lucide) {
    lucide.createIcons();
}
