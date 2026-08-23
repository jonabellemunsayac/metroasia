<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function site_config_defaults(): array
{
    return [
        'venue_name' => 'MetroAsia Arena',
        'address' => '65 Elizco Rd, Pasig, 1600 Metro Manila',
        'contact_phone' => '09XX XXX XXXX',
        'contact_email' => 'support@metroasia.test',
        'messenger_url' => 'https://www.facebook.com/messages/t/',
        'map_embed_url' => 'https://www.google.com/maps?q=65%20Elizco%20Rd%2C%20Pasig%2C%201600%20Metro%20Manila&output=embed',
        'hero_image_path' => 'assets/homepage-court.jpg',
        'about_main_image_path' => 'assets/hero-pickleball.png',
        'about_small_image_path' => 'https://demo.zaktheme.web.id/Pickyard/wp-content/uploads/2025/11/paddle-tennis-equipment-on-the-ground-at-outdoor-c-2024-12-13-18-15-20-utc-1.webp',
        'contact_image_path' => 'assets/homepage-court.jpg',
        'gallery_1_title' => 'Pickleball',
        'gallery_1_caption' => 'Pickleball',
        'gallery_1_image' => 'assets/hero-pickleball.png',
        'gallery_1_images' => "assets/hero-pickleball.png\nassets/courts-preview.png\nassets/images/metro_court_view1.jpg",
        'gallery_2_title' => 'Miami',
        'gallery_2_caption' => 'Miami',
        'gallery_2_image' => 'assets/courts-preview.png',
        'gallery_2_images' => "assets/courts-preview.png\nassets/images/metro_court_view2.jpg\nassets/images/metro_court_view3.jpg",
        'gallery_3_title' => 'Lakers',
        'gallery_3_caption' => 'Lakers',
        'gallery_3_image' => 'assets/homepage-court.jpg',
        'gallery_3_images' => "assets/homepage-court.jpg\nassets/images/metro_court_view4.jpg\nassets/images/metro_court_view5.jpg",
    ];
}

function site_config_gallery_categories(): array
{
    return [
        1 => 'Pickleball',
        2 => 'Miami',
        3 => 'Lakers',
    ];
}

function site_config_gallery_slug(string $category): string
{
    return strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($category)));
}

function site_config_ensure_gallery_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS gallery_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category ENUM('Pickleball','Miami','Lakers') NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_gallery_public (category, is_active, sort_order, id),
            CONSTRAINT fk_gallery_images_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function site_config_seed_gallery_images(PDO $pdo, array $config): void
{
    site_config_ensure_gallery_table($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM gallery_images')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO gallery_images (category, image_path, original_name, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)'
    );

    foreach (site_config_gallery_categories() as $index => $category) {
        $paths = array_values(array_filter(
            array_map('trim', preg_split('/\R+/', (string) ($config["gallery_{$index}_images"] ?? '')) ?: []),
            static fn (string $image): bool => $image !== ''
        ));

        $fallback = trim((string) ($config["gallery_{$index}_image"] ?? ''));
        if (empty($paths) && $fallback !== '') {
            $paths[] = $fallback;
        }

        foreach ($paths as $order => $path) {
            $insert->execute([$category, $path, basename($path), ($order + 1) * 10]);
        }
    }
}

function site_config(PDO $pdo = null): array
{
    $config = site_config_defaults();

    try {
        $pdo ??= db();
        $rows = $pdo->query('SELECT config_key, config_value FROM site_config')->fetchAll();
        foreach ($rows as $row) {
            $key = (string) $row['config_key'];
            if (array_key_exists($key, $config)) {
                $config[$key] = (string) ($row['config_value'] ?? '');
            }
        }
    } catch (Throwable) {
        return $config;
    }

    return $config;
}

function site_config_gallery(array $config): array
{
    try {
        $pdo = db();
        site_config_seed_gallery_images($pdo, $config);
        $stmt = $pdo->query(
            'SELECT category, image_path
             FROM gallery_images
             WHERE is_active = 1
             ORDER BY category, sort_order, id'
        );
        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(string) $row['category']][] = (string) $row['image_path'];
        }

        $items = [];
        foreach (site_config_gallery_categories() as $category) {
            $images = $grouped[$category] ?? [];
            if (empty($images)) {
                continue;
            }
            $items[] = [
                'title' => $category,
                'caption' => $category,
                'image' => $images[0],
                'images' => $images,
            ];
        }

        if (!empty($items)) {
            return $items;
        }
    } catch (Throwable) {
        // Fall back to site_config text fields when the gallery table is unavailable.
    }

    $items = [];
    foreach (site_config_gallery_categories() as $index => $category) {
        $images = array_values(array_filter(
            array_map('trim', preg_split('/\R+/', (string) ($config["gallery_{$index}_images"] ?? '')) ?: []),
            static fn (string $image): bool => $image !== ''
        ));

        $fallbackImage = trim((string) ($config["gallery_{$index}_image"] ?? ''));
        if (empty($images) && $fallbackImage !== '') {
            $images[] = $fallbackImage;
        }

        $items[] = [
            'title' => $category,
            'caption' => $category,
            'image' => $images[0] ?? '',
            'images' => $images,
        ];
    }

    return array_values(array_filter($items, static fn (array $item): bool => trim((string) $item['title']) !== ''));
}

function site_asset_url(string $path): string
{
    $value = trim($path);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#', $value) === 1 || str_starts_with($value, 'data:')) {
        return $value;
    }

    return app_url($value);
}
