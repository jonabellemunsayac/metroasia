<?php
$pageTitle = 'Metro Asia Arena';
$active = 'home';

include __DIR__ . '/../includes/header.php';

$siteConfig = site_config();
$galleryItems = site_config_gallery($siteConfig);
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
$contactHref = $messengerUrl !== '' ? $messengerUrl : app_url('ui/contact.php');

$venueName = trim((string) ($siteConfig['venue_name'] ?? 'MetroAsia Arena'));
$venueName = $venueName !== '' ? $venueName : 'MetroAsia Arena';

/*
 * ThemeForest visual-reference assets.
 * These URLs come from the purchased Pickyard demo referenced by home.json.
 * For production, download/use only assets covered by your license and replace
 * these with local files under assets/images/themeforest/.
 */
$tf = [
    'hero' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/female-paddle-tennis-player-hitting-the-ball-durin-2024-12-13-18-20-43-utc.webp',
    'about_main' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/male-athlete-playing-mixed-doubles-on-paddle-tenni-2024-12-13-18-45-30-utc.jpg',
    'about_small' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/paddle-tennis-equipment-on-the-ground-at-outdoor-c-2024-12-13-18-15-20-utc-1.webp',
    'service_1' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/black-woman-serving-the-ball-while-playing-paddle-2024-12-13-18-33-41-utc.jpg',
    'service_2' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/multiracial-group-of-athletes-playing-paddle-tenni-2024-12-13-16-42-59-utc.webp',
    'service_3' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/boy-playing-padel-on-court-2025-04-03-04-53-55-utc.webp',
    'service_4' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/paddle-tennis-instructor-and-female-athlete-having-2024-12-13-19-46-12-utc.webp',
    'facility' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/black-woman-serving-the-ball-while-playing-paddle-2024-12-13-18-33-41-utc.jpg',
    'testimonial' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/happy-athletic-woman-enjoying-in-playing-paddle-te-2024-12-13-18-15-44-utc.webp',
    'avatar_1' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/testi-image-5.jpg',
    'avatar_2' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/testi-image-18.jpg',
    'avatar_3' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/testi-image-8.jpg',
    'usp_1' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/Pickleball-Court-v2.png',
    'usp_2' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/Pickleball-Player.png',
    'usp_3' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/Pickleball-Community.png',
    'usp_4' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/Pickleball-Tournament.png',
    'usp_5' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/Pickleball-Games.png',
    'usp_6' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/Serve-v2.png',
    'cta' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/close-up-of-man-serving-during-padel-tennis-match-2024-12-13-18-48-42-utc.webp',
];

$heroImage = site_asset_url((string) ($siteConfig['hero_image_path'] ?? ''));
if (trim((string) ($siteConfig['hero_image_path'] ?? '')) === '') {
    $heroImage = $tf['hero'];
}
?>

