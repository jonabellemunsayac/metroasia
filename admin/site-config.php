<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/site-config.php';

$admin = require_admin_menu('admin-site-config');
$pageTitle = 'Site Config';
$active = 'admin-site-config';
$message = null;
$error = null;
$pdo = db();
site_config_ensure_gallery_table($pdo);

function admin_normalize_contact_phone(?string $phone): string
{
    return preg_replace('/[^\d+]/', '', trim((string) $phone)) ?? '';
}

function admin_is_valid_contact_phone(?string $phone): bool
{
    $normalized = normalize_phone_number($phone);
    $raw = admin_normalize_contact_phone($phone);

    return $normalized !== ''
        && (
            preg_match('/^09\d{9}$/', $normalized) === 1
            || preg_match('/^0\d{7,10}$/', $normalized) === 1
            || preg_match('/^\+63\d{8,11}$/', $raw) === 1
            || preg_match('/^\d{7,8}$/', $normalized) === 1
        );
}

function admin_contact_phone_validation_message(): string
{
    return 'Use a valid mobile or landline number, e.g. 0917 123 4567, (02) 8123 4567, or +63 2 8123 4567.';
}

$fieldMeta = [
    'venue_name' => ['Venue Name', 'text'],
    'address' => ['Address', 'text'],
    'contact_phone' => ['Contact Phone', 'text'],
    'contact_email' => ['Contact Email', 'text'],
    'messenger_url' => ['Facebook Messenger Link', 'url'],
    'map_embed_url' => ['Contact Map Embed Link', 'url'],
    'hero_image_path' => ['Home Hero Image Path', 'text'],
    'about_main_image_path' => ['About Main Image Path', 'text'],
    'about_small_image_path' => ['About Small Image Path', 'text'],
    'contact_image_path' => ['Contact Image Path', 'text'],
    'booking_max_date' => ['Booking Max Date', 'date'],
];

function admin_home_service_image_cards(): array
{
    return [
        1 => [
            'key' => 'service_1_image_path',
            'label' => 'Open Play',
            'alt' => 'Open play',
            'slug' => 'open-play',
        ],
        2 => [
            'key' => 'service_2_image_path',
            'label' => 'Group Games',
            'alt' => 'Group games',
            'slug' => 'group-games',
        ],
        3 => [
            'key' => 'service_3_image_path',
            'label' => 'Multi-Sport Play',
            'alt' => 'Multi-sport play',
            'slug' => 'multi-sport-play',
        ],
        4 => [
            'key' => 'service_4_image_path',
            'label' => 'Member Play',
            'alt' => 'Member play',
            'slug' => 'member-play',
        ],
    ];
}

function admin_gallery_images(PDO $pdo): array
{
    $grouped = array_fill_keys(array_values(site_config_gallery_categories()), []);
    $stmt = $pdo->query(
        'SELECT id, category, image_path, original_name, sort_order, is_active, created_at
         FROM gallery_images
         ORDER BY category, sort_order, id'
    );
    foreach ($stmt->fetchAll() as $row) {
        $grouped[(string) $row['category']][] = $row;
    }

    return $grouped;
}

function admin_save_gallery_upload(PDO $pdo, array $admin): void
{
    $category = trim((string) ($_POST['category'] ?? ''));
    if (!in_array($category, site_config_gallery_categories(), true)) {
        throw new RuntimeException('Choose a valid gallery category.');
    }
    if (!isset($_FILES['gallery_image']) || $_FILES['gallery_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose an image to upload.');
    }
    if ($_FILES['gallery_image']['error'] !== UPLOAD_ERR_OK || $_FILES['gallery_image']['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeded 8MB.');
    }

    $tmp = (string) $_FILES['gallery_image']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, or WEBP image.');
    }

    $slug = site_config_gallery_slug($category);
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR . $slug;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'gallery-' . $slug . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Could not save gallery image.');
    }

    $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM gallery_images WHERE category = ?');
    $sortStmt->execute([$category]);
    $sortOrder = (int) $sortStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO gallery_images (category, image_path, original_name, sort_order, is_active, uploaded_by)
         VALUES (?, ?, ?, ?, 1, ?)'
    );
    $stmt->execute([
        $category,
        'uploads/gallery/' . $slug . '/' . $filename,
        (string) ($_FILES['gallery_image']['name'] ?? $filename),
        $sortOrder,
        (int) $admin['id'],
    ]);
}

