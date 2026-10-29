<?php

/**
 * Tests that the block editor hides its raw HTML tools.
 */

define('ABSPATH', __DIR__ . '/../vendor/roots/wordpress-no-content/');
define('WPINC', 'wp-includes');
foreach (['compat', 'plugin', 'functions'] as $file) {
    require ABSPATH . WPINC . '/' . $file . '.php';
}

$inline = [];
function wp_add_inline_script(string $handle, string $data): void
{
    $GLOBALS['inline'][$handle][] = $data;
}

require __DIR__ . '/../lib/input_sanitizer.php';
do_action('enqueue_block_editor_assets');

// Run the inline script against a stub of wp.hooks and register a few blocks through it.
$script = implode(';', [
    'let filter; const wp = { hooks: { addFilter: (hook, ns, cb) => { if (hook === "blocks.registerBlockType") filter = cb; } } }',
    ...$inline['wp-blocks'] ?? [],
    'console.log(JSON.stringify(["core/html", "core/shortcode", "core/paragraph", "zuidwest/callout"].map((name) => filter({ supports: { html: false } }, name).supports)))',
]);
$supports = json_decode((string) shell_exec('node -e ' . escapeshellarg($script)), true);

$cases = [
    'Custom HTML and Shortcode leave the inserter, other blocks stay' => [
        [['html' => false, 'inserter' => false], ['html' => false, 'inserter' => false], ['html' => false], ['html' => false]],
        $supports,
    ],
    'code editing is off and other settings stay' => [['richEditingEnabled' => true, 'codeEditingEnabled' => false], apply_filters('block_editor_settings_all', ['richEditingEnabled' => true], null)],
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

echo 'OK: the block editor hides the Custom HTML and Shortcode blocks and code editing' . PHP_EOL;
