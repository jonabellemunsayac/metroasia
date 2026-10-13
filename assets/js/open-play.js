/* Open Play extends the admin calendar, player grid, and payment flow. */
let openPlayOffers = [];
let openPlayDate = '';
let openPlayRequest = null;
let openPlayState = null;

async function refreshOpenPlay(date) {
    if (openPlayRequest === date) return;
    openPlayRequest = date;
    try {
        const response = await fetch(`${api}?action=openplay-offers&date=${encodeURIComponent(date)}`);
        const payload = await response.json();
        if (!payload.ok) throw new Error('Unable to load Open Play availability.');
        if (openPlayRequest !== date) return;
        openPlayOffers = payload.offers || [];
        openPlayDate = date;
        renderBookingGrid();
    } catch (error) {
        if (els.grid) els.grid.insertAdjacentHTML('beforeend', '<p role="alert">Unable to load session availability. Refresh to try again.</p>');
    } finally {
        if (openPlayRequest === date) openPlayRequest = null;
    }
}

function applyOpenPlayGrid() {
    if (!els.grid || !state) return;
    if (openPlayState !== state) {
        openPlayState = state;
        openPlayDate = '';
    }
    const date = isoDate(selectedDate);
    if (openPlayDate !== date) {
        els.grid.querySelectorAll('[data-book-date]').forEach(button => { button.disabled = true; });
        refreshOpenPlay(date);
        return;
    }
    for (const offer of openPlayOffers) {
        const buttons = [...els.grid.querySelectorAll('[data-book-date]')].filter(button =>
            button.dataset.bookDate === date && offer.slotIds.includes(Number(state.slotDetails?.[button.dataset.bookTime]?.id))
            && bookingResourcesConflict(offer.court, Number(button.dataset.bookCourt)));
        const own = buttons.filter(button => Number(button.dataset.bookCourt) === offer.court && selectedSport === offer.sport);
        for (const button of buttons.filter(button => !own.includes(button))) {
            button.disabled = true;
            button.title = 'Court unavailable during Open Play.';
            button.textContent = 'OPEN PLAY';
        }
        if (!own.length) continue;
        const first = own[0];
        const unavailable = Boolean(offer.retired || offer.disabled) || own.some(button => button.disabled);
        const count = offer.occupied.length;
        const full = count >= offer.capacity;
        const block = document.createElement('button');
        block.type = 'button';
        block.className = first.className + ' open-play-session';
        block.disabled = full || unavailable || offer.memberBooked || new Date(`${date}T${offer.start}:00`) <= new Date();
        block.innerHTML = `<strong>${escapeHtml(offer.time)}</strong><span>OPEN PLAY · ${count}/${offer.capacity} Players${offer.memberBooked ? ' - JOINED' : full ? ' · FULL' : unavailable ? ' · Unavailable' : ''}</span><small>${peso.format(offer.price)} / player · entire session</small>`;
        if (!els.grid.classList.contains('booking-grid-mobile')) block.style.gridColumn = `span ${own.length}`;
        first.replaceWith(block);
        own.slice(1).forEach(button => button.remove());
        block.addEventListener('click', () => openOpenPlaySeats(offer));
    }
}

