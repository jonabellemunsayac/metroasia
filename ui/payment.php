<?php
$pageTitle = 'Payment | Multi-Sport Court Scheduling & Reservation';
$active = 'payment';

include __DIR__ . '/../includes/header.php';

$siteConfig = site_config();
$messengerUrl = trim((string) ($siteConfig['messenger_url'] ?? ''));
?>

<main data-needs-state class="metro-booking-page metro-payment-page">

    <section class="metro-booking-hero">
        <div class="metro-container metro-booking-hero-inner">
            <div>
                <span class="metro-eyebrow">Reservation Payment</span>
                <h1>Complete Your Payment</h1>
                <p>
                    Choose an available payment channel, follow the payment instructions,
                    then submit your payment proof for verification.
                </p>
            </div>

            <div class="metro-booking-hero-note">
                <i data-lucide="receipt-text" class="icon-sm"></i>
                <span>Receipt-based payment confirmation.</span>
            </div>
        </div>
    </section>

    <section class="metro-booking-section">
        <div class="metro-container metro-booking-layout">

            <aside class="metro-booking-sidebar">

                <article class="metro-booking-venue-card">
                    <div class="metro-booking-venue-image">
                        <img
                            src="<?php echo htmlspecialchars(site_asset_url((string) $siteConfig['contact_image_path'])); ?>"
                            alt="Metro Asia Arena payment channels"
                        >
                    </div>

                    <div class="metro-booking-venue-body">
                        <span class="metro-eyebrow">Payment Channels</span>
                        <h2>Choose How You Want to Pay</h2>

                        <p>
                            Select a channel during checkout, pay using the displayed
                            QR code or bank details, then submit your receipt.
                        </p>

                        <a
                            href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>"
                            class="metro-btn metro-btn-accent"
                        >
                            Book a Court
                        </a>
                    </div>
                </article>

                <article class="metro-booking-about-card">
                    <span class="metro-eyebrow">After Payment</span>
                    <h3>How Confirmation Works</h3>

                    <div class="metro-payment-help-list">
                        <div class="metro-payment-help-item">
                            <span class="metro-payment-help-icon">
                                <i data-lucide="user-round" class="icon-sm"></i>
                            </span>

                            <div>
                                <strong>Non-members</strong>
                                <p>
                                    Send payment proof through
                                    <?php if ($messengerUrl !== ''): ?>
                                        <a
                                            href="<?php echo htmlspecialchars($messengerUrl); ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            Facebook Messenger
                                        </a>
                                    <?php else: ?>
                                        Facebook Messenger
                                    <?php endif; ?>
                                    with your reservation name, date, sport, court, and time.
                                </p>
                            </div>
                        </div>

                        <div class="metro-payment-help-item">
                            <span class="metro-payment-help-icon">
                                <i data-lucide="badge-check" class="icon-sm"></i>
                            </span>

                            <div>
                                <strong>Registered members</strong>
                                <p>
                                    Upload payment proof directly to the website.
                                    Admin confirms payment after review.
                                </p>
                            </div>
                        </div>
                    </div>
                </article>

            </aside>

            <section class="metro-booking-panel metro-payment-panel">

                <div class="metro-booking-tabs">
                    <a href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>">
                        Booking
                    </a>

                    <a
                        href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>"
                        class="active"
                    >
                        Payment
                    </a>
                </div>

                <div class="metro-booking-panel-body">

                    <div class="metro-booking-info">
                        <div class="metro-booking-info-icon">
                            <i data-lucide="wallet-cards" class="icon-sm"></i>
                        </div>

                        <div>
                            <strong>Receipt Upload Payment</strong>
                            <p>
                                Payment channels, QR images, and bank details are
                                configured by the administrator.
                            </p>
                        </div>
                    </div>

                    <div class="metro-payment-section-head">
                        <div>
                            <span class="metro-booking-label">Available Channels</span>
                            <h3>Select a Payment Method</h3>
                        </div>

                        <div class="metro-payment-secure-note">
                            <i data-lucide="shield-check" class="icon-sm"></i>
                            <span>Verify the payment details before sending funds.</span>
                        </div>
                    </div>

                    <!-- Existing app.js hook preserved -->
                    <div
                        id="paymentPageChannels"
                        class="metro-payment-channels"
                    ></div>

                </div>
            </section>

        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
