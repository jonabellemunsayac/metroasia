const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
const api = window.appConfig?.apiUrl || 'api.php';
const adminLoginUrl = window.appConfig?.adminLoginUrl || 'login.php';
const rootUrl = window.appConfig?.rootUrl || '';

let state = null;
let selectedDate = new Date();
let adminScheduleDate = new Date();
let adminFilter = 'Payment Submitted';
let selectedSport = 'Pickleball';

const els = {
    rates: document.getElementById('rateCards'),
    grid: document.getElementById('bookingGrid'),
    dateLabel: document.getElementById('bookingDateLabel'),
    openPlay: document.getElementById('openPlayCards'),
    admin: document.getElementById('adminRows'),
    modal: document.getElementById('bookingModal'),
    modalTitle: document.getElementById('modalTitle'),
    modalMeta: document.getElementById('modalMeta'),
    modalKicker: document.getElementById('modalKicker'),
    form: document.getElementById('paymentForm'),
    formMessage: document.getElementById('formMessage'),
    paymentMethod: document.getElementById('paymentMethodSelect'),
    paymentInstructions: document.getElementById('paymentInstructions'),
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
    const target = els.grid || els.openPlay || els.admin;
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
    const value = String(path || '');
    if (!value) return '';
    if (/^(https?:)?\/\//.test(value) || value.startsWith('data:')) return value;
    return `${rootUrl}${value.replace(/^\/+/, '')}`;
}

function isActiveReservation(status) {
    return ['Held', 'Payment Pending', 'Payment Submitted', 'Under Review', 'Confirmed'].includes(status);
}

function statusTone(status) {
    if (status === 'Available') return 'available';
    if (['Confirmed', 'Completed'].includes(status)) return 'booked';
    if (['Cancelled', 'Rejected', 'Expired', 'No Show'].includes(status)) return 'cancelled';
    if (status === 'Blocked') return 'blocked';
    return 'pending';
}

function compactStatusLabel(status) {
    return {
        'Payment Pending': 'Pending',
        'Payment Submitted': 'Submitted',
        'Under Review': 'Review',
        Confirmed: 'Confirmed',
        Held: 'Held',
        Blocked: 'Blocked'
    }[status] || status;
}

function renderAll() {
    renderRates();
    renderBookingGrid();
    renderOpenPlay();
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
    return {
        id: '',
        name: '',
        courtId: '',
        sport: 'Pickleball',
        dayPattern: 'Monday-Friday',
        startsAt: '08:00',
        endsAt: '17:00',
        durationMinutes: 60,
        pricePerHour: 400,
        memberPricePerHour: '',
        effectiveFrom: '',
        effectiveTo: '',
        priority: 0,
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
        ['', 'All courts'],
        ...(state?.courts || []).map(court => [court.id, court.name])
    ], rule.courtId ?? '');
    setSelectOptions(els.adminRateSport, [
        ['', 'All sports'],
        ['Pickleball', 'Pickleball'],
        ['Basketball', 'Basketball'],
        ['Volleyball', 'Volleyball']
    ], rule.sport ?? '');
    setSelectOptions(els.adminRateDay, ['Any', 'Monday-Friday', 'Saturday-Sunday', 'Weekday', 'Weekend', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], rule.dayPattern || rule.dayType || 'Any');
    setSelectOptions(els.adminRateDuration, [30, 60, 90, 120, 180, 240].map(minutes => [minutes, `${minutes} min`]), rule.durationMinutes || 60);
    setSelectOptions(els.adminRateReason, rateReasonList(rule.changeReason), rule.changeReason || 'Regular rate');
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
    const proofNote = state?.member
        ? 'Registered members can upload payment proof directly on the website. Admin confirms payment after review.'
        : 'After payment, non-members should send proof through Facebook Messenger with the reservation name, date, sport, court, and time.';

    if (details.type === 'qr') {
        els.paymentInstructions.innerHTML = `
            <div class="grid gap-4 sm:grid-cols-[132px_1fr]">
                <div class="grid gap-2">
                    ${details.qrPath ? `<img src="${escapeHtml(resourceUrl(details.qrPath))}" alt="${escapeHtml(details.name)} payment QR code" class="h-32 w-32 rounded-md border border-slate-200 bg-white p-2">` : '<div class="grid h-32 w-32 place-items-center rounded-md border border-dashed border-slate-300 bg-white p-2 text-center text-xs font-bold text-slate-500">No QR uploaded</div>'}
                    ${details.qrPath ? `<a href="${escapeHtml(resourceUrl(details.qrPath))}" download class="inline-flex w-32 justify-center rounded-md border border-line bg-white px-3 py-2 text-xs font-black text-primary">Download QR</a>` : ''}
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
                ${details.qrPath ? `<img src="${escapeHtml(resourceUrl(details.qrPath))}" alt="${escapeHtml(details.name)} payment QR code" class="h-32 w-32 rounded-md border border-slate-200 bg-white p-2">` : '<div class="grid h-32 w-32 place-items-center rounded-md border border-dashed border-slate-300 bg-white p-2 text-center text-xs font-bold text-slate-500">No QR uploaded</div>'}
                ${details.qrPath ? `<a href="${escapeHtml(resourceUrl(details.qrPath))}" download class="inline-flex w-32 justify-center rounded-md border border-line bg-white px-3 py-2 text-xs font-black text-primary">Download QR</a>` : ''}
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
        ? `<div class="grid gap-2"><img src="${escapeHtml(resourceUrl(channel.qrPath))}" alt="${escapeHtml(channel.name)} payment QR code" class="h-32 w-32 rounded-md border border-slate-200 bg-slate-50 p-2"><a href="${escapeHtml(resourceUrl(channel.qrPath))}" download class="inline-flex w-32 justify-center rounded-md border border-line px-3 py-2 text-xs font-black text-primary">Download QR</a></div>`
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
    return state.courts.filter(court => court.sports.includes(selectedSport));
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

    const bookings = Object.values(state.bookings).filter(item => item.date === date && item.time === time);

    if (['Basketball', 'Volleyball'].includes(selectedSport) && court === 2) {
        const wooden = bookings.find(item => [5, 6, 7].includes(Number(item.court)));
        if (wooden) {
            return {
                status: 'Blocked',
                sport: wooden.sport,
                shortLabel: `Wooden ${wooden.court}`,
                message: `Miami is unavailable because Wooden Court ${wooden.court} is ${wooden.status} for Pickleball during ${time}.`
            };
        }
    }

    if (selectedSport === 'Pickleball' && [5, 6, 7].includes(court)) {
        const miami = bookings.find(item => Number(item.court) === 2 && ['Basketball', 'Volleyball'].includes(item.sport));
        if (miami) {
            return {
                status: 'Blocked',
                sport: miami.sport,
                shortLabel: 'Miami',
                message: `Miami is reserved for ${miami.sport} during ${time}. Wooden Courts 5, 6 and 7 are unavailable during this period.`
            };
        }
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
        if (!blockSport || blockSport === sport) return true;
        return [1, 2].includes(court);
    }

    if (court === 2 && ['Basketball', 'Volleyball'].includes(sport)) {
        return [5, 6, 7].includes(blockCourt) && (!blockSport || blockSport === 'Pickleball');
    }

    if ([5, 6, 7].includes(court) && sport === 'Pickleball') {
        return blockCourt === 2 && (!blockSport || ['Basketball', 'Volleyball'].includes(blockSport));
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
        { key: 'lakers', label: 'LAKERS', kind: 'lakers' },
        { key: 'miami', label: 'MIAMI', kind: 'miami' },
        { key: 'pb1', label: 'PB1', court: 1, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'pb2', label: 'PB2', court: 2, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'w5', label: 'WOODEN 5', court: 5, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'w6', label: 'WOODEN 6', court: 6, sport: 'Pickleball', openLabel: 'OPEN' },
        { key: 'w7', label: 'WOODEN 7', court: 7, sport: 'Pickleball', openLabel: 'OPEN' }
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
    if (booking.status === 'Confirmed') return pickleballLabel;
    return compactStatusLabel(booking.status).toUpperCase();
}

function scheduleCell(label, status = 'Available', sub = '', title = '') {
    return { label, status, sub, title: title || sub || label };
}

function adminColumnSelection(column, cell) {
    if (column.kind === 'lakers') {
        const sport = cell?.label === 'PB1' ? 'Pickleball' : (cell?.label === 'VOLLEYBALL' ? 'Volleyball' : 'Basketball');
        return { courtId: 1, sport };
    }
    if (column.kind === 'miami') {
        const sport = cell?.label === 'PB2' ? 'Pickleball' : (cell?.label === 'VOLLEYBALL' ? 'Volleyball' : 'Basketball');
        return { courtId: 2, sport };
    }
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
    const slotBookings = bookingsAt(date, time);

    if (column.kind === 'lakers') {
        const direct = directBookingAt(date, time, 1);
        if (direct) {
            const label = adminBookingLabel(direct, 'PB1');
            return scheduleCell(label, direct.status, direct.status, `${label}: ${direct.customerName || 'Reserved'}`);
        }
        return blockCell(date, time, 1, 'Basketball')
            || blockCell(date, time, 1, 'Volleyball')
            || blockCell(date, time, 1, 'Pickleball')
            || scheduleCell('AVAILABLE', 'Available', '', 'Lakers is available');
    }

    if (column.kind === 'miami') {
        const miamiSport = slotBookings.find(item => Number(item.court) === 2 && ['Basketball', 'Volleyball'].includes(item.sport));
        if (miamiSport) {
            return scheduleCell(miamiSport.sport.toUpperCase(), miamiSport.status, miamiSport.status, `Miami reserved for ${miamiSport.sport}`);
        }

        const wooden = slotBookings.find(item => [5, 6, 7].includes(Number(item.court)));
        if (wooden) {
            const label = `WOODEN ${wooden.court}`;
            return scheduleCell(label, 'Blocked', 'Miami unavailable', `${label} is occupied, so Miami basketball and volleyball are unavailable.`);
        }

        const miamiPickleball = slotBookings.find(item => Number(item.court) === 2 && item.sport === 'Pickleball');
        if (miamiPickleball) {
            return scheduleCell(adminBookingLabel(miamiPickleball, 'PB2'), miamiPickleball.status, miamiPickleball.status, 'Pickleball Pro Court 2 is reserved.');
        }

        return blockCell(date, time, 2, 'Basketball')
            || blockCell(date, time, 2, 'Volleyball')
            || scheduleCell('AVAILABLE', 'Available', '', 'Miami is available');
    }

    const direct = directBookingAt(date, time, column.court);
    if (direct) {
        const isSameSport = direct.sport === column.sport;
        const label = isSameSport ? adminBookingLabel(direct, 'BOOKED') : direct.sport.toUpperCase();
        const status = isSameSport ? direct.status : 'Blocked';
        const sub = isSameSport ? direct.status : `Uses ${column.label}`;
        return scheduleCell(label, status, sub, `${column.label}: ${direct.sport} ${direct.status}`);
    }

    if ([5, 6, 7].includes(Number(column.court))) {
        const miami = slotBookings.find(item => Number(item.court) === 2 && ['Basketball', 'Volleyball'].includes(item.sport));
        if (miami) {
            return scheduleCell(miami.sport.toUpperCase(), 'Blocked', 'Miami active', `Miami is reserved for ${miami.sport}, so Wooden Courts 5-7 are unavailable.`);
        }
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
    form.querySelector('[name="status"]').value = status === 'Available' ? 'Confirmed' : 'Confirmed';
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

function rateForSlot(time, courtId = null, sport = selectedSport, date = isoDate(selectedDate)) {
    const slot = state?.slotDetails?.[time];
    const rules = state?.rateRules || [];
    const selectedCourtId = Number(courtId || courtsForSelectedSport()[0]?.id || 0);

    const matches = rules.filter(rule => {
        if (rule.courtId && Number(rule.courtId) !== selectedCourtId) return false;
        if (rule.sport && rule.sport !== sport) return false;
        if (!dayPatternMatches(rule.dayPattern || rule.dayType, date)) return false;
        if (slot && timeToMinutes(rule.startsAt) > timeToMinutes(slot.startsAt)) return false;
        if (slot && timeToMinutes(rule.endsAt) < timeToMinutes(slot.endsAt)) return false;
        if (slot && rule.durationMinutes && Number(rule.durationMinutes) !== Math.round(slotDuration(slot) * 60)) return false;
        if (rule.effectiveFrom && rule.effectiveFrom > date) return false;
        if (rule.effectiveTo && rule.effectiveTo < date) return false;
        return true;
    }).sort((a, b) => {
        if (Number(b.priority) !== Number(a.priority)) return Number(b.priority) - Number(a.priority);
        if (Boolean(b.courtId) !== Boolean(a.courtId)) return Number(Boolean(b.courtId)) - Number(Boolean(a.courtId));
        if (Boolean(b.sport) !== Boolean(a.sport)) return Number(Boolean(b.sport)) - Number(Boolean(a.sport));
        return Number(b.id) - Number(a.id);
    });

    const duration = slotDuration(slot);
    const rule = matches[0];
    if (rule) {
        const hourly = state?.member && rule.memberPricePerHour ? Number(rule.memberPricePerHour) : Number(rule.pricePerHour);
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

function renderBookingGrid() {
    if (!els.grid || !state) return;
    const date = isoDate(selectedDate);
    const today = isoDate(new Date());
    const courts = courtsForSelectedSport();
    els.dateLabel.textContent = `${niceDate(date)}${date === today ? ' - Today' : ''}`;
    els.grid.style.gridTemplateColumns = `minmax(118px, 128px) repeat(${courts.length}, minmax(54px, 1fr))`;
    els.grid.style.minWidth = courts.length > 2 ? `${118 + courts.length * 56}px` : '';

    const header = ['Time', ...courts];
    const headerHtml = header.map((item, index) => `
        <div class="border-b border-slate-200 bg-slate-50 p-2.5 text-xs font-black ${index === 0 ? '' : 'text-center'}">
            ${index === 0 ? item : courtDisplayName(item)}${index > 0 ? `<p class="text-[10px] font-medium text-slate-500">${item.surface || item.type}</p>` : ''}
        </div>
    `).join('');

    const rows = Object.entries(state.timeSlots).map(([period, slots]) => {
        const periodRow = `
            <div class="border-b border-slate-200 bg-slate-200/70 p-2.5 text-xs font-black text-slate-600">${period}</div>
            <div style="grid-column: span ${courts.length} / span ${courts.length}" class="border-b border-slate-200 bg-slate-100"></div>
        `;
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
                const tone = statusTone(status);
                const css = tone === 'booked' ? 'status-booked' : tone === 'pending' ? 'status-pending' : tone === 'blocked' ? 'status-blocked' : 'status-available hover:border-court hover:bg-emerald-50 hover:text-court';
                const disabled = status !== 'Available' ? 'disabled' : '';
                const label = status === 'Available' ? '' : '<span class="sr-only">Unavailable</span>';
                const help = status === 'Available'
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
                        class="slot m-0.5 rounded-md border px-1.5 py-1.5 text-[11px] font-black transition ${css}">
                        ${label || '<span class="sr-only">Available</span>'}
                    </button>
                `;
            }).join('');
            return first + courtCells;
        }).join('');
        return periodRow + slotRows;
    }).join('');

    els.grid.innerHTML = headerHtml + rows;
    els.grid.querySelectorAll('[data-book-date]').forEach(button => {
        button.addEventListener('click', () => openCourtModal(button.dataset));
    });
}