async function openOpenPlaySeats(offer) {
    // Refresh occupied positions at selection time; the server checks again under lock.
    const response = await fetch(`${api}?action=openplay-offers&date=${encodeURIComponent(offer.date)}`);
    const payload = await response.json();
    offer = payload.offers?.find(item => item.key === offer.key);
    if (!offer || offer.disabled || offer.memberBooked || offer.occupied.length >= offer.capacity) {
        window.alert(offer?.memberBooked ? 'You already have a position in this Open Play session.' : 'This slot is already taken. Please select another available slot.');
        openPlayDate = ''; renderBookingGrid(); return;
    }
    const dialog = document.createElement('dialog');
    dialog.className = 'open-play-picker';
    dialog.innerHTML = `<h2>OPEN PLAY</h2><p>${escapeHtml(offer.courtName)} · ${escapeHtml(offer.sport)}<br>${escapeHtml(offer.date)} · ${escapeHtml(offer.time)}</p><p>${peso.format(offer.price)} per player for the entire session · ${offer.occupied.length}/${offer.capacity} Players</p><div class="open-play-seats">${Array.from({length: offer.capacity}, (_, i) => `<button type="button" aria-pressed="false" aria-label="Player ${i+1}${offer.occupied.includes(i+1) ? ', occupied' : ', available'}" data-seat="${i+1}" ${offer.occupied.includes(i+1) ? 'disabled' : ''}>${i+1}</button>`).join('')}</div><p data-total>Select one player position to continue.</p><button type="button" data-close>Cancel</button> <button type="button" data-continue disabled>Continue to payment</button>`;
    document.body.append(dialog);
    const chosen = new Set();
    dialog.querySelectorAll('[data-seat]').forEach(button => button.addEventListener('click', () => {
        if (button.disabled) return;
        const seat = Number(button.dataset.seat);
        const wasSelected = chosen.has(seat);
        chosen.clear();
        if (!wasSelected) chosen.add(seat);
        dialog.querySelectorAll('[data-seat]').forEach(circle => {
            const position = Number(circle.dataset.seat);
            const selected = chosen.has(position);
            circle.setAttribute('aria-pressed', String(selected));
            circle.disabled = offer.occupied.includes(position) || (chosen.size > 0 && !selected);
        });
        dialog.querySelector('[data-total]').textContent = `${chosen.size} player(s) · ${peso.format(chosen.size * offer.price)}`;
        dialog.querySelector('[data-continue]').disabled = !chosen.size;
    }));
    dialog.querySelector('[data-close]').onclick = () => dialog.close();
    dialog.addEventListener('close', () => dialog.remove());
    dialog.querySelector('[data-continue]').onclick = () => {
        selectedBookingSlots = [{date:offer.date,time:offer.time,court:offer.court,courtName:offer.courtName,sport:offer.sport,openPlay:offer,seats:[...chosen]}];
        state.slotDetails[offer.time] = {startsAt:offer.start,endsAt:offer.end,price:offer.price};
        if (isInlineBookingForm()) {
            renderBookingSelectionBar();
            document.getElementById('bookingInlineReservation')?.scrollIntoView({behavior:'smooth',block:'start'});
        } else {
            openCourtModal({});
            document.getElementById('actionType').value = 'openplay-session-book';
            els.modalKicker.textContent = 'OPEN PLAY - Session Payment';
        }
        dialog.close();
    };
    dialog.showModal();
}

const originalOpenPlayGrid = renderBookingGrid;
renderBookingGrid = function () { originalOpenPlayGrid(); applyOpenPlayGrid(); };
if (els.grid && state) renderBookingGrid();

let openPlayAdminTab = 'active';
let openPlayAdminPage = 1;
const openPlayAdminPageSize = 10;
const openPlayExpandedSessions = new Set();

function groupOpenPlayAdminSessions(rows, now = Date.now()) {
    const groups = new Map();
    for (const row of rows) {
        const key = String(row.sessionId);
        if (!groups.has(key)) groups.set(key, {...row, key, players: []});
        groups.get(key).players.push(row);
    }
    return [...groups.values()].map(session => {
        const endTime = String(session.endsAt || '').slice(0,8);
        let end = Date.parse(session.date + 'T' + endTime + '+08:00');
        if (endTime && endTime <= String(session.startsAt || '').slice(0,8)) end += 86400000;
        session.completed = Number.isFinite(end) && end <= now && session.players.every(player => ['Booked','Cancelled'].includes(player.status));
        session.pending = session.players.filter(player => player.status === 'Held').length;
        session.players.sort((a,b) => Number(a.seat)-Number(b.seat) || String(a.id).localeCompare(String(b.id)));
        return session;
    }).sort((a,b) => Number(a.completed)-Number(b.completed) || (a.completed ? -1 : 1) * (a.date.localeCompare(b.date) || String(a.startsAt).localeCompare(String(b.startsAt))) || Number(a.sessionId)-Number(b.sessionId));
}

