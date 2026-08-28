<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data-privacy.php';
require_once __DIR__ . '/../includes/terms-conditions.php';

$error = null;
$memberRedirect = member_redirect_target('ui/member.php');
$activePrivacyPolicy = data_privacy_active_policy();
$activeTermsPolicy = terms_conditions_active_policy();
$memberUploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'members';
if (!is_dir($memberUploadDir)) {
    mkdir($memberUploadDir, 0775, true);
}

if (current_member() !== null) {
    redirect_to($memberRedirect);
}

function public_member_profile_picture(string $uploadDir, ?string &$error): ?string
{
    if (!isset($_FILES['profilePicture']) || $_FILES['profilePicture']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['profilePicture']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Profile picture upload failed.';
        return null;
    }
    if ($_FILES['profilePicture']['size'] > 4 * 1024 * 1024) {
        $error = 'Profile picture must be 4MB or smaller.';
        return null;
    }

    $tmp = $_FILES['profilePicture']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        $error = 'Use a JPG, PNG, or WEBP profile picture.';
        return null;
    }

    $filename = 'member-profile-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
        $error = 'Could not save profile picture.';
        return null;
    }

    return 'uploads/members/' . $filename;
}

function public_member_lookup_token(): string
{
    return 'mem_' . bin2hex(random_bytes(16));
}

