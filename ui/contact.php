<?php
$pageTitle = 'Contact Admin';
$active = 'contact';
include __DIR__ . '/../includes/header.php';

$siteConfig = site_config();
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
$venueName = trim((string) ($siteConfig['venue_name'] ?? 'MetroAsia Arena'));
$venueName = $venueName !== '' ? $venueName : 'MetroAsia Arena';
$venueAddress = trim((string) ($siteConfig['address'] ?? ''));
$mapQuery = $venueAddress !== '' ? $venueAddress : $venueName;
$googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery);
$wazeUrl = 'https://waze.com/ul?q=' . rawurlencode($mapQuery) . '&navigate=yes';
?>

<main class="public-page metro-contact-page">
    <section class="metro-contact-layout">
        <div class="metro-contact-map">
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
                <h1>Address</h1>
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
                <h2>Opening Hours</h2>
                <strong>8:00 AM - 12:00 MN</strong>
                <p>Open 7 days a week</p>
            </section>

            <section class="metro-contact-card-section">
                <h2>Get In Touch</h2>
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
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
