<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$member = require_member();
$pdo = db();
$message = null;
$error = null;
$memberUploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'members';
if (!is_dir($memberUploadDir)) {
    mkdir($memberUploadDir, 0775, true);
}

function member_profile_upload(string $uploadDir): ?string
{
    if (!isset($_FILES['profilePicture']) || $_FILES['profilePicture']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['profilePicture']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Profile picture upload failed.');
    }
    if ($_FILES['profilePicture']['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('Profile picture must be 4MB or smaller.');
    }

    $tmp = $_FILES['profilePicture']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, or WEBP profile picture.');
    }

    $filename = 'member-profile-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Could not save profile picture.');
    }

    return 'uploads/members/' . $filename;
}

function member_profile_display_email(?string $email): string
{
    $email = trim((string) $email);
    if ($email === '' || str_ends_with($email, '@no-email.metroasia.local')) {
        return 'Not set';
    }
    return $email;
}

function member_profile_skill_label(?string $level): string
{
    return [
        '2.0' => '2.0 - Just starting out',
        '2.5' => '2.5 - Learning basic shots & rules',
        '3.0' => '3.0 - Consistent rallies, knows strategy',
        '3.5' => '3.5 - Solid all-court game',
        '4.0' => '4.0 - Advanced placement & strategy',
        '4.5' => '4.5 - Competitive tournament player',
        '5.0' => '5.0+ - Elite / pro level',
    ][$level ?? ''] ?? 'Not set';
}

function member_profile_fetch(PDO $pdo, int $memberId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, nickname, email, phone, birth_month, birth_year, skill_level,
                member_lookup_token, profile_picture_path
         FROM members
         WHERE id = ? AND is_active = 1'
    );
    $stmt->execute([$memberId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        redirect_to('admin/logout.php?as=member');
    }

    return $profile;
}

$memberProfile = member_profile_fetch($pdo, (int) $member['id']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $name = trim((string) ($_POST['name'] ?? ''));
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $birthMonth = (int) ($_POST['birthMonth'] ?? 0);
        $birthYear = (int) ($_POST['birthYear'] ?? 0);
        $skillLevel = trim((string) ($_POST['skillLevel'] ?? ''));
        $currentYear = (int) date('Y');

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Use a valid email address.');
        }
        if ($email !== '' && !email_available_for_member($email, (int) $member['id'])) {
            throw new RuntimeException('That email is already used by another account.');
        }
        if ($phone !== '' && !is_valid_phone_number($phone)) {
            throw new RuntimeException(phone_validation_message());
        }
        if ($birthMonth !== 0 && ($birthMonth < 1 || $birthMonth > 12)) {
            throw new RuntimeException('Choose a valid birth month.');
        }
        if ($birthYear !== 0 && ($birthYear < 1900 || $birthYear > $currentYear)) {
            throw new RuntimeException('Choose a valid birth year.');
        }
        if ($skillLevel !== '' && !in_array($skillLevel, ['2.0', '2.5', '3.0', '3.5', '4.0', '4.5', '5.0'], true)) {
            throw new RuntimeException('Choose a valid skill level.');
        }

        $profilePicture = member_profile_upload($memberUploadDir);
        $storedName = $name !== '' ? $name : ((string) ($memberProfile['name'] ?? '') ?: 'Player');
        $storedNickname = $nickname !== '' ? $nickname : $storedName;
        $storedEmail = $email !== '' ? $email : (string) $memberProfile['email'];
        $storedPhone = $phone !== '' ? normalize_phone_number($phone) : '';
        $storedPicture = $profilePicture ?? ($memberProfile['profile_picture_path'] ?? null);

        $stmt = $pdo->prepare(
            'UPDATE members
             SET name = ?, nickname = ?, email = ?, phone = ?, profile_picture_path = ?,
                 birth_month = ?, birth_year = ?, skill_level = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $storedName,
            $storedNickname,
            $storedEmail,
            $storedPhone,
            $storedPicture,
            $birthMonth > 0 ? $birthMonth : null,
            $birthYear > 0 ? $birthYear : null,
            $skillLevel !== '' ? $skillLevel : null,
            (int) $member['id'],
        ]);

        $message = 'Profile updated successfully.';
        $memberProfile = member_profile_fetch($pdo, (int) $member['id']);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$monthName = !empty($memberProfile['birth_month']) ? date('F', mktime(0, 0, 0, (int) $memberProfile['birth_month'], 1)) : '';
