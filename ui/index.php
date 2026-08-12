<?php
$pageTitle = 'Metro Asia Arena';
$active = 'home';

/*
 * Keep the existing shared header so all current application bootstrap,
 * authentication/session state, helpers, navigation, and shared assets remain intact.
 */
include __DIR__ . '/../includes/header.php';

$siteConfig = site_config();
$galleryItems = site_config_gallery($siteConfig);
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
$contactHref = $messengerUrl !== '' ? $messengerUrl : app_url('ui/contact.php');

$heroImage = site_asset_url((string) ($siteConfig['hero_image_path'] ?? 'assets/homepage-court.jpg'));
$venueName = trim((string) ($siteConfig['venue_name'] ?? 'MetroAsia Arena'));
$venueName = $venueName !== '' ? $venueName : 'MetroAsia Arena';
?>

<!--
    Pickyard / Metro theme styles.
    These paths assume this file remains at MULTISPORTSPASS/ui/index.php.
    Copy the CSS files from the regenerated starter into assets/themes/metro/.
-->
<link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/theme.css')); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('assets/themes/metro/home.css')); ?>">

<main class="metro-home-page">

    <!-- HERO -->
    <section
        id="welcome"
        class="metro-hero"
        aria-label="<?php echo htmlspecialchars($venueName); ?> home screen"
        style="background-image:url('<?php echo htmlspecialchars($heroImage, ENT_QUOTES); ?>')"
    >
        <div class="metro-container metro-hero-inner">
            <div class="metro-hero-copy">
                <span class="metro-eyebrow">Welcome to <?php echo htmlspecialchars($venueName); ?></span>

                <h1>Where Every Game Begins</h1>

                <p>
                    <?php echo htmlspecialchars($venueName); ?> is open daily and ready for your next game.
                    Reserve your preferred court and schedule through our online booking facility.
                </p>

                <div class="metro-actions">
                    <a
                        href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>"
                        class="metro-btn metro-btn-accent"
                    >
                        Book Now
                    </a>

                    <a href="#facilities" class="metro-btn metro-btn-light">
                        View Courts
                    </a>
                </div>
            </div>

            <aside class="metro-hero-card">
                <span class="metro-eyebrow">Play. Connect. Compete.</span>
                <strong>Open Daily</strong>
                <p>
                    Choose your preferred court, date, and time, then complete your reservation online.
                </p>
            </aside>
        </div>
    </section>

    <!-- PICKYARD-STYLE TICKER -->
    <div class="metro-ticker" aria-hidden="true">
        <div class="metro-ticker-track">
            <span>Experience MetroAsia Arena ✦</span>
            <span>Book Your Court ✦</span>
            <span>Play Your Game ✦</span>
            <span>Experience MetroAsia Arena ✦</span>
        </div>
    </div>

    <!-- ABOUT / INTRO -->
    <section id="about" class="metro-section">
        <div class="metro-container metro-about">
            <div
                class="metro-about-image"
                style="background-image:url('<?php echo htmlspecialchars(app_url('assets/courts-preview.png'), ENT_QUOTES); ?>')"
                role="img"
                aria-label="Metro Asia Arena courts"
            ></div>

            <div>
                <span class="metro-eyebrow">About MetroAsia Arena</span>
                <h2>Where Energy Meets the Court</h2>

                <p>
                    <?php echo htmlspecialchars($venueName); ?> brings indoor pickleball, basketball,
                    and volleyball to Pasig with covered courts, clear online reservations,
                    and simple payment confirmation.
                </p>

                <div class="metro-points">
                    <span class="metro-point">Easy online booking</span>
                    <span class="metro-point">Flexible court schedules</span>
                    <span class="metro-point">Member account access</span>
                </div>

                <p>
                    Select your sport, court, date, and preferred time through one streamlined booking flow.
                </p>

                <a
                    href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>"
                    class="metro-btn metro-btn-accent"
                >
                    Check Availability
                </a>
            </div>
        </div>
    </section>

    <!-- BOOK / PLAY / CONFIRM -->
    <section class="metro-section">
        <div class="metro-container metro-soft metro-programs">
            <div class="metro-heading">
                <span class="metro-eyebrow">Simple Court Booking</span>
                <h2>Book. Play. Confirm.</h2>
                <p>
                    Everything you need to reserve a court and keep track of your booking.
                </p>
            </div>

            <div class="metro-grid-4">
                <article class="metro-card">
                    <div class="metro-card-body">
                        <h3>Book a Court</h3>
                        <p>Select your sport, court, date, and available time slot.</p>
                        <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>">Book Now →</a>
                    </div>
                </article>

                <article class="metro-card">
                    <div class="metro-card-body">
                        <h3>Play Your Game</h3>
                        <p>Enjoy dedicated pickleball courts plus multi-sport courts for basketball and volleyball.</p>
                        <a href="<?php echo htmlspecialchars(app_url('ui/rules.php')); ?>">View Rules →</a>
                    </div>
                </article>

                <article class="metro-card">
                    <div class="metro-card-body">
                        <h3>Confirm Payment</h3>
                        <p>Upload your payment proof and keep your reservation moving toward confirmation.</p>
                        <a href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>">Payment →</a>
                    </div>
                </article>

                <article class="metro-card">
                    <div class="metro-card-body">
                        <h3>My Bookings</h3>
                        <p>Members can review reservation details, status, and booking history.</p>
                        <a href="<?php echo htmlspecialchars(app_url($currentMember ? 'ui/member.php' : 'login.php')); ?>">
                            <?php echo $currentMember ? 'Open My Account' : 'Sign In'; ?> →
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- FACILITIES -->
    <section id="facilities" class="metro-section">
        <div class="metro-container metro-facility">
            <div
                class="metro-facility-image"
                style="background-image:url('<?php echo htmlspecialchars(app_url('assets/courts-preview.png'), ENT_QUOTES); ?>')"
                role="img"
                aria-label="Court facilities"
            ></div>

            <div class="metro-facility-copy">
                <span class="metro-eyebrow">Our Facilities</span>
                <h2>Built for Better Games</h2>
                <p>
                    Reserve covered courts with an organized online process designed for both guests and members.
                </p>

                <ul class="metro-list">
                    <li>Pickleball court reservations</li>
                    <li>Basketball and volleyball court options</li>
                    <li>Online schedule selection</li>
                    <li>Member and guest booking</li>
                    <li>Payment proof and booking status tracking</li>
                </ul>

                <a
                    href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>"
                    class="metro-btn metro-btn-accent"
                >
                    Book a Court
                </a>
            </div>
        </div>
    </section>

    <!-- MEMBER EXPERIENCE -->
    <section class="metro-section">
        <div class="metro-container metro-membership">
            <div>
                <span class="metro-eyebrow">Membership</span>
                <h2>One Account. More Ways to Play.</h2>
                <p>
                    Create a member account to manage your reservations and review your booking history.
                </p>

                <?php if ($currentMember): ?>
                    <a
                        href="<?php echo htmlspecialchars(app_url('ui/member.php')); ?>"
                        class="metro-btn metro-btn-accent"
                    >
                        My Bookings
                    </a>
                <?php else: ?>
                    <a
                        href="<?php echo htmlspecialchars(app_url('ui/register.php')); ?>"
                        class="metro-btn metro-btn-accent"
                    >
                        Register
                    </a>
                <?php endif; ?>
            </div>

            <div class="metro-circle">PLAY<br>MORE</div>

            <div>
                <h3><?php echo $currentMember ? 'Welcome Back' : 'Already a Member?'; ?></h3>
                <p>
                    <?php echo $currentMember
                        ? 'Open your account to review and manage your court reservations.'
                        : 'Sign in to access your bookings and reservation history.'; ?>
                </p>

                <a href="<?php echo htmlspecialchars(app_url($currentMember ? 'ui/member.php' : 'ui/member-login.php')); ?>">
                    <?php echo $currentMember ? 'Open Account' : 'Member Login'; ?> →
                </a>
            </div>

            <div class="metro-benefits">
                <div class="metro-benefit">
                    <h3>Easy Booking</h3>
                    <p>Reserve available schedules online.</p>
                </div>

                <div class="metro-benefit">
                    <h3>My Bookings</h3>
                    <p>Review upcoming reservations in one place.</p>
                </div>

                <div class="metro-benefit">
                    <h3>Booking History</h3>
                    <p>Keep track of previous court reservations.</p>
                </div>

                <div class="metro-benefit">
                    <h3>Account Access</h3>
                    <p>Manage your member information and booking activity.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- EXISTING DYNAMIC GALLERY -->
    <?php if (!empty($galleryItems)): ?>
        <section id="gallery" class="metro-section">
            <div class="metro-container metro-soft metro-programs">
                <div class="metro-heading">
                    <span class="metro-eyebrow">Gallery</span>
                    <h2>Inside the Arena</h2>
                    <p>A closer look at the courts, playing areas, and facilities.</p>
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

    <!-- METROASIA DIFFERENCE -->
    <section class="metro-section">
        <div class="metro-container metro-soft metro-difference">
            <div class="metro-heading">
                <span class="metro-eyebrow">Why MetroAsia</span>
                <h2>The MetroAsia Difference</h2>
                <p>
                    A modern court-booking experience built around convenience, organized scheduling, and easier access.
                </p>
            </div>

            <div class="metro-difference-grid">
                <div class="metro-difference-item">
                    <h3>Covered Courts</h3>
                    <p>Enjoy facilities prepared for organized indoor play.</p>
                </div>

                <div class="metro-difference-item">
                    <h3>Easy Scheduling</h3>
                    <p>Check availability before choosing your preferred time.</p>
                </div>

                <div class="metro-difference-item">
                    <h3>Multi-Sport Access</h3>
                    <p>Pickleball, basketball, and volleyball booking options.</p>
                </div>

                <div class="metro-difference-item">
                    <h3>Member Access</h3>
                    <p>Manage bookings and reservation history from your account.</p>
                </div>

                <div class="metro-difference-item">
                    <h3>Payment Tracking</h3>
                    <p>Upload payment proof and follow your booking status.</p>
                </div>

                <div class="metro-difference-item">
                    <h3>Admin Review</h3>
                    <p>Bookings are managed separately through the administrative interface.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CALL TO ACTION -->
    <section class="metro-section">
        <div class="metro-container">
            <div
                class="metro-cta"
                style="background-image:url('<?php echo htmlspecialchars($heroImage, ENT_QUOTES); ?>')"
            >
                <div class="metro-cta-content">
                    <span class="metro-eyebrow">Ready to Play?</span>
                    <h2>Reserve Your Court Today</h2>
                    <p>
                        Select your preferred sport, court, and schedule through our online booking facility.
                    </p>

                    <a
                        href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>"
                        class="metro-btn metro-btn-accent"
                    >
                        Book Your Court
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CONTACT / LOCATION - PRESERVE EXISTING DYNAMIC CONFIG -->
    <section id="contact-us" class="metro-section">
        <div class="metro-container">
            <div class="landing-contact-grid">
                <div>
                    <span class="metro-eyebrow">Contact Us</span>
                    <h2>Visit Metro Asia Arena</h2>
                    <p>
                        Reserve online before you arrive, or use the location below to find the arena.
                    </p>

                    <div class="contact-list">
                        <p>
                            <i data-lucide="map-pin" class="icon-sm"></i>
                            <?php echo htmlspecialchars((string) $siteConfig['address']); ?>
                        </p>

                        <p>
                            <i data-lucide="clock" class="icon-sm"></i>
                            Open daily for scheduled court reservations
                        </p>

                        <p>
                            <i data-lucide="calendar-days" class="icon-sm"></i>
                            Online booking and member account access available
                        </p>
                    </div>

                    <div class="metro-actions">
                        <a
                            href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>"
                            class="metro-btn metro-btn-accent"
                        >
                            Book a Court
                        </a>

                        <a
                            href="<?php echo htmlspecialchars($contactHref); ?>"
                            <?php echo $messengerUrl !== '' ? 'target="_blank" rel="noopener"' : ''; ?>
                            class="metro-btn"
                            style="border-color:var(--metro-primary);"
                        >
                            Contact Admin
                        </a>
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
        </div>
    </section>

</main>

<?php
/*
 * Keep the application's existing shared footer.
 */
include __DIR__ . '/../includes/footer.php';
?>