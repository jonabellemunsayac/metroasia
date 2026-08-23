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
   * 2. Copy contact pin/address text
   * ---------------------------------------------------------- */
  doc.querySelectorAll('[data-copy-text]').forEach(button => {
    button.addEventListener('click', async () => {
      const text = button.getAttribute('data-copy-text') || '';
      const label = button.querySelector('[data-copy-label]');
      const originalText = label ? label.textContent.trim() : button.textContent.trim() || 'Copy';

      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
        } else {
          const textarea = doc.createElement('textarea');
          textarea.value = text;
          textarea.setAttribute('readonly', '');
          textarea.style.position = 'fixed';
          textarea.style.top = '-999px';
          doc.body.appendChild(textarea);
          textarea.select();
          doc.execCommand('copy');
          textarea.remove();
        }

        button.dataset.copied = 'true';
        if (label) {
          label.textContent = 'Copied';
        } else {
          button.textContent = 'Copied';
        }
        window.setTimeout(() => {
          button.dataset.copied = 'false';
          if (label) {
            label.textContent = originalText;
          } else {
            button.textContent = originalText;
          }
          if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
          }
        }, 1600);
      } catch (error) {
        if (label) {
          label.textContent = 'Copy failed';
        } else {
          button.textContent = 'Copy failed';
        }
        window.setTimeout(() => {
          if (label) {
            label.textContent = originalText;
          } else {
            button.textContent = originalText;
          }
        }, 1600);
      }
    });
  });

  /* ------------------------------------------------------------
   * 3. Gallery modal image slider
   * ---------------------------------------------------------- */
  const galleryModal = doc.querySelector('[data-gallery-modal]');
  const galleryModalImage = galleryModal?.querySelector('[data-gallery-modal-image]');
  const galleryModalTitle = galleryModal?.querySelector('[data-gallery-modal-title]');
  const galleryModalCount = galleryModal?.querySelector('[data-gallery-modal-count]');
  const galleryModalPrev = galleryModal?.querySelector('[data-gallery-modal-prev]');
  const galleryModalNext = galleryModal?.querySelector('[data-gallery-modal-next]');
  const galleryModalClose = galleryModal?.querySelector('.gallery-modal-close');
  let galleryImages = [];
  let galleryIndex = 0;
  let galleryTitle = '';
  let galleryPreviousFocus = null;

  const renderGalleryModal = () => {
    if (!galleryModalImage || !galleryImages.length) return;

    galleryModalImage.src = galleryImages[galleryIndex];
    galleryModalImage.alt = `${galleryTitle || 'Gallery'} image ${galleryIndex + 1}`;

    if (galleryModalTitle) {
      galleryModalTitle.textContent = galleryTitle;
    }

    if (galleryModalCount) {
      galleryModalCount.textContent = `${galleryIndex + 1} / ${galleryImages.length}`;
    }

    const hasMultipleImages = galleryImages.length > 1;
    galleryModalPrev?.toggleAttribute('hidden', !hasMultipleImages);
    galleryModalNext?.toggleAttribute('hidden', !hasMultipleImages);
  };

  const moveGalleryModal = direction => {
    if (galleryImages.length < 2) return;
    galleryIndex = (galleryIndex + direction + galleryImages.length) % galleryImages.length;
    renderGalleryModal();
  };

  const closeGalleryModal = () => {
    if (!galleryModal || galleryModal.hidden) return;

    galleryModal.hidden = true;
    galleryModal.setAttribute('aria-hidden', 'true');
    body.classList.remove('gallery-modal-open');

    if (galleryModalImage) {
      galleryModalImage.removeAttribute('src');
      galleryModalImage.alt = '';
    }

    if (galleryPreviousFocus && typeof galleryPreviousFocus.focus === 'function') {
      galleryPreviousFocus.focus();
    }
  };

  const openGalleryModal = card => {
    if (!galleryModal || !galleryModalImage) return;

    let images = [];
    try {
      images = JSON.parse(card.getAttribute('data-gallery-images') || '[]');
    } catch (error) {
      images = [];
    }

    images = images.filter(Boolean);
    if (!images.length) return;

    galleryImages = images;
    galleryIndex = 0;
    galleryTitle = card.getAttribute('data-gallery-title') || '';
    galleryPreviousFocus = doc.activeElement;

    renderGalleryModal();
    galleryModal.hidden = false;
    galleryModal.setAttribute('aria-hidden', 'false');
    body.classList.add('gallery-modal-open');
    galleryModalClose?.focus();
  };

  doc.querySelectorAll('[data-gallery-card]').forEach(card => {
    card.addEventListener('click', () => openGalleryModal(card));

    card.addEventListener('keydown', event => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      openGalleryModal(card);
    });
  });

  if (galleryModal) {
    galleryModal.querySelectorAll('[data-gallery-modal-close]').forEach(button => {
      button.addEventListener('click', closeGalleryModal);
    });

    galleryModalPrev?.addEventListener('click', () => moveGalleryModal(-1));
    galleryModalNext?.addEventListener('click', () => moveGalleryModal(1));

    doc.addEventListener('keydown', event => {
      if (galleryModal.hidden) return;

      if (event.key === 'Escape') {
        closeGalleryModal();
      } else if (event.key === 'ArrowLeft') {
        moveGalleryModal(-1);
      } else if (event.key === 'ArrowRight') {
        moveGalleryModal(1);
      }
    });
  }

  /* ------------------------------------------------------------
   * 4. Header behavior
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
   * 5. Homepage navigation scroll spy
   * ---------------------------------------------------------- */
  const samePageHash = link => {
    const href = link.getAttribute('href') || '';
    if (!href.includes('#')) return '';

    try {
      const url = new URL(href, window.location.href);
      const currentPath = window.location.pathname.replace(/\/+$/, '');
      const linkPath = url.pathname.replace(/\/+$/, '');

      return url.origin === window.location.origin && linkPath === currentPath
        ? url.hash
        : '';
    } catch (error) {
      return href.startsWith('#') ? href : '';
    }
  };

  const navSectionLinks = nav
    ? Array.from(nav.querySelectorAll('a[href*="#"]')).filter(link => {
        const hash = samePageHash(link);
        return hash && doc.querySelector(hash);
      })
    : [];

  const navSections = navSectionLinks.map(link => ({
    link,
    hash: samePageHash(link),
    target: doc.querySelector(samePageHash(link))
  }));

  const setActiveNavSection = activeHash => {
    if (!navSections.length) return;

    navSections.forEach(({ link, hash }) => {
      const isActive = hash === activeHash;
      link.classList.toggle('active', isActive);
      if (isActive) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  };

  const syncActiveNavSection = () => {
    if (!navSections.length) return;

    const headerOffset = header ? Math.min(header.offsetHeight, 96) : 0;
    const marker = window.scrollY + headerOffset + Math.round(window.innerHeight * 0.22);
    let activeSection = navSections[0];

    navSections.forEach(section => {
      if (section.target.offsetTop <= marker) {
        activeSection = section;
      }
    });

    setActiveNavSection(activeSection.hash);
  };

  if (navSections.length) {
    syncActiveNavSection();
    window.addEventListener('scroll', syncActiveNavSection, { passive: true });
    window.addEventListener('resize', syncActiveNavSection);
  }

  /* ------------------------------------------------------------
   * 6. Smooth anchor navigation
   * ---------------------------------------------------------- */
  doc.querySelectorAll('a[href*="#"]').forEach(link => {
    link.addEventListener('click', event => {
      const selector = samePageHash(link);
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

      if (navSections.some(section => section.hash === selector)) {
        setActiveNavSection(selector);
      }
    });
  });

  /* ------------------------------------------------------------
   * 7. Elementor-like reveal animation
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
   * 8. Infinite ticker / marquee
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
   * 9. Subtle image parallax on large screens
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
   * 10. Current year
   * ---------------------------------------------------------- */
  doc.querySelectorAll('[data-metro-year]').forEach(el => {
    el.textContent = new Date().getFullYear();
  });

  /* ------------------------------------------------------------
   * 11. Lucide icons
   * Your project already loads Lucide from header.php.
   * ---------------------------------------------------------- */
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
})();
