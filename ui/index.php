<?php
$pageTitle = 'Metro Asia Arena';
$active = 'home';
include __DIR__ . '/../includes/header.php';
$siteConfig = site_config();
$galleryItems = site_config_gallery($siteConfig);
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
$contactHref = $messengerUrl !== '' ? $messengerUrl : app_url('ui/contact.php');
?>
<main class="home-screen-page">
    <section id="welcome" class="arena-home-screen" aria-label="Metro Asia Arena home screen" style="--arena-hero-image: url('<?php echo htmlspecialchars(site_asset_url((string) $siteConfig['hero_image_path']), ENT_QUOTES); ?>')">
        <div class="arena-home-content">
            <h1>Welcome to MetroAsia Arena</h1>
            <p class="arena-home-copy"><?php echo htmlspecialchars((string) $siteConfig['venue_name']); ?> is open daily and ready for your next game!</p>
            <p class="arena-home-copy">
                Reserve your preferred court and schedule by clicking "Book Now" and completing our online booking form.
            </p>
            <p class="arena-home-copy">Book your court today. We'll see you on the court!</p>
            <div class="arena-home-actions">
                <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="btn btn-lime arena-book-button">
                    Book Now
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="landing-section">
        <div class="container-xl">
            <div class="landing-heading">
                <p>About Us</p>
                <h2>Why choose <span>Metro Asia Arena?</span></h2>
                <div class="landing-heading-copy">
                    <?php echo htmlspecialchars((string) $siteConfig['venue_name']); ?> brings premium indoor pickleball, basketball, and volleyball to Pasig with bright covered courts, clear online reservations, and simple payment confirmation.
                </div>
            </div>

            <div class="landing-feature-grid">
                <article class="landing-feature-card">
                    <span class="feature-number">01</span>
                    <i data-lucide="calendar-check" class="feature-icon"></i>
                    <h3>Book</h3>
                    <p>Select your sport, court, date, and time from one clean booking flow.</p>
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="main-button">Book Now</a>
                </article>
                <article class="landing-feature-card">
                    <span class="feature-number">02</span>
                    <i data-lucide="dumbbell" class="feature-icon"></i>
                    <h3>Play</h3>
                    <p>Enjoy dedicated pickleball courts plus multi-sport courts for basketball and volleyball.</p>
                    <a href="<?php echo htmlspecialchars(app_url('ui/rules.php')); ?>" class="main-button main-button-outline">View Rules</a>
                </article>
                <article class="landing-feature-card">
                    <span class="feature-number">03</span>
                    <i data-lucide="receipt-text" class="feature-icon"></i>
                    <h3>Confirm</h3>
                    <p>Upload payment proof and track reservation status from your member account.</p>
                    <a href="<?php echo htmlspecialchars(app_url($currentMember ? 'ui/member.php' : 'login.php')); ?>" class="main-button main-button-outline">
                        <?php echo $currentMember ? 'My Bookings' : 'Sign In'; ?>
                    </a>
                </article>
            </div>
        </div>
    </section>

    <section id="gallery" class="landing-section landing-section-soft">
        <div class="container-xl">
            <div class="landing-heading">
                <p>Gallery</p>
                <h2>Inside the <span>arena</span></h2>
                <div class="landing-heading-copy">
                    A quick look at the covered courts, playing surfaces, and reservation-ready facilities.
                </div>
            </div>

            <div class="landing-gallery-grid">
                <?php foreach ($galleryItems as $item): ?>
                    <figure>
                        <img src="<?php echo htmlspecialchars(site_asset_url((string) $item['image'])); ?>" alt="<?php echo htmlspecialchars((string) $item['title']); ?>" onerror="this.closest('figure').classList.add('image-missing')">
                        <figcaption><?php echo htmlspecialchars((string) $item['title']); ?></figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="contact-us" class="landing-section contact-section">
        <div class="container-xl">
            <div class="landing-contact-grid">
                <div>
                    <div class="landing-heading landing-heading-left">
                        <p>Contact Us</p>
                        <h2>Visit <span>Metro Asia Arena</span></h2>
                        <div class="landing-heading-copy">
                            Reserve online before you arrive, or use the location below to find the arena.
                        </div>
                    </div>

                    <div class="contact-list">
                        <p><i data-lucide="map-pin" class="icon-sm"></i><?php echo htmlspecialchars((string) $siteConfig['address']); ?></p>
                        <p><i data-lucide="clock" class="icon-sm"></i>Open daily for scheduled court reservations</p>
                        <p><i data-lucide="calendar-days" class="icon-sm"></i>Online booking and member account access available</p>
                    </div>

                    <div class="arena-home-actions landing-contact-actions">
                        <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>" class="btn btn-primary btn-lg">
                            <i data-lucide="calendar-check" class="icon-sm"></i>
                            Book a Court
                        </a>
                        <a href="<?php echo htmlspecialchars($contactHref); ?>" <?php echo $messengerUrl !== '' ? 'target="_blank" rel="noopener"' : ''; ?> class="btn btn-outline-primary btn-lg">
                            <i data-lucide="message-circle" class="icon-sm"></i>
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
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
