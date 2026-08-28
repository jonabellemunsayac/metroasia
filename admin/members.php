<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data-privacy.php';
require_once __DIR__ . '/../includes/terms-conditions.php';
$admin = require_admin_menu('admin-members');
$canManageMembers = admin_can_manage_members($admin);
$canManageStaff = admin_can_manage_staff($admin);
$pageTitle = $canManageStaff ? 'Users / Members' : 'Members';
$active = 'admin-members';
$activePrivacyPolicy = data_privacy_active_policy();
$activeTermsPolicy = terms_conditions_active_policy();
include __DIR__ . '/../includes/header.php';
?>
<main data-needs-state class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker"><?php echo $canManageStaff ? 'Users / Members' : 'Members'; ?></span>
                <h2 class="mt-1 mb-1 fw-black">Access and account management</h2>
                <p class="mb-0 small text-secondary fw-semibold">
                    <?php echo $canManageStaff
                        ? 'Manage member profiles, QR lookup, entrance-fee payments, staff accounts, and permissions.'
                        : 'Manage member profiles, QR lookup, and entrance-fee payments.'; ?>
                </p>
            </div>
            <?php if ($canManageMembers): ?>
                <button id="adminAddMember" class="btn btn-primary btn-sm" type="button">
                    <i data-lucide="user-plus" class="icon-sm"></i>Add Member
                </button>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canManageStaff): ?>
        <section class="app-card mb-3">
            <ul class="nav nav-tabs" id="adminUsersMembersTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link active fw-bold"
                        id="members-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#members-pane"
                        type="button"
                        role="tab"
                        aria-controls="members-pane"
                        aria-selected="true"
                    >
                        <span class="d-inline-flex align-items-center gap-2">
                            <i data-lucide="users" class="icon-sm"></i>
                            Members
                        </span>
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link fw-bold"
                        id="staff-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#staff-pane"
                        type="button"
                        role="tab"
                        aria-controls="staff-pane"
                        aria-selected="false"
                    >
                        <span class="d-inline-flex align-items-center gap-2">
                            <i data-lucide="badge-check" class="icon-sm"></i>
                            Staff
                        </span>
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link fw-bold"
                        id="permissions-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#permissions-pane"
                        type="button"
                        role="tab"
                        aria-controls="permissions-pane"
                        aria-selected="false"
                    >
                        <span class="d-inline-flex align-items-center gap-2">
                            <i data-lucide="shield-check" class="icon-sm"></i>
                            Permission Assignment
                        </span>
                    </button>
                </li>

                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link fw-bold"
                        id="access-logs-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#access-logs-pane"
                        type="button"
                        role="tab"
                        aria-controls="access-logs-pane"
                        aria-selected="false"
                    >
                        <span class="d-inline-flex align-items-center gap-2">
                            <i data-lucide="history" class="icon-sm"></i>
                            Access Logs
                        </span>
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-3" id="adminUsersMembersTabContent">
                <div
                    class="tab-pane fade show active"
                    id="members-pane"
                    role="tabpanel"
                    aria-labelledby="members-tab"
                    tabindex="0"
                >
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <span class="section-kicker">Members</span>
                            <h2 class="mt-1 mb-0 fw-black">Registered member list</h2>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <input
                                id="adminMemberSearch"
                                class="form-input form-input-sm"
                                placeholder="Search name, nickname, phone, or email"
                            >
                            <button id="adminScanMemberQr" class="btn btn-outline-secondary btn-sm" type="button">
                                <i data-lucide="scan-line" class="icon-sm"></i>Scan QR
                            </button>
                        </div>
                    </div>

                    <div id="adminMembers"></div>
                    <div id="adminMembersPagination" class="mt-3"></div>
                </div>

                <div
                    class="tab-pane fade"
                    id="staff-pane"
                    role="tabpanel"
                    aria-labelledby="staff-tab"
                    tabindex="0"
                >
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <span class="section-kicker">Admin Users</span>
                            <h2 class="mt-1 mb-0 fw-black">Staff access</h2>
                        </div>
                        <span class="badge text-bg-primary">Admin only</span>
                    </div>

                    <div id="adminUsers" class="grid gap-3"></div>
                    <div id="adminUsersPagination" class="mt-3"></div>
                </div>

                <div
                    class="tab-pane fade"
                    id="permissions-pane"
                    role="tabpanel"
                    aria-labelledby="permissions-tab"
                    tabindex="0"
                >
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <span class="section-kicker">Role Access</span>
                            <h2 class="mt-1 mb-0 fw-black">Menu permissions</h2>
                        </div>
                        <p class="mb-0 small text-secondary fw-semibold">
                            Choose which admin menus each staff role can open.
                        </p>
                    </div>

                    <div id="adminRolePermissions"></div>
                </div>

                <div
                    class="tab-pane fade"
                    id="access-logs-pane"
                    role="tabpanel"
                    aria-labelledby="access-logs-tab"
                    tabindex="0"
                >
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <span class="section-kicker">Access History</span>
                            <h2 class="mt-1 mb-0 fw-black">Admin and member sessions</h2>
                        </div>

                        <input
                            id="adminAccessLogSearch"
                            class="form-input form-input-sm"
                            placeholder="Search name, email, role, event, IP, or session"
                        >
                    </div>

                    <div id="adminAccessLogs"></div>
                    <div id="adminAccessLogsPagination" class="mt-3"></div>
                </div>
            </div>
        </section>
    <?php else: ?>
        <section class="row g-3">
            <div class="col-12">
                <div class="app-card h-100">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <span class="section-kicker">Members</span>
                            <h2 class="mt-1 mb-0 fw-black">Registered member list</h2>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <input
                                id="adminMemberSearch"
                                class="form-input form-input-sm"
                                placeholder="Search name, nickname, phone, or email"
                            >
                            <button id="adminScanMemberQr" class="btn btn-outline-secondary btn-sm" type="button">
                                <i data-lucide="scan-line" class="icon-sm"></i>Scan QR
                            </button>
                        </div>
                    </div>
                    <div id="adminMembers"></div>
                    <div id="adminMembersPagination" class="mt-3"></div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div id="adminMemberModal" class="modal fade" tabindex="-1" aria-labelledby="adminMemberModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="adminMemberForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <div>
                            <span class="section-kicker">Member Profile</span>
                            <h2 id="adminMemberModalTitle" class="modal-title fw-black">Add Member</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body admin-player-account-form">
                        <input type="hidden" name="id" id="adminMemberId">
                        <div class="admin-player-form-head">
                            <div class="admin-player-icon"><i data-lucide="user" class="icon-sm"></i></div>
                            <div>
                                <h3>Create your player account</h3>
                                <p>This uses the same player registration flow: required nickname, email verification, password, and skill profile. Your nickname is the only name shown on queues and public boards.</p>
                            </div>
                        </div>

                        <div class="admin-player-form-grid">
                            <label class="admin-player-field admin-player-field-full">
                                <span>Full name *</span>
                                <input required name="name" id="adminMemberName" class="form-input" placeholder="Full name *">
                            </label>
                            <label class="admin-player-field admin-player-field-full">
                                <span>Nickname for queue/public display *</span>
                                <input required name="nickname" id="adminMemberNickname" class="form-input" placeholder="Nickname for queue/public display *">
                            </label>

                            <div class="admin-profile-upload admin-player-field-full">
                                <div id="adminMemberProfilePreview" class="admin-profile-preview">P</div>
                                <div>
                                    <strong>Profile picture</strong>
                                    <p>Shown with your nickname on live boards, leaderboards, global ranking, and operator player cards.</p>
                                    <label class="admin-upload-link">
                                        <i data-lucide="image-up" class="icon-xs"></i>
                                        <span>Upload</span>
                                        <input type="file" name="profilePicture" id="adminMemberProfilePicture" accept=".jpg,.jpeg,.png,.webp" hidden>
                                    </label>
                                </div>
                            </div>

                            <label class="admin-player-field admin-player-field-full">
                                <span>Email *</span>
                                <input required type="email" name="email" id="adminMemberEmail" class="form-input" placeholder="Email *">
                            </label>
                            <label class="admin-player-field admin-player-field-full">
                                <span>Phone *</span>
                                <input name="phone" id="adminMemberPhone" class="form-input" placeholder="Phone *">
                            </label>

                            <div class="admin-player-field">
                                <span>Birth Month *</span>
                                <select required name="birthMonth" id="adminMemberBirthMonth" class="form-select">
                                    <option value="">Month</option>
                                    <?php foreach (range(1, 12) as $month): ?>
                                        <option value="<?php echo $month; ?>"><?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="admin-player-field">
                                <span>Birth Year *</span>
                                <select required name="birthYear" id="adminMemberBirthYear" class="form-select">
                                    <option value="">Year</option>
                                    <?php for ($year = (int) date('Y'); $year >= 1900; $year--): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <p class="admin-player-help admin-player-field-full">Birth month/year are required for tournament age and category eligibility.</p>

                            <label class="admin-player-field admin-password-field admin-player-field-full">
                                <span>Create password</span>
                                <input type="password" name="password" id="adminMemberPassword" class="form-input" minlength="8" placeholder="Create password">
                                <button type="button" class="admin-password-toggle" data-password-toggle="adminMemberPassword" aria-label="Show password">
                                    <i data-lucide="eye" class="icon-sm"></i>
                                </button>
                            </label>
                            <label class="admin-player-field admin-password-field admin-player-field-full">
                                <span>Confirm password *</span>
                                <input type="password" name="confirmPassword" id="adminMemberConfirmPassword" class="form-input" minlength="8" placeholder="Confirm password *">
                                <button type="button" class="admin-password-toggle" data-password-toggle="adminMemberConfirmPassword" aria-label="Show password">
                                    <i data-lucide="eye" class="icon-sm"></i>
                                </button>
                            </label>
                            <p class="admin-player-help admin-player-field-full">Use at least 8 characters with one capital letter, one number, and one special character.</p>

                            <label class="admin-player-field admin-player-field-full">
                                <span>Self-Assessed Skill Level *</span>
                                <select required name="skillLevel" id="adminMemberSkillLevel" class="form-select">
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

                            <label class="admin-privacy-check admin-player-field-full">
                                <input required type="checkbox" name="termsConditionsAgree" id="adminMemberTermsAgree" value="1">
                                <span>
                                    I have read and agree to the
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-open-terms-conditions>Terms and Conditions</button>.
                                </span>
                            </label>

                            <label class="admin-privacy-check admin-player-field-full">
                                <input required type="checkbox" name="dataPrivacyActAgree" id="adminMemberPrivacyAgree" value="1">
                                <span>
                                    I have read and agree to the
                                    <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-open-privacy-policy>Data Privacy Policy</button>
                                    and consent to the processing of my personal data for MetroAsia Arena platform services.
                                </span>
                            </label>
                            <label class="admin-active-check admin-player-field-full">
                                <input type="checkbox" name="isActive" id="adminMemberIsActive" value="1" checked>
                                <span>Active member</span>
                            </label>
                        </div>
                        <div id="adminMemberFormMessage" class="hidden rounded-md p-2 text-xs font-bold mt-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary btn-sm admin-register-submit">Complete Registration</button>
                        <button type="button" class="btn btn-link btn-sm admin-register-cancel" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="termsConditionsModal" class="modal fade" tabindex="-1" aria-labelledby="termsConditionsTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="termsConditionsTitle" class="modal-title fw-black"><?php echo htmlspecialchars((string) $activeTermsPolicy['title']); ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body small fw-semibold text-secondary privacy-policy-content">
                    <?php echo (string) $activeTermsPolicy['contentHtml']; ?>
                    <p class="mt-3 mb-0 text-xs fw-bold text-muted">Version: <?php echo htmlspecialchars((string) $activeTermsPolicy['version']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div id="adminPrivacyPolicyModal" class="modal fade" tabindex="-1" aria-labelledby="adminPrivacyPolicyTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="adminPrivacyPolicyTitle" class="modal-title fw-black"><?php echo htmlspecialchars((string) $activePrivacyPolicy['title']); ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body small fw-semibold text-secondary privacy-policy-content">
                    <?php echo (string) $activePrivacyPolicy['contentHtml']; ?>
                    <p class="mt-3 mb-0 text-xs fw-bold text-muted">Version: <?php echo htmlspecialchars((string) $activePrivacyPolicy['version']); ?></p>
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

<style>
/* Permission Assignment: vertical permission list.
   Presentation only — existing forms, checkbox names, role values,
   save buttons, permissions logic, and app.js handlers remain unchanged. */

#permissions-pane #adminRolePermissions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    align-items: start;
}

