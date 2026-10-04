<?php

/**
 * Covers the RSS enclosure for articles with a featured video fragment.
 */

$fixtures = [];
$hooks = [];

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

function esc_url(string $url): string
{
    return htmlspecialchars($url, ENT_QUOTES | ENT_XML1);
}

function add_action(string $hook, callable $callback): void
{
    $GLOBALS['hooks'][$hook] = $callback;
}

require __DIR__ . '/../lib/article_feed.php';

function enclosures(): array
{
    ob_start();
    $GLOBALS['hooks']['rss2_item']();
    $enclosures = [];
    foreach (simplexml_load_string('<item>' . ob_get_clean() . '</item>')->enclosure as $enclosure) {
        $enclosures[] = ((array) $enclosure->attributes())['@attributes'];
    }
    return $enclosures;
}

$valid = [
    1 => ['status' => 'publish', 'meta' => ['post_fragment_is_featured' => '1', 'post_gekoppeld_fragment' => ['2']]],
    2 => ['status' => 'publish', 'meta' => ['enclosure' => "https://cdn.example/video.mp4?a=1&b=2\n12345\nvideo/mp4"]],
];
$featured = [['url' => 'https://cdn.example/video.mp4?a=1&b=2', 'length' => '12345', 'type' => 'video/mp4']];
$cases = [
    'featured video' => [[], $featured],
    'not featured' => [[1 => ['meta' => ['post_fragment_is_featured' => '0']]], []],
    'protected article' => [[1 => ['post_password' => 'secret']], []],
    'no linked fragment' => [[1 => ['meta' => ['post_gekoppeld_fragment' => '']]], []],
    'deleted fragment' => [[1 => ['meta' => ['post_gekoppeld_fragment' => ['99']]]], []],
    'draft fragment' => [[2 => ['status' => 'draft']], []],
    'protected fragment' => [[2 => ['post_password' => 'secret']], []],
    'unresolved video' => [[2 => ['meta' => ['enclosure' => '']]], []],
    'audio fragment' => [[2 => ['meta' => ['enclosure' => "https://cdn.example/audio.mp3\n123\naudio/mpeg"]]], []],
];
foreach ($cases as $name => [$overrides, $expected]) {
    $fixtures = array_replace_recursive($valid, $overrides);
    if (enclosures() !== $expected) {
        throw new \RuntimeException($name . ': unexpected enclosures ' . json_encode(enclosures()));
    }
}
echo "Article RSS enclosure tests passed.\n";
