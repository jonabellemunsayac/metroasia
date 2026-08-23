<?php
$modalMember = function_exists('current_member') ? current_member() : null;
$modalSiteConfig = function_exists('site_config') ? site_config() : [];
$modalMessengerUrl = trim((string) ($modalSiteConfig['messenger_url'] ?? ''));
?>
<div id="bookingModal" class="modal-shell fixed inset-0 z-50 hidden items-center justify-center px-4 py-8">
    <div class="modal-card max-h-[92vh] w-full max-w-2xl overflow-y-auto">
        <div class="modal-header-clean">
            <div>
                <p id="modalKicker" class="modal-kicker">Booking Process</p>
                <h3 id="modalTitle" class="modal-title-clean">Complete reservation</h3>
                <div id="modalMeta" class="modal-meta-clean"></div>
            </div>
            <button id="closeModal" class="modal-close-button" aria-label="Close payment form">x</button>
        </div>
        <form id="paymentForm" class="modal-step-form">
            <input type="hidden" name="actionType" id="actionType">
            <input type="hidden" name="date" id="formDate">
            <input type="hidden" name="time" id="formTime">
            <input type="hidden" name="court" id="formCourt">
            <input type="hidden" name="sport" id="formSport" value="Pickleball">
            <input type="hidden" name="sessionId" id="formSessionId">

            <ol class="booking-stepper" aria-label="Booking steps">
                <li data-booking-step="info">Booking Details</li>
                <li data-booking-step="review">Review Booking</li>
                <li data-booking-step="payment">Review / Payment</li>
                <li data-booking-step="proof">Reservation Confirmation</li>
            </ol>

            <section data-booking-step-panel="info" class="modal-step-panel">
                <div class="modal-section-heading">
                    <p>Step 1</p>
                    <h4>Enter Player Information</h4>
                </div>
                <label class="modal-field">Name
                    <input required name="name" class="modal-input" placeholder="Juan Dela Cruz" value="<?php echo htmlspecialchars((string) ($modalMember['name'] ?? '')); ?>" <?php echo $modalMember ? 'readonly' : ''; ?>>
                </label>
                <div class="modal-grid-two">
                    <label class="modal-field">Phone
                        <input required name="phone" class="modal-input" placeholder="09xx xxx xxxx" value="<?php echo htmlspecialchars((string) ($modalMember['phone'] ?? '')); ?>" <?php echo $modalMember ? 'readonly' : ''; ?>>
                    </label>
                    <label class="modal-field">Email
                        <input type="email" name="email" class="modal-input" placeholder="optional@email.com" value="<?php echo htmlspecialchars((string) ($modalMember['email'] ?? '')); ?>" <?php echo $modalMember ? 'readonly' : ''; ?>>
                    </label>
                </div>
            </section>

            <section data-booking-step-panel="review" class="modal-step-panel">
                <div class="modal-section-heading">
                    <p><?php echo $modalMember ? 'Step 1' : 'Step 2'; ?></p>
                    <h4>Review Booking</h4>
                </div>
                <div id="bookingReviewSummary" class="booking-review-summary"></div>
            </section>

            <section data-booking-step-panel="payment" class="modal-step-panel">
                <div class="modal-section-heading">
                    <p><?php echo $modalMember ? 'Step 2' : 'Step 3'; ?></p>
                    <h4>Payment Instructions</h4>
                </div>
                <label class="modal-field">Payment channel
                    <select required name="paymentMethod" id="paymentMethodSelect" class="modal-input">
                        <option value="">Select payment channel</option>
                        <option value="GCash">GCash</option>
                        <option value="BDO">BDO Online</option>
                    </select>
                </label>
                <div id="paymentInstructions" class="hidden modal-payment-instructions"></div>
            </section>

            <section data-booking-step-panel="proof" class="modal-step-panel">
                <div class="modal-section-heading">
                    <p><?php echo $modalMember ? 'Step 3' : 'Step 4'; ?></p>
                    <h4><?php echo $modalMember ? 'Upload Payment Proof' : 'Booking Reference'; ?></h4>
                </div>
                <?php if ($modalMember): ?>
                    <label class="modal-field">Upload receipt
                        <input required type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="modal-input modal-file-input">
                        <span class="modal-help-text">Registered members can upload payment proof directly here. JPG, PNG, WEBP, or PDF. Max 5MB.</span>
                    </label>
                <?php else: ?>
                    <div class="modal-info-panel">
                        <p class="modal-info-title">Non-member payment proof</p>
                        <p>
                            After submitting, a booking reference will be generated. Send your payment proof through
                            <?php if ($modalMessengerUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($modalMessengerUrl); ?>" target="_blank" rel="noopener">Facebook Messenger</a>
                            <?php else: ?>
                                Facebook Messenger
                            <?php endif; ?>
                            with your reference, reservation name, sport, court, date, and time.
                        </p>
                    </div>
                <?php endif; ?>
                <div id="bookingReferencePanel" class="hidden modal-reference-panel"></div>
            </section>

            <div id="formMessage" class="hidden modal-message"></div>
            <div class="modal-actions">
                <button id="modalBackButton" type="button" class="btn btn-outline-primary">Back</button>
                <button id="modalNextButton" type="button" class="btn btn-primary">Proceed</button>
                <button id="modalSubmitButton" type="submit" class="btn btn-primary">Submit Reservation</button>
            </div>
        </form>
    </div>
</div>
