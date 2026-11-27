<?php

/**
 * Tests the imgproxy output format per source and which article images go through imgproxy.
 * zw_imgproxy() itself is a stand-in here, so its call to Imgproxy::extension() is not covered.
 */

use Streekomroep\Imgproxy;

require __DIR__ . '/../vendor/autoload.php';

$failures = [];
$check = function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

// Output format per source.
foreach (
    [
        'https://example.test/wp-content/uploads/photo.jpg' => 'jpeg',
        'https://example.test/wp-content/uploads/photo.jpeg' => 'jpeg',
        'https://example.test/wp-content/uploads/logo.png' => 'webp',
        'https://example.test/wp-content/uploads/LOGO.PNG' => 'webp',
        'https://example.test/wp-content/uploads/logo.png?ver=2' => 'webp',
        'https://example.test/wp-content/uploads/anim.gif' => 'webp',
        'https://example.test/wp-content/uploads/still.webp' => 'webp',
        // Bunny thumbnails and other sources without an extension stay JPEG.
        'https://example.test/thumbnail' => 'jpeg',
        'https://example.test/png/thumbnail' => 'jpeg',
    ] as $src => $expected
) {
    $actual = Imgproxy::extension($src);
    $check($actual === $expected, 'Imgproxy::extension(' . $src . ') is ' . $actual . ', expected ' . $expected . '.');
}

// Load just enough of core for WP_HTML_Tag_Processor, which escapes URLs with kses.
$core = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';
define('ABSPATH', dirname($core) . '/');
define('WPINC', 'wp-includes');
foreach (
    [
        'compat-utf8.php', 'utf8.php', 'html-api/html5-named-character-references.php', 'compat.php', 'load.php', 'plugin.php',
        'formatting.php', 'functions.php', 'kses.php', 'html-api/class-wp-html-attribute-token.php',
        'html-api/class-wp-html-span.php', 'html-api/class-wp-html-text-replacement.php',
        'html-api/class-wp-html-decoder.php', 'html-api/class-wp-html-tag-processor.php',
    ] as $file
) {
    require_once $core . '/' . $file;
}

// Stand-ins for lookups that need the database. Attachment 1 is a JPEG, 2 a PNG, 3 a GIF, 4 a WebP.
$attachments = [
    1 => ['image/jpeg', 'https://example.test/uploads/photo.jpg'],
    2 => ['image/png', 'https://example.test/uploads/chart.png'],
    3 => ['image/gif', 'https://example.test/uploads/anim.gif'],
    4 => ['image/webp', 'https://example.test/uploads/still.webp'],
];

function get_post_mime_type(int $id): string
{
    return $GLOBALS['attachments'][$id][0];
}

function wp_get_attachment_image_src(int $id, string $size): array
{
    return [$GLOBALS['attachments'][$id][1], 2000, 1000];
}

function zw_imgproxy_is_configured(): bool
{
    return true;
}

function zw_imgproxy($src, $width, $height): string
{
    return 'https://imgproxy.test/rs:fill:' . $width . ':' . $height . '/' . rawurlencode($src) . '.' . Imgproxy::extension($src);
}

function wp_img_tag_add_auto_sizes(string $image): string
{
    return $image;
}

require __DIR__ . '/../lib/content_images.php';

$imgTag = '<img src="https://example.test/uploads/file-1024x512.ext" width="1024" height="512" alt="">';

$jpeg = zw_content_image_cdn($imgTag, 'the_content', 1);
$check(str_contains($jpeg, 'src="https://imgproxy.test/') && str_contains($jpeg, '.jpeg'), 'A JPEG in content must go through imgproxy as JPEG.');

$png = zw_content_image_cdn($imgTag, 'the_content', 2);
$check(str_contains($png, 'src="https://imgproxy.test/'), 'A PNG in content must go through imgproxy.');
$check(str_contains($png, '.webp') && !str_contains($png, '.jpeg'), 'A PNG in content must come out as WebP to keep transparency.');

// imgproxy may drop animation, so GIF and WebP keep core's markup.
$check(zw_content_image_cdn($imgTag, 'the_content', 3) === $imgTag, 'A GIF in content must keep core\'s markup.');
$check(zw_content_image_cdn($imgTag, 'the_content', 4) === $imgTag, 'A WebP in content must keep core\'s markup.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: imgproxy picks WebP for PNG, GIF and WebP sources and article PNGs go through it' . PHP_EOL;
