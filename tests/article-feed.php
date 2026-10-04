<?php

namespace Streekomroep {
    class Fragment
    {
        public const string TYPE_VIDEO = 'Video';
    }
}

namespace {
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

    function esc_url(string $url): string
    {
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
        'feature removed after publication' => [[1 => ['post_fragment_is_featured' => '']], false],
        'linked fragment replaced' => [[1 => ['post_gekoppeld_fragment' => ['3']]], false],
    ];
    foreach ($cases as $name => [$overrides, $expected]) {
        $meta = array_replace_recursive($valid_meta, $overrides);
        // Empty relationship arrays replace the original list completely.
        if (isset($overrides[1]['post_gekoppeld_fragment'])) {
            $meta[1]['post_gekoppeld_fragment'] = $overrides[1]['post_gekoppeld_fragment'];
        }
        ob_start();
        $hooks['rss2_item']();
        $output = ob_get_clean();
        if (($output !== '') !== $expected) {
            throw new \RuntimeException($name . ': unexpected enclosure: ' . $output);
        }
        if ($expected) {
            ob_start();
            rss_enclosure();
            $hooks['rss2_item']();
            $output = ob_get_clean();
            $xml = simplexml_load_string('<item>' . $output . '</item>');
            if (
                count($xml->enclosure) !== 1
                || (string) $xml->enclosure['url'] !== 'https://cdn.example/video.mp4?a=1&b=2'
                || (string) $xml->enclosure['length'] !== '12345'
                || (string) $xml->enclosure['type'] !== 'video/mp4'
            ) {
                throw new \RuntimeException('Incorrect RSS enclosure attributes');
            }
        }
        if (!$expected && $filters['rss_enclosure']('native enclosure') !== 'native enclosure') {
            throw new \RuntimeException('Native enclosure suppressed without a featured video');
        }
    }
    $meta = $valid_meta;
    foreach (['draft', 'private', 'trash'] as $fragment_status) {
        $statuses[2] = $fragment_status;
        ob_start();
        $hooks['rss2_item']();
        if (ob_get_clean() !== '') {
            throw new \RuntimeException('Non-public fragment exposed: ' . $fragment_status);
        }
    }
    $statuses[2] = 'publish';
    foreach ([1, 2] as $protected_id) {
        $passwords = [$protected_id => 'secret'];
        ob_start();
        $hooks['rss2_item']();
        if (ob_get_clean() !== '') {
            throw new \RuntimeException('Password-protected media exposed');
        }
    }
    echo "Article RSS enclosure tests passed.\n";
}
