(() => {
    const buttons = document.querySelectorAll('[data-venue-filter]');
    const panels = document.querySelectorAll('[data-venue-panel]');
    const status = document.querySelector('[data-venue-status]');
    buttons.forEach(button => button.addEventListener('click', () => {
        buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        panels.forEach(panel => { panel.hidden = panel.dataset.venuePanel !== button.dataset.venueFilter; });
        if (status) status.textContent = `Showing ${button.textContent.trim()} photos`;
    }));
})();