#permissions-pane .admin-role-permission-card {
    display: block;
    margin: 0;
    padding: 18px 20px;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 12px;
    background: #fff;
}

#permissions-pane .admin-role-permission-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 14px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
}

#permissions-pane .admin-role-permission-head > div {
    min-width: 0;
}

#permissions-pane .admin-role-permission-head h3 {
    margin: 0 0 4px;
    font-size: 1rem;
    font-weight: 800;
}

#permissions-pane .admin-role-permission-head p {
    margin: 0;
    font-size: 0.78rem;
    line-height: 1.45;
    color: var(--bs-secondary-color, #6c757d);
}

#permissions-pane .admin-role-permission-head .btn {
    flex: 0 0 auto;
}

#permissions-pane .admin-role-menu-grid {
    display: flex;
    flex-direction: column;
    gap: 0;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 10px;
    overflow: hidden;
}

#permissions-pane .admin-role-menu-item {
    display: grid;
    grid-template-columns: 24px minmax(0, 1fr);
    align-items: start;
    gap: 10px;
    width: 100%;
    margin: 0;
    padding: 12px 14px;
    border: 0;
    border-bottom: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0;
    background: #fff;
    cursor: pointer;
}

#permissions-pane .admin-role-menu-item:last-child {
    border-bottom: 0;
}

