<?php
$pageTitle = 'Gallery | Multi-Sport Court Scheduling & Reservation';
$active = 'gallery';
include __DIR__ . '/../includes/header.php';
$siteConfig = site_config();
$gallery = site_config_gallery($siteConfig);
$heroImage = $gallery[0]['image'] ?? $siteConfig['contact_image_path'];
?>
<main class="public-page">
    <section class="public-card overflow-hidden">
        <div class="grid gap-0 lg:grid-cols-[1.1fr_.9fr]">
            <div class="relative min-h-[360px]">
                <img src="<?php echo htmlspecialchars(site_asset_url((string) $heroImage)); ?>" alt="Covered indoor courts at Metro Asia" class="h-full w-full object-cover">
                <span class="absolute bottom-4 left-4 rounded-full bg-ink/85 px-4 py-2 text-sm font-black text-white">Covered Courts</span>
            </div>
            <div class="p-6 lg:p-8">
                <p class="section-kicker">Gallery</p>
                <h1 class="mt-3 font-display text-4xl font-black leading-tight">See the court layout before you book.</h1>
                <p class="mt-4 text-sm font-semibold leading-7 text-muted">
                    Browse the venue view, dedicated pickleball courts, and the multi-sport areas used for basketball and volleyball.
                </p>
                <div class="mt-6 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xl font-black">2</p>
                        <p class="text-xs font-bold text-muted">USAPA Pro</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xl font-black">3</p>
                        <p class="text-xs font-bold text-muted">Wooden</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xl font-black">2</p>
                        <p class="text-xs font-bold text-muted">Multi-sport</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($gallery as $item): ?>
            <article class="venue-card">
                <img src="<?php echo htmlspecialchars(site_asset_url((string) $item['image'])); ?>" alt="<?php echo htmlspecialchars((string) $item['title']); ?>" class="h-[220px] w-full object-cover">
                <div class="p-4">
                    <h2 class="text-lg font-black"><?php echo htmlspecialchars((string) $item['title']); ?></h2>
                    <p class="mt-2 text-sm font-semibold leading-6 text-muted"><?php echo htmlspecialchars((string) $item['caption']); ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
