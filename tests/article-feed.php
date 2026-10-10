<?php

/**
 * Covers the RSS enclosure for articles with a featured fragment.
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

echo 'OK: articles expose only their featured fragment as an RSS enclosure' . PHP_EOL;
