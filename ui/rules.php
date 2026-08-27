<?php
$pageTitle = 'Rules';
$active = 'rules';
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page">
    <section class="mx-auto grid max-w-[980px] gap-6">
        <div>
            <p class="section-kicker">Rules</p>
            <h1 class="mt-2 font-display text-4xl font-black leading-tight">Rules</h1>
            <p class="mt-3 max-w-3xl text-sm font-semibold leading-7 text-muted">
                Please review the venue booking policy, house rules, and liability reminders before reserving a court.
            </p>
        </div>

        <article class="public-card p-5 privacy-policy-content">
            <h2>Booking Policy</h2>
            <p><strong>Strictly no refunds.</strong></p>
            <p><strong>No rescheduling.</strong></p>
            <p><strong>No cancellation.</strong></p>
            <p>Members must sign up/register to book a schedule.</p>
            <p>Once a slot has been selected, the member will have 15 minutes to complete the payment and upload the proof of payment. Failure to do so within the allotted time will result in the selected slot being released and made available to other clients.</p>
            <p>MAD MetroAsia Arena reserves the right to cancel or reject a booking under any of the following circumstances:</p>
            <ul>
                <li>Payment is not reflected in the designated bank account.</li>
                <li>The court or facility is deemed unsafe or unavailable due to brownouts, electrical problems, acts of nature, emergencies, or other unforeseen circumstances.</li>
            </ul>

            <h2>House Rules &amp; Liability</h2>
            <h3>Safety &amp; Injuries</h3>
            <p>MAD MetroAsia Arena shall not be held liable for any injury, accident, or illness sustained while using the premises or facilities. Basic first-aid supplies are available on-site. All activities and use of the facilities are undertaken at your own risk.</p>

            <h3>Parking</h3>
            <p>Parking is provided at your own risk. MAD MetroAsia Arena shall not be held liable for any loss, damage, or theft involving vehicles or personal belongings left inside or around the parking area.</p>

            <h3>Personal Belongings</h3>
            <p>Please keep a close watch on your bags, phones, valuables, and sports equipment. MAD MetroAsia Arena and its management shall not be held liable for any lost, stolen, or damaged personal belongings.</p>

            <h3>Facility Care</h3>
            <p>All guests, including children, are responsible for helping maintain the cleanliness and condition of the venue and its facilities. Any damage caused to the property or equipment will be charged to the responsible person and may be subject to additional penalties.</p>

            <h2>Our Goal</h2>
            <p>To build and serve a healthy, active, and welcoming community for everyone!</p>
            <p>Thank you for your cooperation and understanding.</p>
            <p class="mb-0"><strong>MAD MetroAsia Arena Team</strong></p>
        </article>

        <div>
            <a href="<?php echo htmlspecialchars($bookingCtaHref); ?>" class="btn btn-primary">Let's Play</a>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