function admin_save_site_config_value(PDO $pdo, array $admin, string $key, string $value, string $label, string $fieldType = 'text', int $sortOrder = 10): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO site_config (config_key, config_value, label, field_type, sort_order, updated_by)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), label = VALUES(label),
            field_type = VALUES(field_type), sort_order = VALUES(sort_order), updated_by = VALUES(updated_by)'
    );
    $stmt->execute([$key, $value, $label, $fieldType, $sortOrder, (int) $admin['id']]);
}

function admin_delete_managed_home_about_file(string $path): void
{
    $normalizedPath = str_replace('\\', '/', trim($path));
    if (!str_starts_with($normalizedPath, 'uploads/home-about/')) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    $uploadRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'home-about' . DIRECTORY_SEPARATOR;
    $resolvedPath = realpath($absolutePath);
    $resolvedRoot = realpath($uploadRoot);
    if ($resolvedPath !== false && $resolvedRoot !== false && str_starts_with($resolvedPath, $resolvedRoot) && is_file($resolvedPath)) {
        unlink($resolvedPath);
    }
}

function admin_delete_managed_home_service_file(string $path): void
{
    $normalizedPath = str_replace('\\', '/', trim($path));
    if (!str_starts_with($normalizedPath, 'uploads/home-services/')) {
        return;
    }

    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);
    $uploadRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'home-services' . DIRECTORY_SEPARATOR;
    $resolvedPath = realpath($absolutePath);
    $resolvedRoot = realpath($uploadRoot);
    if ($resolvedPath !== false && $resolvedRoot !== false && str_starts_with($resolvedPath, $resolvedRoot) && is_file($resolvedPath)) {
        unlink($resolvedPath);
    }
}

function admin_upload_home_about_image(PDO $pdo, array $admin): void
{
    if (!isset($_FILES['about_main_image']) || $_FILES['about_main_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose an image to upload.');
    }
    if ($_FILES['about_main_image']['error'] !== UPLOAD_ERR_OK || $_FILES['about_main_image']['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeded 8MB.');
    }

    $tmp = (string) $_FILES['about_main_image']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, or WEBP image.');
    }

    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'home-about';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'more-than-court-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Could not save home about image.');
    }

    $currentConfig = site_config($pdo);
    admin_delete_managed_home_about_file((string) ($currentConfig['about_main_image_path'] ?? ''));

    admin_save_site_config_value(
        $pdo,
        $admin,
        'about_main_image_path',
        'uploads/home-about/' . $filename,
        'About Main Image Path',
        'text',
        90
    );
}

function admin_update_home_about_image_path(PDO $pdo, array $admin): void
{
    $path = trim((string) ($_POST['about_main_image_path'] ?? ''));
    admin_save_site_config_value($pdo, $admin, 'about_main_image_path', $path, 'About Main Image Path', 'text', 90);
}

function admin_delete_home_about_image(PDO $pdo, array $admin, string $currentPath): void
{
    admin_delete_managed_home_about_file($currentPath);
    admin_save_site_config_value($pdo, $admin, 'about_main_image_path', '', 'About Main Image Path', 'text', 90);
}

function admin_home_service_image_meta(): array
{
    $index = (int) ($_POST['service_index'] ?? 0);
    $cards = admin_home_service_image_cards();
    if (!isset($cards[$index])) {
        throw new RuntimeException('Choose a valid service card.');
    }

    return $cards[$index];
}

