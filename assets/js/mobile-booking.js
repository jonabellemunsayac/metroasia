(() => {
  const MOBILE_BREAKPOINT = 700;

  if (typeof renderBookingGrid !== 'function') return;

  const desktopRenderBookingGrid = renderBookingGrid;

  /* Default: all time slots are closed on first load. */
  let openTimeSlot = null;

  const isMobileBookingView = () =>
    window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT}px)`).matches;

  function mobileSlotStatus(conflict, isPast) {
    const status = conflict?.status || 'Available';
    const tone = statusTone(status);

    if (isPast) {
      return {
        status,
        label: 'Past',
        css: 'mobile-slot-unavailable',
        disabled: true
      };
    }

    if (status === 'Available') {
      return {
        status,
        label: 'Available',
        css: 'mobile-slot-available',
        disabled: false
      };
    }

    if (tone === 'blocked') {
      return {
        status,
        label: 'Unavailable',
        css: 'mobile-slot-unavailable',
        disabled: true
      };
    }

    return {
      status,
      label: compactStatusLabel(status),
      css: 'mobile-slot-booked',
      disabled: true
    };
  }

  function renderMobileBookingGrid() {
    if (!els.grid || !state) return;

    const date = isoDate(selectedDate);
    const today = isoDate(new Date());
    const courts = courtsForSelectedSport();
    const allTimes = Object.values(state.timeSlots || {}).flat();

    if (els.dateLabel) {
      els.dateLabel.textContent =
        `${niceDate(date)}${date === today ? ' - Today' : ''}`;
    }

    const prevButton = document.getElementById('prevDate');
    if (prevButton) {
      prevButton.disabled = date <= today;
      prevButton.classList.toggle('opacity-50', date <= today);
    }

    els.grid.style.gridTemplateColumns = '';
    els.grid.style.minWidth = '';
    els.grid.classList.add('booking-grid-mobile');
    els.grid.classList.remove('grid');

    if (!allTimes.length || !courts.length) {
      els.grid.innerHTML = `
        <div class="mobile-slot-empty">
          No available court schedule is configured for this sport.
        </div>
      `;
      renderBookingSelectionBar();
      return;
    }

    /* If the currently open time no longer exists, close all. */
    if (openTimeSlot && !allTimes.includes(openTimeSlot)) {
      openTimeSlot = null;
    }

    els.grid.innerHTML = allTimes.map((time, index) => {
      const isOpen = openTimeSlot === time;
      const past = slotIsPast(date, time);

      const firstCourtId = courts[0]?.id || null;
      const displayPrice = slotPrice(
        time,
        firstCourtId,
        selectedSport,
        date
      );

      const availableCount = courts.filter(courtInfo => {
        const conflict = relatedConflictFor(date, time, courtInfo);

        return (
          !past &&
          (!conflict || conflict.status === 'Available')
        );
      }).length;

      const courtButtons = courts.map(courtInfo => {
        const court = courtInfo.id;
        const courtName = courtDisplayName(courtInfo);
        const conflict = relatedConflictFor(date, time, courtInfo);
        const statusInfo = mobileSlotStatus(conflict, past);

        const slotData = {
          date,
          time,
          court,
          courtName,
          sport: selectedSport
        };

        const selected = selectedBookingSlots.some(
          item => bookingSlotKey(item) === bookingSlotKey(slotData)
        );

        const price = slotPrice(
          time,
          court,
          selectedSport,
          date
        );

        const buttonClass = selected
          ? 'mobile-slot-selected'
          : statusInfo.css;

        const disabled = statusInfo.disabled ? 'disabled' : '';
        const stateLabel = selected
          ? 'Selected'
          : statusInfo.label;

        return `
          <button
            type="button"
            ${disabled}
            data-book-date="${escapeHtml(date)}"
            data-book-time="${escapeHtml(time)}"
            data-book-court="${escapeHtml(court)}"
            data-book-court-name="${escapeHtml(courtName)}"
            data-book-sport="${escapeHtml(selectedSport)}"
            class="mobile-court-slot ${buttonClass}"
          >
            <span class="mobile-court-slot-main">
              <span class="mobile-court-name">
                ${escapeHtml(courtName)}
              </span>

              <span class="mobile-court-price">
                ${peso.format(price)}
              </span>
            </span>

            <span class="mobile-court-slot-state">
              ${escapeHtml(stateLabel)}
            </span>
          </button>
        `;
      }).join('');

      return `
        <article class="mobile-time-card ${isOpen ? 'is-open' : ''}">

          <button
            type="button"
            class="mobile-time-card-toggle"
            data-mobile-time-toggle="${escapeHtml(time)}"
            aria-expanded="${isOpen ? 'true' : 'false'}"
            aria-controls="mobile-time-panel-${index}"
          >
            <span class="mobile-time-card-header-main">
              <span class="mobile-time-kicker">
                Time Slot
              </span>

              <strong>
                ${escapeHtml(compactTime(time))}
              </strong>

              <span class="mobile-time-availability">
                ${availableCount}
                court${availableCount === 1 ? '' : 's'}
                available
              </span>
            </span>

            <span class="mobile-time-from">
              <small>From</small>
              <strong>${peso.format(displayPrice)}</strong>
            </span>
          </button>

          <div
            id="mobile-time-panel-${index}"
            class="mobile-time-card-panel"
            ${isOpen ? '' : 'hidden'}
          >
            <div class="mobile-time-card-courts">
              ${courtButtons}
            </div>
          </div>

        </article>
      `;
    }).join('');

    /* Click header:
       closed -> open
       open -> closed
       opening another closes the previous one
    */
    els.grid
      .querySelectorAll('[data-mobile-time-toggle]')
      .forEach(toggle => {
        toggle.addEventListener('click', () => {
          const time = toggle.dataset.mobileTimeToggle;

          openTimeSlot =
            openTimeSlot === time
              ? null
              : time;

          renderMobileBookingGrid();
        });
      });

    els.grid
      .querySelectorAll('[data-book-date]')
      .forEach(button => {
        button.addEventListener('click', () => {
          if (!button.disabled) {
            toggleBookingSelection(button.dataset);
          }
        });
      });

    renderBookingSelectionBar();
  }

  renderBookingGrid = function () {
    if (isMobileBookingView()) {
      return renderMobileBookingGrid();
    }

    if (els.grid) {
      els.grid.classList.remove('booking-grid-mobile');
      els.grid.classList.add('grid');
    }

    return desktopRenderBookingGrid();
  };

  let lastMobileState = isMobileBookingView();
  let resizeTimer;

  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);

    resizeTimer = setTimeout(() => {
      const nextMobileState = isMobileBookingView();

      if (nextMobileState !== lastMobileState) {
        lastMobileState = nextMobileState;

        /* Reset accordions whenever crossing desktop/mobile breakpoint. */
        openTimeSlot = null;

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