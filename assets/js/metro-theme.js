(() => {
  'use strict';

  const doc = document;
  const body = doc.body;
  const header = doc.querySelector('.metro-header');
  const nav = doc.querySelector('[data-metro-nav]');
  const toggle = doc.querySelector('[data-metro-menu-toggle]');
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ------------------------------------------------------------
   * 1. Mobile navigation
   * ---------------------------------------------------------- */
  if (toggle && nav) {
    const closeMenu = () => {
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      body.classList.remove('metro-menu-open');
    };

    toggle.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      body.classList.toggle('metro-menu-open', isOpen);
    });

    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', closeMenu);
    });

    doc.addEventListener('click', event => {
      if (!nav.classList.contains('open')) return;
      if (nav.contains(event.target) || toggle.contains(event.target)) return;
      closeMenu();
    });

    doc.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeMenu();
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 900) closeMenu();
    });
  }

  /* ------------------------------------------------------------
   * 2. Header behavior
   * Pickyard uses a transparent header over the hero. This adds
   * a solid background once the visitor starts scrolling.
   * ---------------------------------------------------------- */
  const syncHeader = () => {
    if (!header) return;
    header.classList.toggle('scrolled', window.scrollY > 28);
  };

  syncHeader();
  window.addEventListener('scroll', syncHeader, { passive: true });

  /* ------------------------------------------------------------
   * 3. Smooth anchor navigation
   * ---------------------------------------------------------- */
  doc.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', event => {
      const selector = link.getAttribute('href');
      if (!selector || selector === '#') return;

      const target = doc.querySelector(selector);
      if (!target) return;

      event.preventDefault();
      const headerOffset = header ? Math.min(header.offsetHeight, 96) : 0;
      const top = target.getBoundingClientRect().top + window.scrollY - headerOffset;

      window.scrollTo({
        top,
        behavior: prefersReducedMotion ? 'auto' : 'smooth'
      });
    });
  });

  /* ------------------------------------------------------------
   * 4. Elementor-like reveal animation
   * The purchased template contains fadeIn animation settings.
   * We reproduce that behavior without Elementor.
   * ---------------------------------------------------------- */
  const revealSelectors = [
    '.metro-hero-copy > *',
    '.metro-hero-card',
    '.metro-about-image',
    '.metro-about > div:last-child > *',
    '.metro-heading > *',
    '.metro-card',
    '.metro-facility-image',
    '.metro-facility-copy > *',
    '.metro-membership > *',
    '.metro-difference-item',
    '.metro-cta-content > *',
    '.landing-gallery-grid figure',
    '.landing-contact-grid > *'
  ];

  const revealElements = [
    ...new Set(
      revealSelectors.flatMap(selector =>
        Array.from(doc.querySelectorAll(selector))
      )
    )
  ];

  if (prefersReducedMotion || !('IntersectionObserver' in window)) {
    revealElements.forEach(el => el.classList.add('metro-revealed'));
  } else {
    revealElements.forEach((el, index) => {
      el.classList.add('metro-reveal');
      el.style.setProperty('--metro-reveal-delay', `${(index % 4) * 85}ms`);
    });

    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('metro-revealed');
        observer.unobserve(entry.target);
      });
    }, {
      threshold: 0.12,
      rootMargin: '0px 0px -35px 0px'
    });

    revealElements.forEach(el => observer.observe(el));
  }

  /* ------------------------------------------------------------
   * 5. Infinite ticker / marquee
   * The starter had static ticker text. Duplicate it and move it
   * continuously so it feels like the purchased design.
   * ---------------------------------------------------------- */
  doc.querySelectorAll('.metro-ticker').forEach(ticker => {
    const track = ticker.querySelector('.metro-ticker-track');
    if (!track || track.dataset.metroReady === '1') return;

    track.dataset.metroReady = '1';

    const originalItems = Array.from(track.children);
    if (!originalItems.length) return;

    // Duplicate enough content for a seamless marquee.
    while (track.scrollWidth < window.innerWidth * 2.2) {
      originalItems.forEach(item => {
        const clone = item.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        track.appendChild(clone);
      });
    }

    if (!prefersReducedMotion) {
      track.classList.add('metro-ticker-animate');
    }
  });

  /* ------------------------------------------------------------
   * 6. Subtle image parallax on large screens
   * Keeps the sports landing page lively without Elementor.
   * ---------------------------------------------------------- */
  const parallaxItems = Array.from(
    doc.querySelectorAll('.metro-hero, .metro-cta')
  );

  const updateParallax = () => {
    if (prefersReducedMotion || window.innerWidth < 901) return;

    parallaxItems.forEach(el => {
      const rect = el.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > window.innerHeight) return;

      const viewportCenter = window.innerHeight / 2;
      const elementCenter = rect.top + rect.height / 2;
      const offset = (elementCenter - viewportCenter) * -0.025;
      el.style.backgroundPosition = `center calc(50% + ${offset}px)`;
    });
  };

  updateParallax();
  window.addEventListener('scroll', updateParallax, { passive: true });
  window.addEventListener('resize', updateParallax);

  /* ------------------------------------------------------------
   * 7. Current year
   * ---------------------------------------------------------- */
  doc.querySelectorAll('[data-metro-year]').forEach(el => {
    el.textContent = new Date().getFullYear();
  });

  /* ------------------------------------------------------------
   * 8. Lucide icons
   * Your project already loads Lucide from header.php.
   * ---------------------------------------------------------- */
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
})();
