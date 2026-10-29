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

// Other post fields must pass through untouched.
$postData = fn (string $content): array => ['post_title' => 'T', 'post_content' => wp_slash($content)];
$sanitize = fn (string $content): array => zw_sanitize_post_content($postData($content), []);

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

// Hand-written, modelled on block editor output: blocks the allowlist would
// strip, including escaped JSON attributes and a Custom HTML block with <p style>,
// <script>, onclick and a collapsible.
$blocks = <<<'HTML'
<!-- wp:heading {"anchor":"a\u002d\u002db","className":"Zeg \u0022hoi\u0022 \u002d\u002d en \u003cb\u003e \u0026 \u005c ’ é 🎉 \u002d\u002d\u003e \u003c!\u002d\u002d x"} -->
<h2 class="wp-block-heading Zeg &quot;hoi&quot; -- en &lt;b&gt; &amp; \ ’ é 🎉 --&gt; &lt;!-- x" id="a--b">Met anker</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":123,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="https://e.nl/a.jpg" alt="Zeg &quot;hoi&quot; -- en &lt;b&gt; &amp; \ ’ é 🎉 --&gt; &lt;!-- x" class="wp-image-123"/><figcaption class="wp-element-caption">Foto: <em>ANP</em> "x" --</figcaption></figure>
<!-- /wp:image -->

<!-- wp:embed {"url":"https://www.youtube.com/watch?v=abc\u0026t=1","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=abc&amp;t=1
</div></figure>
<!-- /wp:embed -->

<!-- wp:shortcode -->
[zw_embed url="https://x.nl/?a=1&b=2" titel="é -- 'q'"]
<!-- /wp:shortcode -->

<!-- wp:html -->
<p style="color:red">rood</p><script>alert(1)</script><b onclick="x()">vet</b><s>doorgehaald</s><div class="collapsible"><h3 class="collapsible-title">T</h3><details class="collapsible-item"><summary>K</summary>A</details></div><iframe src="https://e.nl" allowfullscreen></iframe>
<!-- /wp:html -->
HTML;

// The editor saves a Classic block as plain HTML without block comments.
$mixed = "<p>Losse <s>klassieke</s> tekst</p>\n\n<!-- wp:paragraph -->\n<p class=\"x\">Blok \\ \"x\"</p>\n<!-- /wp:paragraph -->";

$cases = [
    'classic content is filtered through the allowlist' => [$postData($classicExpected), $sanitize($classic)],
    'block content is saved as submitted' => [$postData($blocks), $sanitize($blocks)],
    'a classic block beside blocks is saved as submitted' => [$postData($mixed), $sanitize($mixed)],
    'posts without content stay without content' => [['post_title' => 'T'], zw_sanitize_post_content(['post_title' => 'T'], [])],
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