function openPlayPlayerRows(players) {
    return players.map(row => '<tr><td>' + escapeHtml(row.customerName) + '<br><small>Player ' + Number(row.seat) + (row.bookingReference ? ' | ' + escapeHtml(row.bookingReference) : '') + '</small></td><td>' + peso.format(row.finalAmount) + '</td><td>' + escapeHtml(row.status) + '<br><small>' + escapeHtml(row.paymentMethod) + ' | ' + (row.status === 'Booked' ? 'Verified' : row.status === 'Cancelled' ? 'Cancelled' : 'Awaiting review') + '</small></td><td>' + (row.receipt ? '<a href="' + escapeHtml(resourceUrl(row.receipt)) + '" target="_blank" rel="noopener">Payment proof</a>' : 'No proof uploaded') + '</td><td>' + (row.status === 'Held' ? '<button class="btn btn-sm btn-primary" data-op-confirm="' + escapeHtml(row.id) + '">Confirm</button> ' : '') + (row.status !== 'Cancelled' ? '<button class="btn btn-sm btn-outline-danger" data-op-cancel="' + escapeHtml(row.id) + '">Cancel</button>' : '') + '</td></tr>').join('');
}

function renderOpenPlayAdmin() {
    if (!document.getElementById('adminRows') && !document.getElementById('dashboard')) return;
    let panel = document.getElementById('adminOpenPlayRecords');
    if (!panel) {
        panel = document.createElement('section');
        panel.id = 'adminOpenPlayRecords';
        panel.className = 'app-card mt-3';
        document.querySelector('main').append(panel);
    }
    const rows = state?.adminOpenPlayReservations || [];
    if (document.getElementById('dashboard')) {
        const counts = {...(state?.adminDashboardCourtCounts || {})};
        rows.forEach(row => { counts[row.status] = (counts[row.status] || 0) + 1; });
        renderAdminStats(counts);
    }
    const sessions = groupOpenPlayAdminSessions(rows);
    const activeCount = sessions.filter(session => !session.completed).length;
    const completedCount = sessions.length - activeCount;
    const filtered = sessions.filter(session => session.completed === (openPlayAdminTab === 'completed'));
    const pages = Math.max(1, Math.ceil(filtered.length / openPlayAdminPageSize));
    openPlayAdminPage = Math.max(1, Math.min(openPlayAdminPage, pages));
    const first = (openPlayAdminPage - 1) * openPlayAdminPageSize;
    const visible = filtered.slice(first, first + openPlayAdminPageSize);
    panel.innerHTML = '<h2>OPEN PLAY bookings</h2><div class="op-session-tabs" role="tablist" aria-label="Open Play session status">' +
        [['active','Active/Pending',activeCount],['completed','Completed',completedCount]].map(([key,label,count]) => '<button type="button" role="tab" aria-selected="' + (key===openPlayAdminTab) + '" data-op-tab="' + key + '" class="btn btn-sm ' + (key===openPlayAdminTab ? 'btn-primary' : 'btn-outline-primary') + '">' + label + ' (' + count + ')</button>').join('') +
        '</div><p class="small text-secondary">Completed sessions have ended and have no players awaiting confirmation. Expand a session to review its players.</p><div class="op-session-list">' + visible.map(session =>
            '<details class="op-session" data-op-session="' + escapeHtml(session.key) + '"' + (openPlayExpandedSessions.has(session.key) ? ' open' : '') + '><summary>' +
            '<span><small>Date</small><strong>' + escapeHtml(session.date) + '</strong></span>' +
            '<span><small>Time</small>' + escapeHtml(session.time) + '</span>' +
            '<span><small>Court</small>' + escapeHtml(session.courtName) + '</span>' +
            '<span><small>Sport</small>' + escapeHtml(session.sport) + '</span>' +
            '<span><small>Rate / Player</small>' + peso.format(session.ratePerPlayer ?? session.finalAmount) + '</span>' +
            '<span><small>Capacity</small>' + Number(session.occupied) + '/' + Number(session.capacity) + ' Players' + (session.pending ? '<small>' + session.pending + ' pending</small>' : '') + '</span></summary>' +
            '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Player / Reference</th><th>Amount</th><th>Status / Payment</th><th>Proof</th><th>Actions</th></tr></thead><tbody>' + openPlayPlayerRows(session.players) + '</tbody></table></div></details>'
        ).join('') + (visible.length ? '' : '<p class="text-secondary">No sessions in this tab.</p>') + '</div>' +
        '<div class="op-session-pagination"><button class="btn btn-sm btn-outline-primary" data-op-page="previous"' + (openPlayAdminPage===1 ? ' disabled' : '') + '>Previous</button><span>' + (filtered.length ? first+1 : 0) + '-' + Math.min(first+openPlayAdminPageSize,filtered.length) + ' of ' + filtered.length + ' sessions | Page ' + openPlayAdminPage + ' of ' + pages + '</span><button class="btn btn-sm btn-outline-primary" data-op-page="next"' + (openPlayAdminPage===pages ? ' disabled' : '') + '>Next</button></div>';
    panel.querySelectorAll('[data-op-tab]').forEach(button => button.onclick = () => {
        openPlayAdminTab = button.dataset.opTab; openPlayAdminPage = 1; renderOpenPlayAdmin();
        panel.querySelector('[data-op-tab="' + openPlayAdminTab + '"]')?.focus();
    });
    panel.querySelectorAll('[data-op-page]').forEach(button => button.onclick = () => {
        openPlayAdminPage += button.dataset.opPage === 'next' ? 1 : -1; renderOpenPlayAdmin();
    });
    panel.querySelectorAll('[data-op-session]').forEach(details => details.ontoggle = () => {
        if (!details.isConnected) return;
        details.open ? openPlayExpandedSessions.add(details.dataset.opSession) : openPlayExpandedSessions.delete(details.dataset.opSession);
    });
    panel.querySelectorAll('[data-op-confirm]').forEach(button => button.onclick = () => updateStatus(button.dataset.opConfirm,'Booked'));
    panel.querySelectorAll('[data-op-cancel]').forEach(button => button.onclick = async () => {
        const reason = window.prompt('Cancellation reason');
        if (!reason?.trim()) return;
        const data = new FormData();
        data.set('id',button.dataset.opCancel); data.set('status','Cancelled'); data.set('reason',reason);
        const response = await fetch(adminStateUrl('admin-status'),{method:'POST',body:data});
        const payload = await response.json();
        if (payload.availabilityChanged && payload.state) { state=payload.state; renderAll(); }
        if (!payload.ok) { window.alert(payload.message); return; }
        state=payload.state; renderAll();
    });
}
const originalOpenPlayAdmin = renderAdmin;
renderAdmin = function () { originalOpenPlayAdmin(); renderOpenPlayAdmin(); };
if (state) renderOpenPlayAdmin();
// Move sessions when their end time passes, without modifying any booking status.
if (document.getElementById('adminRows') || document.getElementById('dashboard')) {
    let completionSignature = '';
    setInterval(() => {
        if (document.hidden || !state) return;
        const signature = groupOpenPlayAdminSessions(state.adminOpenPlayReservations || []).map(s => s.key + ':' + s.completed).join('|');
        if (signature !== completionSignature) { completionSignature = signature; renderOpenPlayAdmin(); }
    }, 30000);
}


