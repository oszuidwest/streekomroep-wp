<?php

/**
 * Covers RSS enclosures for articles with a featured video fragment.
 */

require __DIR__ . '/../vendor/autoload.php';

$meta = [];
$types = [1 => 'post', 2 => 'fragment'];
$statuses = [2 => 'publish'];
$passwords = [];
$hooks = [];
$filters = [];

function get_the_ID(): int
{
    return 1;
}

function get_post_type(int $id): string
{
    return $GLOBALS['types'][$id] ?? '';
}

function get_post_status(int $id): string
{
    return $GLOBALS['statuses'][$id] ?? '';
}

function get_post_field(string $field, int $id): string
{
    return $GLOBALS['passwords'][$id] ?? '';
}

function get_post_meta(int $id, string $key, bool $single): mixed
{
    return $GLOBALS['meta'][$id][$key] ?? '';
}

// Model esc_url() rejecting unsafe schemes so the empty-URL guard is exercised.
function esc_url(string $url): string
{
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
        return '';
    }

    return htmlspecialchars($url, ENT_QUOTES | ENT_XML1);
}

function add_action(string $hook, callable $callback): void
{
    $GLOBALS['hooks'][$hook] = $callback;
}

function add_filter(string $hook, callable $callback): void
{
    $GLOBALS['filters'][$hook] = $callback;
}

function apply_filters(string $hook, mixed $value): mixed
{
    return isset($GLOBALS['filters'][$hook]) ? $GLOBALS['filters'][$hook]($value) : $value;
}

function get_post_custom(): array
{
    return ['enclosure' => ["https://cdn.example/old.mp4\n999\nvideo/mp4"]];
}

function post_password_required(): bool
{
    return false;
}

function absint(mixed $number): int
{
    return abs((int) $number);
}

function esc_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
}

require __DIR__ . '/../lib/article_feed.php';
require __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes/feed.php';

// Renders an item's enclosures in feed-rss2.php order: core's own first, then rss2_item.
function render_enclosures(): SimpleXMLElement
{
    ob_start();
    rss_enclosure();
    $GLOBALS['hooks']['rss2_item']();
    return simplexml_load_string('<item>' . ob_get_clean() . '</item>');
}

function enclosure_urls(SimpleXMLElement $item): array
{
    $urls = [];
    foreach ($item->enclosure as $enclosure) {
        $urls[] = (string) $enclosure['url'];
    }
    return $urls;
}

$featured_url = 'https://cdn.example/video.mp4?a=1&b=2';
$native_urls = ['https://cdn.example/old.mp4'];
$valid_meta = [
    1 => ['post_fragment_is_featured' => '1', 'post_gekoppeld_fragment' => ['2']],
    2 => ['fragment_type' => 'Video', 'enclosure' => "https://cdn.example/video.mp4?a=1&b=2\n12345\nvideo/mp4"],
];
$cases = [
    'featured video' => [[], true],
    'not featured' => [[1 => ['post_fragment_is_featured' => '0']], false],
    'no fragment' => [[1 => ['post_gekoppeld_fragment' => []]], false],
    'missing fragment' => [[1 => ['post_gekoppeld_fragment' => ['99']]], false],
    'audio fragment' => [[2 => ['fragment_type' => 'Audio']], false],
    'unresolved video' => [[2 => ['enclosure' => '']], false],
    'HLS is not a file' => [[2 => ['enclosure' => "https://cdn.example/a.m3u8\n123\napplication/x-mpegURL"]], false],
    'zero size' => [[2 => ['enclosure' => "https://cdn.example/a.mp4\n0\nvideo/mp4"]], false],
    'unsafe URL scheme' => [[2 => ['enclosure' => "javascript:alert(1)\n123\nvideo/mp4"]], false],
    'featured flag absent' => [[1 => ['post_fragment_is_featured' => '']], false],
];
foreach ($cases as $name => [$overrides, $expected]) {
    $meta = $valid_meta;
    foreach ($overrides as $override_id => $fields) {
        $meta[$override_id] = array_replace($meta[$override_id], $fields);
    }
    $item = render_enclosures();
    if (enclosure_urls($item) !== ($expected ? [$featured_url] : $native_urls)) {
        throw new \RuntimeException($name . ': unexpected enclosures: ' . $item->asXML());
    }
    if ($expected && ((string) $item->enclosure['length'] !== '12345' || (string) $item->enclosure['type'] !== 'video/mp4')) {
        throw new \RuntimeException('Incorrect RSS enclosure attributes');
    }
}
$meta = $valid_meta;
foreach (['draft', 'private', 'trash'] as $fragment_status) {
    $statuses[2] = $fragment_status;
    if (enclosure_urls(render_enclosures()) !== $native_urls) {
        throw new \RuntimeException('Non-public fragment exposed: ' . $fragment_status);
    }
}
$statuses[2] = 'publish';
foreach ([1, 2] as $protected_id) {
    $passwords = [$protected_id => 'secret'];
    if (enclosure_urls(render_enclosures()) !== $native_urls) {
        throw new \RuntimeException('Password-protected media exposed');
    }
}
echo "Article RSS enclosure tests passed.\n";
