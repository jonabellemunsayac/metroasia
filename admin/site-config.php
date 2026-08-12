<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/site-config.php';

$admin = require_admin();
$pageTitle = 'Site Config | Multi-Sport Court Scheduling & Reservation';
$active = 'admin-site-config';
$message = null;
$error = null;
$pdo = db();

$fieldMeta = [
    'venue_name' => ['Venue Name', 'text'],
    'address' => ['Address', 'text'],
    'contact_phone' => ['Contact Phone', 'text'],
    'contact_email' => ['Contact Email', 'text'],
    'messenger_url' => ['Facebook Messenger Link', 'url'],
    'map_embed_url' => ['Contact Map Embed Link', 'url'],
    'hero_image_path' => ['Home Hero Image Path', 'text'],
    'contact_image_path' => ['Contact Image Path', 'text'],
    'gallery_1_title' => ['Gallery 1 Title', 'text'],
    'gallery_1_caption' => ['Gallery 1 Caption', 'textarea'],
    'gallery_1_image' => ['Gallery 1 Image Path', 'text'],
    'gallery_2_title' => ['Gallery 2 Title', 'text'],
    'gallery_2_caption' => ['Gallery 2 Caption', 'textarea'],
    'gallery_2_image' => ['Gallery 2 Image Path', 'text'],
    'gallery_3_title' => ['Gallery 3 Title', 'text'],
    'gallery_3_caption' => ['Gallery 3 Caption', 'textarea'],
    'gallery_3_image' => ['Gallery 3 Image Path', 'text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$config = site_config($pdo);

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
        <section class="app-card">
            <div class="row g-3">
                <?php foreach (['venue_name', 'address', 'contact_phone', 'contact_email', 'messenger_url', 'map_embed_url', 'hero_image_path', 'contact_image_path'] as $key): ?>
                    <?php [$label, $type] = $fieldMeta[$key]; ?>
                    <label class="col-md-6 small fw-bold"><?php echo htmlspecialchars($label); ?>
                        <input name="<?php echo htmlspecialchars($key); ?>" type="<?php echo $type === 'url' ? 'url' : 'text'; ?>" value="<?php echo htmlspecialchars((string) ($config[$key] ?? '')); ?>" class="form-input mt-1">
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 mb-0 small text-secondary">Image paths can be local, like <code>assets/homepage-court.jpg</code>, or full URLs.</p>
        </section>

        <section class="app-card">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <span class="section-kicker">Home Gallery</span>
                    <h3 class="mt-1 mb-0 fw-black">Gallery cards</h3>
                </div>
            </div>
            <div class="row g-3">
                <?php for ($index = 1; $index <= 3; $index++): ?>
                    <div class="col-lg-4">
                        <div class="rounded-lg border border-line p-3 h-100">
                            <p class="small fw-black text-primary mb-2">Gallery <?php echo $index; ?></p>
                            <label class="small fw-bold w-100">Title
                                <input name="gallery_<?php echo $index; ?>_title" value="<?php echo htmlspecialchars((string) ($config["gallery_{$index}_title"] ?? '')); ?>" class="form-input mt-1">
                            </label>
                            <label class="small fw-bold w-100 mt-2">Caption
                                <textarea name="gallery_<?php echo $index; ?>_caption" rows="3" class="form-textarea mt-1"><?php echo htmlspecialchars((string) ($config["gallery_{$index}_caption"] ?? '')); ?></textarea>
                            </label>
                            <label class="small fw-bold w-100 mt-2">Image Path
                                <input name="gallery_<?php echo $index; ?>_image" value="<?php echo htmlspecialchars((string) ($config["gallery_{$index}_image"] ?? '')); ?>" class="form-input mt-1">
                            </label>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </section>

        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Save Config</button>
        </div>
    </form>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
