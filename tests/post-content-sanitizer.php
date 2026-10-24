<?php

/**
 * Tests that the post content allowlist filters classic content and leaves block content alone.
 */

require __DIR__ . '/../vendor/autoload.php';

// Load just enough of core for kses and has_blocks().
$core = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';
define('ABSPATH', dirname($core) . '/');
define('WPINC', 'wp-includes');
foreach (
    [
        'compat-utf8.php', 'utf8.php', 'html-api/html5-named-character-references.php', 'compat.php', 'load.php', 'plugin.php',
        'formatting.php', 'functions.php', 'kses.php', 'blocks.php',
    ] as $file
) {
    require_once $core . '/' . $file;
}
// kses escapes attribute values, which reads the charset option.
add_filter('pre_option_blog_charset', fn () => 'UTF-8');
require __DIR__ . '/../lib/input_sanitizer.php';

$sanitize = fn (string $slashed): string => zw_sanitize_post_content(['post_content' => $slashed], [])['post_content'];

$classic = implode("\n\n", [
    '<p class="x">Intro <strong>vet</strong> <s>weg</s></p>',
    '<div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A</details></div>',
    '<p>Slot \\ "x"</p><iframe src="https://e.nl/v" style="border: none;" onload="x()"></iframe>',
]);
$classicExpected = implode("\n\n", [
    'Intro <strong>vet</strong> weg',
    implode("\n", ['<div class="collapsible">', '<h3 class="collapsible-title">T</h3>', '<details class="collapsible-item">', '<summary>K</summary>', 'A', '</details>', '</div>']),
    'Slot \\ &quot;x&quot;<iframe src="https://e.nl/v" style="border: none"></iframe>',
]);

// Block editor output, serialized by @wordpress/blocks (WordPress 7.1), including
// Custom HTML blocks with <p style>, <script> and onclick.
$blocks = wp_slash(file_get_contents(__DIR__ . '/fixtures/block-content.html'));

$cases = [
    'classic content is filtered through the allowlist' => [wp_slash($classicExpected), $sanitize(wp_slash($classic))],
    'block content is saved as submitted' => [$blocks, $sanitize($blocks)],
    'posts without content stay without content' => [[], zw_sanitize_post_content([], [])],
];

$failures = [];
foreach ($cases as $label => [$expected, $actual]) {
    if ($actual !== $expected) {
        $failures[] = sprintf("%s:\n  expected %s\n  got      %s", $label, json_encode($expected), json_encode($actual));
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: the post content allowlist filters classic content and leaves block content untouched' . PHP_EOL;
