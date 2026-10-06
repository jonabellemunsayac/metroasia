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
$venuePhotos['courts'] = array_values(array_unique($venuePhotos['courts'] ?? [app_url('assets/images/metro_court_view1.jpg')]));
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
                <a class="metro-sport-card metro-sport-card--<?php echo htmlspecialchars(strtolower($sport)); ?>" href="<?php echo htmlspecialchars(app_url('ui/booking.php?sport=' . strtolower($sport))); ?>">
                    <svg class="metro-sport-icon" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <?php if ($sport === 'Pickleball'): ?>
                            <g transform="rotate(30 25 31)">
                                <path d="M19 8h12a8 8 0 0 1 8 8v13a9 9 0 0 1-4 7.5L29 40h-8l-6-3.5a9 9 0 0 1-4-7.5V16a8 8 0 0 1 8-8Z"/>
                                <path d="M21 40v13a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V40M21 46h8"/>
                            </g>
                            <circle cx="47" cy="43" r="9"/>
                            <g fill="currentColor" stroke="none">
                                <circle cx="47" cy="39" r="1.2"/>
                                <circle cx="43" cy="43" r="1.2"/>
                                <circle cx="51" cy="43" r="1.2"/>
                                <circle cx="47" cy="47" r="1.2"/>
                            </g>
                        <?php elseif ($sport === 'Basketball'): ?>
                            <circle cx="32" cy="32" r="23"/>
                            <g transform="rotate(-30 32 32)">
                                <path d="M9 32h46M32 9v46"/>
                                <path d="M15.74 15.74C31 22 31 42 15.74 48.26M48.26 15.74C33 22 33 42 48.26 48.26"/>
                            </g>
                        <?php else: ?>
                            <circle cx="32" cy="32" r="23"/>
                            <path d="M32 32Q46 29 51.92 20.5M32 9Q33 21 45.95 26.54"/>
                            <path d="M32 32Q46 29 51.92 20.5M32 9Q33 21 45.95 26.54" transform="rotate(120 32 32)"/>
                            <path d="M32 32Q46 29 51.92 20.5M32 9Q33 21 45.95 26.54" transform="rotate(240 32 32)"/>
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
                        <?php foreach (array_slice($venuePhotos[$category], 0, 5) as $index => $photo): ?>
                            <button type="button" class="metro-editorial-photo" data-gallery-card data-gallery-images="<?php echo htmlspecialchars(json_encode($venuePhotos[$category], JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>" data-gallery-index="<?php echo $index; ?>" data-gallery-title="<?php echo htmlspecialchars($label); ?>" aria-label="View <?php echo htmlspecialchars($label); ?> photo <?php echo $index + 1; ?>">
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
