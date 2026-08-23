<?php
$pageTitle = "Let's Play";
$active = 'booking';

include __DIR__ . '/../includes/header.php';
$siteConfig = site_config();
$bookingMember = current_member();

$sportOptions = [
    'pickleball' => [
        'label' => 'Pickleball',
        'description' => 'Fast rallies, social play, and dedicated pickleball court schedules.',
    ],
    'basketball' => [
        'label' => 'Basketball',
        'description' => 'Reserve court time for shootarounds, team runs, and full games.',
    ],
    'volleyball' => [
        'label' => 'Volleyball',
        'description' => 'Book an indoor court for drills, practices, and group matches.',
    ],
];

$requestedSport = strtolower(trim((string) ($_GET['sport'] ?? '')));
$selectedSport = $sportOptions[$requestedSport] ?? null;
?>

<?php if ($selectedSport): ?>
    <script>
        window.metroBookingSport = <?php echo json_encode($selectedSport['label']); ?>;
    </script>
<?php endif; ?>

<main <?php echo $selectedSport ? 'data-needs-state' : ''; ?> class="metro-booking-page">

    <?php if (!$selectedSport): ?>
    <section class="metro-sport-select-section">
        <div class="metro-container">
            <div class="metro-sport-select-heading">
                <span class="metro-eyebrow">Court Reservation</span>
                <h1>Select Your Sport</h1>
                <p>Choose a sport first, then continue to available courts, dates, and time slots.</p>
            </div>

            <div class="metro-sport-select-grid">
                <?php foreach ($sportOptions as $slug => $sport): ?>
                    <a
                        class="metro-sport-select-card"
                        href="<?php echo htmlspecialchars(app_url('ui/booking.php?sport=' . $slug)); ?>"
                    >
                        <span class="metro-sport-select-body">
                            <span>
                                <strong><?php echo htmlspecialchars($sport['label']); ?></strong>
                                <small><?php echo htmlspecialchars($sport['description']); ?></small>
                            </span>
                            <span class="metro-sport-select-action">
                                Select <?php echo htmlspecialchars($sport['label']); ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php else: ?>
    <section class="metro-booking-section">
        <div class="metro-container metro-booking-layout metro-booking-layout-full">

            <section class="metro-reservation-shell">
                <!-- <ol class="metro-reservation-progress" aria-label="Booking progress">
                    <li class="is-active">
                        <span>1</span>
                        <strong><?php //echo htmlspecialchars($selectedSport['label']); ?> Booking</strong>
                    </li>
                    <li>
                        <span>2</span>
                        <strong>Reservation</strong>
                    </li>
                    <li>
                        <span>3</span>
                        <strong>Payment</strong>
                    </li>
                    <li>
                        <span>4</span>
                        <strong>Confirmation</strong>
                    </li>
                </ol> -->

                <div class="metro-booking-topline">
                    <div>
                        <span class="metro-booking-label">Court Reservation</span>
                        <h1>Reserve Your Court</h1>
                        <p><?php echo htmlspecialchars($selectedSport['label']); ?> booking schedule</p>
                    </div>

                    <div class="metro-booking-rates">
                        <span class="metro-booking-label">Court Rates</span>
                        <div id="rateCards" class="metro-rate-cards"></div>
                    </div>
                </div>

                <!-- BOOKING PANEL -->
                <section class="metro-booking-panel">
                <div class="metro-booking-panel-body">
                    <div class="metro-booking-date-board">
                        <span class="metro-booking-label">Select Booking Date</span>
                        <div class="metro-booking-date-strip">
                            <button
                                id="prevDate"
                                type="button"
                                class="metro-date-arrow"
                                aria-label="Previous date"
                            >
                                <i data-lucide="chevron-left" class="icon-sm"></i>
                            </button>

                            <div id="bookingDateCards" class="metro-date-cards"></div>

                            <button
                                id="nextDate"
                                type="button"
                                class="metro-date-arrow"
                                aria-label="Next date"
                            >
                                <i data-lucide="chevron-right" class="icon-sm"></i>
                            </button>
                        </div>
                        <p id="bookingDateLabel" class="metro-date-current"></p>
                    </div>

                    <!-- Existing dynamic booking grid -->
                    <div class="metro-booking-grid-shell">
                        <div id="bookingGrid" class="booking-grid grid"></div>
                    </div>

                    <div class="metro-booking-note" role="note">
                        <i data-lucide="info" class="icon-sm"></i>
                        <p>
                            <strong>These slots are held temporarily and will become available again if the reservation is not finalized.</strong>
                        </p>
                    </div>

                    <section id="bookingInlineReservation" class="metro-inline-reservation">
                        <div class="metro-inline-reservation-head">
                            <div>
                                <span class="metro-booking-label">Reservation Details</span>
                                <h2>Player Information</h2>
                            </div>
                            <p id="bookingInlineEmpty">Select one or more available slots to complete your reservation.</p>
                        </div>

                        <form id="paymentForm" class="metro-inline-form" data-booking-inline="1">
                            <input type="hidden" name="actionType" id="actionType" value="book">
                            <input type="hidden" name="date" id="formDate">
                            <input type="hidden" name="time" id="formTime">
                            <input type="hidden" name="court" id="formCourt">
                            <input type="hidden" name="sport" id="formSport" value="<?php echo htmlspecialchars($selectedSport['label']); ?>">
                            <input type="hidden" name="sessionId" id="formSessionId">

                            <div class="metro-inline-player-layout">
                                <section class="metro-inline-player-card">
                                    <div class="metro-inline-field-grid">
                                        <label class="metro-inline-field">Full Name
                                            <input required name="name" class="modal-input" placeholder="e.g. Juan Dela Cruz" value="<?php echo htmlspecialchars((string) ($bookingMember['name'] ?? '')); ?>" <?php echo $bookingMember ? 'readonly' : ''; ?>>
                                        </label>

                                        <label class="metro-inline-field">Nickname
                                            <input name="nickname" class="modal-input" placeholder="e.g. Juan">
                                            <span>This is the name other players will see on your booked time slot.</span>
                                        </label>

                                        <label class="metro-inline-field">Phone Number
                                            <input required name="phone" class="modal-input" placeholder="09xx xxx xxxx" value="<?php echo htmlspecialchars((string) ($bookingMember['phone'] ?? '')); ?>" <?php echo $bookingMember ? 'readonly' : ''; ?>>
                                        </label>

                                        <label class="metro-inline-field">Email Address
                                            <input type="email" name="email" class="modal-input" placeholder="e.g. juan@example.com" value="<?php echo htmlspecialchars((string) ($bookingMember['email'] ?? '')); ?>" <?php echo $bookingMember ? 'readonly' : ''; ?>>
                                        </label>

                                        <label class="metro-inline-field">Notes
                                            <textarea name="notes" class="modal-input metro-inline-notes" placeholder="Any specific requests, questions, or notes for the staff..."></textarea>
                                        </label>
                                    </div>
                                </section>

                                <div class="metro-inline-summary-card">
                                    <div class="metro-inline-summary-head">
                                        <span class="metro-booking-label">Reservation Summary</span>
                                        <span id="bookingSummaryDateLabel" class="metro-summary-date"></span>
                                    </div>
                                    <div id="bookingReviewSummary" class="booking-review-summary"></div>
                                </div>
                            </div>

                            <section class="metro-inline-payment-section">
                                <div>
                                    <span class="metro-booking-label">Secure Your Payment</span>
                                    <h2>Payment Details</h2>
                                </div>

                                <div class="metro-inline-payment-grid">
                                    <label class="metro-inline-field">Payment Channel
                                        <select required name="paymentMethod" id="paymentMethodSelect" class="modal-input">
                                            <option value="">Select payment channel</option>
                                        </select>
                                    </label>

                                    <?php if ($bookingMember): ?>
                                        <label class="metro-inline-field">Upload Receipt
                                            <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="modal-input modal-file-input">
                                            <span>JPG, PNG, WEBP, or PDF. Max 5MB.</span>
                                        </label>
                                    <?php endif; ?>
                                </div>

                                <div id="paymentInstructions" class="hidden modal-payment-instructions metro-inline-payment-instructions"></div>
                                <div id="bookingReferencePanel" class="hidden modal-reference-panel"></div>
                                <div id="formMessage" class="hidden modal-message"></div>
                                <button id="modalSubmitButton" type="submit" class="metro-btn metro-btn-primary metro-inline-submit" disabled>
                                    Confirm Reservation
                                </button>
                            </section>
                        </form>
                    </section>

                </div>
            </section>
            </section>

        </div>
    </section>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
