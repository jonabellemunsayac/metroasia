<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$pageTitle = 'Users / Members';
$active = 'admin-members';
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Users / Members</span>
                <h2 class="mt-1 mb-1 fw-black">Access and account management</h2>
                <p class="mb-0 small text-secondary fw-semibold">Manage member profiles, QR lookup, entrance-fee payments, and staff/admin accounts.</p>
            </div>
            <button id="adminAddMember" class="btn btn-primary btn-sm" type="button">
                <i data-lucide="user-plus" class="icon-sm"></i>Add Member
            </button>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-12">
            <div class="app-card h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <span class="section-kicker">Members</span>
                        <h2 class="mt-1 mb-0 fw-black">Registered member list</h2>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <input id="adminMemberSearch" class="form-input form-input-sm" placeholder="Search name, nickname, phone, or email">
                        <button id="adminScanMemberQr" class="btn btn-outline-secondary btn-sm" type="button">
                            <i data-lucide="scan-line" class="icon-sm"></i>Scan QR
                        </button>
                    </div>
                </div>
                <div id="adminMembers"></div>
            </div>
        </div>
        <div class="col-12">
            <div class="app-card h-100">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <span class="section-kicker">Admin Users</span>
                        <h2 class="mt-1 mb-0 fw-black">Staff access</h2>
                    </div>
                    <span class="badge text-bg-primary">Admin / staff</span>
                </div>
                <div id="adminUsers" class="grid gap-3"></div>
            </div>
        </div>
    </section>

    <div id="adminMemberModal" class="modal fade" tabindex="-1" aria-labelledby="adminMemberModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="adminMemberForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Member Profile</span>
                            <h2 id="adminMemberModalTitle" class="modal-title fw-black">Add Member</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="adminMemberId">
                        <div class="row g-3">
                            <label class="col-md-6 small fw-bold">Name
                                <input required name="name" id="adminMemberName" class="form-input mt-1">
                            </label>
                            <label class="col-md-6 small fw-bold">Nickname
                                <input name="nickname" id="adminMemberNickname" class="form-input mt-1">
                            </label>
                            <label class="col-md-6 small fw-bold">Phone
                                <input required name="phone" id="adminMemberPhone" class="form-input mt-1">
                            </label>
                            <label class="col-md-6 small fw-bold">Email
                                <input required type="email" name="email" id="adminMemberEmail" class="form-input mt-1">
                            </label>
                            <label class="col-md-4 small fw-bold">Birth Month
                                <select required name="birthMonth" id="adminMemberBirthMonth" class="form-select mt-1">
                                    <option value="">Select month</option>
                                    <?php foreach (range(1, 12) as $month): ?>
                                        <option value="<?php echo $month; ?>"><?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="col-md-4 small fw-bold">Birth Year
                                <input required type="number" name="birthYear" id="adminMemberBirthYear" class="form-input mt-1" min="1900" max="<?php echo (int) date('Y'); ?>">
                            </label>
                            <label class="col-md-4 small fw-bold">Self-Assessed Skill Level
                                <select required name="skillLevel" id="adminMemberSkillLevel" class="form-select mt-1">
                                    <option value="">Select a level...</option>
                                    <option value="2.0">2.0 - Just starting out</option>
                                    <option value="2.5">2.5 - Learning basic shots &amp; rules</option>
                                    <option value="3.0">3.0 - Consistent rallies, knows strategy</option>
                                    <option value="3.5">3.5 - Solid all-court game</option>
                                    <option value="4.0">4.0 - Advanced placement &amp; strategy</option>
                                    <option value="4.5">4.5 - Competitive tournament player</option>
                                    <option value="5.0">5.0+ - Elite / pro level</option>
                                </select>
                            </label>
                            <label class="col-md-6 small fw-bold">Password
                                <input type="password" name="password" id="adminMemberPassword" class="form-input mt-1" minlength="8" placeholder="Required for new members">
                            </label>
                            <label class="col-md-6 small fw-bold d-flex align-items-center gap-2 mt-4">
                                <input type="checkbox" name="isActive" id="adminMemberIsActive" value="1" checked>
                                Active member
                            </label>
                            <label class="col-12 small fw-bold d-flex align-items-start gap-2">
                                <input required type="checkbox" name="dataPrivacyActAgree" id="adminMemberPrivacyAgree" value="1" class="mt-1">
                                <span>
                                    I have read and agree to the
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-open-privacy-policy>Data Privacy Policy</button>
                                    and consent to the processing of my personal data for MetroAsia Arena platform services.
                                </span>
                            </label>
                        </div>
                        <div id="adminMemberFormMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="adminPrivacyPolicyModal" class="modal fade" tabindex="-1" aria-labelledby="adminPrivacyPolicyTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="adminPrivacyPolicyTitle" class="modal-title fw-black">Data Privacy Policy</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body small fw-semibold text-secondary">
                    <p>MetroAsia Arena collects member information to manage accounts, bookings, payments, QR lookup, entrance-fee records, and platform support.</p>
                    <p>Personal data is used only for MetroAsia Arena services, operational verification, audit/history records, and legally required administration. Access is limited to authorized staff.</p>
                    <p>Members may request correction or deactivation of their account details through MetroAsia Arena administration.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="adminMemberQrModal" class="modal fade" tabindex="-1" aria-labelledby="adminMemberQrTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <span class="section-kicker">Member QR</span>
                        <h2 id="adminMemberQrTitle" class="modal-title fw-black">Member QR Code</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="adminMemberQrBody" class="modal-body"></div>
            </div>
        </div>
    </div>

    <div id="adminQrScanModal" class="modal fade" tabindex="-1" aria-labelledby="adminQrScanTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="adminQrScanForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Scan QR</span>
                            <h2 id="adminQrScanTitle" class="modal-title fw-black">Find member</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <video id="adminQrVideo" class="admin-qr-video" playsinline hidden></video>
                        <label class="small fw-bold w-100">QR payload or lookup token
                            <input name="qrPayload" id="adminQrPayload" class="form-input mt-1" placeholder="Scan or paste member QR payload">
                        </label>
                        <div id="adminQrScanMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="adminStartQrCamera" class="btn btn-outline-secondary btn-sm">Use Camera</button>
                        <button type="submit" class="btn btn-primary btn-sm">Find Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="adminEntranceFeeModal" class="modal fade" tabindex="-1" aria-labelledby="adminEntranceFeeTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="adminEntranceFeeForm">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Entrance Fee</span>
                            <h2 id="adminEntranceFeeTitle" class="modal-title fw-black">Pay Entrance Fee</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="memberId" id="adminEntranceMemberId">
                        <p id="adminEntranceMemberSummary" class="small fw-semibold text-secondary"></p>
                        <div class="row g-3">
                            <label class="col-md-6 small fw-bold">Amount
                                <input required type="number" min="1" step="0.01" name="amount" id="adminEntranceAmount" class="form-input mt-1" value="50.00">
                            </label>
                            <label class="col-md-6 small fw-bold">Payment Method
                                <input name="paymentMethod" id="adminEntrancePaymentMethod" class="form-input mt-1" value="Cash">
                            </label>
                            <label class="col-md-6 small fw-bold">Date
                                <input required type="date" name="paymentDate" id="adminEntrancePaymentDate" class="form-input mt-1">
                            </label>
                            <label class="col-md-6 small fw-bold">Time
                                <input required type="time" name="paymentTime" id="adminEntrancePaymentTime" class="form-input mt-1">
                            </label>
                            <label class="col-12 small fw-bold">Reference Number
                                <input name="referenceNumber" id="adminEntranceReference" class="form-input mt-1" placeholder="Optional booking reference">
                            </label>
                            <label class="col-12 small fw-bold">Receipt
                                <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-input mt-1">
                            </label>
                            <label class="col-12 small fw-bold">Notes
                                <input name="notes" id="adminEntranceNotes" class="form-input mt-1" placeholder="Optional notes">
                            </label>
                        </div>
                        <div id="adminEntranceFeeMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary btn-sm">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
