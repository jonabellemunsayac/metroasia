<?php
$pageTitle = 'Book a Court | Multi-Sport Court Scheduling & Reservation';
$active = 'booking';

include __DIR__ . '/../includes/header.php';
$siteConfig = site_config();
?>

<main data-needs-state class="metro-booking-page">

    <!-- Page introduction -->
    <section class="metro-booking-hero">
        <div class="metro-container metro-booking-hero-inner">
            <div>
                <span class="metro-eyebrow">Court Reservation</span>
                <h1>Book Your Court</h1>
                <p>
                    Choose your sport, select an available date and court,
                    then complete your reservation in just a few steps.
                </p>
            </div>

            <div class="metro-booking-hero-note">
                <i data-lucide="calendar-check" class="icon-sm"></i>
                <span>Multi-day booking is available.</span>
            </div>
        </div>
    </section>

    <section class="metro-booking-section">
        <div class="metro-container metro-booking-layout">

            <!-- VENUE INFO -->
            <aside class="metro-booking-sidebar">
                <article class="metro-booking-venue-card">
                    <div class="metro-booking-venue-image">
                        <img
                            src="<?php echo htmlspecialchars(site_asset_url((string) $siteConfig['contact_image_path'])); ?>"
                            alt="Covered Metro Asia courts"
                        >
                        <span class="metro-booking-image-index">2 / 4</span>
                    </div>

                    <div class="metro-booking-venue-body">
                        <div class="metro-booking-venue-head">
                            <div>
                                <h2>Metro Asia Arena</h2>
                                <p class="metro-booking-address">
                                    <i data-lucide="map-pin" class="icon-sm"></i>
                                    <?php echo htmlspecialchars((string) $siteConfig['address']); ?>
                                </p>
                            </div>

                            <div class="metro-booking-starting-rate">
                                <strong>PHP 265</strong>
                                <span>/hr</span>
                            </div>
                        </div>

                        <div class="metro-booking-mini-actions">
                            <button type="button">
                                <i data-lucide="heart" class="icon-sm"></i>
                                <span>174</span>
                            </button>

                            <button type="button">
                                <i data-lucide="share-2" class="icon-sm"></i>
                                <span>Share</span>
                            </button>

                            <button type="button">
                                <i data-lucide="flag" class="icon-sm"></i>
                                <span>Support</span>
                            </button>
                        </div>
                    </div>
                </article>

                <article class="metro-booking-about-card">
                    <span class="metro-eyebrow">About the Venue</span>
                    <h3>Indoor Multi-Sport Courts</h3>
                    <p>
                        Metro Asia brings indoor pickleball, basketball, and volleyball
                        to Pasig with covered courts and a convenient online reservation flow.
                    </p>
                    <p>
                        Each listed court is scheduled independently. Select your preferred
                        sport first to see the applicable courts and available time slots.
                    </p>
                </article>
            </aside>

            <!-- BOOKING PANEL -->
            <section class="metro-booking-panel">

                <div class="metro-booking-tabs">
                    <a
                        href="<?php echo htmlspecialchars(app_url('ui/booking.php')); ?>"
                        class="active"
                    >
                        Booking
                    </a>

                    <a
                        href="<?php echo htmlspecialchars(app_url('ui/payment.php')); ?>"
                    >
                        Payment
                    </a>
                </div>

                <div class="metro-booking-panel-body">

                    <!-- Info banner -->
                    <div class="metro-booking-info">
                        <div class="metro-booking-info-icon">
                            <i data-lucide="calendar-range" class="icon-sm"></i>
                        </div>
                        <div>
                            <strong>Multi-Day Booking Enabled</strong>
                            <p>Book across multiple dates in a single reservation.</p>
                        </div>
                    </div>

                    <!-- Activity selection -->
                    <div class="metro-booking-form-block">
                        <div class="metro-booking-block-head">
                            <div>
                                <span class="metro-booking-label">Activity</span>
                                <h3>Choose Your Sport</h3>
                            </div>

                            <div class="metro-booking-warning">
                                <i data-lucide="clock-3" class="icon-sm"></i>
                                <span>Past dates and times cannot be booked.</span>
                            </div>
                        </div>

                        <div class="metro-sport-options">
                            <button
                                data-sport="Pickleball"
                                class="sport-option-active metro-sport-option"
                                type="button"
                            >
                                <span class="metro-sport-icon">P</span>
                                <span>Pickleball</span>
                            </button>

                            <button
                                data-sport="Basketball"
                                class="sport-option metro-sport-option"
                                type="button"
                            >
                                <span class="metro-sport-icon">B</span>
                                <span>Basketball</span>
                            </button>

                            <button
                                data-sport="Volleyball"
                                class="sport-option metro-sport-option"
                                type="button"
                            >
                                <span class="metro-sport-icon">V</span>
                                <span>Volleyball</span>
                            </button>
                        </div>
                    </div>

                    <!-- Date control -->
                    <div class="metro-booking-date-row">
                        <div class="metro-booking-date-title">
                            <i data-lucide="calendar-days" class="icon-sm"></i>
                            <div>
                                <span class="metro-booking-label">Selected Date</span>
                                <p id="bookingDateLabel"></p>
                            </div>
                        </div>

                        <div class="metro-booking-date-actions">
                            <button
                                id="prevDate"
                                type="button"
                                aria-label="Previous date"
                            >
                                <i data-lucide="chevron-left" class="icon-sm"></i>
                            </button>

                            <button
                                type="button"
                                aria-label="Calendar"
                            >
                                <i data-lucide="calendar" class="icon-sm"></i>
                            </button>

                            <button
                                id="nextDate"
                                type="button"
                                aria-label="Next date"
                            >
                                <i data-lucide="chevron-right" class="icon-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Rates -->
                    <div class="metro-booking-rates">
                        <div class="metro-booking-rates-label">
                            <span class="metro-booking-label">Rates</span>
                            <p>Rates update according to the selected sport and court.</p>
                        </div>

                        <div id="rateCards" class="metro-rate-cards"></div>
                    </div>

                    <!-- Existing dynamic booking grid -->
                    <div class="metro-booking-grid-shell">
                        <div id="bookingGrid" class="booking-grid grid"></div>
                    </div>

                    <!-- Existing selection state / app.js hooks -->
                    <div id="bookingSelectionBar" class="booking-selection-bar hidden metro-booking-selection-bar">
                        <div>
                            <p class="booking-selection-title">Selected slots</p>
                            <p id="bookingSelectionSummary" class="booking-selection-summary"></p>
                        </div>

                        <button
                            id="bookingSelectionBookNow"
                            type="button"
                            class="metro-btn metro-btn-accent"
                        >
                            Book Now
                        </button>
                    </div>

                </div>
            </section>

        </div>
    </section>

</main>

<?php include __DIR__ . '/../includes/payment-modal.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
