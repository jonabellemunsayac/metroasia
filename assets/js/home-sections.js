(() => {
    const header = document.querySelector('.metro-header');
    const hero = document.querySelector('.metro-home-hero');
    if (header && hero) {
        const updateHeroSpace = () => {
            hero.style.setProperty('--home-header-height', Math.ceil(header.getBoundingClientRect().height) + 'px');
        };
        updateHeroSpace();
        // The marquee is inside this header, so its height is included once.
        if ('ResizeObserver' in window) {
            new ResizeObserver(updateHeroSpace).observe(header);
        }
        window.addEventListener('load', updateHeroSpace);
        document.fonts?.ready.then(updateHeroSpace);
        window.addEventListener('resize', updateHeroSpace);
    }

    const buttons = document.querySelectorAll('[data-venue-filter]');
    const panels = document.querySelectorAll('[data-venue-panel]');
    const status = document.querySelector('[data-venue-status]');
    buttons.forEach(button => button.addEventListener('click', () => {
        buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        panels.forEach(panel => { panel.hidden = panel.dataset.venuePanel !== button.dataset.venueFilter; });
        if (status) status.textContent = `Showing ${button.textContent.trim()} photos`;
    }));
})();
