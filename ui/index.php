<?php
$pageTitle = 'MetroAsia Sports Center';
$active = 'home';

include __DIR__ . '/../includes/header.php';

$siteConfig = site_config();
$galleryItems = site_config_gallery($siteConfig);
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
$contactHref = $messengerUrl !== '' ? $messengerUrl : app_url('ui/contact.php');

$venueName = trim((string) ($siteConfig['venue_name'] ?? 'MetroAsia Sports Center'));
$venueName = $venueName !== '' ? $venueName : 'MetroAsia Sports Center';
$venueAddress = trim((string) ($siteConfig['address'] ?? ''));
$mapQuery = $venueAddress !== '' ? $venueAddress : $venueName;
$googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery);
$wazeUrl = 'https://waze.com/ul?q=' . rawurlencode($mapQuery) . '&navigate=yes';

/*
 * ThemeForest visual-reference assets.
 * These URLs come from the purchased Pickyard demo referenced by home.json.
 * For production, download/use only assets covered by your license and replace
 * these with local files under assets/images/themeforest/.
 */
$tf = [
    'hero' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/female-paddle-tennis-player-hitting-the-ball-durin-2024-12-13-18-20-43-utc.webp',
    'about_main' => 'assets/images/basketball_court.jpg',
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

$aboutMainImage = site_asset_url((string) ($siteConfig['about_main_image_path'] ?? ''));
if ($aboutMainImage === '') {
    $aboutMainImage = site_asset_url((string) $tf['about_main']);
}

$aboutSmallImage = site_asset_url((string) ($siteConfig['about_small_image_path'] ?? ''));
if ($aboutSmallImage === '') {
    $aboutSmallImage = site_asset_url((string) $tf['about_small']);
}

$serviceImages = [];
for ($i = 1; $i <= 4; $i++) {
    $serviceImage = site_asset_url((string) ($siteConfig["service_{$i}_image_path"] ?? ''));
    $serviceImages[$i] = $serviceImage !== '' ? $serviceImage : (string) $tf["service_{$i}"];
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
                <p>MetroAsia Sports Center is open daily and ready for your next game.</p>

                <div class="metro-actions">
                    <a href="<?php //echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="metro-btn metro-btn-accent">
                        Let's Play
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
            preload="auto"
            poster="<?php echo htmlspecialchars(
                app_url('assets/images/courts-aerial-poster.jpg')
            ); ?>"
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
                <h1>MetroAsia Sports Center</h1>
                <h1>One Arena. <br>Three Sports. <br>Endless Energy.</h1>

                <!-- <p>
                    MetroAsia Sports Center is open daily and ready for your next game.
                </p> -->

                <!-- <div class="metro-actions">
                    <a
                        href="<?php //echo htmlspecialchars($bookingCtaHref); ?>"
                        class="metro-btn metro-btn-accent"
                    >
                        Let's Play
                    </a>
                </div> -->
            </div>

            <!-- <div class="metro-community-card">
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
            </div> -->
        </div>
    </section>

    <?php include __DIR__ . '/../includes/home-sections.php'; ?>

    <!-- CTA -->
    <section class="metro-home-block" hidden style="display: none;">
        <div class="metro-container metro-cta-card" style="background-image:url('<?php echo htmlspecialchars($tf['cta'], ENT_QUOTES); ?>')">
            <div class="metro-cta-overlay"></div>
            <div class="metro-cta-copy">
                <h2>Ready to Play? Let's Hit the Court</h2>
                <p>Book your next game in minutes.</p>
                <a href="<?php echo htmlspecialchars($bookingCtaHref); ?>" class="metro-btn metro-btn-accent">
                    Let's Play
                </a>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <!-- <section class="metro-stats-section">
        <div class="metro-container metro-stats">
            <div><strong>Daily</strong><span>Court Availability</span></div>
            <div><strong>Online</strong><span>Reservation Access</span></div>
            <div><strong>3</strong><span>Supported Sports</span></div>
            <div><strong>Fast</strong><span>Booking Flow</span></div>
        </div>
    </section> -->

    <!-- CONTACT retained because it is dynamic in the existing app -->
    <section id="contact-us" class="metro-section metro-contact-section">
        <div class="metro-container">
            <div class="metro-contact-heading">
                <!-- <span class="metro-eyebrow">Contact Us</span> -->
                <h2>Visit <?php echo htmlspecialchars($venueName); ?></h2>
            </div>

            <div class="metro-contact-layout">
                <div id="map" class="metro-contact-map">
                    <iframe
                        title="<?php echo htmlspecialchars($venueName); ?> map"
                        src="<?php echo htmlspecialchars((string) $siteConfig['map_embed_url']); ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>
                </div>

                <article class="metro-contact-card">
                    <section class="metro-contact-card-section">
                        <h3>Address</h3>
                        <strong><?php echo htmlspecialchars($venueName); ?></strong>
                        <p><?php echo htmlspecialchars($venueAddress); ?></p>

                        <div class="metro-contact-divider"></div>

                        <span class="metro-contact-label">Grab / Angkas Pin Name</span>
                        <div class="metro-contact-copy-row">
                            <code><?php echo htmlspecialchars($venueName . ', ' . $venueAddress); ?></code>
                            <button type="button" data-copy-text="<?php echo htmlspecialchars($venueName . ', ' . $venueAddress, ENT_QUOTES); ?>">
                                <i data-lucide="copy" class="icon-sm"></i>
                                <span data-copy-label>Copy</span>
                            </button>
                        </div>

                        <p class="metro-contact-note">Free on-site parking in front of the facility</p>

                        <div class="metro-contact-actions">
                            <a href="<?php echo htmlspecialchars($googleMapsUrl); ?>" target="_blank" rel="noopener">
                                Google Maps
                            </a>
                            <a href="<?php echo htmlspecialchars($wazeUrl); ?>" target="_blank" rel="noopener">
                                Waze App
                            </a>
                        </div>
                    </section>

                    <section class="metro-contact-card-section">
                        <h3>Opening Hours</h3>
                        <strong>8:00 AM - 12:00 MN</strong>
                        <p>Open 7 days a week</p>
                    </section>

                    <section class="metro-contact-card-section">
                        <h3>Get In Touch</h3>
                        <p>Questions about bookings, payments, or events? Send us a message and our team will help.</p>
                        <div class="metro-contact-socials">
                            <?php if ($messengerUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($messengerUrl); ?>" target="_blank" rel="noopener">
                                    Facebook
                                </a>
                            <?php endif; ?>
                            <a href="mailto:<?php echo htmlspecialchars((string) $siteConfig['contact_email']); ?>">
                                Email
                            </a>
                        </div>
                    </section>
                </article>
            </div>
        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