#permissions-pane .admin-role-menu-item:hover {
    background: var(--bs-tertiary-bg, #f8f9fa);
}

#permissions-pane .admin-role-menu-item.is-locked {
    background: var(--bs-tertiary-bg, #f8f9fa);
    opacity: 0.75;
    cursor: default;
}

#permissions-pane .admin-role-menu-item input[type="checkbox"] {
    margin-top: 3px;
}

#permissions-pane .admin-role-menu-item > span {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

#permissions-pane .admin-role-menu-item strong {
    font-size: 0.84rem;
    line-height: 1.3;
}

#permissions-pane .admin-role-menu-item small {
    display: block;
    font-size: 0.7rem;
    line-height: 1.4;
    color: var(--bs-secondary-color, #6c757d);
}

#permissions-pane [data-admin-role-permission-message] {
    margin-top: 12px !important;
}

@media (max-width: 991.98px) {
    #permissions-pane #adminRolePermissions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    #permissions-pane #adminRolePermissions {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 575.98px) {
    #permissions-pane .admin-role-permission-card {
        padding: 14px;
    }

    #permissions-pane .admin-role-permission-head {
        flex-direction: column;
    }
}
</style>

<script>
(() => {
    'use strict';

    const PAGE_SIZE = 10;

    function createTablePaginator(containerId, paginationId) {
        const container = document.getElementById(containerId);
        const pagination = document.getElementById(paginationId);

        if (!container || !pagination) return;

        let currentPage = 1;
        let rendering = false;

        const getRows = () =>
            Array.from(container.querySelectorAll('table tbody tr'));

        const renderPagination = () => {
            if (rendering) return;
            rendering = true;

            const rows = getRows();
            const totalItems = rows.length;
            const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            if (currentPage < 1) {
                currentPage = 1;
            }

            const start = (currentPage - 1) * PAGE_SIZE;
            const end = start + PAGE_SIZE;

            rows.forEach((row, index) => {
                row.style.display = index >= start && index < end ? '' : 'none';
            });

            if (totalItems <= PAGE_SIZE) {
                pagination.innerHTML = '';
                rendering = false;
                return;
            }

            const firstItem = start + 1;
            const lastItem = Math.min(end, totalItems);

            const pageButtons = [];

            const addPageButton = page => {
                pageButtons.push(`
                    <li class="page-item ${page === currentPage ? 'active' : ''}">
                        <button
                            class="page-link"
                            type="button"
                            data-page="${page}"
                            ${page === currentPage ? 'aria-current="page"' : ''}
                        >
                            ${page}
                        </button>
                    </li>
                `);
            };

            // Keep the pagination compact when there are many pages.
            const visiblePages = new Set([
                1,
                totalPages,
                currentPage - 1,
                currentPage,
                currentPage + 1
            ]);

            let previousPage = 0;

            [...visiblePages]
                .filter(page => page >= 1 && page <= totalPages)
                .sort((a, b) => a - b)
                .forEach(page => {
                    if (previousPage && page - previousPage > 1) {
                        pageButtons.push(`
                            <li class="page-item disabled" aria-hidden="true">
                                <span class="page-link">…</span>
                            </li>
                        `);
                    }

                    addPageButton(page);
                    previousPage = page;
                });

            pagination.innerHTML = `
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="small text-secondary fw-semibold">
                        Showing ${firstItem}-${lastItem} of ${totalItems}
                    </div>

                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                                <button
                                    class="page-link"
                                    type="button"
                                    data-page="${currentPage - 1}"
                                    aria-label="Previous page"
                                    ${currentPage === 1 ? 'disabled' : ''}
                                >
                                    Previous
                                </button>
                            </li>

                            ${pageButtons.join('')}

                            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                                <button
                                    class="page-link"
                                    type="button"
                                    data-page="${currentPage + 1}"
                                    aria-label="Next page"
                                    ${currentPage === totalPages ? 'disabled' : ''}
                                >
                                    Next
                                </button>
                            </li>
                        </ul>
                    </nav>
                </div>
            `;

            rendering = false;
        };

        pagination.addEventListener('click', event => {
            const button = event.target.closest('[data-page]');
            if (!button || button.disabled) return;

            const requestedPage = Number(button.dataset.page);
            if (!Number.isInteger(requestedPage) || requestedPage < 1) return;

            currentPage = requestedPage;
            renderPagination();

            container.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        });

        const observer = new MutationObserver(() => {
            if (rendering) return;

            // app.js replaces table markup after search/save operations.
            // Reset to page one whenever the underlying rendered list changes.
            currentPage = 1;
            window.requestAnimationFrame(renderPagination);
        });

        observer.observe(container, {
            childList: true,
            subtree: true
        });

        renderPagination();
    }

    function initAdminUserPagination() {
        createTablePaginator(
            'adminMembers',
            'adminMembersPagination'
        );

        createTablePaginator(
            'adminUsers',
            'adminUsersPagination'
        );

        createTablePaginator(
            'adminAccessLogs',
            'adminAccessLogsPagination'
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initAdminUserPagination,
            { once: true }
        );
    } else {
        initAdminUserPagination();
    }
})();
</script>

</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