function admin_upload_home_service_image(PDO $pdo, array $admin): void
{
    $meta = admin_home_service_image_meta();
    if (!isset($_FILES['home_service_image']) || $_FILES['home_service_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose an image to upload.');
    }
    if ($_FILES['home_service_image']['error'] !== UPLOAD_ERR_OK || $_FILES['home_service_image']['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Image upload failed or exceeded 8MB.');
    }

    $tmp = (string) $_FILES['home_service_image']['tmp_name'];
    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use a JPG, PNG, or WEBP image.');
    }

    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'home-services';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'service-' . $meta['slug'] . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmp, $dir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Could not save service image.');
    }

    $currentConfig = site_config($pdo);
    admin_delete_managed_home_service_file((string) ($currentConfig[$meta['key']] ?? ''));

    admin_save_site_config_value(
        $pdo,
        $admin,
        $meta['key'],
        'uploads/home-services/' . $filename,
        $meta['label'] . ' Image Path',
        'text',
        100
    );
}

function admin_update_home_service_image_path(PDO $pdo, array $admin): void
{
    $meta = admin_home_service_image_meta();
    $path = trim((string) ($_POST['service_image_path'] ?? ''));
    admin_save_site_config_value($pdo, $admin, $meta['key'], $path, $meta['label'] . ' Image Path', 'text', 100);
}

function admin_delete_home_service_image(PDO $pdo, array $admin): void
{
    $meta = admin_home_service_image_meta();
    $currentConfig = site_config($pdo);
    admin_delete_managed_home_service_file((string) ($currentConfig[$meta['key']] ?? ''));
    admin_save_site_config_value($pdo, $admin, $meta['key'], '', $meta['label'] . ' Image Path', 'text', 100);
}