$displayEmail = member_profile_display_email($memberProfile['email'] ?? '');
$displayBirth = trim($monthName . ' ' . (string) ($memberProfile['birth_year'] ?? '')) ?: 'Not set';
$profileInitial = strtoupper(substr((string) ($memberProfile['nickname'] ?: $memberProfile['name'] ?: 'M'), 0, 1));
$formEmailValue = str_ends_with((string) ($memberProfile['email'] ?? ''), '@no-email.metroasia.local') ? '' : (string) $memberProfile['email'];

$pageTitle = 'Member Profile';
$active = 'member-profile';
$memberAccountStyles = true;
include __DIR__ . '/../includes/header.php';
?>
<main class="public-page member-dashboard-page member-profile-page">
    <?php if ($message): ?>
        <div class="member-dashboard-alert is-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="member-dashboard-alert is-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="member-profile-layout">
        <aside class="member-profile-card member-profile-card-page public-card">
            <p class="member-kicker">Member Profile</p>
            <div class="member-profile-avatar">
                <?php if (!empty($memberProfile['profile_picture_path'])): ?>
                    <img src="<?php echo htmlspecialchars(app_url((string) $memberProfile['profile_picture_path'])); ?>" alt="">
                <?php else: ?>
                    <?php echo htmlspecialchars($profileInitial); ?>
                <?php endif; ?>
            </div>
            <h1><?php echo htmlspecialchars((string) $memberProfile['name']); ?></h1>
            <p><?php echo htmlspecialchars((string) ($memberProfile['nickname'] ?: 'No nickname set')); ?></p>

            <dl>
                <div><dt>Email</dt><dd><?php echo htmlspecialchars($displayEmail); ?></dd></div>
                <div><dt>Phone</dt><dd><?php echo htmlspecialchars((string) ($memberProfile['phone'] ?: 'Not set')); ?></dd></div>
                <div><dt>Birth</dt><dd><?php echo htmlspecialchars($displayBirth); ?></dd></div>
                <div><dt>Skill Level</dt><dd><?php echo htmlspecialchars(member_profile_skill_label($memberProfile['skill_level'] ?? null)); ?></dd></div>
            </dl>

            <?php if (!empty($memberProfile['member_lookup_token'])): ?>
                <div class="member-profile-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=132x132&data=<?php echo rawurlencode('member=' . (string) $memberProfile['member_lookup_token']); ?>" alt="Member QR code">
                    <span>Member QR</span>
                </div>
            <?php endif; ?>
        </aside>

        <form method="post" enctype="multipart/form-data" class="public-card member-profile-edit-card">
            <div class="member-section-head">
                <div>
                    <p class="member-kicker">Edit Profile</p>
                    <h2>Account Details</h2>
                </div>
            </div>

            <div class="admin-player-account-form public-member-account-form member-profile-form">
                <div class="admin-player-form-grid">
                    <label class="admin-player-field public-member-span-6">
                        <span>Full name</span>
                        <input name="name" class="form-input" placeholder="Full name" autocomplete="name" value="<?php echo htmlspecialchars((string) ($_POST['name'] ?? $memberProfile['name'])); ?>">
                    </label>
                    <label class="admin-player-field public-member-span-6">
                        <span>Nickname for public display</span>
                        <input name="nickname" class="form-input" placeholder="Nickname" value="<?php echo htmlspecialchars((string) ($_POST['nickname'] ?? $memberProfile['nickname'])); ?>">
                    </label>

                    <div class="admin-profile-upload admin-player-field-full">
                        <div id="adminMemberProfilePreview" class="admin-profile-preview">
                            <?php if (!empty($memberProfile['profile_picture_path'])): ?>
                                <img src="<?php echo htmlspecialchars(app_url((string) $memberProfile['profile_picture_path'])); ?>" alt="">
                            <?php else: ?>
                                <?php echo htmlspecialchars($profileInitial); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong>Profile picture</strong>
                            <p>Shown with your nickname on booking boards and member records.</p>
                            <label class="admin-upload-link">
                                <i data-lucide="image-up" class="icon-xs"></i>
                                <span>Upload</span>
                                <input type="file" name="profilePicture" id="adminMemberProfilePicture" accept=".jpg,.jpeg,.png,.webp" hidden>
                            </label>
                        </div>
                    </div>

                    <label class="admin-player-field public-member-span-6">
                        <span>Email</span>
                        <input type="email" name="email" class="form-input" placeholder="Email" autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? $formEmailValue)); ?>">
                    </label>
                    <label class="admin-player-field public-member-span-6">
                        <span>Phone</span>
                        <input name="phone" class="form-input" placeholder="Phone" autocomplete="tel" value="<?php echo htmlspecialchars((string) ($_POST['phone'] ?? $memberProfile['phone'])); ?>">
                    </label>

                    <div class="admin-player-field">
                        <span>Birth Month</span>
                        <select name="birthMonth" class="form-select">
                            <option value="">Month</option>
                            <?php foreach (range(1, 12) as $month): ?>
                                <?php $selectedMonth = (int) ($_POST['birthMonth'] ?? ($memberProfile['birth_month'] ?? 0)); ?>
                                <option value="<?php echo $month; ?>" <?php echo $selectedMonth === $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-player-field">
                        <span>Birth Year</span>
                        <select name="birthYear" class="form-select">
                            <option value="">Year</option>
                            <?php for ($year = (int) date('Y'); $year >= 1900; $year--): ?>
                                <?php $selectedYear = (int) ($_POST['birthYear'] ?? ($memberProfile['birth_year'] ?? 0)); ?>
                                <option value="<?php echo $year; ?>" <?php echo $selectedYear === $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <label class="admin-player-field admin-player-field-full">
                        <span>Self-Assessed Skill Level</span>
                        <select name="skillLevel" class="form-select">
                            <?php $selectedSkill = (string) ($_POST['skillLevel'] ?? ($memberProfile['skill_level'] ?? '')); ?>
                            <option value="">Select a level...</option>
                            <option value="2.0" <?php echo $selectedSkill === '2.0' ? 'selected' : ''; ?>>2.0 - Just starting out</option>
                            <option value="2.5" <?php echo $selectedSkill === '2.5' ? 'selected' : ''; ?>>2.5 - Learning basic shots &amp; rules</option>
                            <option value="3.0" <?php echo $selectedSkill === '3.0' ? 'selected' : ''; ?>>3.0 - Consistent rallies, knows strategy</option>
                            <option value="3.5" <?php echo $selectedSkill === '3.5' ? 'selected' : ''; ?>>3.5 - Solid all-court game</option>
                            <option value="4.0" <?php echo $selectedSkill === '4.0' ? 'selected' : ''; ?>>4.0 - Advanced placement &amp; strategy</option>
                            <option value="4.5" <?php echo $selectedSkill === '4.5' ? 'selected' : ''; ?>>4.5 - Competitive tournament player</option>
                            <option value="5.0" <?php echo $selectedSkill === '5.0' ? 'selected' : ''; ?>>5.0+ - Elite / pro level</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="member-profile-actions">
                <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
                <a href="<?php echo htmlspecialchars(app_url('ui/member.php')); ?>" class="btn btn-outline-secondary btn-sm">Back to Bookings</a>
            </div>
        </form>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
