<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/page-content.php';

function rules_default_policy(): array
{
    return [
        'id' => 0,
        'title' => 'Rules',
        'version' => 'default',
        'contentHtml' => rules_default_content_html(),
        'status' => 'Active',
        'isActive' => true,
    ];
}

function rules_default_content_html(): string
{
    return (string) file_get_contents(__DIR__ . '/rules-default.html');
}

function rules_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS rules_policies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            version VARCHAR(40) NOT NULL,
            content_html MEDIUMTEXT NOT NULL,
            status ENUM('Draft','Active','Archived') NOT NULL DEFAULT 'Draft',
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_rules_status (status, updated_at),
            UNIQUE KEY uniq_rules_version (version),
            CONSTRAINT fk_rules_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id),
            CONSTRAINT fk_rules_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    page_content_seed($pdo, 'rules_policies', rules_default_policy());
}

function rules_sanitize_html(string $html): string
{
    return page_content_sanitize_html($html);
}

function rules_policy_payload(array $row): array
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

function rules_active_policy(PDO $pdo = null): array
{
    try {
        $pdo ??= db();
        rules_ensure_table($pdo);
        $stmt = $pdo->query(
            "SELECT id, title, version, content_html, status, updated_at
             FROM rules_policies
             WHERE status = 'Active'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1"
        );
        $row = $stmt->fetch();
        return $row ? rules_policy_payload($row) : array_replace(rules_default_policy(), ['contentHtml' => '', 'status' => 'Draft', 'isActive' => false]);
    } catch (Throwable) {
        return rules_default_policy();
    }
}

function rules_policies(PDO $pdo): array
{
    rules_ensure_table($pdo);
    $stmt = $pdo->query(
        "SELECT id, title, version, content_html, status, updated_at
         FROM rules_policies
         ORDER BY status = 'Active' DESC, updated_at DESC, id DESC"
    );
    return array_map('rules_policy_payload', $stmt->fetchAll());
}
