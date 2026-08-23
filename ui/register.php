<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data-privacy.php';

$error = null;
$activePrivacyPolicy = data_privacy_active_policy();
$memberUploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'members';
if (!is_dir($memberUploadDir)) {
    mkdir($memberUploadDir, 0775, true);
}

if (current_member() !== null) {
    redirect_to('ui/member.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $nickname = trim((string) ($_POST['nickname'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $birthMonth = (int) ($_POST['birthMonth'] ?? 0);
    $birthYear = (int) ($_POST['birthYear'] ?? 0);
    $skillLevel = trim((string) ($_POST['skillLevel'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');
    $privacyAgree = isset($_POST['dataPrivacyActAgree']) && $_POST['dataPrivacyActAgree'] === '1';
    $currentYear = (int) date('Y');

    if ($name === '') {
        $error = 'Full name is required.';
    } elseif ($nickname === '') {
        $error = 'Nickname is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Use a valid email address.';
    } elseif ($phone === '') {
        $error = 'Phone number is required.';
    } elseif ($birthMonth < 1 || $birthMonth > 12) {
        $error = 'Choose a valid birth month.';
    } elseif ($birthYear < 1900 || $birthYear > $currentYear) {
        $error = 'Choose a valid birth year.';
    } elseif (!in_array($skillLevel, ['2.0', '2.5', '3.0', '3.5', '4.0', '4.5', '5.0'], true)) {
        $error = 'Choose a valid skill level.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } elseif (!$privacyAgree) {
        $error = 'Data Privacy Policy consent is required.';
    } else {
        try {
            $pdo = db();
            $profilePicture = public_member_profile_picture($memberUploadDir, $error);
            if ($error === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO members
                     (name, nickname, email, phone, profile_picture_path, birth_month, birth_year, skill_level,
                      data_privacy_act_agree, data_privacy_policy_version, data_privacy_agreed_at,
                      member_lookup_token, password_hash, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, ?, 1)'
                );
                $stmt->execute([
                    $name,
                    $nickname,
                    $email,
                    $phone,
                    $profilePicture,
                    $birthMonth,
                    $birthYear,
                    $skillLevel,
                    (string) ($activePrivacyPolicy['version'] ?? 'default'),
                    public_member_lookup_token(),
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                session_regenerate_id(true);
                unset($_SESSION['admin_id']);
                $_SESSION['member_id'] = (int) $pdo->lastInsertId();
                redirect_to('ui/member.php');
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
            <div class="admin-player-account-form public-member-account-form">
                <div class="admin-player-form-head">
                    <div class="admin-player-icon"><i data-lucide="user" class="icon-sm"></i></div>
                    <div>
                        <h3>Create your player account</h3>
                        <p>This uses the same player registration flow: required nickname, email verification, password, and skill profile. Your nickname is the only name shown on queues and public boards.</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="mb-3 rounded-lg bg-rose-50 p-3 text-sm font-bold text-rose-700"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="admin-player-form-grid">
                    <label class="admin-player-field public-member-span-6">
                        <span>Full name *</span>
                        <input required name="name" class="form-input" placeholder="Full name *" autocomplete="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? '')); ?>">
                    </label>
                    <label class="admin-player-field public-member-span-6">
                        <span>Nickname for queue/public display *</span>
                        <input required name="nickname" class="form-input" placeholder="Nickname for queue/public display *" value="<?php echo htmlspecialchars((string) ($_POST['nickname'] ?? '')); ?>">
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
                        <span>Email *</span>
                        <input required type="email" name="email" class="form-input" placeholder="Email *" autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? '')); ?>">
                    </label>
                    <label class="admin-player-field public-member-span-6">
                        <span>Phone *</span>
                        <input required name="phone" class="form-input" placeholder="Phone *" autocomplete="tel" value="<?php echo htmlspecialchars((string) ($_POST['phone'] ?? '')); ?>">
                    </label>

                    <div class="admin-player-field">
                        <span>Birth Month *</span>
                        <select required name="birthMonth" class="form-select">
                            <option value="">Month</option>
                            <?php foreach (range(1, 12) as $month): ?>
                                <option value="<?php echo $month; ?>" <?php echo (int) ($_POST['birthMonth'] ?? 0) === $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-player-field">
                        <span>Birth Year *</span>
                        <select required name="birthYear" class="form-select">
                            <option value="">Year</option>
                            <?php for ($year = (int) date('Y'); $year >= 1900; $year--): ?>
                                <option value="<?php echo $year; ?>" <?php echo (int) ($_POST['birthYear'] ?? 0) === $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <p class="admin-player-help admin-player-field-full">Birth month/year are required for tournament age and category eligibility.</p>

                    <label class="admin-player-field admin-password-field public-member-span-6">
                        <span>Create password</span>
                        <input type="password" name="password" id="publicMemberPassword" class="form-input" minlength="8" placeholder="Create password" autocomplete="new-password">
                        <button type="button" class="admin-password-toggle" data-password-toggle="publicMemberPassword" aria-label="Show password">
                            <i data-lucide="eye" class="icon-sm"></i>
                        </button>
                    </label>
                    <label class="admin-player-field admin-password-field public-member-span-6">
                        <span>Confirm password *</span>
                        <input type="password" name="confirmPassword" id="publicMemberConfirmPassword" class="form-input" minlength="8" placeholder="Confirm password *" autocomplete="new-password">
                        <button type="button" class="admin-password-toggle" data-password-toggle="publicMemberConfirmPassword" aria-label="Show password">
                            <i data-lucide="eye" class="icon-sm"></i>
                        </button>
                    </label>
                    <p class="admin-player-help admin-player-field-full">Use at least 8 characters with one capital letter, one number, and one special character.</p>

                    <label class="admin-player-field admin-player-field-full">
                        <span>Self-Assessed Skill Level *</span>
                        <select required name="skillLevel" class="form-select">
                            <option value="">Select a level...</option>
                            <?php
                            $skillOptions = [
                                '2.0' => '2.0 - Just starting out',
                                '2.5' => '2.5 - Learning basic shots & rules',
                                '3.0' => '3.0 - Consistent rallies, knows strategy',
                                '3.5' => '3.5 - Solid all-court game',
                                '4.0' => '4.0 - Advanced placement & strategy',
                                '4.5' => '4.5 - Competitive tournament player',
                                '5.0' => '5.0+ - Elite / pro level',
                            ];
                            foreach ($skillOptions as $value => $label):
                            ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (string) ($_POST['skillLevel'] ?? '') === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="admin-privacy-check admin-player-field-full">
                        <input required type="checkbox" name="dataPrivacyActAgree" value="1" <?php echo isset($_POST['dataPrivacyActAgree']) ? 'checked' : ''; ?>>
                        <span>
                            I have read and agree to the
                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-open-privacy-policy>Data Privacy Policy</button>
                            and consent to the processing of my personal data for MetroAsia Arena platform services.
                        </span>
                    </label>
                </div>
            </div>
            <div class="public-member-account-actions">
                <button type="submit" class="btn btn-primary btn-sm admin-register-submit">Complete Registration</button>
                <a href="<?php echo htmlspecialchars(app_url('ui/member-login.php')); ?>" class="admin-register-cancel text-center text-decoration-none">Already have an account? Log in</a>
            </div>
        </form>
    </section>

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
