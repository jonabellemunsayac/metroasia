<?php $modalMember = function_exists('current_member') ? current_member() : null; ?>
<div id="bookingModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-ink/70 px-4 py-8">
    <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-lg bg-white shadow-soft">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
            <div>
                <p id="modalKicker" class="text-sm font-black uppercase tracking-[.18em] text-court">Payment</p>
                <h3 id="modalTitle" class="mt-1 text-2xl font-black">Complete reservation</h3>
                <p id="modalMeta" class="mt-1 text-sm font-semibold text-slate-500"></p>
            </div>
            <button id="closeModal" class="grid h-10 w-10 place-items-center rounded-full border border-slate-200 text-xl font-black" aria-label="Close payment form">x</button>
        </div>
        <form id="paymentForm" class="grid gap-4 p-5">
            <input type="hidden" name="actionType" id="actionType">
            <input type="hidden" name="date" id="formDate">
            <input type="hidden" name="time" id="formTime">
            <input type="hidden" name="court" id="formCourt">
            <input type="hidden" name="sport" id="formSport" value="Pickleball">
            <input type="hidden" name="sessionId" id="formSessionId">
            <label class="grid gap-2 text-sm font-bold">Name
                <input required name="name" class="rounded-md border border-slate-300 px-4 py-3 font-medium outline-none focus:border-court" placeholder="Juan Dela Cruz" value="<?php echo htmlspecialchars((string) ($modalMember['name'] ?? '')); ?>" <?php echo $modalMember ? 'readonly' : ''; ?>>
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-2 text-sm font-bold">Phone
                    <input required name="phone" class="rounded-md border border-slate-300 px-4 py-3 font-medium outline-none focus:border-court" placeholder="09xx xxx xxxx" value="<?php echo htmlspecialchars((string) ($modalMember['phone'] ?? '')); ?>" <?php echo $modalMember ? 'readonly' : ''; ?>>
                </label>
                <label class="grid gap-2 text-sm font-bold">Email
                    <input type="email" name="email" class="rounded-md border border-slate-300 px-4 py-3 font-medium outline-none focus:border-court" placeholder="optional@email.com" value="<?php echo htmlspecialchars((string) ($modalMember['email'] ?? '')); ?>" <?php echo $modalMember ? 'readonly' : ''; ?>>
                </label>
            </div>
            <label class="grid gap-2 text-sm font-bold">Payment channel
                <select required name="paymentMethod" id="paymentMethodSelect" class="rounded-md border border-slate-300 bg-white px-4 py-3 font-medium outline-none focus:border-court">
                    <option value="">Select payment channel</option>
                    <option value="GCash">GCash</option>
                    <option value="BDO">BDO Online</option>
                </select>
            </label>
            <div id="paymentInstructions" class="hidden rounded-lg border border-slate-200 bg-slate-50 p-4"></div>
            <?php if ($modalMember): ?>
                <label class="grid gap-2 text-sm font-bold">Upload receipt
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm file:mr-4 file:rounded-full file:border-0 file:bg-ink file:px-4 file:py-2 file:font-bold file:text-white">
                    <span class="text-xs font-semibold text-slate-500">Registered members can upload payment proof directly here or later from My Bookings. JPG, PNG, WEBP, or PDF. Max 5MB.</span>
                </label>
            <?php else: ?>
                <div class="rounded-lg border border-blue-200 bg-blue-50/60 p-4">
                    <p class="text-sm font-black text-primary">Non-member payment proof</p>
                    <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">After payment, send your proof through Facebook Messenger with your reservation name, sport, court, date, and time. Members can upload proof directly on the website.</p>
                </div>
            <?php endif; ?>
            <div id="formMessage" class="hidden rounded-md p-3 text-sm font-bold"></div>
            <button class="rounded-full bg-limevolt px-6 py-4 text-base font-black text-ink transition hover:bg-ink hover:text-white">Submit Reservation</button>
        </form>
    </div>
</div>