<main class="metro-home-page">

    <!-- HERO: mirrors Pickyard hero composition -->
    <!-- <section
        id="welcome"
        class="metro-hero metro-home-hero"
        style="background-image:url('<?php //echo htmlspecialchars($heroImage, ENT_QUOTES); ?>')"
        aria-label="<?php //echo htmlspecialchars($venueName); ?> home"
    >
        <div class="metro-container metro-hero-inner">
            <div class="metro-hero-copy">
                <h1>Where Passion Meets Performance</h1>
                <p>MetroAsia Arena is open daily and ready for your next game.</p>

                <div class="metro-actions">
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="metro-btn metro-btn-accent">
                        Book a Court
                    </a>
                </div>
            </div>

            <div class="metro-community-card">
                <div class="metro-community-top">
                    <div class="metro-avatar-stack" aria-hidden="true">
                        <img src="<?php //echo htmlspecialchars($tf['avatar_1']); ?>" alt="">
                        <img src="<?php //echo htmlspecialchars($tf['avatar_2']); ?>" alt="">
                        <img src="<?php //echo htmlspecialchars($tf['avatar_3']); ?>" alt="">
                    </div>
                    <span class="metro-round-arrow">↗</span>
                </div>
                <strong>Open Daily</strong>
                <span>Court reservations</span>
                <p>A growing community of players across multiple sports and skill levels.</p>
            </div>
        </div>
    </section> -->

    <section
        id="welcome"
        class="metro-hero metro-home-hero"
        aria-label="<?php echo htmlspecialchars($venueName); ?> home"
    >
        <video
            class="metro-hero-video"
            autoplay
            muted
            loop
            playsinline
            preload="metadata"
            poster="<?php echo htmlspecialchars($heroImage); ?>"
        >
            <source
                src="<?php echo htmlspecialchars(
                    app_url('assets/videos/courts_aerial_view.mp4')
                ); ?>"
                type="video/mp4"
            >
        </video>

        <div class="metro-hero-video-overlay"></div>

        <div class="metro-container metro-hero-inner">
            <div class="metro-hero-copy">
                <h1>Where Passion Meets Performance</h1>

                <p>
                    MetroAsia Arena is open daily and ready for your next game.
                </p>

                <div class="metro-actions">
                    <a
                        href="<?php echo htmlspecialchars(
                            app_url('ui/booking.php')
                        ); ?>"
                        class="metro-btn metro-btn-accent"
                    >
                        Book a Court
                    </a>
                </div>
            </div>

            <div class="metro-community-card">
                <div class="metro-community-top">
                    <div class="metro-avatar-stack" aria-hidden="true">
                        <img src="<?php echo htmlspecialchars($tf['avatar_1']); ?>" alt="">
                        <img src="<?php echo htmlspecialchars($tf['avatar_2']); ?>" alt="">
                        <img src="<?php echo htmlspecialchars($tf['avatar_3']); ?>" alt="">
                    </div>
                    <span class="metro-round-arrow">↗</span>
                </div>
                <strong>Open Daily</strong>
                <span>Court reservations</span>
                <p>A growing community of players across multiple sports and skill levels.</p>
            </div>
        </div>
    </section>

    <!-- TEXT STRIP -->
    <section class="metro-experience-strip" aria-hidden="true">
        <div class="metro-experience-track">
            <span>Experience MetroAsia</span><i>✦</i>
            <span>Experience MetroAsia</span><i>✦</i>
            <span>Experience MetroAsia</span><i>✦</i>
        </div>
    </section>

    <!-- ABOUT -->
    <section id="about" class="metro-section metro-about-section">
        <div class="metro-container metro-about">
            <div class="metro-about-visual">
                <div class="metro-about-main" style="background-image:url('<?php echo htmlspecialchars($tf['about_main'], ENT_QUOTES); ?>')"></div>
            </div>

            <div class="metro-about-copy">
                <div class="metro-about-small" style="background-image:url('<?php echo htmlspecialchars($tf['about_small'], ENT_QUOTES); ?>')"></div>

                <h2>Where Energy Meets Elegance</h2>
                <p>
                    <?php echo htmlspecialchars($venueName); ?> is more than a place to reserve a court.
                    It is a multi-sport destination designed for players who want a smooth,
                    social, and convenient playing experience.
                </p>

                <div class="metro-about-divider"></div>

                <div class="metro-feature-row">
                    <span><b>✓</b> Fun for all levels</span>
                    <span><b>✓</b> Inclusive &amp; social</span>
                    <span><b>✓</b> Easy to book</span>
                </div>

                <p class="metro-about-note">
                    Reserve your preferred sport, court, date, and time through one simple online booking flow.
                </p>

                <div class="metro-actions">
                    <a href="#difference" class="metro-btn metro-btn-accent">About Us</a>
                    <a href="#facilities" class="metro-btn metro-btn-outline-dark">View Amenities</a>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES / PLAY OPTIONS -->
    <section class="metro-home-block metro-services-block">
        <div class="metro-container metro-panel metro-services-panel">
            <div class="metro-heading">
                <h2>From First Game to Match Day</h2>
                <p>Choose how you want to play and reserve the court time that fits your schedule.</p>
            </div>

            <div class="metro-service-grid">
                <article class="metro-service-card">
                    <img src="<?php echo htmlspecialchars($tf['service_1']); ?>" alt="Open play">
                    <h3>Open Play</h3>
                    <p>Reserve a court for casual games with friends, family, or teammates.</p>
                </article>

                <article class="metro-service-card">
                    <img src="<?php echo htmlspecialchars($tf['service_2']); ?>" alt="Group games">
                    <h3>Group Games</h3>
                    <p>Organize group sessions and enjoy dedicated court time together.</p>
                </article>

                <article class="metro-service-card">
                    <img src="<?php echo htmlspecialchars($tf['service_3']); ?>" alt="Multi-sport play">
                    <h3>Multi-Sport Play</h3>
                    <p>Book available pickleball, basketball, and volleyball court schedules.</p>
                </article>

                <article class="metro-service-card">
                    <img src="<?php echo htmlspecialchars($tf['service_4']); ?>" alt="Member play">
                    <h3>Member Play</h3>
                    <p>Sign in to manage bookings, payment status, and reservation history.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- FACILITIES -->
    <section id="facilities" class="metro-home-block">
        <div class="metro-container metro-facilities">
            <div class="metro-facility-photo" style="background-image:url('<?php echo htmlspecialchars($tf['facility'], ENT_QUOTES); ?>')">
                <span class="metro-play-button" aria-hidden="true">▶</span>
            </div>

            <div class="metro-facility-content">
                <h2>Our Facilities</h2>
                <p>Step into a venue built around a better court experience:</p>

                <ul>
                    <li>Dedicated pickleball court reservations</li>
                    <li>Basketball and volleyball court options</li>
                    <li>Easy online schedule selection</li>
                    <li>Member and guest reservation flows</li>
                    <li>Payment confirmation and booking status tracking</li>
                </ul>

                <p class="metro-facility-note">
                    Everything is designed to make your reservation experience clear, convenient, and organized.
                </p>

                <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="metro-btn metro-btn-accent">
                    Get Started Now
                </a>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/amenities-gallery.php'; ?>
    <!-- MEMBERSHIP -->
    <section class="metro-section metro-membership-section">
        <div class="metro-container">
            <div class="metro-membership-top">
                <div class="metro-membership-intro">
                    <h2>One Membership,<br>Endless Play</h2>
                    <p>Create an account and keep your bookings organized in one place.</p>

                    <?php if ($currentMember): ?>
                        <a
                            href="<?php echo htmlspecialchars(app_url('ui/member.php')); ?>"
                            class="metro-btn metro-btn-outline-dark"
                        >
                            My Bookings
                        </a>
                    <?php else: ?>
                        <a
                            href="<?php echo htmlspecialchars(app_url('ui/register.php')); ?>"
                            class="metro-btn metro-btn-outline-dark"
                        >
                            Become a Member Today
                        </a>
                    <?php endif; ?>
                </div>

                <div class="metro-membership-description">
                    <h3>MetroAsia member access</h3>
                    <p>
                        Manage bookings, upload payment proof, review reservation status,
                        and keep your booking history accessible from your account.
                    </p>
                </div>
            </div>

            <div class="metro-membership-benefits">
                <article>
                    <h3>Online Court Access</h3>
                    <p>Reserve available court schedules online.</p>
                </article>

                <article>
                    <h3>Booking History</h3>
                    <p>Review your previous and upcoming reservations.</p>
                </article>

                <article>
                    <h3>Payment Tracking</h3>
                    <p>Upload proof and follow reservation status.</p>
                </article>

                <article>
                    <h3>Member Convenience</h3>
                    <p>Keep your court activity connected to one account.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="metro-section metro-testimonials-section">
        <div class="metro-container">
            <div class="metro-heading">
                <h2>What Players Can Expect</h2>
            </div>

            <div class="metro-testimonial-layout">
                <div class="metro-rating-card">
                    <img class="metro-rating-photo" src="<?php echo htmlspecialchars($tf['testimonial']); ?>" alt="Player at the court">
                    <div class="metro-rating-bottom">
                        <div class="metro-avatar-stack">
                            <img src="<?php echo htmlspecialchars($tf['avatar_1']); ?>" alt="">
                            <img src="<?php echo htmlspecialchars($tf['avatar_2']); ?>" alt="">
                            <img src="<?php echo htmlspecialchars($tf['avatar_3']); ?>" alt="">
                        </div>
                        <strong>4.9</strong>
                        <span>★★★★★<small> Player experience</small></span>
                    </div>
                </div>

                <div class="metro-quotes">
                    <blockquote>
                        “The online booking process makes it simple to choose a court and schedule.”
                        <cite>Easy reservation flow</cite>
                    </blockquote>
                    <blockquote>
                        “Members can quickly check their bookings and reservation status in one place.”
                        <cite>Member-friendly access</cite>
                    </blockquote>
                    <blockquote>
                        “The site is designed around getting players from schedule selection to the court.”
                        <cite>Built for players</cite>
                    </blockquote>
                </div>
            </div>
        </div>
    </section>

    <!-- USP -->
    <section id="difference" class="metro-home-block">
        <div class="metro-container metro-panel metro-difference-panel">
            <div class="metro-difference-heading">
                <h2>The MetroAsia Difference</h2>
                <p>
                    A modern multi-sport court experience blending convenient reservations,
                    player access, and organized booking management.
                </p>
            </div>

            <div class="metro-usp-grid">
                <?php
                $uspItems = [
                    [$tf['usp_1'], 'Quality Courts', 'Court spaces prepared for organized and enjoyable play.'],
                    [$tf['usp_2'], 'Player Focus', 'A customer-facing experience built around simple court access.'],
                    [$tf['usp_3'], 'Vibrant Community', 'A place for casual players, groups, teams, and members.'],
                    [$tf['usp_4'], 'Multi-Sport Energy', 'Support for pickleball, basketball, and volleyball reservations.'],
                    [$tf['usp_5'], 'Seamless Booking', 'Reserve courts and manage bookings through one online platform.'],
                    [$tf['usp_6'], 'Easy Access', 'Use guest or member flows depending on how you prefer to book.'],
                ];
                foreach ($uspItems as [$icon, $title, $description]):
                ?>
                    <article class="metro-usp-item">
                        <img src="<?php echo htmlspecialchars($icon); ?>" alt="">
                        <h3><?php echo htmlspecialchars($title); ?></h3>
                        <p><?php echo htmlspecialchars($description); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="metro-home-block">
        <div class="metro-container metro-cta-card" style="background-image:url('<?php echo htmlspecialchars($tf['cta'], ENT_QUOTES); ?>')">
            <div class="metro-cta-overlay"></div>
            <div class="metro-cta-copy">
                <h2>Ready to Play? Let's Hit the Court</h2>
                <p>Book your next game in minutes.</p>
                <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="metro-btn metro-btn-accent">
                    Book Your Court
                </a>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <section class="metro-stats-section">
        <div class="metro-container metro-stats">
            <div><strong>Daily</strong><span>Court Availability</span></div>
            <div><strong>Online</strong><span>Reservation Access</span></div>
            <div><strong>3</strong><span>Supported Sports</span></div>
            <div><strong>Fast</strong><span>Booking Flow</span></div>
        </div>
    </section>

    <!-- DYNAMIC GALLERY retained from the current application -->
    <?php if (!empty($galleryItems)): ?>
        <section id="gallery" class="metro-section metro-gallery-section">
            <div class="metro-container">
                <div class="metro-heading">
                    <span class="metro-eyebrow">Gallery</span>
                    <h2>Inside the Arena</h2>
                </div>

                <div class="landing-gallery-grid">
                    <?php foreach ($galleryItems as $item): ?>
                        <figure>
                            <img
                                src="<?php echo htmlspecialchars(site_asset_url((string) $item['image'])); ?>"
                                alt="<?php echo htmlspecialchars((string) $item['title']); ?>"
                                onerror="this.closest('figure').classList.add('image-missing')"
                            >
                            <figcaption><?php echo htmlspecialchars((string) $item['title']); ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- CONTACT retained because it is dynamic in the existing app -->
    <section id="contact-us" class="metro-section metro-contact-section">
        <div class="metro-container landing-contact-grid">
            <div>
                <span class="metro-eyebrow">Contact Us</span>
                <h2>Visit Metro Asia Arena</h2>
                <p>Reserve online before you arrive, or use the location below to find the arena.</p>

                <div class="contact-list">
                    <p><i data-lucide="map-pin" class="icon-sm"></i><?php echo htmlspecialchars((string) $siteConfig['address']); ?></p>
                    <p><i data-lucide="clock" class="icon-sm"></i>Open daily for scheduled court reservations</p>
                    <p><i data-lucide="calendar-days" class="icon-sm"></i>Online booking and member account access available</p>
                </div>

                <div class="metro-actions">
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="metro-btn metro-btn-accent">Book a Court</a>
                    <a
                        href="<?php echo htmlspecialchars($contactHref); ?>"
                        <?php echo $messengerUrl !== '' ? 'target="_blank" rel="noopener"' : ''; ?>
                        class="metro-btn metro-btn-outline-dark"
                    >Contact Admin</a>
                </div>
            </div>

            <div id="map" class="landing-map">
                <iframe
                    title="Metro Asia Arena map"
                    src="<?php echo htmlspecialchars((string) $siteConfig['map_embed_url']); ?>"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