function renderOpenPlay() {
    if (!els.openPlay || !state) return;
    els.openPlay.innerHTML = state.openPlays.map(session => {
        const reserved = state.openPlayReservations.filter(item => item.sessionId === session.id && isActiveReservation(item.status)).length;
        const left = Math.max(0, Number(session.capacity) - reserved);
        return `
            <article class="public-card p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-black text-primary">${niceDate(session.date)} - ${session.time}</p>
                        <h3 class="mt-2 text-lg font-black">${session.title}</h3>
                    </div>
                    <span class="rounded-full bg-limevolt px-3 py-2 text-sm font-black text-ink">${peso.format(Number(session.price))}</span>
                </div>
                <p class="mt-3 text-sm font-bold text-slate-500">${session.level}</p>
                <p class="mt-3 min-h-[60px] text-sm leading-6 text-slate-600">${session.description}</p>
                <div class="mt-5 flex items-center justify-between gap-4">
                    <p class="text-sm font-black">${left} spots left</p>
                    <button data-openplay="${session.id}" ${left === 0 ? 'disabled' : ''} class="btn btn-primary disabled:cursor-not-allowed disabled:bg-slate-300">Join</button>
                </div>
            </article>
        `;
    }).join('');

    els.openPlay.querySelectorAll('[data-openplay]').forEach(button => {
        const session = state.openPlays.find(item => item.id === button.dataset.openplay);
        button.addEventListener('click', () => openPlayModal(session));
    });
}

