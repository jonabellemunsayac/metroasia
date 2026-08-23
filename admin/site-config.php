<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/site-config.php';

$admin = require_admin();
$pageTitle = 'Site Config';
$active = 'admin-site-config';
$message = null;
$error = null;
$pdo = db();
site_config_ensure_gallery_table($pdo);

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
];

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
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO site_config (config_key, config_value, label, field_type, sort_order, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), label = VALUES(label),
                    field_type = VALUES(field_type), sort_order = VALUES(sort_order), updated_by = VALUES(updated_by)'
            );
            $sort = 10;
            foreach ($fieldMeta as $key => [$label, $type]) {
                $stmt->execute([
                    $key,
                    trim((string) ($_POST[$key] ?? '')),
                    $label,
                    $type === 'textarea' ? 'textarea' : ($type === 'url' ? 'url' : 'text'),
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

    <form method="post" class="grid gap-3">
        <input type="hidden" name="action" value="site_config">
        <section class="app-card">
            <div class="row g-3">
                <?php foreach (['venue_name', 'address', 'contact_phone', 'contact_email', 'messenger_url', 'map_embed_url', 'hero_image_path', 'about_main_image_path', 'about_small_image_path', 'contact_image_path'] as $key): ?>
                    <?php [$label, $type] = $fieldMeta[$key]; ?>
                    <label class="col-md-6 small fw-bold"><?php echo htmlspecialchars($label); ?>
                        <input name="<?php echo htmlspecialchars($key); ?>" type="<?php echo $type === 'url' ? 'url' : 'text'; ?>" value="<?php echo htmlspecialchars((string) ($config[$key] ?? '')); ?>" class="form-input mt-1">
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 mb-0 small text-secondary">Image paths can be local, like <code>assets/homepage-court.jpg</code>, or full URLs.</p>
        </section>

        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Save Config</button>
        </div>
    </form>

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
