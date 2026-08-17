const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
const api = window.appConfig?.apiUrl || 'api.php';
const adminLoginUrl = window.appConfig?.adminLoginUrl || 'login.php';
const rootUrl = window.appConfig?.rootUrl || '';

let state = null;
let selectedDate = new Date();
let adminScheduleDate = new Date();
let adminFilter = 'Pending Payment';
let selectedSport = 'Pickleball';
let selectedBookingSlots = [];
let activeBookingSlots = [];
let bookingModalStep = 0;
let bookingModalCloseUnlocked = false;

const els = {
    rates: document.getElementById('rateCards'),
    grid: document.getElementById('bookingGrid'),
    dateLabel: document.getElementById('bookingDateLabel'),
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
    paymentPageChannels: document.getElementById('paymentPageChannels'),
    adminPaymentChannels: document.getElementById('adminPaymentChannels'),
    adminRateSummary: document.getElementById('adminRateSummary'),
    adminAddRate: document.getElementById('adminAddRate'),
    adminRateModal: document.getElementById('adminRateModal'),
    adminRateForm: document.getElementById('adminRateForm'),
    adminRateModalTitle: document.getElementById('adminRateModalTitle'),
    adminRateId: document.getElementById('adminRateId'),
    adminRateCourt: document.getElementById('adminRateCourt'),
    adminRateSport: document.getElementById('adminRateSport'),
    adminRateTimeSlot: document.getElementById('adminRateTimeSlot'),
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
    adminCourtBlocks: document.getElementById('adminCourtBlocks'),
    adminOverrideLogs: document.getElementById('adminOverrideLogs'),
    adminMembers: document.getElementById('adminMembers'),
    adminUsers: document.getElementById('adminUsers'),
    adminScheduleGrid: document.getElementById('adminScheduleGrid'),
    adminScheduleDateLabel: document.getElementById('adminScheduleDateLabel'),
    adminOverrideBookingForm: document.getElementById('adminOverrideBookingForm'),
    adminOverrideBookingMessage: document.getElementById('adminOverrideBookingMessage'),
    adminOverrideDate: document.getElementById('adminOverrideDate'),
    adminOverrideTime: document.getElementById('adminOverrideTime'),
    adminOverrideCourt: document.getElementById('adminOverrideCourt'),
    adminOverrideSport: document.getElementById('adminOverrideSport'),
    adminOverrideContext: document.getElementById('adminOverrideContext'),
    adminOverrideBookingModal: document.getElementById('adminOverrideBookingModal')
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

function compactTime(label) {
    return label.replace(/\b0(\d:)/g, '$1');
}

async function loadState() {
    const response = await fetch(`${api}?action=state`);
    const payload = await response.json();
    if (!payload.ok) {
        showLoadError(payload.message || 'Could not load booking data.');
        return;
    }
    state = payload.state;
    renderAll();
}

function showLoadError(message) {
    const target = els.grid || els.admin;
    if (target) {
        target.innerHTML = `<div class="rounded-lg border border-rose-200 bg-rose-50 p-5 text-sm font-bold text-rose-700">${message}</div>`;
    }
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
    return ['Pending Payment', 'Held', 'Booked'].includes(status);
}

function statusTone(status) {
    if (status === 'Available') return 'available';
    if (status === 'Booked') return 'booked';
    if (status === 'Blocked') return 'blocked';
    return 'pending';
}

function compactStatusLabel(status) {
    return {
        'Pending Payment': 'Pending Payment',
        Available: 'Available',
        Booked: 'Booked',
        Held: 'Held',
        Blocked: 'Blocked'
    }[status] || status;
}

function renderAll() {
    renderRates();
    renderBookingGrid();
    renderAdmin();
    renderAdminSchedule();
    renderAdminOverrideBookingForm();
    renderPaymentOptions();
    renderPaymentPage();
    renderAdminPaymentChannels();
    renderAdminRateSummary();
    renderAdminRateAudit();
    renderAdminCourtBlocks();
    renderAdminOverrideLogs();
    renderAdminMembers();
    renderAdminUsers();
    if (window.lucide) lucide.createIcons();
}

function defaultRateRule() {
    const firstCourtInfo = state?.courts?.[0] || null;
    const firstCourt = firstCourtInfo?.id || '';
    const firstSlot = Object.values(state?.slotDetails || {})[0]?.id || '';
    return {
        id: '',
        name: '',
        courtId: firstCourt,
        sport: firstCourtInfo?.sports?.[0] || 'Pickleball',
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

function populateAdminRateOptions(rule) {
    setSelectOptions(els.adminRateCourt, [
        ...(state?.courts || []).map(court => [court.id, court.name])
    ], rule.courtId ?? '');
    setSelectOptions(els.adminRateSport, [
        ['Pickleball', 'Pickleball'],
        ['Basketball', 'Basketball'],
        ['Volleyball', 'Volleyball']
    ], rule.sport ?? '');
    setSelectOptions(els.adminRateTimeSlot, Object.values(state?.slotDetails || {}).map(slot => [
        slot.id,
        compactTime(slot.label)
    ]), rule.timeSlotId ?? '');
    if (els.adminRateReason) els.adminRateReason.value = rule.changeReason || 'Regular rate';
}

function currentRateRuleName() {
    const court = els.adminRateCourt?.selectedOptions?.[0]?.textContent || 'All courts';
    const sport = els.adminRateSport?.value || 'All sports';
    const slot = els.adminRateTimeSlot?.selectedOptions?.[0]?.textContent || '';
    return `${court} ${sport} ${slot}`.trim();
}

function simpleRateDay(value) {
    const day = String(value || '');
    if (day === 'Monday-Friday') return 'Monday - Friday';
    if (day === 'Saturday-Sunday') return 'Saturday - Sunday';
    if (day === 'Any') return 'All days';
    return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].includes(day) ? day : 'All days';
}

function expandedRateDays(selection) {
    if (selection === 'Monday-Friday') return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    if (selection === 'Saturday-Sunday') return ['Saturday', 'Sunday'];
    return [selection || 'Monday'];
}

function matchingRateRuleId(formData, day, fallbackId = '') {
    const courtId = String(formData.get('courtId') || '');
    const sport = String(formData.get('sport') || '');
    const timeSlotId = String(formData.get('timeSlotId') || '');
    const match = (state?.adminRateRules || []).find(rule =>
        String(rule.courtId ?? '') === courtId &&
        String(rule.sport ?? '') === sport &&
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
    if (els.adminRateId) els.adminRateId.value = rule.id || '';
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
        : `After payment, non-members should send proof through ${messengerText} with the reservation name, date, sport, court, and time.`;
    const qrImage = details.qrPath
        ? `<img src="${escapeHtml(resourceUrl(details.qrPath))}" alt="${escapeHtml(details.name)} payment QR code" class="h-32 w-32 rounded-md border border-slate-200 bg-white p-2" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"><div class="hidden grid h-32 w-32 place-items-center rounded-md border border-dashed border-slate-300 bg-white p-2 text-center text-xs font-bold text-slate-500">QR image not found</div>`
        : '<div class="grid h-32 w-32 place-items-center rounded-md border border-dashed border-slate-300 bg-white p-2 text-center text-xs font-bold text-slate-500">No QR uploaded</div>';
    const downloadLink = details.qrPath
        ? `<a href="${escapeHtml(resourceUrl(details.qrPath))}" download class="inline-flex w-32 justify-center rounded-md border border-line bg-white px-3 py-2 text-xs font-black text-primary">Download QR</a>`
        : '';

    if (details.type === 'qr') {
        els.paymentInstructions.innerHTML = `
            <div class="grid gap-4 sm:grid-cols-[132px_1fr]">
                <div class="grid gap-2">
                    ${qrImage}
                    ${downloadLink}
                </div>
                <div>
                    <p class="text-sm font-black uppercase tracking-[.14em] text-court">${escapeHtml(details.name)} QR</p>
                    <h4 class="mt-1 text-lg font-black">Send payment via ${escapeHtml(details.name)}</h4>
                    <dl class="mt-3 grid gap-1 text-sm">
                        <div><dt class="inline font-black">Account name:</dt> <dd class="inline text-slate-600">${escapeHtml(details.accountName || '-')}</dd></div>
                        <div><dt class="inline font-black">Account no.:</dt> <dd class="inline text-slate-600">${escapeHtml(details.accountNumber || '-')}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs font-semibold text-slate-500">${escapeHtml(details.instructions || 'Complete the transfer, then upload your receipt.')}</p>
                    <p class="mt-2 rounded-md bg-white px-3 py-2 text-xs font-bold text-slate-600">${proofNote}</p>
                </div>
            </div>
        `;
        return;
    }

    els.paymentInstructions.innerHTML = `
        <div class="grid gap-4 sm:grid-cols-[132px_1fr]">
            <div class="grid gap-2">
                ${qrImage}
                ${downloadLink}
            </div>
            <div>
                <p class="text-sm font-black uppercase tracking-[.14em] text-court">${escapeHtml(details.bankName || details.name)}</p>
                <h4 class="mt-1 text-lg font-black">${escapeHtml(details.name)} transfer</h4>
                <dl class="mt-3 grid gap-2 text-sm">
                    <div class="flex justify-between gap-4 rounded-md bg-white px-3 py-2"><dt class="font-black">Bank information</dt><dd class="text-right text-slate-600">${escapeHtml(details.bankName || details.name)}</dd></div>
                    <div class="flex justify-between gap-4 rounded-md bg-white px-3 py-2"><dt class="font-black">Account name</dt><dd class="text-right text-slate-600">${escapeHtml(details.accountName || '-')}</dd></div>
                    <div class="flex justify-between gap-4 rounded-md bg-white px-3 py-2"><dt class="font-black">Account no.</dt><dd class="text-right text-slate-600">${escapeHtml(details.accountNumber || '-')}</dd></div>
                </dl>
                <p class="mt-3 text-xs font-semibold text-slate-500">${escapeHtml(details.instructions || 'Upload the deposit or transfer receipt before submitting.')}</p>
                <p class="mt-2 rounded-md bg-white px-3 py-2 text-xs font-bold text-slate-600">${proofNote}</p>
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
        ? `<div class="grid gap-2"><img src="${escapeHtml(resourceUrl(channel.qrPath))}" alt="${escapeHtml(channel.name)} payment QR code" class="h-32 w-32 rounded-md border border-slate-200 bg-slate-50 p-2" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');"><div class="hidden grid h-32 w-32 place-items-center rounded-md border border-dashed border-slate-300 bg-slate-50 p-2 text-center text-xs font-bold text-slate-500">QR image not found</div><a href="${escapeHtml(resourceUrl(channel.qrPath))}" download class="inline-flex w-32 justify-center rounded-md border border-line px-3 py-2 text-xs font-black text-primary">Download QR</a></div>`
        : '<div class="grid h-32 w-32 place-items-center rounded-md border border-dashed border-slate-300 bg-slate-50 p-2 text-center text-xs font-bold text-slate-500">No QR uploaded</div>';

    if (channel.type === 'qr') {
        return `
            <article class="public-card p-4">
                <p class="text-xs font-black uppercase tracking-[.12em] text-muted">QR / Wallet</p>
                <h2 class="mt-2 text-2xl font-black">${escapeHtml(channel.name)}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-[132px_1fr]">
                    ${qrPanel}
                    <div>
                        <p class="text-sm font-semibold leading-6 text-muted">${escapeHtml(channel.instructions || 'Scan to pay, then upload the receipt in the booking form.')}</p>
                        <dl class="mt-3 grid gap-2 text-sm">
                            <div class="rounded-md bg-slate-50 p-2.5"><dt class="font-black">Account name</dt><dd class="mt-1 text-slate-600">${escapeHtml(channel.accountName || '-')}</dd></div>
                            <div class="rounded-md bg-slate-50 p-2.5"><dt class="font-black">Account no.</dt><dd class="mt-1 text-slate-600">${escapeHtml(channel.accountNumber || '-')}</dd></div>
                        </dl>
                    </div>
                </div>
            </article>
        `;
    }

    return `
        <article class="public-card p-4">
            <p class="text-xs font-black uppercase tracking-[.12em] text-muted">Bank</p>
            <h2 class="mt-2 text-2xl font-black">${escapeHtml(channel.name)}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-[132px_1fr]">
                ${qrPanel}
                <div>
                    <dl class="grid gap-2 text-sm">
                        <div class="flex justify-between gap-4 rounded-md bg-slate-50 p-2.5"><dt class="font-black">Bank information</dt><dd class="text-right text-slate-600">${escapeHtml(channel.bankName || channel.name)}</dd></div>
                        <div class="flex justify-between gap-4 rounded-md bg-slate-50 p-2.5"><dt class="font-black">Account name</dt><dd class="text-right text-slate-600">${escapeHtml(channel.accountName || '-')}</dd></div>
                        <div class="flex justify-between gap-4 rounded-md bg-slate-50 p-2.5"><dt class="font-black">Account no.</dt><dd class="text-right text-slate-600">${escapeHtml(channel.accountNumber || '-')}</dd></div>
                    </dl>
                    <p class="mt-3 text-sm font-semibold leading-6 text-muted">${escapeHtml(channel.instructions || 'Upload a deposit or transfer receipt for admin review.')}</p>
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
    els.rates.innerHTML = state.rates.map(rate => `
        <div class="inline-flex items-center gap-2 rounded-md border border-line bg-white px-3 py-2 text-sm">
            <span class="font-black text-primary">${peso.format(Number(rate.price))}</span>
            <span class="font-semibold text-muted">${rate.time}</span>
        </div>
    `).join('');
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
            status: direct.status,
            sport: direct.sport,
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
    return [
        { key: 'lakers', label: 'LAKERS', court: 1, sport: 'Basketball', openLabel: 'AVAILABLE' },
        { key: 'miami', label: 'MIAMI', court: 2, sport: 'Basketball', openLabel: 'AVAILABLE' },
        { key: 'pb1', label: 'PB1', court: 3, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'pb2', label: 'PB2', court: 4, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'pb3', label: 'PB3', court: 5, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'pb4', label: 'PB4', court: 6, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'w5', label: 'WOODEN 5', court: 7, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'w6', label: 'WOODEN 6', court: 8, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'w7', label: 'WOODEN 7', court: 9, sport: 'Pickleball', openLabel: 'OPEN' }
    ];
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

function scheduleCell(label, status = 'Available', sub = '', title = '') {
    return { label, status, sub, title: title || sub || label };
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
    const conflict = blockConflictFor(date, time, courtId, sport);
    if (!conflict) return null;
    return scheduleCell('BLOCKED', 'Blocked', conflict.message, conflict.message);
}

function adminScheduleCell(date, time, column) {
    const direct = directBookingAt(date, time, column.court);
    if (direct) {
        const isSameSport = direct.sport === column.sport;
        const label = isSameSport ? adminBookingLabel(direct, 'BOOKED') : direct.sport.toUpperCase();
        const status = isSameSport ? direct.status : 'Blocked';
        const sub = isSameSport ? direct.status : `Uses ${column.label}`;
        return scheduleCell(label, status, sub, `${column.label}: ${direct.sport} ${direct.status}`);
    }

    const block = blockCell(date, time, column.court, column.sport);
    if (block) return block;

    return scheduleCell(column.openLabel || 'AVAILABLE', 'Available', '', `${column.label} is available`);
}

function adminScheduleCellClass(status) {
    const tone = statusTone(status);
    if (tone === 'booked') return 'bg-green-50 text-green-900 ring-green-200';
    if (tone === 'pending') return 'bg-amber-50 text-amber-900 ring-amber-200';
    if (tone === 'blocked' || tone === 'cancelled') return 'bg-rose-50 text-rose-800 ring-rose-200';
    return 'bg-slate-50 text-slate-600 ring-slate-200';
}

function renderAdminSchedule() {
    if (!els.adminScheduleGrid || !state) return;
    const date = isoDate(adminScheduleDate);
    const columns = adminScheduleColumns();
    const slots = Object.values(state.timeSlots || {}).flat();
    els.adminScheduleDateLabel.textContent = adminScheduleDateText(date);
    els.adminScheduleGrid.style.gridTemplateColumns = `76px repeat(${columns.length}, minmax(78px, 1fr))`;

    const header = ['TIME', ...columns.map(column => column.label)].map((label, index) => `
        <div class="border-b border-line bg-white p-2 text-[10px] font-black ${index === 0 ? 'text-left' : 'text-center'}">${label}</div>
    `).join('');

    const rows = slots.map(time => {
        const timeCell = `<div class="border-b border-line bg-white p-2 text-[10px] font-black text-ink">${adminTimeStart(time)}</div>`;
        const cells = columns.map(column => {
            const cell = adminScheduleCell(date, time, column);
            const selection = adminColumnSelection(column, cell);
            const slot = state.slotDetails?.[time] || {};
            return `
                <div class="border-b border-line bg-white p-1.5">
                    <button type="button"
                        title="${escapeHtml(cell.title)}"
                        class="admin-schedule-action min-h-[38px] rounded-md px-1.5 py-1.5 text-center text-[10px] font-black leading-3 ring-1 ${adminScheduleCellClass(cell.status)}"
                        data-admin-calendar-booking
                        data-date="${escapeHtml(date)}"
                        data-time="${escapeHtml(time)}"
                        data-time-slot-id="${escapeHtml(slot.id || '')}"
                        data-court-id="${escapeHtml(selection.courtId)}"
                        data-sport="${escapeHtml(selection.sport)}"
                        data-cell-label="${escapeHtml(cell.label)}"
                        data-cell-status="${escapeHtml(cell.status)}"
                        data-cell-title="${escapeHtml(cell.title)}">
                        <span>${escapeHtml(cell.label)}</span>
                        ${cell.sub ? `<span class="mt-0.5 block text-[9px] font-bold opacity-75">${escapeHtml(cell.sub)}</span>` : ''}
                    </button>
                </div>
            `;
        }).join('');
        return timeCell + cells;
    }).join('');

    els.adminScheduleGrid.innerHTML = header + rows;
}

function renderAdminOverrideBookingForm() {
    if (!els.adminOverrideBookingForm || !state) return;

    if (els.adminOverrideCourt) {
        els.adminOverrideCourt.innerHTML = (state.courts || []).map(court => `
            <option value="${court.id}">${escapeHtml(adminCourtOptionLabel(court))}</option>
        `).join('');
    }
    updateAdminOverrideSports();
}

function updateAdminOverrideSports(preferredSport = '') {
    if (!els.adminOverrideCourt || !els.adminOverrideSport || !state) return;
    const court = courtById(els.adminOverrideCourt.value);
    const sports = court?.sports || [];
    els.adminOverrideSport.innerHTML = sports.map(sport => `<option value="${escapeHtml(sport)}">${escapeHtml(sport)}</option>`).join('');
    if (preferredSport && sports.includes(preferredSport)) {
        els.adminOverrideSport.value = preferredSport;
    }
}

function openAdminCalendarBooking(button) {
    if (!els.adminOverrideBookingForm || !state) return;
    const form = els.adminOverrideBookingForm;
    const date = button.dataset.date || isoDate(adminScheduleDate);
    const time = button.dataset.time || '';
    const courtId = button.dataset.courtId || '';
    const sport = button.dataset.sport || '';
    const status = button.dataset.cellStatus || 'Available';
    const label = button.dataset.cellLabel || 'AVAILABLE';
    const title = button.dataset.cellTitle || '';
    const court = courtById(courtId);
    const courtLabel = court ? adminCourtOptionLabel(court) : `Court ${courtId}`;

    form.reset();
    if (els.adminOverrideBookingMessage) {
        els.adminOverrideBookingMessage.textContent = '';
        els.adminOverrideBookingMessage.className = 'hidden rounded-md p-2 text-xs font-bold mt-3';
    }
    if (els.adminOverrideDate) els.adminOverrideDate.value = date;
    if (els.adminOverrideTime) els.adminOverrideTime.value = button.dataset.timeSlotId || '';
    if (els.adminOverrideCourt) els.adminOverrideCourt.value = courtId;
    updateAdminOverrideSports(sport);
    form.querySelector('[name="status"]').value = 'Booked';
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
    const rule = rules.find(item =>
        Number(item.courtId) === selectedCourtId &&
        item.sport === sport &&
        Number(item.timeSlotId) === Number(slot?.id)
    );
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
        info: 'Player Information',
        review: 'Review Booking',
        payment: 'Payment Instructions',
        proof: state?.member ? 'Upload Payment Proof' : 'Booking Reference'
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
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `MA${year}${month}-${hours}${minutes}${seconds}`;
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
    const date = isoDate(selectedDate);
    const today = isoDate(new Date());
    const courts = courtsForSelectedSport();
    els.dateLabel.textContent = `${niceDate(date)}${date === today ? ' - Today' : ''}`;
    const prevButton = document.getElementById('prevDate');
    if (prevButton) {
        prevButton.disabled = date <= today;
        prevButton.classList.toggle('opacity-50', date <= today);
    }
    els.grid.style.gridTemplateColumns = `minmax(118px, 128px) repeat(${courts.length}, minmax(54px, 1fr))`;
    els.grid.style.minWidth = courts.length > 2 ? `${118 + courts.length * 56}px` : '';

    const header = ['Time', ...courts];
    const headerHtml = header.map((item, index) => `
        <div class="border-b border-slate-200 bg-slate-50 p-2.5 text-xs font-black ${index === 0 ? '' : 'text-center'}">
            ${index === 0 ? item : courtDisplayName(item)}
        </div>
    `).join('');

    const rows = Object.values(state.timeSlots).map(slots => {
        const slotRows = slots.map(time => {
            const first = `
                <div class="border-b border-slate-200 bg-white p-2 text-[11px] font-black leading-4">
                    ${compactTime(time)}<p class="text-primary">${peso.format(slotPrice(time, courts[0]?.id, selectedSport, date))}</p>
                </div>
            `;
            const courtCells = courts.map(courtInfo => {
                const court = courtInfo.id;
                const conflict = relatedConflictFor(date, time, courtInfo);
                const status = conflict?.status || 'Available';
                const isPast = slotIsPast(date, time);
                const tone = statusTone(status);
                const slotData = {
                    date,
                    time,
                    court,
                    courtName: courtDisplayName(courtInfo),
                    sport: selectedSport
                };
                const isSelected = selectedBookingSlots.some(item => bookingSlotKey(item) === bookingSlotKey(slotData));
                const css = isSelected
                    ? 'booking-slot-selected'
                    : isPast
                    ? 'booking-slot-unavailable'
                    : tone === 'booked' || tone === 'pending'
                    ? 'booking-slot-booked'
                    : tone === 'blocked'
                    ? 'booking-slot-unavailable'
                    : 'booking-slot-available';
                const disabled = status !== 'Available' || isPast ? 'disabled' : '';
                const label = isSelected
                    ? 'Selected'
                    : status === 'Available' && !isPast
                    ? 'Select'
                    : tone === 'blocked' || isPast
                    ? ''
                    : compactStatusLabel(status);
                const help = isPast
                    ? 'Past dates and time slots cannot be booked.'
                    : status === 'Available'
                    ? `Select ${courtDisplayName(courtInfo)} ${selectedSport} slot`
                    : `${courtDisplayName(courtInfo)} is unavailable for this time.`;
                return `
                    <button ${disabled}
                        data-book-date="${date}"
                        data-book-time="${time}"
                        data-book-court="${court}"
                        data-book-court-name="${courtDisplayName(courtInfo)}"
                        data-book-sport="${selectedSport}"
                        title="${escapeHtml(help)}"
                        class="slot booking-slot m-0.5 rounded-md border px-1.5 py-1.5 text-[11px] transition ${css}">
                        <span class="slot-label">${escapeHtml(label)}</span>
                    </button>
                `;
            }).join('');
            return first + courtCells;
        }).join('');
        return slotRows;
    }).join('');

    els.grid.innerHTML = headerHtml + rows;
    els.grid.querySelectorAll('[data-book-date]').forEach(button => {
        button.addEventListener('click', () => toggleBookingSelection(button.dataset));
    });
    renderBookingSelectionBar();
}

function renderAdmin() {
    if (!state) return;
    const allRows = (state.adminReservations || []).filter(item => item.type === 'court');
    renderAdminStats(allRows);
    if (!els.admin) return;
    const rows = allRows.filter(item => adminFilter === 'All' || item.status === adminFilter);

    if (rows.length === 0) {
        els.admin.innerHTML = '<tr><td colspan="6" class="text-secondary">No reservations in this status.</td></tr>';
        return;
    }

    els.admin.innerHTML = rows.sort((a, b) => a.createdAt < b.createdAt ? 1 : -1).map(item => `
        <tr>
            <td>
                <p class="mb-1 fw-black text-ink">${niceDate(item.date)} - ${item.time}</p>
                <p class="mb-0 text-xs text-secondary">Court ${item.court} - ${item.sport}</p>
            </td>
            <td>
                <p class="mb-1 fw-black">${escapeHtml(item.customerName)}</p>
                <p class="mb-0 text-xs text-secondary">${escapeHtml(item.customerPhone || 'No phone')}</p>
                <p class="mb-0 text-xs text-secondary">${escapeHtml(item.customerEmail || 'No email')}</p>
                ${item.memberName ? `<p class="mb-0 text-xs fw-black text-primary">Member: ${escapeHtml(item.memberName)}</p>` : ''}
            </td>
            <td>
                <p class="mb-1 fw-black">${escapeHtml(item.paymentMethod || 'N/A')}</p>
                <p class="mb-0 text-xs text-secondary">${peso.format(Number(item.finalAmount || 0))}</p>
            </td>
            <td>
                <span class="status-badge ${adminStatusClass(item.status)}">${escapeHtml(item.status)}</span>
                ${item.reviewedByName ? `<p class="mb-0 mt-1 text-xs text-secondary">By ${escapeHtml(item.reviewedByName)}</p>` : ''}
                ${item.cancelReason ? `<p class="mb-0 mt-1 text-xs text-warning">Reason: ${escapeHtml(item.cancelReason)}</p>` : ''}
            </td>
            <td>
                ${item.receipt ? `<a class="btn btn-outline-primary btn-sm" target="_blank" href="${escapeHtml(resourceUrl(item.receipt))}">View</a>` : '<span class="text-xs text-secondary">None</span>'}
            </td>
            <td class="text-end">
                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                    <button data-admin-id="${item.id}" data-status="Booked" class="btn btn-success btn-sm">Booked</button>
                    <button data-admin-id="${item.id}" data-status="Held" class="btn btn-secondary btn-sm">Held</button>
                    <button data-admin-id="${item.id}" data-status="Pending Payment" class="btn btn-outline-secondary btn-sm">Pending Payment</button>
                    <button data-admin-id="${item.id}" data-status="Available" class="btn btn-outline-danger btn-sm">Available</button>
                </div>
            </td>
        </tr>
    `).join('');

    els.admin.querySelectorAll('[data-admin-id]').forEach(button => {
        button.addEventListener('click', () => updateStatus(button.dataset.adminId, button.dataset.status));
    });
}

function adminStatusClass(status) {
    if (statusTone(status) === 'pending') return 'status-badge-pending';
    if (statusTone(status) === 'available') return 'status-badge-available';
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
    if (pending) pending.textContent = (counts.Held || 0) + (counts['Pending Payment'] || 0);
    if (booked) booked.textContent = counts.Booked || 0;
    if (cancelled) cancelled.textContent = counts.Available || 0;
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
                <span class="status-badge bg-blue-100 text-primary">${typeLabel}</span>
            </div>
            <input type="hidden" name="id" value="${channel.id || ''}">
            <input type="hidden" name="channelType" value="${fixedType}">
            <input type="hidden" name="isActive" value="1">
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
    const [hourRaw, minuteRaw = '00'] = String(value || '00:00').split(':');
    let hour = Number(hourRaw);
    const minute = Number(minuteRaw);
    const suffix = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}${minute ? `:${String(minute).padStart(2, '0')}` : ''} ${suffix}`;
}

function renderAdminRateSummary() {
    if (!els.adminRateSummary || !state) return;
    bindAdminRateForm();
    const rows = (state.adminRateRules || [])
        .sort((a, b) => {
            const courtCompare = String(a.courtName || 'All courts').localeCompare(String(b.courtName || 'All courts'));
            if (courtCompare) return courtCompare;
            const sportCompare = String(a.sport || 'All sports').localeCompare(String(b.sport || 'All sports'));
            if (sportCompare) return sportCompare;
            return timeToMinutes(a.startsAt) - timeToMinutes(b.startsAt);
        });

    if (rows.length === 0) {
        els.adminRateSummary.innerHTML = '<tr><td colspan="5" class="text-secondary">No rates configured.</td></tr>';
        return;
    }

    els.adminRateSummary.innerHTML = rows.map(rule => `
        <tr>
            <td>${escapeHtml(rule.courtName || 'All courts')}</td>
            <td>${escapeHtml(rule.sport || 'All sports')}</td>
            <td>${formatRuleTime(rule.startsAt)}-${formatRuleTime(rule.endsAt)}</td>
            <td class="text-end">${peso.format(Number(rule.pricePerHour || 0))}</td>
            <td class="text-end">
                <div class="d-inline-flex gap-1">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-admin-rate-edit="${rule.id}">
                        Edit
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-admin-rate-delete="${rule.id}" data-admin-rate-label="${escapeHtml(`${rule.courtName || 'All courts'} ${rule.sport || 'All sports'} ${formatRuleTime(rule.startsAt)}-${formatRuleTime(rule.endsAt)}`)}">
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

function renderAdminCourtBlocks() {
    if (!els.adminCourtBlocks || !state) return;
    const blocks = state.adminCourtBlocks || [];
    const courts = state.courts || [];
    const slots = Object.values(state.slotDetails || {});
    const newBlock = {
        id: '',
        date: isoDate(new Date()),
        timeSlotId: slots[0]?.id || '',
        courtId: '',
        sport: '',
        reason: 'Maintenance',
        notes: '',
        status: 'Active'
    };

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
    const slotOptions = selected => slots.map(slot =>
        `<option value="${slot.id}" ${Number(selected) === Number(slot.id) ? 'selected' : ''}>${escapeHtml(compactTime(slot.label))}</option>`
    ).join('');

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
                <label class="grid gap-2 text-sm font-bold">Time
                    <select required name="timeSlotId" class="form-select">${slotOptions(block.timeSlotId || newBlock.timeSlotId)}</select>
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

    els.adminCourtBlocks.innerHTML = [formFor(newBlock), ...blocks.map(formFor)].join('');
    els.adminCourtBlocks.querySelectorAll('[data-court-block-form]').forEach(form => {
        form.addEventListener('submit', submitCourtBlock);
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
    if (!validateModalStep()) return;
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
    const referencePrefix = bookingReferencePrefix();

    for (const [index, slot] of slots.entries()) {
        const formData = new FormData(els.form);
        formData.set('action', endpoint);
        formData.set('date', slot.date);
        formData.set('time', slot.time);
        formData.set('court', slot.court);
        formData.set('sport', slot.sport);
        formData.set('bookingReference', `${referencePrefix}-${String(index + 1).padStart(4, '0')}`);

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
        if (payload.bookingReference) references.push(payload.bookingReference);
        state = payload.state;
    }

    if (els.bookingReferencePanel && references.length > 0) {
        bookingModalCloseUnlocked = true;
        setBookingModalStep(bookingModalStep);
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
        clearBookingSelection();
        renderAll();
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
    const members = state.adminMembers || [];

    if (members.length === 0) {
        els.adminMembers.innerHTML = '<div class="rounded-xl border border-dashed border-line bg-white p-5 text-sm font-bold text-muted">No registered members yet.</div>';
        return;
    }

    els.adminMembers.innerHTML = `
        <div class="table-responsive">
            <table class="table table-hover align-middle admin-members-table mb-0">
                <thead>
                    <tr>
                        <th scope="col">Member</th>
                        <th scope="col">Contact</th>
                        <th scope="col" class="text-center">Courts</th>
                        <th scope="col" class="text-center">Booked</th>
                        <th scope="col">Activity</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${members.map(member => `
                        <tr>
                            <td>
                                <div class="fw-black text-ink">${escapeHtml(member.name)}</div>
                                <div class="text-xs font-semibold text-muted">Member #${member.id}</div>
                            </td>
                            <td>
                                <div class="fw-bold">${escapeHtml(member.email)}</div>
                                <div class="text-xs font-semibold text-muted">${escapeHtml(member.phone || 'No phone')}</div>
                            </td>
                            <td class="text-center fw-black text-primary">${member.courtBookingsCount || 0}</td>
                            <td class="text-center fw-black text-primary">${member.confirmedCount || 0}</td>
                            <td class="text-sm fw-semibold text-secondary">
                                ${member.lastLoginAt ? `Last login ${new Date(member.lastLoginAt).toLocaleDateString()}` : `Joined ${new Date(member.createdAt).toLocaleDateString()}`}
                            </td>
                            <td>
                                <span class="status-badge ${member.isActive ? 'status-badge-booked' : 'status-badge-cancelled'}">${member.isActive ? 'Active' : 'Inactive'}</span>
                            </td>
                            <td class="text-end">
                                <button type="button" data-admin-member-id="${member.id}" data-is-active="${member.isActive ? '0' : '1'}" class="btn ${member.isActive ? 'btn-outline-danger' : 'btn-success'} btn-sm">
                                    ${member.isActive ? 'Deactivate' : 'Activate'}
                                </button>
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
}

function adminUserTableRow(user = null) {
    const isNew = !user;
    const row = user || {
        id: '',
        name: '',
        email: '',
        role: 'staff',
        isActive: true,
        lastLoginAt: null,
        createdAt: new Date().toISOString()
    };

    return `
        <tr data-admin-user-row>
            <td>
                <input type="hidden" name="id" value="${row.id || ''}">
                <div class="fw-black text-ink">${isNew ? 'New user' : `Admin #${row.id}`}</div>
                <div class="text-xs font-semibold text-muted">${isNew ? 'Create staff/admin access' : (row.lastLoginAt ? `Last login ${new Date(row.lastLoginAt).toLocaleDateString()}` : `Created ${new Date(row.createdAt).toLocaleDateString()}`)}</div>
            </td>
            <td><input required name="name" value="${escapeHtml(row.name)}" class="form-input" placeholder="Staff name"></td>
            <td><input required type="email" name="email" value="${escapeHtml(row.email)}" class="form-input" placeholder="staff@example.com"></td>
            <td>
                <select name="role" class="form-select">
                    <option value="staff" ${row.role === 'staff' ? 'selected' : ''}>Staff</option>
                    <option value="admin" ${row.role === 'admin' ? 'selected' : ''}>Admin</option>
                </select>
            </td>
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
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    }
}

async function updateStatus(id, status) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('status', status);
    if (status === 'Available') {
        const reason = window.prompt('Reason for marking this reservation available?', 'Marked available by admin');
        if (reason === null) return;
        formData.append('reason', reason.trim() || 'Marked available by admin');
    }

    const response = await fetch(`${api}?action=admin-status`, { method: 'POST', body: formData });
    const payload = await response.json();
    if (payload.ok) {
        state = payload.state;
        renderAll();
    } else if (response.status === 401) {
        window.location.href = adminLoginUrl;
    } else {
        alert(payload.message || 'Could not update status.');
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
}

async function submitCourtBlock(event) {
    event.preventDefault();
    await saveCourtBlock(event.currentTarget, false);
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

    const response = await fetch(`${api}?action=admin-override-booking`, { method: 'POST', body: formData });
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
        if (dateValue) adminScheduleDate = new Date(`${dateValue}T00:00:00`);
        if (window.bootstrap && els.adminOverrideBookingModal) {
            bootstrap.Modal.getInstance(els.adminOverrideBookingModal)?.hide();
        }
        form.reset();
        renderAll();
    }
}

document.getElementById('prevDate')?.addEventListener('click', () => {
    if (isoDate(selectedDate) <= isoDate(new Date())) return;
    selectedDate.setDate(selectedDate.getDate() - 1);
    clearBookingSelection();
    renderBookingGrid();
});

document.getElementById('nextDate')?.addEventListener('click', () => {
    selectedDate.setDate(selectedDate.getDate() + 1);
    clearBookingSelection();
    renderBookingGrid();
});

document.getElementById('adminSchedulePrev')?.addEventListener('click', () => {
    adminScheduleDate.setDate(adminScheduleDate.getDate() - 1);
    renderAdminSchedule();
});

document.getElementById('adminScheduleNext')?.addEventListener('click', () => {
    adminScheduleDate.setDate(adminScheduleDate.getDate() + 1);
    renderAdminSchedule();
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
els.adminOverrideCourt?.addEventListener('change', () => updateAdminOverrideSports());
els.adminScheduleGrid?.addEventListener('click', event => {
    const button = event.target.closest('[data-admin-calendar-booking]');
    if (button) openAdminCalendarBooking(button);
});
els.paymentMethod?.addEventListener('change', event => renderPaymentInstructions(event.target.value));
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

if (document.querySelector('[data-needs-state]')) {
    loadState();
} else if (window.lucide) {
    lucide.createIcons();
}
