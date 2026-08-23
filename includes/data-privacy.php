<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function data_privacy_default_policy(): array
{
    return [
        'id' => 0,
        'title' => 'Data Privacy Policy',
        'version' => 'default',
        'contentHtml' => '<p>MetroAsia Arena collects member information to manage accounts, bookings, payments, QR lookup, entrance-fee records, and platform support.</p><p>Personal data is used only for MetroAsia Arena services, operational verification, audit/history records, and legally required administration. Access is limited to authorized staff.</p><p>Members may request correction or deactivation of their account details through MetroAsia Arena administration.</p>',
        'status' => 'Active',
        'isActive' => true,
    ];
}

function data_privacy_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS data_privacy_policies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            version VARCHAR(40) NOT NULL,
            content_html MEDIUMTEXT NOT NULL,
            status ENUM('Draft','Active','Archived') NOT NULL DEFAULT 'Draft',
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_privacy_status (status, updated_at),
            UNIQUE KEY uniq_privacy_version (version),
            CONSTRAINT fk_privacy_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id),
            CONSTRAINT fk_privacy_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function data_privacy_sanitize_html(string $html): string
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? '';
    $html = preg_replace('/<a\b(?![^>]*\btarget=)/i', '<a target="_blank" rel="noopener"', $html) ?? '';
    return trim($html);
}

function data_privacy_policy_payload(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'version' => (string) $row['version'],
        'contentHtml' => (string) $row['content_html'],
        'status' => (string) $row['status'],
        'isActive' => (string) $row['status'] === 'Active',
        'updatedAt' => isset($row['updated_at']) && $row['updated_at'] ? date(DATE_ATOM, strtotime((string) $row['updated_at'])) : null,
    ];
}

function data_privacy_active_policy(PDO $pdo = null): array
{
    try {
        $pdo ??= db();
        data_privacy_ensure_table($pdo);
        $stmt = $pdo->query(
            "SELECT id, title, version, content_html, status, updated_at
             FROM data_privacy_policies
             WHERE status = 'Active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        $row = $stmt->fetch();
        return $row ? data_privacy_policy_payload($row) : data_privacy_default_policy();
    } catch (Throwable) {
        return data_privacy_default_policy();
    }
}

function data_privacy_policies(PDO $pdo): array
{
    data_privacy_ensure_table($pdo);
    $stmt = $pdo->query(
        "SELECT id, title, version, content_html, status, updated_at
         FROM data_privacy_policies
         ORDER BY status = 'Active' DESC, updated_at DESC, id DESC"
    );
    return array_map('data_privacy_policy_payload', $stmt->fetchAll());
}