function public_register_column_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['members', $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function public_register_ensure_consent_columns(PDO $pdo): void
{
    $columns = [
        'terms_conditions_agree' => 'ALTER TABLE members ADD terms_conditions_agree TINYINT(1) NOT NULL DEFAULT 0 AFTER skill_level',
        'terms_agreed_at' => 'ALTER TABLE members ADD terms_agreed_at DATETIME NULL AFTER terms_conditions_agree',
        'marketing_consent' => 'ALTER TABLE members ADD marketing_consent TINYINT(1) NOT NULL DEFAULT 0 AFTER data_privacy_agreed_at',
    ];

    foreach ($columns as $column => $sql) {
        if (!public_register_column_exists($pdo, $column)) {
            $pdo->exec($sql);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $nickname = trim((string) ($_POST['nickname'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $birthMonth = (int) ($_POST['birthMonth'] ?? 0);
    $birthYear = (int) ($_POST['birthYear'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');
    $termsAgree = isset($_POST['termsConditionsAgree']) && $_POST['termsConditionsAgree'] === '1';
    $privacyAgree = isset($_POST['dataPrivacyActAgree']) && $_POST['dataPrivacyActAgree'] === '1';
    $marketingConsent = isset($_POST['marketingConsent']) && $_POST['marketingConsent'] === '1';
    $currentYear = (int) date('Y');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Use a valid email address.';
    } elseif ($phone !== '' && !is_valid_phone_number($phone)) {
        $error = phone_validation_message();
    } elseif ($birthMonth !== 0 && ($birthMonth < 1 || $birthMonth > 12)) {
        $error = 'Choose a valid birth month.';
    } elseif ($birthYear !== 0 && ($birthYear < 1900 || $birthYear > $currentYear)) {
        $error = 'Choose a valid birth year.';
    } elseif ($password !== '' && !is_strong_password($password)) {
        $error = strong_password_message();
    } elseif (($password !== '' || $confirmPassword !== '') && $password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } elseif (!$termsAgree) {
        $error = 'Terms and Conditions agreement is required.';
    } elseif (!$privacyAgree) {
        $error = 'Privacy Policy and Privacy Notice acknowledgement is required.';
    } else {
        try {
            $pdo = db();
            public_register_ensure_consent_columns($pdo);
            if ($email !== '' && !email_available_for_member($email)) {
                $error = 'That email is already used by another account.';
            }
            $profilePicture = public_member_profile_picture($memberUploadDir, $error);
            if ($error === null) {
                $lookupToken = public_member_lookup_token();
                $storedName = $name !== '' ? $name : 'Player';
                $storedNickname = $nickname !== '' ? $nickname : $storedName;
                $storedEmail = $email !== '' ? $email : $lookupToken . '@no-email.metroasia.local';
                $storedPhone = $phone !== '' ? normalize_phone_number($phone) : '';
                $storedPassword = $password !== '' ? $password : bin2hex(random_bytes(16));
                $privacyVersion = $privacyAgree ? (string) ($activePrivacyPolicy['version'] ?? 'default') : null;
                $privacyAgreedAt = $privacyAgree ? date('Y-m-d H:i:s') : null;
                $stmt = $pdo->prepare(
                    'INSERT INTO members
                     (name, nickname, email, phone, profile_picture_path, birth_month, birth_year, skill_level,
                      terms_conditions_agree, terms_agreed_at,
                      data_privacy_act_agree, data_privacy_policy_version, data_privacy_agreed_at, marketing_consent,
                      member_lookup_token, password_hash, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
                );
                $stmt->execute([
                    $storedName,
                    $storedNickname,
                    $storedEmail,
                    $storedPhone,
                    $profilePicture,
                    $birthMonth > 0 ? $birthMonth : null,
                    $birthYear > 0 ? $birthYear : null,
                    1,
                    date('Y-m-d H:i:s'),
                    $privacyAgree ? 1 : 0,
                    $privacyVersion,
                    $privacyAgreedAt,
                    $marketingConsent ? 1 : 0,
                    $lookupToken,
                    password_hash($storedPassword, PASSWORD_DEFAULT),
                ]);
                session_regenerate_id(true);
                unset($_SESSION['admin_id']);
                $_SESSION['member_id'] = (int) $pdo->lastInsertId();
                start_access_session('member', (int) $_SESSION['member_id'], 'member', 'registration');
                redirect_to($memberRedirect);
            }
        } catch (PDOException $exception) {
            $error = str_contains($exception->getMessage(), 'Duplicate') ? 'That email is already registered.' : 'Could not create member account.';
        } catch (Throwable) {
            $error = 'Database is not ready. Open setup.php first.';
        }
    }
}

$pageTitle = 'Become a Member';
$active = 'member';
$memberAccountStyles = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page public-member-account-page">
    <section class="mx-auto public-member-account-shell">
        <form method="post" enctype="multipart/form-data" class="public-card public-member-account-card">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($memberRedirect); ?>">
            <div class="admin-player-account-form public-member-account-form">
                <div class="admin-player-form-head">
                    <div class="admin-player-icon"><i data-lucide="user" class="icon-sm"></i></div>
                    <div>
                        <h3>Create your player account</h3>
                        <p>Create a player profile for bookings and member activity. Add only the details you want to provide now.</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="mb-3 rounded-lg bg-rose-50 p-3 text-sm font-bold text-rose-700"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="admin-player-form-grid">
                    <label class="admin-player-field public-member-span-6">
                        <span>Full name</span>
                        <input name="name" class="form-input" placeholder="Full name" autocomplete="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? '')); ?>">
                    </label>
                    <label class="admin-player-field public-member-span-6">
                        <span>Nickname for queue/public display</span>
                        <input name="nickname" class="form-input" placeholder="Nickname for queue/public display" value="<?php echo htmlspecialchars((string) ($_POST['nickname'] ?? '')); ?>">
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

                    <label class="admin-player-field public-member-span-6">
                        <span>Email</span>
                        <input type="email" name="email" class="form-input" placeholder="Email" autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? '')); ?>">
                    </label>
                    <label class="admin-player-field public-member-span-6">
                        <span>Phone</span>
                        <input name="phone" class="form-input" placeholder="Phone" autocomplete="tel" value="<?php echo htmlspecialchars((string) ($_POST['phone'] ?? '')); ?>">
                    </label>

                    <div class="admin-player-field">
                        <span>Birth Month</span>
                        <select name="birthMonth" class="form-select">
                            <option value="">Month</option>
                            <?php foreach (range(1, 12) as $month): ?>
                                <option value="<?php echo $month; ?>" <?php echo (int) ($_POST['birthMonth'] ?? 0) === $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-player-field">
                        <span>Birth Year</span>
                        <select name="birthYear" class="form-select">
                            <option value="">Year</option>
                            <?php for ($year = (int) date('Y'); $year >= 1900; $year--): ?>
                                <option value="<?php echo $year; ?>" <?php echo (int) ($_POST['birthYear'] ?? 0) === $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <p class="admin-player-help admin-player-field-full">Birth month/year can help with tournament age and category eligibility.</p>

                    <label class="admin-player-field admin-password-field public-member-span-6">
                        <span>Create password</span>
                        <input type="password" name="password" id="publicMemberPassword" class="form-input" minlength="8" placeholder="Create password" autocomplete="new-password">
                        <button type="button" class="admin-password-toggle" data-password-toggle="publicMemberPassword" aria-label="Show password">
                            <i data-lucide="eye" class="icon-sm"></i>
                        </button>
                    </label>
                    <label class="admin-player-field admin-password-field public-member-span-6">
                        <span>Confirm password</span>
                        <input type="password" name="confirmPassword" id="publicMemberConfirmPassword" class="form-input" minlength="8" placeholder="Confirm password" autocomplete="new-password">
                        <button type="button" class="admin-password-toggle" data-password-toggle="publicMemberConfirmPassword" aria-label="Show password">
                            <i data-lucide="eye" class="icon-sm"></i>
                        </button>
                    </label>
                    <div class="admin-player-help admin-player-field-full">
                        <strong>Choose a Strong Password</strong>
                        <p class="mb-0">Password must be at least 8 characters and contain a combination of letters and numbers.</p>
                    </div>

                    <label class="admin-privacy-check admin-player-field-full">
                        <input required type="checkbox" name="termsConditionsAgree" value="1" <?php echo isset($_POST['termsConditionsAgree']) ? 'checked' : ''; ?>>
                        <span>
                            I have read and agree to the
                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-open-terms-conditions>Terms and Conditions</button>.
                        </span>
                    </label>

                    <label class="admin-privacy-check admin-player-field-full">
                        <input required type="checkbox" name="dataPrivacyActAgree" value="1" <?php echo isset($_POST['dataPrivacyActAgree']) ? 'checked' : ''; ?>>
                        <span>
                            I acknowledge that I have read and understood the
                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-open-privacy-policy>Privacy Policy and Privacy Notice</button>.
                        </span>
                    </label>

                    <div class="admin-player-help admin-player-field-full">
                        <strong>Optional marketing consent</strong>
                    </div>

                    <label class="admin-privacy-check admin-player-field-full">
                        <input type="checkbox" name="marketingConsent" value="1" <?php echo isset($_POST['marketingConsent']) ? 'checked' : ''; ?>>
                        <span>Yes, I would like to receive updates, promotions, events, tournaments, and special offers from MetroAsia. I understand that I can opt out at any time.</span>
                    </label>
                </div>
            </div>
            <div class="public-member-account-actions">
                <button type="submit" class="btn btn-primary btn-sm admin-register-submit">Complete Registration</button>
                <a href="<?php echo htmlspecialchars(app_url('ui/member-login.php?redirect=' . rawurlencode($memberRedirect))); ?>" class="admin-register-cancel text-center text-decoration-none">Already have an account? Log in</a>
            </div>
        </form>
    </section>

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
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
