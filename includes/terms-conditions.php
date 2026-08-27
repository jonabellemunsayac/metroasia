<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function terms_conditions_default_policy(): array
{
    return [
        'id' => 0,
        'title' => 'Content Policy',
        'version' => 'default',
        'contentHtml' => terms_conditions_default_content_html(),
        'status' => 'Active',
        'isActive' => true,
    ];
}

function terms_conditions_default_content_html(): string
{
    return '<h2>Content Policy</h2>'
        . '<h3>Our Goal</h3>'
        . '<p>To build and serve a healthy, active, and welcoming community for everyone!</p>'
        . '<p>Thank you for your cooperation and understanding.</p>'
        . '<p><strong>MAD MetroAsia Arena Team</strong></p>';
}

function terms_conditions_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS terms_conditions_policies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            version VARCHAR(40) NOT NULL,
            content_html MEDIUMTEXT NOT NULL,
            status ENUM('Draft','Active','Archived') NOT NULL DEFAULT 'Draft',
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_terms_status (status, updated_at),
            UNIQUE KEY uniq_terms_version (version),
            CONSTRAINT fk_terms_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id),
            CONSTRAINT fk_terms_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function terms_conditions_sanitize_html(string $html): string
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? '';
    $html = preg_replace('/<a\b(?![^>]*\btarget=)/i', '<a target="_blank" rel="noopener"', $html) ?? '';
    return trim($html);
}

function terms_conditions_policy_payload(array $row): array
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

function terms_conditions_active_policy(PDO $pdo = null): array
{
    try {
        $pdo ??= db();
        terms_conditions_ensure_table($pdo);
        $stmt = $pdo->query(
            "SELECT id, title, version, content_html, status, updated_at
             FROM terms_conditions_policies
             WHERE status = 'Active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        $row = $stmt->fetch();
        return $row ? terms_conditions_policy_payload($row) : terms_conditions_default_policy();
    } catch (Throwable) {
        return terms_conditions_default_policy();
    }
}

function terms_conditions_policies(PDO $pdo): array
{
    terms_conditions_ensure_table($pdo);
    $stmt = $pdo->query(
        "SELECT id, title, version, content_html, status, updated_at
         FROM terms_conditions_policies
         ORDER BY status = 'Active' DESC, updated_at DESC, id DESC"
    );
    return array_map('terms_conditions_policy_payload', $stmt->fetchAll());
}
