(() => {
  const MOBILE_BREAKPOINT = 700;
  if (typeof renderBookingGrid !== 'function') return;

  const desktopRenderBookingGrid = renderBookingGrid;
  let selectedMobileCourtId = null;
  let courtDropdownOpen = false;

  const isMobile = () => window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT}px)`).matches;

  function slotState(conflict, isPast, selected) {
    const status = conflict?.status || 'Available';
    const tone = statusTone(status);

    if (selected) return { label: 'Selected', css: 'mobile-time-selected', disabled: false };
    if (isPast && status === 'Available') return { label: 'Past', css: 'mobile-time-available mobile-time-past', disabled: true };
    if (isPast) return { label: 'Past', css: 'mobile-time-unavailable', disabled: true };
    if (status === 'Available') return { label: '', css: 'mobile-time-available', disabled: false };
    if (tone === 'blocked') return { label: 'Unavailable', css: 'mobile-time-unavailable', disabled: true };

    return {
      label: publicSlotLabel(status, conflict),
      css: status === 'Held' ? 'mobile-time-held' : 'mobile-time-booked',
      disabled: true
    };
  }

  function availableCount(courtInfo, times, date) {
    return times.filter(time => {
      const conflict = relatedConflictFor(date, time, courtInfo);
      return !slotIsPast(date, time) && (!conflict || conflict.status === 'Available');
    }).length;
  }

  function selectedCount(courtId, date) {
    return selectedBookingSlots.filter(slot =>
      Number(slot.court) === Number(courtId) &&
      slot.date === date &&
      slot.sport === selectedSport
    ).length;
  }

  function renderMobile() {
    if (!els.grid || !state) return;

    const date = isoDate(selectedDate);
    const today = isoDate(new Date());
    const courts = courtsForSelectedSport();
    const times = Object.values(state.timeSlots || {}).flat();

    if (els.dateLabel) {
      els.dateLabel.textContent = `${niceDate(date)}${date === today ? ' - Today' : ''}`;
    }

    const prevButton = document.getElementById('prevDate');
    if (prevButton) {
      prevButton.disabled = date <= today;
      prevButton.classList.toggle('opacity-50', date <= today);
    }

    els.grid.style.gridTemplateColumns = '';
    els.grid.style.minWidth = '';
    els.grid.classList.add('booking-grid-mobile', 'booking-grid-court-dropdown');
    els.grid.classList.remove('grid');

    if (!courts.length || !times.length) {
      els.grid.innerHTML = '<div class="mobile-slot-empty">No court schedule is configured for this sport.</div>';
      renderBookingSelectionBar();
      return;
    }

    if (!selectedMobileCourtId || !courts.some(court => Number(court.id) === Number(selectedMobileCourtId))) {
      selectedMobileCourtId = Number(courts[0].id);
    }

    const selectedCourt = courts.find(court => Number(court.id) === Number(selectedMobileCourtId)) || courts[0];
    const selectedCourtName = courtDisplayName(selectedCourt);

    const options = courts.map(court => {
      const courtId = court.id;
      const current = Number(courtId) === Number(selectedMobileCourtId);
      const count = availableCount(court, times, date);
      const chosen = selectedCount(courtId, date);

      return `
        <button
          type="button"
          class="mobile-court-dropdown-option ${current ? 'is-current' : ''}"
          data-mobile-court-option="${escapeHtml(courtId)}"
          role="option"
          aria-selected="${current ? 'true' : 'false'}">
          <span class="mobile-court-option-copy">
            <span class="mobile-court-option-kicker">Court</span>
            <strong>${escapeHtml(courtDisplayName(court))}</strong>
            <small>${count} available time slot${count === 1 ? '' : 's'}</small>
          </span>
          ${chosen > 0 ? `<span class="mobile-court-selected-count">${chosen} selected</span>` : ''}
        </button>
      `;
    }).join('');

    const timeButtons = times.map(time => {
      const conflict = relatedConflictFor(date, time, selectedCourt);
      const isPast = slotIsPast(date, time);

      const slotData = {
        date,
        time,
        court: Number(selectedCourt.id),
        courtName: selectedCourtName,
        sport: selectedSport
      };

      const selected = selectedBookingSlots.some(
        item => bookingSlotKey(item) === bookingSlotKey(slotData)
      );

      const ui = slotState(conflict, isPast, selected);
      const price = slotPrice(time, selectedCourt.id, selectedSport, date);

      return `
        <button
          type="button"
          ${ui.disabled ? 'disabled' : ''}
          class="mobile-court-time-slot ${ui.css}"
          data-book-date="${escapeHtml(date)}"
          data-book-time="${escapeHtml(time)}"
          data-book-court="${escapeHtml(selectedCourt.id)}"
          data-book-court-name="${escapeHtml(selectedCourtName)}"
          data-book-sport="${escapeHtml(selectedSport)}">
          <span class="mobile-court-time-main">
            <strong>${escapeHtml(compactTime(time))}</strong>
            <small>${peso.format(price)}</small>
          </span>
          <span class="mobile-court-time-state">${escapeHtml(ui.label)}</span>
        </button>
      `;
    }).join('');

    const chosen = selectedCount(selectedCourt.id, date);
    const available = availableCount(selectedCourt, times, date);

    els.grid.innerHTML = `
      <section class="mobile-court-dropdown-section">
        <div class="mobile-court-first-heading">
          <span class="mobile-court-first-step">1</span>
          <div>
            <span>Step 1</span>
            <h3>Choose Your Court</h3>
          </div>
        </div>

        <div class="mobile-court-dropdown ${courtDropdownOpen ? 'is-open' : ''}">
          <button
            type="button"
            class="mobile-court-dropdown-trigger"
            data-mobile-court-trigger
            aria-expanded="${courtDropdownOpen ? 'true' : 'false'}"
            aria-haspopup="listbox">
            <span class="mobile-court-option-copy">
              <span class="mobile-court-option-kicker">Court</span>
              <strong>${escapeHtml(selectedCourtName)}</strong>
              <small>${available} available time slot${available === 1 ? '' : 's'}</small>
            </span>

            <span class="mobile-court-trigger-right">
              ${chosen > 0 ? `<span class="mobile-court-selected-count">${chosen} selected</span>` : ''}
              <span class="mobile-court-dropdown-indicator" aria-hidden="true">+</span>
            </span>
          </button>

          <div
            class="mobile-court-dropdown-menu"
            role="listbox"
            ${courtDropdownOpen ? '' : 'hidden'}>
            ${options}
          </div>
        </div>
      </section>

      <div class="mobile-court-step-divider">
        <span class="mobile-court-first-step">2</span>
        <div>
          <span>Step 2</span>
          <strong>Select Your Time</strong>
        </div>
      </div>

      <section class="mobile-court-times-panel">
        <div class="mobile-court-times-heading">
          <span class="mobile-time-panel-kicker">Available Schedule</span>
          <h3>${escapeHtml(selectedCourtName)}</h3>
        </div>

        <p class="mobile-time-panel-help">
          Select one or multiple time slots. Tap a selected time again to remove it.
        </p>

        <div class="mobile-court-time-list">
          ${timeButtons}
        </div>
      </section>
    `;

    els.grid.querySelector('[data-mobile-court-trigger]')?.addEventListener('click', event => {
      event.stopPropagation();
      courtDropdownOpen = !courtDropdownOpen;
      renderMobile();
    });

    els.grid.querySelectorAll('[data-mobile-court-option]').forEach(button => {
      button.addEventListener('click', event => {
        event.stopPropagation();
        selectedMobileCourtId = Number(button.dataset.mobileCourtOption);
        courtDropdownOpen = false;
        renderMobile();
      });
    });

    els.grid.querySelectorAll('[data-book-date]').forEach(button => {
      button.addEventListener('click', () => {
        if (!button.disabled) {
          toggleBookingSelection(button.dataset);
        }
      });
    });

    renderBookingSelectionBar();
  }

  renderBookingGrid = function () {
    if (isMobile()) {
      return renderMobile();
    }

    if (els.grid) {
      els.grid.classList.remove('booking-grid-mobile', 'booking-grid-court-dropdown');
      els.grid.classList.add('grid');
    }

    return desktopRenderBookingGrid();
  };

  document.querySelectorAll('[data-sport]').forEach(button => {
    button.addEventListener('click', () => {
      selectedMobileCourtId = null;
      courtDropdownOpen = false;
    });
  });

  document.addEventListener('click', event => {
    if (!courtDropdownOpen) return;
    if (event.target.closest('.mobile-court-dropdown')) return;

    courtDropdownOpen = false;

    if (state && els.grid && isMobile()) {
      renderMobile();
    }
  });

  let lastMobileState = isMobile();
  let resizeTimer;

  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);

    resizeTimer = setTimeout(() => {
      const nextMobile = isMobile();

      if (nextMobile !== lastMobileState) {
        lastMobileState = nextMobile;
        selectedMobileCourtId = null;
        courtDropdownOpen = false;

        if (state && els.grid) {
          renderBookingGrid();
        }
      }
    }, 120);
  });

  if (state && els.grid) {
    renderBookingGrid();
  }
})();
