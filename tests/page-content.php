<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/page-content.php';
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$safe = '<h2>Heading</h2><p><strong>Bold</strong></p><ul><li>Bullet</li></ul><ol><li>Number</li></ol><a href="https://example.com">Link</a>';
$output = page_content_sanitize_html($safe);
foreach (['<h2>', '<strong>', '<ul>', '<ol>', 'href="https://example.com"'] as $part) check(str_contains($output, $part), 'Lost supported formatting: ' . $part);
foreach (['javascript:alert(1)', 'java&#x73;cript:alert(1)', '&#10;javascript:alert(1)', 'data:text/html,test', '//example.com'] as $url) {
    $output = page_content_sanitize_html('<a href="' . $url . '" onclick="alert(1)" style="font-family:serif">Text</a><script>alert(1)</script>');
    check($output === '<a>Text</a>', 'Unsafe content retained: ' . $output);
}
check(page_content_sanitize_html('<div>First</div><div>Second</div>') === 'First<br>Second<br>', 'Editor paragraph breaks lost.');
check(str_contains(page_content_sanitize_html('<p>Café &amp; sports</p>'), 'Café &amp; sports'), 'Unicode changed.');
echo "PASS: rich text formatting, URL and attribute sanitization, paragraph breaks, Unicode.\n";
