<?php
declare(strict_types=1);

/** Keep imported formatting, but never accept executable markup or pasted font styles. */
function page_content_sanitize_html(string $html): string
{
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><body>' . $html . '</body>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote'];
    $clean = function (DOMNode $parent) use (&$clean, $allowed): void {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'], true)) {
                    $parent->removeChild($node);
                    continue;
                }
                $clean($node);
                if (!in_array($tag, $allowed, true)) {
                    if ($tag === 'div') $node->appendChild($node->ownerDocument->createElement('br'));
                    while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                    $parent->removeChild($node);
                    continue;
                }
                $href = trim($node->getAttribute('href'));
                while ($node->attributes->length) $node->removeAttributeNode($node->attributes->item(0));
                if ($tag === 'a' && preg_match('~^(https?://|mailto:|/(?!/)|#)~i', $href) && !preg_match('/[\x00-\x20\x7f]/', $href)) {
                    $node->setAttribute('href', $href);
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
            } elseif (!($node instanceof DOMText)) {
                $parent->removeChild($node);
            }
        }
    };
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) return '';
    $clean($body);
    $result = '';
    foreach ($body->childNodes as $node) $result .= $doc->saveHTML($node);
    return trim($result);
}

/** A marker prevents deleted content being recreated on subsequent visits. */
function page_content_seed(PDO $pdo, string $table, array $default): void
{
    if (!in_array($table, ['rules_policies', 'terms_conditions_policies', 'data_privacy_policies'], true)) {
        throw new InvalidArgumentException('Unknown content table.');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_content_migrations (name VARCHAR(80) PRIMARY KEY, migrated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->beginTransaction();
    try {
        $marker = $pdo->prepare('INSERT IGNORE INTO page_content_migrations (name) VALUES (?)');
        $marker->execute([$table]);
        if ($marker->rowCount() && !(int) $pdo->query("SELECT COUNT(*) FROM `$table` WHERE status = 'Active'")->fetchColumn()) {
            $version = 'default';
            $exists = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE version = ?");
            do {
                $exists->execute([$version]);
                $taken = (int) $exists->fetchColumn();
                if ($taken) $version = 'import-' . bin2hex(random_bytes(8));
            } while ($taken);
            $insert = $pdo->prepare("INSERT INTO `$table` (title, version, content_html, status) VALUES (?, ?, ?, 'Active')");
            $insert->execute([$default['title'], $version, $default['contentHtml']]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function page_content_verify_csrf(): void
{
    if (empty($_SESSION['page_content_csrf']) || !hash_equals($_SESSION['page_content_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Your session expired. Reload the page and try again.');
    }
}
