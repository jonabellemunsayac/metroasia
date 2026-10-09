/* Open Play extends the existing rate form, grid, and payment flow. */
let openPlayOffers = [];
let openPlayDate = '';
let openPlayRequest = null;
let openPlayState = null;

function configureOpenPlayRate(rule = {}) {
    const toggle = document.getElementById('adminRateOpenPlay');
    if (!toggle) return;
    toggle.checked = Boolean(rule.openPlayKey);
    document.getElementById('adminRateMaxPlayers').value = rule.maxPlayers || '';
    document.getElementById('adminRatePerPlayer').value = rule.ratePerPlayer || '';
    const sync = () => {
        document.getElementById('adminOpenPlayFields').hidden = !toggle.checked;
        for (const id of ['adminRateDayOfWeek', 'adminRatePrice']) {
            const field = document.getElementById(id);
            field.closest('label').hidden = toggle.checked;
        }
        const hourlyPrice = document.getElementById('adminRatePrice');
        hourlyPrice.required = !toggle.checked;
        hourlyPrice.disabled = toggle.checked;
        for (const id of ['adminRateMaxPlayers', 'adminRatePerPlayer']) {
            document.getElementById(id).required = toggle.checked;
            document.getElementById(id).disabled = !toggle.checked;
        }
    };
    toggle.onchange = () => { sync(); syncAdminRateHolidayFields(); };
    sync();
}

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
        block.disabled = full || unavailable || new Date(`${date}T${offer.start}:00`) <= new Date();
        block.innerHTML = `<strong>${escapeHtml(offer.time)}</strong><span>OPEN PLAY · ${count}/${offer.capacity} Players${full ? ' · FULL' : unavailable ? ' · Unavailable' : ''}</span><small>${peso.format(offer.price)} / player · entire session</small>`;
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
    if (!offer) { window.alert('This session is no longer available.'); return; }
    const dialog = document.createElement('dialog');
    dialog.className = 'open-play-picker';
    dialog.innerHTML = `<h2>OPEN PLAY</h2><p>${escapeHtml(offer.courtName)} · ${escapeHtml(offer.sport)}<br>${escapeHtml(offer.date)} · ${escapeHtml(offer.time)}</p><p>${peso.format(offer.price)} per player for the entire session · ${offer.occupied.length}/${offer.capacity} Players</p><div class="open-play-seats">${Array.from({length: offer.capacity}, (_, i) => `<button type="button" aria-pressed="false" aria-label="Player ${i+1}${offer.occupied.includes(i+1) ? ', occupied' : ', available'}" data-seat="${i+1}" ${offer.occupied.includes(i+1) ? 'disabled' : ''}>${i+1}</button>`).join('')}</div><p data-total>Select players to continue.</p><button type="button" data-close>Cancel</button> <button type="button" data-continue disabled>Continue to payment</button>`;
    document.body.append(dialog);
    const chosen = new Set();
    dialog.querySelectorAll('[data-seat]').forEach(button => button.addEventListener('click', () => {
        const seat = Number(button.dataset.seat);
        chosen.has(seat) ? chosen.delete(seat) : chosen.add(seat);
        button.setAttribute('aria-pressed', String(chosen.has(seat)));
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
configureOpenPlayRate();
if (els.grid && state) renderBookingGrid();

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
    panel.innerHTML = `<h2>OPEN PLAY bookings</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Session</th><th>Player</th><th>Rate / Player</th><th>Capacity</th><th>Status / Payment</th><th>Actions</th></tr></thead><tbody>${rows.map(row => `<tr><td><strong>OPEN PLAY</strong><br>${escapeHtml(row.date)} ${escapeHtml(row.time)}<br>${escapeHtml(row.courtName)} · ${escapeHtml(row.sport)}</td><td>${escapeHtml(row.customerName)}<br>Player ${Number(row.seat)}</td><td>${peso.format(row.finalAmount)}</td><td>${Number(row.occupied)}/${Number(row.capacity)}</td><td>${escapeHtml(row.status)}<br>${escapeHtml(row.paymentMethod)} · ${row.status === 'Booked' ? 'Verified' : row.status === 'Cancelled' ? 'Cancelled' : 'Awaiting review'}${row.receipt ? `<br><a href="${escapeHtml(resourceUrl(row.receipt))}" target="_blank" rel="noopener">Payment proof</a>` : ''}</td><td>${row.status === 'Held' ? `<button class="btn btn-sm btn-primary" data-op-confirm="${escapeHtml(row.id)}">Confirm</button>` : ''} ${row.status !== 'Cancelled' ? `<button class="btn btn-sm btn-outline-danger" data-op-cancel="${escapeHtml(row.id)}">Cancel</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="6">No Open Play bookings.</td></tr>'}</tbody></table></div>`;
    panel.querySelectorAll('[data-op-confirm]').forEach(button => button.onclick = () => updateStatus(button.dataset.opConfirm,'Booked'));
    panel.querySelectorAll('[data-op-cancel]').forEach(button => button.onclick = async () => {
        const reason = window.prompt('Cancellation reason');
        if (!reason?.trim()) return;
        const data = new FormData();
        data.set('id',button.dataset.opCancel); data.set('status','Cancelled'); data.set('reason',reason);
        const response = await fetch(`${api}?action=admin-status`,{method:'POST',body:data});
        const payload = await response.json();
        if (!payload.ok) { window.alert(payload.message); return; }
        state=payload.state; renderAll();
    });
}
const originalOpenPlayAdmin = renderAdmin;
renderAdmin = function () { originalOpenPlayAdmin(); renderOpenPlayAdmin(); };
if (state) renderOpenPlayAdmin();