// Drag selects Open Play; a plain click retains regular booking behavior.
let openPlayDrag = null;
let openPlaySuppressClick = false;
let openPlaySelectedCells = [];
function syncOpenPlayCalendarSelection() {
    const toolbar = document.getElementById('adminOpenPlayToolbar');
    if (!toolbar) return;
    toolbar.hidden = !adminCanManageOperations();
    document.getElementById('adminOpenPlayClear').hidden = !openPlaySelectedCells.length;
    document.getElementById('adminOpenPlaySet').disabled = !openPlaySelectedCells.length;
    els.adminScheduleGrid.querySelectorAll('[data-admin-calendar-booking]').forEach(button => {
        const selected = openPlaySelectedCells.some(cell => cell.button === button);
        button.classList.toggle('open-play-selected', selected);
        button.classList.toggle('open-play-draggable', Boolean(openPlayCalendarCell(button)));
        button.setAttribute('aria-pressed', String(selected));
    });
    const first = openPlaySelectedCells[0], last = openPlaySelectedCells.at(-1);
    document.getElementById('adminOpenPlaySelectionHelp').textContent = first
        ? first.sport + ' - ' + first.courtName + ' - ' + first.date + ' | ' + formatRuleTime(first.start) + ' - ' + formatRuleTime(last.end)
        : 'Drag consecutive available slots to set Open Play. Click for regular booking; Shift-click to select a single slot or extend a range.';
}
function openPlayCalendarCell(button) {
    if (!button || button.disabled || button.dataset.cellStatus !== 'Available' || !adminCanManageOperations()) return null;
    const slot = state?.slotDetails?.[button.dataset.time];
    if (!slot || !slotAvailableForSport(slot, button.dataset.sport)) return null;
    return {button, id:Number(button.dataset.timeSlotId), date:button.dataset.date,
        court:Number(button.dataset.courtId), sport:button.dataset.sport, start:slot.startsAt, end:slot.endsAt,
        courtName:courtById(Number(button.dataset.courtId))?.name || 'Court ' + button.dataset.courtId};
}
function highlightOpenPlayRange(anchor, target) {
    if (!target || anchor.date !== target.date || anchor.court !== target.court || anchor.sport !== target.sport) return;
    const low = Math.min(timeToMinutes(anchor.start), timeToMinutes(target.start));
    const high = Math.max(timeToMinutes(anchor.start), timeToMinutes(target.start));
    const cells = [...els.adminScheduleGrid.querySelectorAll('[data-admin-calendar-booking]')]
        .filter(button => button.dataset.date === anchor.date && Number(button.dataset.courtId) === anchor.court && button.dataset.sport === anchor.sport)
        .map(button => ({button, slot:state.slotDetails[button.dataset.time]}))
        .filter(({slot}) => slot && timeToMinutes(slot.startsAt) >= low && timeToMinutes(slot.startsAt) <= high)
        .sort((a,b) => timeToMinutes(a.slot.startsAt) - timeToMinutes(b.slot.startsAt));
    // Walk out from the anchor; an occupied, disabled or missing hour stops the range.
    if (timeToMinutes(target.start) < timeToMinutes(anchor.start)) cells.reverse();
    const selected = [];
    for (const {button} of cells) {
        const cell = openPlayCalendarCell(button);
        if (!cell) break;
        const previous = selected.at(-1);
        if (previous && (timeToMinutes(target.start) < timeToMinutes(anchor.start)
            ? timeToMinutes(cell.end) !== timeToMinutes(previous.start)
            : timeToMinutes(previous.end) !== timeToMinutes(cell.start))) break;
        selected.push(cell);
    }
    openPlaySelectedCells = selected.sort((a,b) => timeToMinutes(a.start)-timeToMinutes(b.start));
    syncOpenPlayCalendarSelection();
}
function openCalendarOpenPlayModal() {
    if (!openPlaySelectedCells.length) return;
    const selected = [...openPlaySelectedCells];
    const first = selected[0];
    const dialog = document.createElement('dialog');
    dialog.className = 'open-play-picker';
    dialog.innerHTML = '<form><h2>Set as Open Play</h2><p>' + escapeHtml(document.getElementById('adminOpenPlaySelectionHelp').textContent) + '</p><label class="d-block mb-3">Rate Per Player<input class="form-input" required type="number" min="0.01" step="0.01" name="ratePerPlayer"></label><label class="d-block mb-3">Maximum Players<input class="form-input" required type="number" min="1" max="200" step="1" name="maxPlayers"></label><p role="alert" data-error></p><button type="button" class="btn btn-outline-secondary" data-close>Cancel</button> <button class="btn btn-primary" type="submit">Save Open Play</button></form>';
    document.body.append(dialog);
    dialog.querySelector('[data-close]').onclick = () => dialog.close();
    dialog.addEventListener('close', () => dialog.remove());
    dialog.querySelector('form').onsubmit = async event => {
        event.preventDefault();
        const form = event.currentTarget, submit = form.querySelector('[type="submit"]');
        const data = new FormData(form);
        data.set('date', first.date); data.set('courtId', first.court); data.set('sport', first.sport);
        data.set('slotIds', JSON.stringify(selected.map(cell => cell.id)));
        submit.disabled = true;
        try {
            const response = await fetch(api + '?action=admin-openplay-session', {method:'POST',body:data});
            const payload = await response.json();
            if (payload.availabilityChanged && payload.state) { state=payload.state; renderAll(); }
            if (!payload.ok) throw new Error(payload.message || 'Unable to create the session.');
            state = payload.state;
            openPlayDrag = null; openPlaySelectedCells = [];
            dialog.close(); renderAll();
        } catch (error) { form.querySelector('[data-error]').textContent = error.message; }
        finally { submit.disabled = false; }
    };
    dialog.showModal();
}
if (document.getElementById('adminOpenPlayToolbar')) {
    const grid = els.adminScheduleGrid;
    const clearSelection = () => {
        if (openPlayDrag && grid.hasPointerCapture(openPlayDrag.pointerId)) grid.releasePointerCapture(openPlayDrag.pointerId);
        openPlayDrag = null; openPlaySelectedCells = []; syncOpenPlayCalendarSelection();
    };
    document.getElementById('adminOpenPlayClear').onclick = clearSelection;
    document.getElementById('adminOpenPlaySet').onclick = openCalendarOpenPlayModal;
    grid.addEventListener('pointerdown', event => {
        if (event.button !== 0 || !event.isPrimary) return;
        openPlaySuppressClick = false;
        const anchor = openPlayCalendarCell(event.target.closest('[data-admin-calendar-booking]'));
        if (!anchor) return;
        openPlayDrag = {anchor, pointerId:event.pointerId, x:event.clientX, y:event.clientY, moved:false};
        grid.setPointerCapture(event.pointerId);
    });
    grid.addEventListener('pointermove', event => {
        if (!openPlayDrag || event.pointerId !== openPlayDrag.pointerId) return;
        if (!openPlayDrag.moved && Math.hypot(event.clientX-openPlayDrag.x, event.clientY-openPlayDrag.y) < 5) return;
        if (!openPlayDrag.moved) {
            openPlayDrag.moved = true;
            highlightOpenPlayRange(openPlayDrag.anchor, openPlayDrag.anchor);
        }
        event.preventDefault();
        const button = document.elementFromPoint(event.clientX,event.clientY)?.closest('[data-admin-calendar-booking]');
        // Keep the anchor's court/date. Checking every intervening cell prevents jumping across conflicts.
        if (button) {
            const slot = state.slotDetails[button.dataset.time];
            if (slot) highlightOpenPlayRange(openPlayDrag.anchor, {date:button.dataset.date,court:Number(button.dataset.courtId),sport:button.dataset.sport,start:slot.startsAt});
        }
    });
    grid.addEventListener('pointerup', event => {
        if (!openPlayDrag || event.pointerId !== openPlayDrag.pointerId) return;
        const drag = openPlayDrag;
        openPlayDrag = null;
        openPlaySuppressClick = drag.moved;
        if (grid.hasPointerCapture(event.pointerId)) grid.releasePointerCapture(event.pointerId);
        // Pointer capture retargets click to the grid. Explicitly preserve a plain click on its original cell.
        if (!drag.moved && !event.shiftKey) {
            openPlaySelectedCells = []; syncOpenPlayCalendarSelection();
            openAdminCalendarBooking(drag.anchor.button);
            openPlaySuppressClick = true;
        } else if (!drag.moved && event.shiftKey) {
            highlightOpenPlayRange(openPlaySelectedCells[0] || drag.anchor, drag.anchor);
            openPlaySuppressClick = true;
        }
    });
    grid.addEventListener('pointercancel', clearSelection);
    grid.addEventListener('lostpointercapture', () => { if (openPlayDrag) clearSelection(); });
    window.addEventListener('blur', () => { if (openPlayDrag) clearSelection(); });
    grid.addEventListener('keydown', event => { if (event.key === 'Escape') clearSelection(); });
    grid.addEventListener('click', event => {
        if (openPlaySuppressClick) {
            openPlaySuppressClick = false; event.stopImmediatePropagation(); event.preventDefault(); return;
        }
        const cell = openPlayCalendarCell(event.target.closest('[data-admin-calendar-booking]'));
        if (cell && event.shiftKey) {
            event.stopImmediatePropagation(); event.preventDefault();
            highlightOpenPlayRange(openPlaySelectedCells[0] || cell,cell);
        }
    }, true);
    const originalOpenPlaySchedule = renderAdminSchedule;
    renderAdminSchedule = function () {
        clearSelection();
        originalOpenPlaySchedule(); syncOpenPlayCalendarSelection();
    };
    syncOpenPlayCalendarSelection();
}