function admin_update_gallery_images(PDO $pdo): void
{
    $ids = array_map('intval', (array) ($_POST['gallery_ids'] ?? []));
    $orders = (array) ($_POST['sort_order'] ?? []);
    $active = array_flip(array_map('intval', (array) ($_POST['is_active'] ?? [])));
    $stmt = $pdo->prepare('UPDATE gallery_images SET sort_order = ?, is_active = ? WHERE id = ?');

    foreach ($ids as $id) {
        if ($id <= 0) {
            continue;
        }
        $stmt->execute([
            max(0, (int) ($orders[$id] ?? 0)),
            isset($active[$id]) ? 1 : 0,
            $id,
        ]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string) ($_POST['action'] ?? 'site_config');

        if ($action === 'gallery_upload') {
            admin_save_gallery_upload($pdo, $admin);
            $message = 'Gallery image uploaded.';
        } elseif ($action === 'gallery_update') {
            admin_update_gallery_images($pdo);
            $message = 'Gallery ordering and visibility saved.';
        } elseif ($action === 'about_image_upload') {
            admin_upload_home_about_image($pdo, $admin);
            $message = 'Home about image uploaded.';
        } elseif ($action === 'about_image_update_path') {
            admin_update_home_about_image_path($pdo, $admin);
            $message = 'Home about image path saved.';
        } elseif ($action === 'about_image_delete') {
            $currentConfig = site_config($pdo);
            admin_delete_home_about_image($pdo, $admin, (string) ($currentConfig['about_main_image_path'] ?? ''));
            $message = 'Home about image removed.';
        } elseif ($action === 'service_image_upload') {
            admin_upload_home_service_image($pdo, $admin);
            $message = 'Service card image uploaded.';
        } elseif ($action === 'service_image_update_path') {
            admin_update_home_service_image_path($pdo, $admin);
            $message = 'Service card image path saved.';
        } elseif ($action === 'service_image_delete') {
            admin_delete_home_service_image($pdo, $admin);
            $message = 'Service card image removed.';
        } else {
            if (trim((string) ($_POST['contact_phone'] ?? '')) !== '' && !admin_is_valid_contact_phone((string) $_POST['contact_phone'])) {
                throw new RuntimeException(admin_contact_phone_validation_message());
            }
            $bookingMaxDate = trim((string) ($_POST['booking_max_date'] ?? ''));
            if ($bookingMaxDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingMaxDate)) {
                throw new RuntimeException('Use a valid Booking Max Date.');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO site_config (config_key, config_value, label, field_type, sort_order, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), label = VALUES(label),
                    field_type = VALUES(field_type), sort_order = VALUES(sort_order), updated_by = VALUES(updated_by)'
            );
            $sort = 10;
            foreach ($fieldMeta as $key => [$label, $type]) {
                if (!array_key_exists($key, $_POST)) {
                    $sort += 10;
                    continue;
                }
                $stmt->execute([
                    $key,
                    $key === 'contact_phone'
                        ? normalize_phone_number((string) ($_POST[$key] ?? ''))
                        : ($key === 'booking_max_date' ? $bookingMaxDate : trim((string) ($_POST[$key] ?? ''))),
                    $label,
                    $type === 'textarea' ? 'textarea' : ($type === 'url' ? 'url' : ($type === 'date' ? 'date' : 'text')),
                    $sort,
                    (int) $admin['id'],
                ]);
                $sort += 10;
            }
            $message = 'Site configuration saved.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$config = site_config($pdo);
site_config_seed_gallery_images($pdo, $config);
$galleryImages = admin_gallery_images($pdo);
$aboutMainImagePath = trim((string) ($config['about_main_image_path'] ?? ''));
$aboutMainImageUrl = site_asset_url($aboutMainImagePath);
$serviceImageCards = array_map(static function (array $card) use ($config): array {
    $path = trim((string) ($config[$card['key']] ?? ''));
    $card['path'] = $path;
    $card['url'] = site_asset_url($path);
    return $card;
}, admin_home_service_image_cards());

include __DIR__ . '/../includes/header.php';
?>
<main class="app-main admin-compact">
    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="section-kicker">Site Config</span>
                <h2 class="mt-1 mb-1 fw-black">Public content and links</h2>
                <p class="mb-0 small text-secondary fw-semibold">Update homepage gallery, address, contact map, and Messenger link from the database.</p>
            </div>
            <a href="<?php echo htmlspecialchars(app_url('ui/index.php')); ?>" class="btn btn-outline-primary btn-sm">View Site</a>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="alert alert-primary"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="grid gap-3 mb-3">
        <input type="hidden" name="action" value="site_config">
        <section class="app-card">
            <div class="row g-3">
                <?php foreach (['venue_name', 'address', 'contact_phone', 'contact_email', 'messenger_url', 'map_embed_url', 'booking_max_date', 'hero_image_path', 'about_small_image_path', 'contact_image_path'] as $key): ?>
                    <?php [$label, $type] = $fieldMeta[$key]; ?>
                    <label class="col-md-6 small fw-bold"><?php echo htmlspecialchars($label); ?>
                        <input
                            name="<?php echo htmlspecialchars($key); ?>"
                            type="<?php echo $type === 'url' ? 'url' : ($type === 'date' ? 'date' : 'text'); ?>"
                            value="<?php echo htmlspecialchars((string) ($config[$key] ?? '')); ?>"
                            class="form-input mt-1"
                            <?php echo $key === 'contact_phone' ? 'data-phone-mode="contact" placeholder="0917 123 4567 or (02) 8123 4567"' : ''; ?>
                        >
                        <?php if ($key === 'booking_max_date'): ?>
                            <span class="d-block mt-1 text-xs fw-semibold text-secondary">Last date players can select in the booking calendar. Leave blank for no custom limit.</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 mb-0 small text-secondary">Image paths can be local, like <code>assets/homepage-court.jpg</code>, or full URLs.</p>
        </section>

        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Save Config</button>
        </div>
        
    </form>

    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <span class="section-kicker">Home Screen</span>
                <h3 class="mt-1 mb-1 fw-black">More Than Just a Court image</h3>
                <p class="mb-0 small text-secondary fw-semibold">Manage the large image shown beside the homepage about copy.</p>
            </div>
            <a href="<?php echo htmlspecialchars(app_url('ui/index.php#about')); ?>" class="btn btn-outline-primary btn-sm">View Section</a>
        </div>

        <div class="row g-3 align-items-start">
            <div class="col-lg-4">
                <div class="rounded-lg border border-line p-2">
                    <?php if ($aboutMainImageUrl !== ''): ?>
                        <img
                            src="<?php echo htmlspecialchars($aboutMainImageUrl); ?>"
                            alt="More Than Just a Court preview"
                            class="rounded object-cover w-100"
                            style="aspect-ratio: 4 / 3;"
                        >
                    <?php else: ?>
                        <div class="rounded bg-light text-muted fw-semibold" style="aspect-ratio: 4 / 3; display: grid; place-items: center;">
                            No image selected
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-xs font-semibold text-muted mt-2">
                    <?php echo $aboutMainImagePath !== '' ? htmlspecialchars($aboutMainImagePath) : 'Upload an image or add a path/URL.'; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="post" enctype="multipart/form-data" class="rounded-lg border border-line p-3 h-100">
                            <input type="hidden" name="action" value="about_image_upload">
                            <label class="small fw-bold d-block">Upload or replace image
                                <input
                                    required
                                    type="file"
                                    name="about_main_image"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="form-input mt-1"
                                >
                            </label>
                            <p class="small text-secondary fw-semibold mt-2 mb-3">JPG, PNG, or WEBP up to 8MB.</p>
                            <button class="btn btn-primary btn-sm" type="submit">Upload Image</button>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <form method="post" class="rounded-lg border border-line p-3 h-100">
                            <input type="hidden" name="action" value="about_image_update_path">
                            <label class="small fw-bold d-block">Image path or URL
                                <input
                                    name="about_main_image_path"
                                    type="text"
                                    value="<?php echo htmlspecialchars($aboutMainImagePath); ?>"
                                    class="form-input mt-1"
                                    placeholder="assets/hero-pickleball.png"
                                >
                            </label>
                            <p class="small text-secondary fw-semibold mt-2 mb-3">Use a local path like <code>assets/hero-pickleball.png</code> or a full URL.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-primary btn-sm" type="submit">Save Path</button>
                                <button
                                    class="btn btn-outline-danger btn-sm"
                                    type="submit"
                                    name="action"
                                    value="about_image_delete"
                                    onclick="return confirm('Remove the homepage about image?');"
                                >
                                    Delete Image
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="app-card mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <span class="section-kicker">Home Screen</span>
                <h3 class="mt-1 mb-1 fw-black">From First Game to Match Day images</h3>
                <p class="mb-0 small text-secondary fw-semibold">Manage the four image cards shown in the homepage play options section.</p>
            </div>
            <a href="<?php echo htmlspecialchars(app_url('ui/index.php#play-options')); ?>" class="btn btn-outline-primary btn-sm">View Section</a>
        </div>

        <div class="row g-3">
            <?php foreach ($serviceImageCards as $index => $card): ?>
                <div class="col-xl-3 col-md-6">
                    <div class="rounded-lg border border-line p-3 h-100">
                        <p class="small fw-black text-primary mb-2"><?php echo htmlspecialchars((string) $card['label']); ?></p>
                        <?php if ($card['url'] !== ''): ?>
                            <img
                                src="<?php echo htmlspecialchars((string) $card['url']); ?>"
                                alt="<?php echo htmlspecialchars((string) $card['alt']); ?>"
                                class="rounded object-cover w-100 mb-2"
                                style="aspect-ratio: 4 / 3;"
                            >
                        <?php else: ?>
                            <div class="rounded bg-light text-muted fw-semibold mb-2" style="aspect-ratio: 4 / 3; display: grid; place-items: center;">
                                Default image
                            </div>
                        <?php endif; ?>
                        <div class="text-xs font-semibold text-muted mb-3">
                            <?php echo $card['path'] !== '' ? htmlspecialchars((string) $card['path']) : 'Using homepage default.'; ?>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="grid gap-2 mb-3">
                            <input type="hidden" name="action" value="service_image_upload">
                            <input type="hidden" name="service_index" value="<?php echo (int) $index; ?>">
                            <input
                                required
                                type="file"
                                name="home_service_image"
                                accept=".jpg,.jpeg,.png,.webp"
                                class="form-input"
                            >
                            <button class="btn btn-primary btn-sm" type="submit">Upload Image</button>
                        </form>

                        <form method="post" class="grid gap-2">
                            <input type="hidden" name="action" value="service_image_update_path">
                            <input type="hidden" name="service_index" value="<?php echo (int) $index; ?>">
                            <input
                                name="service_image_path"
                                type="text"
                                value="<?php echo htmlspecialchars((string) $card['path']); ?>"
                                class="form-input"
                                placeholder="assets/homepage-court.jpg"
                            >
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-primary btn-sm" type="submit">Save Path</button>
                                <button
                                    class="btn btn-outline-danger btn-sm"
                                    type="submit"
                                    name="action"
                                    value="service_image_delete"
                                    onclick="return confirm('Remove this service card image?');"
                                >
                                    Delete
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="app-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <span class="section-kicker">Home Gallery</span>
                <h3 class="mt-1 mb-0 fw-black">Category image groups</h3>
                <p class="mb-0 small text-secondary fw-semibold">Upload images into Pickleball, Miami, or Lakers. Active images appear on the website in sort order.</p>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach (site_config_gallery_categories() as $category): ?>
                <div class="col-lg-4">
                    <div class="rounded-lg border border-line p-3 h-100">
                        <p class="small fw-black text-primary mb-2"><?php echo htmlspecialchars($category); ?></p>
                        <form method="post" enctype="multipart/form-data" class="grid gap-2">
                            <input type="hidden" name="action" value="gallery_upload">
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            <input
                                required
                                type="file"
                                name="gallery_image"
                                accept=".jpg,.jpeg,.png,.webp"
                                class="form-input"
                            >
                            <button class="btn btn-primary btn-sm" type="submit">Upload Image</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" class="mt-4">
            <input type="hidden" name="action" value="gallery_update">
            <?php foreach ($galleryImages as $category => $images): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <h4 class="mb-0 fw-black"><?php echo htmlspecialchars($category); ?></h4>
                        <span class="badge text-bg-light"><?php echo count($images); ?> images</span>
                    </div>

                    <?php if (empty($images)): ?>
                        <div class="rounded-lg border border-dashed border-line p-3 text-sm font-semibold text-muted">No images uploaded yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Preview</th>
                                        <th>File</th>
                                        <th class="text-center">Active</th>
                                        <th class="text-end">Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($images as $image): ?>
                                        <tr>
                                            <td style="width: 94px;">
                                                <img
                                                    src="<?php echo htmlspecialchars(site_asset_url((string) $image['image_path'])); ?>"
                                                    alt=""
                                                    class="rounded object-cover"
                                                    style="width: 78px; height: 54px;"
                                                >
                                            </td>
                                            <td>
                                                <input type="hidden" name="gallery_ids[]" value="<?php echo (int) $image['id']; ?>">
                                                <div class="fw-black text-ink"><?php echo htmlspecialchars((string) ($image['original_name'] ?: basename((string) $image['image_path']))); ?></div>
                                                <div class="text-xs font-semibold text-muted"><?php echo htmlspecialchars((string) $image['image_path']); ?></div>
                                            </td>
                                            <td class="text-center">
                                                <input
                                                    type="checkbox"
                                                    name="is_active[]"
                                                    value="<?php echo (int) $image['id']; ?>"
                                                    <?php echo ((int) $image['is_active'] === 1) ? 'checked' : ''; ?>
                                                >
                                            </td>
                                            <td class="text-end" style="width: 120px;">
                                                <input
                                                    type="number"
                                                    name="sort_order[<?php echo (int) $image['id']; ?>]"
                                                    value="<?php echo (int) $image['sort_order']; ?>"
                                                    min="0"
                                                    step="1"
                                                    class="form-input text-end"
                                                >
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">Save Gallery Order</button>
            </div>
        </form>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
