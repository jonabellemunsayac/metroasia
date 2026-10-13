<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/announcements.php';
$admin=require_admin_menu('admin-announcements');
$pdo=db();
$announcementSettings=announcement_settings($pdo);
$error=null;
$_SESSION['announcement_csrf'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $uploaded=null;
    try {
        if (!hash_equals($_SESSION['announcement_csrf'],(string)($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Please reload the page and try again.');
        $start=trim((string)($_POST['start'] ?? ''));
        $end=trim((string)($_POST['end'] ?? ''));
        if (!announcement_valid_date($start) || !announcement_valid_date($end) || $start>$end) throw new InvalidArgumentException('Choose valid dates with the end date on or after the start date.');
        $enabled=isset($_POST['enabled']);
        if (isset($_FILES['image']) && $_FILES['image']['error']!==UPLOAD_ERR_NO_FILE) $uploaded=announcement_upload($_FILES['image']);
        $image=$uploaded ?? $announcementSettings['image'];
        if ($enabled && !$image) throw new InvalidArgumentException('Upload an image before enabling the announcement.');
        $next=['image'=>$image,'enabled'=>$enabled,'start'=>$start,'end'=>$end];
        $q=$pdo->prepare("INSERT INTO site_config (config_key,config_value,label,field_type,updated_by) VALUES ('announcement_popup',?,'Announcement popup','text',?) ON DUPLICATE KEY UPDATE config_value=VALUES(config_value),updated_by=VALUES(updated_by)");
        $q->execute([json_encode($next,JSON_THROW_ON_ERROR),(int)$admin['id']]);
        redirect_to('admin/announcements.php?saved=1');
    } catch (Throwable $e) {
        if ($uploaded) unlink(dirname(__DIR__).'/'.$uploaded);
        $error=$e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not save the announcement. Please try again.';
    }
}
$pageTitle='Announcements'; $active='admin-announcements';
include __DIR__.'/../includes/header.php';
?>
<main class="app-main admin-compact">
    <section class="app-card">
        <h2 class="fw-black mb-2">Announcement popup</h2>
        <p class="text-secondary small">Show an image announcement only on the index page, on each load or refresh. Dates include the entire start and end day in venue time.</p>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (isset($_GET['saved'])): ?><div class="alert alert-success" role="status">Announcement saved.</div><?php endif; ?>
        <div class="row g-4">
            <form method="post" enctype="multipart/form-data" class="col-lg-6">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['announcement_csrf']); ?>">
                <label class="d-flex align-items-center gap-2 mb-3"><input type="checkbox" class="form-check-input m-0" name="enabled" <?php echo $announcementSettings['enabled'] ? 'checked' : ''; ?>> Enable announcement</label>
                <label class="d-block fw-bold mb-2" for="announcementImage">Announcement image</label>
                <input id="announcementImage" class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                <p class="small text-secondary mt-1">JPG, PNG, WEBP or GIF, up to 5 MB. Leave empty to keep the current image.</p>
                <div class="row g-3 mb-3">
                    <label class="col-sm-6">Start Date<input class="form-control mt-1" type="date" name="start" required value="<?php echo htmlspecialchars($announcementSettings['start'] ?: date('Y-m-d')); ?>"></label>
                    <label class="col-sm-6">End Date<input class="form-control mt-1" type="date" name="end" required value="<?php echo htmlspecialchars($announcementSettings['end'] ?: date('Y-m-d',strtotime('+30 days'))); ?>"></label>
                </div>
                <button class="btn btn-primary" type="submit">Save Announcement</button>
            </form>
            <div class="col-lg-6">
                <p class="fw-bold mb-2">Current image</p>
                <?php if ($announcementSettings['image']): ?>
                    <img src="<?php echo htmlspecialchars(app_url($announcementSettings['image'])); ?>" alt="Current announcement" style="max-width:100%;max-height:420px;object-fit:contain;border-radius:8px">
                <?php else: ?><p class="text-secondary">No announcement image uploaded yet.</p><?php endif; ?>
                <p class="small text-secondary mt-2"><?php echo !$announcementSettings['enabled'] ? 'Disabled' : (announcement_is_active($announcementSettings) ? 'Active now' : 'Outside the scheduled date range'); ?></p>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__.'/../includes/footer.php'; ?>