function showAdminOpenPlaySession(id) {
    const session = (state.adminOpenPlaySessions || []).find(row => Number(row.sessionId) === id);
    if (!session) return;
    const rows = (state.adminOpenPlayReservations || []).filter(row => Number(row.sessionId) === id);
    const dialog = document.createElement('dialog');
    dialog.className = 'open-play-picker';
    dialog.innerHTML = '<h2>OPEN PLAY</h2><p>' + escapeHtml(session.courtName) + ' - ' + escapeHtml(session.sport) + '<br>' + escapeHtml(session.date) + ' | ' + escapeHtml(session.time) + '</p><p>' + Number(session.occupied) + '/' + Number(session.capacity) + ' Players - ' + peso.format(session.price) + ' / player</p><ul>' + rows.map(row => '<li>Player ' + Number(row.seat) + ': ' + escapeHtml(row.customerName) + ' - ' + escapeHtml(row.status) + '</li>').join('') + '</ul><button type="button" class="btn btn-primary">Close</button>';
    dialog.querySelector('button').onclick = () => dialog.close();
    dialog.addEventListener('close', () => dialog.remove());
    document.body.append(dialog); dialog.showModal();
}

// Size the matrix independently of the number of hours and wrapped toolbar text.
(() => {
    const panel = document.getElementById('schedule-dashboard');
    const scroll = document.getElementById('adminScheduleScroll');
    const toolbar = document.getElementById('adminMatrixActions');
    const topbar = document.querySelector('.admin-topbar');
    if (!panel || !scroll || !toolbar) return;
    const measure = () => {
        panel.style.setProperty('--matrix-topbar-height', (topbar?.getBoundingClientRect().height || 0) + 'px');
        panel.style.setProperty('--matrix-toolbar-height', toolbar.getBoundingClientRect().height + 'px');
    };
    if (window.ResizeObserver) {
        const observer = new ResizeObserver(measure);
        [topbar, toolbar].filter(Boolean).forEach(element => observer.observe(element));
    }
    window.addEventListener('resize', measure);
    measure();

    // Continue selecting lower/upper hours when dragging near the viewport edge.
    let point = null, frame = null;
    const stop = () => { point = null; cancelAnimationFrame(frame); frame = null; };
    const tick = () => {
        frame = null;
        if (!openPlayDrag || !point) return;
        const rect = scroll.getBoundingClientRect();
        const headerHeight = scroll.querySelector('.admin-schedule-head')?.getBoundingClientRect().height || 0;
        const top = Math.max(rect.top, toolbar.getBoundingClientRect().bottom) + headerHeight;
        const bottom = Math.min(rect.bottom, window.innerHeight);
        if (openPlayDrag.moved && point.x >= rect.left && point.x <= rect.right) {
            const delta = point.y < top + 32 ? -10 : point.y > bottom - 32 ? 10 : 0;
            if (delta) {
                scroll.scrollTop += delta;
                const button = document.elementFromPoint(point.x, Math.max(top + 2, Math.min(bottom - 2, point.y)))?.closest('[data-admin-calendar-booking]');
                const slot = button && state.slotDetails[button.dataset.time];
                if (slot) highlightOpenPlayRange(openPlayDrag.anchor, {date:button.dataset.date,court:Number(button.dataset.courtId),sport:button.dataset.sport,start:slot.startsAt});
            }
        }
        frame = requestAnimationFrame(tick);
    };
    scroll.addEventListener('pointermove', event => {
        if (!openPlayDrag) return;
        point = {x:event.clientX, y:event.clientY};
        if (frame === null) frame = requestAnimationFrame(tick);
    });
    window.addEventListener('pointerup', stop);
    window.addEventListener('pointercancel', stop);
    window.addEventListener('blur', stop);
})();
