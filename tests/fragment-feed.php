<?php

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses -- Minimal Timber and fragment test doubles.

namespace Streekomroep {
    class Fragment
    {
        public int $ID = 2;

        public function meta(string $key, array $options): mixed
        {
            return \get_post_meta($this->ID, $key, true);
        }

        public function getSources(): array
        {
            return $GLOBALS['sources'];
        }
    }
}

namespace Timber {
    class Timber
    {
        public static function get_post(int $post_id): \Streekomroep\Fragment
        {
            return new \Streekomroep\Fragment();
        }
    }
}

namespace {
    $meta = ['fragment_url' => 'https://player.example/123'];
    $sources = [['src' => 'https://cdn.example/video.mp4', 'type' => 'video/mp4']];
    $touches = [];
    // WP-Cron runs without a user, so core's kses filters are active.
    $kses = true;

    function add_action(string $hook, callable $callback): void
    {
    }

    function get_post_meta(int $post_id, string $key, bool $single): mixed
    {
        return $GLOBALS['meta'][$key] ?? '';
    }

    function update_post_meta(int $post_id, string $key, mixed $value): bool
    {
        if (($GLOBALS['meta'][$key] ?? null) === $value) {
            return false;
        }
        $GLOBALS['meta'][$key] = $value;
        return true;
    }

    function delete_post_meta(int $post_id, string $key): bool
    {
        $exists = isset($GLOBALS['meta'][$key]);
        unset($GLOBALS['meta'][$key]);
        return $exists;
    }

    function wp_cache_delete(int $post_id, string $group): void
    {
    }

    function wp_safe_remote_head(string $url, array $options): array
    {
        return [];
    }

    function wp_remote_retrieve_header(array $response, string $header): string
    {
        return '12345';
    }

    function wp_remote_retrieve_response_code(array $response): int
    {
        return 200;
    }

    function has_filter(string $hook, string $callback): bool
    {
        return $GLOBALS['kses'];
    }

    function kses_remove_filters(): void
    {
        $GLOBALS['kses'] = false;
    }

    function kses_init_filters(): void
    {
        $GLOBALS['kses'] = true;
    }

    function wp_update_post(array $post_data): int
    {
        if ($GLOBALS['kses']) {
            throw new \RuntimeException('kses would strip embeds from the touched content');
        }
        $GLOBALS['touches'][] = $post_data['ID'];
        return $post_data['ID'];
    }

    require __DIR__ . '/../lib/fragment_feed.php';

    zw_fragment_update_enclosure(2);
    if ($meta['enclosure'] !== "https://cdn.example/video.mp4\n12345\nvideo/mp4" || $touches !== [2]) {
        throw new \RuntimeException('New enclosure must update the post timestamp for feed validators');
    }
    if (!$kses) {
        throw new \RuntimeException('kses must be restored after the update');
    }
    zw_fragment_update_enclosure(2);
    if ($touches !== [2]) {
        throw new \RuntimeException('Unchanged enclosure must preserve validators');
    }
    $sources[0]['src'] = 'https://cdn.example/replacement.mp4';
    zw_fragment_update_enclosure(2);
    if ($touches !== [2, 2]) {
        throw new \RuntimeException('Replacement enclosure must refresh validators');
    }
    $sources = [];
    zw_fragment_update_enclosure(2);
    if (isset($meta['enclosure']) || $touches !== [2, 2, 2]) {
        throw new \RuntimeException('Removed enclosure must refresh validators');
    }
    zw_fragment_update_enclosure(2);
    if ($touches !== [2, 2, 2]) {
        throw new \RuntimeException('Still unavailable media must not refresh validators');
    }
    // WP-CLI without --user removes kses itself; the update must not switch it back on.
    $kses = false;
    $sources = [['src' => 'https://cdn.example/video.mp4', 'type' => 'video/mp4']];
    zw_fragment_update_enclosure(2);
    if ($touches !== [2, 2, 2, 2] || $kses) {
        throw new \RuntimeException('kses must stay off when it was off before the update');
    }
    echo "Fragment enclosure cache tests passed.\n";
}
