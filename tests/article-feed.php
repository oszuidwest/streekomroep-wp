<?php

/**
 * Covers the RSS media tag for featured images and the enclosure for articles with a featured fragment.
 */

define('ABSPATH', __DIR__ . '/../vendor/roots/wordpress-no-content/');
define('WPINC', 'wp-includes');
foreach (['compat', 'plugin', 'functions', 'formatting', 'kses', 'utf8'] as $file) {
    require ABSPATH . WPINC . '/' . $file . '.php';
}
add_filter('pre_option_blog_charset', fn () => 'UTF-8');

$fixtures = [];

function get_the_ID(): int
{
    return 1;
}

function get_post_status(int $id): string
{
    return $GLOBALS['fixtures'][$id]['status'] ?? '';
}

function get_post_field(string $field, int $id): string
{
    return $GLOBALS['fixtures'][$id][$field] ?? '';
}

function get_post_meta(int $id, string $key, bool $single): mixed
{
    return $GLOBALS['fixtures'][$id]['meta'][$key] ?? '';
}

// Post 1's featured image is attachment 3.
function get_post_thumbnail_id(): int|false
{
    return isset($GLOBALS['fixtures'][3]) ? 3 : false;
}

function wp_get_attachment_image_src(int $id, string $size): array|false
{
    return $GLOBALS['fixtures'][$id]['large'] ?? false;
}

require __DIR__ . '/../lib/article_feed.php';

$valid = [
    1 => ['meta' => ['post_fragment_is_featured' => '1', 'post_gekoppeld_fragment' => ['2']]],
    2 => ['status' => 'publish', 'meta' => ['enclosure' => "https://cdn.example/video.mp4?a=1&b=2\n12345\nvideo/mp4"]],
];
$cases = [
    'featured video' => [[], '<enclosure url="https://cdn.example/video.mp4?a=1&#038;b=2" length="12345" type="video/mp4" />' . "\n"],
    'not featured' => [[1 => ['meta' => ['post_fragment_is_featured' => '0']]], ''],
    'protected article' => [[1 => ['post_password' => 'secret']], ''],
    'no linked fragment' => [[1 => ['meta' => ['post_gekoppeld_fragment' => '']]], ''],
    'draft fragment' => [[2 => ['status' => 'draft']], ''],
    'protected fragment' => [[2 => ['post_password' => 'secret']], ''],
    'unresolved media' => [[2 => ['meta' => ['enclosure' => '']]], ''],
    'zero length' => [[2 => ['meta' => ['enclosure' => "https://cdn.example/video.mp4\n0\nvideo/mp4"]]], ''],
    'negative length' => [[2 => ['meta' => ['enclosure' => "https://cdn.example/video.mp4\n-1\nvideo/mp4"]]], ''],
    'nonnumeric length' => [[2 => ['meta' => ['enclosure' => "https://cdn.example/video.mp4\nunknown\nvideo/mp4"]]], ''],
    'empty URL' => [[2 => ['meta' => ['enclosure' => "\n123\nvideo/mp4"]]], ''],
    'blank URL' => [[2 => ['meta' => ['enclosure' => " \t \n123\nvideo/mp4"]]], ''],
    'rejected URL' => [[2 => ['meta' => ['enclosure' => "javascript:alert(1)\n123\nvideo/mp4"]]], ''],
    'featured audio' => [
        [2 => ['meta' => ['enclosure' => "https://cdn.example/audio.mp3\n123\naudio/mpeg"]]],
        '<enclosure url="https://cdn.example/audio.mp3" length="123" type="audio/mpeg" />' . "\n",
    ],
];
foreach ($cases as $name => [$overrides, $expected]) {
    $fixtures = array_replace_recursive($valid, $overrides);
    ob_start();
    do_action('rss2_item');
    $actual = ob_get_clean();
    if ($actual !== $expected) {
        fwrite(STDERR, $name . ': unexpected RSS item output ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$media = fn (string $type, string $url): string => '<media:content xmlns:media="http://search.yahoo.com/mrss/" medium="image" type="' . $type . '" url="' . $url . '" width="1024" height="661" />' . "\n";
$imageCases = [
    'JPEG' => [['https://example.test/photo-1024x661.jpg', 1024, 661], $media('image/jpeg', 'https://example.test/photo-1024x661.jpg')],
    'PNG' => [['https://example.test/chart-1024x661.png', 1024, 661], $media('image/png', 'https://example.test/chart-1024x661.png')],
    'uppercase WebP' => [['https://example.test/still-1024x661.WEBP', 1024, 661], $media('image/webp', 'https://example.test/still-1024x661.WEBP')],
    'escaped URL' => [['https://example.test/a&b-1024x661.jpg', 1024, 661], $media('image/jpeg', 'https://example.test/a&#038;b-1024x661.jpg')],
    'missing file' => [false, ''],
    'unknown type' => [['https://example.test/file-1024x661', 1024, 661], ''],
    'rejected URL' => [['javascript:alert(1).jpg', 1024, 661], ''],
];
foreach ($imageCases as $name => [$large, $expected]) {
    $fixtures = [3 => ['large' => $large]];
    ob_start();
    do_action('rss2_item');
    $actual = ob_get_clean();
    if ($actual !== $expected) {
        fwrite(STDERR, $name . ': unexpected RSS media tag ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

echo 'OK: articles expose their featured image with its real type and only their featured fragment as an RSS enclosure' . PHP_EOL;
