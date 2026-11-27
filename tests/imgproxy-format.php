<?php

/**
 * Tests the imgproxy output format per source and which article images go through imgproxy.
 * zw_imgproxy() itself is a stand-in here, so its call to Imgproxy::outputExtension() is not covered.
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
        'https://example.test/wp-content/uploads/logo.png' => 'webp',
        'https://example.test/wp-content/uploads/LOGO.PNG' => 'webp',
        'https://example.test/wp-content/uploads/logo.png?ver=2' => 'webp',
        'https://example.test/wp-content/uploads/anim.gif' => 'webp',
        'https://example.test/wp-content/uploads/still.webp' => 'webp',
        // Sources without an extension get JPEG.
        'https://example.test/thumbnail' => 'jpeg',
        'https://example.test/png/thumbnail' => 'jpeg',
    ] as $src => $expected
) {
    $actual = Imgproxy::outputExtension($src);
    $check($actual === $expected, 'Imgproxy::outputExtension(' . $src . ') is ' . $actual . ', expected ' . $expected . '.');
}

// WP_HTML_Tag_Processor comes from the autoloader; it escapes URLs with kses, so load that from core.
$core = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';
define('ABSPATH', dirname($core) . '/');
define('WPINC', 'wp-includes');
foreach (
    [
        'compat-utf8.php', 'utf8.php', 'html-api/html5-named-character-references.php', 'compat.php', 'load.php', 'plugin.php',
        'formatting.php', 'functions.php', 'kses.php',
    ] as $file
) {
    require_once $core . '/' . $file;
}

// Stand-ins for lookups that need the database or functions.php.
$attachments = [1 => 'image/jpeg', 2 => 'image/png', 3 => 'image/gif', 4 => 'image/webp'];

function get_post_mime_type(int $id): string
{
    return $GLOBALS['attachments'][$id];
}

function wp_get_attachment_image_src(int $id, string $size): array
{
    return ['https://example.test/uploads/full.ext', 2000, 1000];
}

function zw_imgproxy_is_configured(): bool
{
    return true;
}

function zw_imgproxy($src, $width, $height): string
{
    return 'https://imgproxy.test/' . $width;
}

function wp_img_tag_add_auto_sizes(string $image): string
{
    return $image;
}

$inFeed = false;

function is_feed(): bool
{
    return $GLOBALS['inFeed'];
}

require __DIR__ . '/../lib/content_images.php';

$imgTag = '<img src="https://example.test/uploads/file-1024x512.ext" width="1024" height="512" alt="">';

// imgproxy may drop animation, so only JPEG and PNG go through it; GIF and WebP keep core's markup.
foreach ([1 => true, 2 => true, 3 => false, 4 => false] as $attachmentId => $routed) {
    $out = zw_content_image_cdn($imgTag, 'the_content', $attachmentId);
    $check(
        $routed ? str_contains($out, 'src="https://imgproxy.test/') : $out === $imgTag,
        $attachments[$attachmentId] . ($routed ? ' in content must go through imgproxy.' : ' in content must keep core\'s markup.')
    );
}

// Feed readers and mail clients may not show WebP, so PNGs in feeds keep core's markup.
$inFeed = true;
$check(zw_content_image_cdn($imgTag, 'the_content', 2) === $imgTag, 'image/png in a feed must keep core\'s markup.');
$check(str_contains(zw_content_image_cdn($imgTag, 'the_content', 1), 'src="https://imgproxy.test/'), 'image/jpeg in a feed must still go through imgproxy.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'OK: imgproxy picks WebP for PNG, GIF and WebP sources and article PNGs go through it outside feeds' . PHP_EOL;
