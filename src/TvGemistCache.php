<?php

namespace Streekomroep;

/**
 * Transient-backed cache for the front-page TV catch-up candidate lists.
 *
 * Entries hold scalar refs (show ID plus video guid) in one transient per
 * block configuration, so concurrent requests never rewrite each other's
 * entries and every entry keeps its own expiry. Invalidation bumps a version
 * that is part of every entry's transient name; entries stored under an old
 * version are never read again and expire through their own TTL.
 */
class TvGemistCache
{
    private const PREFIX = 'zw_tv_gemist_';
    private const VERSION_KEY = 'zw_tv_gemist_version';
    private const TTL = 10 * MINUTE_IN_SECONDS;

    /** @return array{videos: array, shows: array}|null */
    public static function get(string $key): ?array
    {
        $entry = get_transient(self::PREFIX . self::version() . '_' . $key);

        return is_array($entry) && isset($entry['videos'], $entry['shows']) ? $entry : null;
    }

    /** @param array{videos: array, shows: array} $entry */
    public static function set(string $key, array $entry): void
    {
        set_transient(self::PREFIX . self::version() . '_' . $key, $entry, self::TTL);
    }

    public static function invalidate(): void
    {
        // A plain overwrite instead of a read-modify-write, so concurrent
        // invalidations cannot resurrect stale data.
        set_transient(self::VERSION_KEY, self::newVersion(), 0);
    }

    private static function version(): string
    {
        $version = get_transient(self::VERSION_KEY);
        if (!is_string($version) || $version === '') {
            $version = self::newVersion();
            set_transient(self::VERSION_KEY, $version, 0);
        }

        return $version;
    }

    private static function newVersion(): string
    {
        return time() . '-' . wp_rand(1000, 9999);
    }
}
