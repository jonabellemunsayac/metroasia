<?php
$homeSports = ['Pickleball', 'Basketball', 'Volleyball'];
$venuePhotos = [];
foreach ($galleryItems as $item) {
    foreach ((array) ($item['images'] ?? [$item['image'] ?? '']) as $photo) {
        if (trim((string) $photo) !== '') {
            $venuePhotos['courts'][] = site_asset_url((string) $photo);
        }
    }
}
$venuePhotos['courts'] = array_slice(array_values(array_unique($venuePhotos['courts'] ?? [app_url('assets/images/metro_court_view1.jpg')])), 0, 6);
foreach (['parking' => 5, 'lounge' => 7] as $category => $count) {
    for ($i = 1; $i <= $count; $i++) {
        $venuePhotos[$category][] = app_url("assets/images/{$category}{$i}.jpg");
    }
}
$galleryCategories = ['courts' => 'Courts', 'parking' => 'Parking', 'lounge' => 'Lounge', 'shower' => 'Shower Area', 'toilet' => 'Toilet'];
?>
<section id="sports" class="metro-section metro-sports-section">
    <div class="metro-container">
        <div class="metro-heading"><h2>Choose your sports:</h2></div>
        <div class="metro-sports-grid">
            <?php foreach ($homeSports as $sport): ?>
                <a class="metro-sport-card" href="<?php echo htmlspecialchars(app_url('ui/booking.php?sport=' . strtolower($sport))); ?>">
                    <svg class="metro-sport-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <?php if ($sport === 'Pickleball'): ?>
                            <path d="M18 10c-9 4-12 15-6 24l7 8 15-9c8-6 10-16 4-22-5-5-13-5-20-1Z"/>
                            <path d="m19 42 6 11c1 2 3 3 5 1l3-2c2-1 2-3 1-5l-7-10"/>
                            <circle cx="48" cy="41" r="9"/>
                            <circle cx="45" cy="38" r="1"/><circle cx="51" cy="40" r="1"/><circle cx="47" cy="45" r="1"/>
                        <?php elseif ($sport === 'Basketball'): ?>
                            <circle cx="32" cy="32" r="23"/>
                            <path d="M9 32h46M32 9v46M16 15c17 9 17 25 0 34M48 15c-17 9-17 25 0 34"/>
                        <?php else: ?>
                            <circle cx="32" cy="32" r="23"/>
                            <path d="M32 32c-3-10 0-18 7-22M32 32c10 2 18 0 23-6M32 32c-7 8-9 16-5 22M15 17c7 1 12 5 16 11M10 38c7-2 14-1 19 1M43 52c-1-8-5-14-11-20M26 10c-3 8-3 15 2 22M53 42c-8 1-15-1-21-6"/>
                        <?php endif; ?>
                    </svg>
                    <div class="metro-sport-caption"><h3><?php echo htmlspecialchars($sport); ?></h3><span aria-hidden="true">&#8599;</span></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="membership" class="metro-section metro-membership-section">
    <div class="metro-container metro-membership-top">
        <div class="metro-membership-intro">
            <h2>Become a MetroAsia Member</h2>
            <p>Create your free member account to access court schedules, rates, reservations, and payment tracking.</p>
            <a href="<?php echo htmlspecialchars(app_url($currentMember ? 'ui/member.php' : 'ui/register.php')); ?>" class="metro-btn metro-btn-accent"><?php echo $currentMember ? 'MY ACCOUNT' : 'CREATE MY ACCOUNT'; ?></a>
        </div>
    </div>
</section>

<section id="about" class="metro-section metro-built-section">
    <div class="metro-container">
        <div class="metro-heading"><h2>Built for the Game</h2></div>
        <div class="metro-built-grid">
            <?php foreach ([
                ['Premium Courts', 'Purpose-built spaces for competitive and casual play.'],
                ['Multi-Sport Arena', 'Pickleball, basketball and volleyball under one roof.'],
                ['Easy Reservations', 'Choose your court and reserve your schedule online.'],
            ] as [$title, $description]): ?>
                <article><h3><?php echo htmlspecialchars($title); ?></h3><p><?php echo htmlspecialchars($description); ?></p></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="gallery" class="metro-section metro-editorial-section">
    <div class="metro-container">
        <div class="metro-heading"><h2>Inside MetroAsia</h2></div>
        <div class="metro-gallery-filters" role="group" aria-label="Gallery categories">
            <?php foreach ($galleryCategories as $key => $label): ?>
                <button type="button" data-venue-filter="<?php echo $key; ?>" aria-pressed="<?php echo $key === 'courts' ? 'true' : 'false'; ?>" aria-controls="venue-gallery-<?php echo $key; ?>"><?php echo htmlspecialchars($label); ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($galleryCategories as $category => $label): ?>
            <div id="venue-gallery-<?php echo $category; ?>" data-venue-panel="<?php echo $category; ?>" <?php echo $category !== 'courts' ? 'hidden' : ''; ?>>
                <?php if (!empty($venuePhotos[$category])): ?>
                    <div class="metro-editorial-grid">
                        <?php foreach ($venuePhotos[$category] as $index => $photo): ?>
                            <button type="button" class="metro-editorial-photo" data-gallery-card data-gallery-images="<?php echo htmlspecialchars(json_encode([$photo], JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>" data-gallery-title="<?php echo htmlspecialchars($label); ?>" aria-label="View <?php echo htmlspecialchars($label); ?> photo <?php echo $index + 1; ?>">
                                <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($label); ?> at MetroAsia Arena" loading="lazy">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="metro-gallery-empty"><?php echo htmlspecialchars($label); ?> photos coming soon.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <span class="visually-hidden" data-venue-status role="status" aria-live="polite">Showing Courts photos</span>
    </div>
</section>
