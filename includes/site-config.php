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
        'contact_image_path' => 'assets/homepage-court.jpg',
        'gallery_1_title' => 'Main Indoor Courts',
        'gallery_1_caption' => 'Premium covered courts ready for daily reservations.',
        'gallery_1_image' => 'assets/homepage-court.jpg',
        'gallery_2_title' => 'Multi-Sport Setup',
        'gallery_2_caption' => 'Basketball, volleyball, and pickleball courts in one venue.',
        'gallery_2_image' => 'assets/courts-preview.png',
        'gallery_3_title' => 'Pickleball Ready',
        'gallery_3_caption' => 'Dedicated pickleball courts for casual and competitive games.',
        'gallery_3_image' => 'assets/hero-pickleball.png',
    ];
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
    $items = [];
    for ($index = 1; $index <= 3; $index++) {
        $items[] = [
            'title' => $config["gallery_{$index}_title"] ?? '',
            'caption' => $config["gallery_{$index}_caption"] ?? '',
            'image' => $config["gallery_{$index}_image"] ?? '',
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