function renderAdmin() {
    if (!state) return;
    const allRows = state.adminReservations || [];
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
                <p class="mb-1 fw-black text-ink">${item.type === 'court' ? `${niceDate(item.date)} - ${item.time}` : escapeHtml(item.sessionTitle)}</p>
                <p class="mb-0 text-xs text-secondary">${item.type === 'court' ? `Court ${item.court} - ${item.sport}` : `Open Play - ${niceDate(item.date)} - ${item.time}`}</p>
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
                    <button data-admin-id="${item.id}" data-status="Confirmed" class="btn btn-success btn-sm">Confirm</button>
                    <button data-admin-id="${item.id}" data-status="Under Review" class="btn btn-secondary btn-sm">Review</button>
                    <button data-admin-id="${item.id}" data-status="Completed" class="btn btn-secondary btn-sm">Complete</button>
                    <button data-admin-id="${item.id}" data-status="Cancelled" class="btn btn-danger btn-sm">Cancel</button>
                    <button data-admin-id="${item.id}" data-status="Rejected" class="btn btn-warning btn-sm">Reject</button>
                    <button data-admin-id="${item.id}" data-status="No Show" class="btn btn-warning btn-sm">No Show</button>
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
    if (statusTone(status) === 'cancelled') return 'status-badge-cancelled';
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
    if (pending) pending.textContent = (counts.Held || 0) + (counts['Payment Pending'] || 0) + (counts['Payment Submitted'] || 0) + (counts['Under Review'] || 0);
    if (booked) booked.textContent = counts.Confirmed || 0;
    if (cancelled) cancelled.textContent = (counts.Cancelled || 0) + (counts.Rejected || 0);
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
        .filter(rule => rule.isActive)
        .sort((a, b) => {
            const courtCompare = String(a.courtName || 'All courts').localeCompare(String(b.courtName || 'All courts'));
            if (courtCompare) return courtCompare;
            const sportCompare = String(a.sport || 'All sports').localeCompare(String(b.sport || 'All sports'));
            if (sportCompare) return sportCompare;
            return timeToMinutes(a.startsAt) - timeToMinutes(b.startsAt);
        });

    if (rows.length === 0) {
        els.adminRateSummary.innerHTML = '<tr><td colspan="6" class="text-secondary">No active rates configured.</td></tr>';
        return;
    }

    els.adminRateSummary.innerHTML = rows.map(rule => `
        <tr>
            <td>${escapeHtml(rule.courtName || 'All courts')}</td>
            <td>${escapeHtml(rule.sport || 'All sports')}</td>
            <td>${escapeHtml(rule.dayPattern || rule.dayType || 'Any')}</td>
            <td>${formatRuleTime(rule.startsAt)}-${formatRuleTime(rule.endsAt)}</td>
            <td class="text-end">${peso.format(Number(rule.pricePerHour || 0))}/hr</td>
            <td class="text-end">
                <button type="button" class="btn btn-outline-primary btn-sm" data-admin-rate-edit="${rule.id}">
                    Edit
                </button>
            </td>
        </tr>
    `).join('');

    els.adminRateSummary.querySelectorAll('[data-admin-rate-edit]').forEach(button => {
        button.addEventListener('click', () => openAdminRateModal(button.dataset.adminRateEdit));
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
            ['2|', 'Entire Miami facility'],
            ['5|Pickleball', 'Wooden Court 5'],
            ['6|Pickleball', 'Wooden Court 6'],
            ['7|Pickleball', 'Wooden Court 7'],
            ['1|', 'Lakers'],
            ['1|Pickleball', 'Pickleball Pro Court 1'],
            ['2|Pickleball', 'Pickleball Pro Court 2']
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
    els.modalKicker.textContent = 'Court Booking Payment';
    els.modalTitle.textContent = `${data.bookCourtName || `Court ${data.bookCourt}`} - ${data.bookSport || selectedSport}`;
    const rate = rateForSlot(data.bookTime, Number(data.bookCourt), data.bookSport || selectedSport, data.bookDate);
    els.modalMeta.textContent = `${niceDate(data.bookDate)} - ${data.bookTime} - ${peso.format(rate.amount)} (${rate.ruleName})`;
    showModal();
    document.getElementById('actionType').value = 'book';
    document.getElementById('formDate').value = data.bookDate;
    document.getElementById('formTime').value = data.bookTime;
    document.getElementById('formCourt').value = data.bookCourt;
    document.getElementById('formSport').value = data.bookSport || selectedSport;
    document.getElementById('formSessionId').value = '';
}

function openPlayModal(session) {
    if (!els.modal) return;
    els.modalKicker.textContent = 'Open Play Payment';
    els.modalTitle.textContent = session.title;
    els.modalMeta.textContent = `${niceDate(session.date)} - ${session.time} - ${peso.format(Number(session.price))}`;
    showModal();
    document.getElementById('actionType').value = 'openplay';
    document.getElementById('formDate').value = session.date;
    document.getElementById('formTime').value = session.time;
    document.getElementById('formCourt').value = '';
    document.getElementById('formSport').value = '';
    document.getElementById('formSessionId').value = session.id;
}

function showModal() {
    els.form.reset();
    renderPaymentInstructions('');
    els.formMessage.className = 'hidden rounded-md p-3 text-sm font-bold';
    els.modal.classList.remove('hidden');
    els.modal.classList.add('flex');
    document.body.classList.add('modal-open');
}

function closeModal() {
    els.modal.classList.add('hidden');
    els.modal.classList.remove('flex');
    document.body.classList.remove('modal-open');
}

function showFormMessage(message, ok) {
    els.formMessage.textContent = message;
    els.formMessage.className = `rounded-md p-3 text-sm font-bold ${ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;
}

async function submitPayment(event) {
    event.preventDefault();
    const actionType = document.getElementById('actionType').value;
    const formData = new FormData(els.form);
    const endpoint = actionType === 'openplay' ? 'openplay' : 'book';
    formData.append('action', endpoint);

    const response = await fetch(`${api}?action=${endpoint}`, { method: 'POST', body: formData });
    const payload = await response.json();
    showFormMessage(payload.message || 'Saved.', payload.ok);

    if (payload.ok) {
        state = payload.state;
        renderAll();
        setTimeout(closeModal, 900);
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
                        <th scope="col" class="text-center">Open Play</th>
                        <th scope="col" class="text-center">Confirmed</th>
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
                            <td class="text-center fw-black text-primary">${member.openPlayCount || 0}</td>
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
    if (['Cancelled', 'Rejected', 'No Show'].includes(status)) {
        const reason = window.prompt(`Reason for ${status.toLowerCase()}?`, `${status} by admin`);
        if (reason === null) return;
        formData.append('reason', reason.trim() || `${status} by admin`);
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
    formData.set('isActive', form.querySelector('[name="isActive"]').checked ? '1' : '0');

    const response = await fetch(`${api}?action=admin-rate-rule`, { method: 'POST', body: formData });
    const payload = await response.json();
    message.textContent = payload.message || 'Saved.';
    message.className = `rounded-md p-3 text-sm font-bold ${payload.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`;

    if (payload.ok) {
        state = payload.state;
        renderAll();
        if (window.bootstrap && els.adminRateModal) {
            bootstrap.Modal.getInstance(els.adminRateModal)?.hide();
        }
    }
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
    selectedDate.setDate(selectedDate.getDate() - 1);
    renderBookingGrid();
});

document.getElementById('nextDate')?.addEventListener('click', () => {
    selectedDate.setDate(selectedDate.getDate() + 1);
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

document.getElementById('closeModal')?.addEventListener('click', closeModal);
els.modal?.addEventListener('click', event => {
    if (event.target === els.modal) closeModal();
});
els.form?.addEventListener('submit', submitPayment);
els.adminOverrideBookingForm?.addEventListener('submit', submitAdminOverrideBooking);
els.adminOverrideCourt?.addEventListener('change', () => updateAdminOverrideSports());
els.adminScheduleGrid?.addEventListener('click', event => {
    const button = event.target.closest('[data-admin-calendar-booking]');
    if (button) openAdminCalendarBooking(button);
});
els.paymentMethod?.addEventListener('change', event => renderPaymentInstructions(event.target.value));

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
