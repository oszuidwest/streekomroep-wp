<?php

namespace Streekomroep;

/**
 * Transient-backed cache for the front-page TV catch-up candidate lists.
 *
 * Entries hold scalar refs (show ID plus video guid) keyed by the block
 * settings, so differently configured blocks share one transient that the
 * Bunny cron and content saves can invalidate as a whole.
 */
class TvGemistCache
{
    private const KEY = 'zw_tv_gemist';
    private const TTL = 10 * MINUTE_IN_SECONDS;

    /** @return array{videos: array, shows: array}|null */
    public static function get(string $key): ?array
    {
        $cache = get_transient(self::KEY);
        $entry = is_array($cache) ? ($cache[$key] ?? null) : null;

        return is_array($entry) && isset($entry['videos'], $entry['shows']) ? $entry : null;
    }

    /** @param array{videos: array, shows: array} $entry */
    public static function set(string $key, array $entry): void
    {
        $cache = get_transient(self::KEY);
        $cache = is_array($cache) ? $cache : [];
        $cache[$key] = $entry;
        set_transient(self::KEY, $cache, self::TTL);
    }

    public static function invalidate(): void
    {
        delete_transient(self::KEY);
    }
}
