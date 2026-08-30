<?php

namespace Streekomroep;

use Timber\Timber;

/**
 * Front-page TV catch-up block: candidate selection plus its transient cache.
 *
 * The candidate lists are derived from bunny_data post meta on every TV show,
 * which is expensive to load and sort. Entries hold scalar refs (show ID plus
 * video guid) in one transient per block configuration, so concurrent
 * requests never rewrite each other's entries and every entry keeps its own
 * expiry. Invalidation bumps a version that is part of every entry's
 * transient name; entries stored under an old version are never read again
 * and expire through their own TTL.
 */
class TvGemistCache
{
    private const PREFIX = 'zw_tv_gemist_';
    private const VERSION_KEY = 'zw_tv_gemist_version';
    private const TTL = 10 * MINUTE_IN_SECONDS;

    /** Per-request memo of the version transient. */
    private static ?string $version = null;

    /**
     * Returns the featured episodes and the secondary show list for one block
     * configuration, as ['show' => post, 'video' => Video] pairs.
     *
     * @return array{videos: array, shows: array}
     */
    public static function getBlock(bool $deduplicate, int $videosToShow): array
    {
        $key = ($deduplicate ? '1' : '0') . '_' . $videosToShow;

        $refs = self::get($key);
        $resolved = $refs === null ? null : self::hydrate($refs);

        // A cached ref that no longer resolves (deleted show, vanished
        // video) means the cache is stale; rebuild instead of rendering a
        // shrunken block until the next invalidation.
        if (
            $resolved === null
            || count($resolved['videos']) !== count($refs['videos'])
            || count($resolved['shows']) !== count($refs['shows'])
        ) {
            $refs = self::computeRefs($deduplicate, $videosToShow);
            self::set($key, $refs);
            $resolved = self::hydrate($refs);
        }

        return $resolved;
    }

    public static function invalidate(): void
    {
        // A plain overwrite instead of a read-modify-write, so concurrent
        // invalidations cannot resurrect stale data.
        self::$version = (string) microtime(true);
        set_transient(self::VERSION_KEY, self::$version, 0);
    }

    /**
     * Turns ['show' => id, 'video' => guid] refs back into post and Video
     * objects; the warm and the cold path share this one step.
     */
    private static function hydrate(array $refs): array
    {
        $showIds = array_unique(array_merge(
            array_column($refs['videos'], 'show'),
            array_column($refs['shows'], 'show')
        ));

        $showsById = [];
        if ($showIds) {
            $shows = Timber::get_posts([
                'post_type' => 'tv',
                'post_status' => 'publish',
                'post__in' => $showIds,
                'ignore_sticky_posts' => true,
                'nopaging' => true,
            ]);
            foreach ($shows as $show) {
                $showsById[$show->ID] = $show;
            }
        }

        $resolve = static function ($ref) use ($showsById) {
            $show = $showsById[$ref['show']] ?? null;
            if (!$show) {
                return null;
            }

            $video = VideoCollection::findVideo($show->ID, $ref['video']);
            return $video ? ['show' => $show, 'video' => $video] : null;
        };

        return [
            'videos' => array_values(array_filter(array_map($resolve, $refs['videos']))),
            'shows' => array_values(array_filter(array_map($resolve, $refs['shows']))),
        ];
    }

    private static function computeRefs(bool $deduplicate, int $videosToShow): array
    {
        $shows = Timber::get_posts([
            'post_type' => 'tv',
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
            'nopaging' => true,
        ]);

        $episodesWithDuplicateShows = [];
        $latestEpisodePerShow = [];

        $candidate = static fn ($show, $video) => ['show' => $show, 'video' => $video];
        foreach ($shows as $show) {
            $videos = VideoCollection::forTvShow($show->ID);
            if (!$videos) {
                continue;
            }

            // Keep one latest episode per show for deduplicated videos and the secondary show list.
            $latestEpisodePerShow[] = $candidate($show, $videos[0]);

            // Without deduplication, every recent episode may become a featured video.
            if (false === $deduplicate) {
                $videos = array_slice($videos, 0, 10); // limit the buildup of the array.
                foreach ($videos as $video) {
                    $episodesWithDuplicateShows[] = $candidate($show, $video);
                }
            }
        }

        // Rank shows by the broadcast date of their latest episode.
        $newestFirst = static fn ($left, $right) => $right['video']->getBroadcastDate() <=> $left['video']->getBroadcastDate();
        usort($latestEpisodePerShow, $newestFirst);

        if (!empty($episodesWithDuplicateShows)) {
            // Rank the expanded episode pool independently when duplicate shows are allowed.
            usort($episodesWithDuplicateShows, $newestFirst);
        }

        $featured = array_slice($episodesWithDuplicateShows ?: $latestEpisodePerShow, 0, $videosToShow);
        $featuredShowIds = array_map(static fn ($item) => $item['show']->ID, $featured);
        $secondary = array_slice(array_values(array_filter($latestEpisodePerShow, static fn ($item) => !in_array($item['show']->ID, $featuredShowIds, true))), 0, 4);

        $toRef = static fn ($item) => ['show' => $item['show']->ID, 'video' => $item['video']->getId()];
        return [
            'videos' => array_map($toRef, $featured),
            'shows' => array_map($toRef, $secondary),
        ];
    }

    /** @return array{videos: array, shows: array}|null */
    private static function get(string $key): ?array
    {
        $entry = get_transient(self::PREFIX . self::version() . '_' . $key);

        return is_array($entry) && isset($entry['videos'], $entry['shows']) ? $entry : null;
    }

    /** @param array{videos: array, shows: array} $entry */
    private static function set(string $key, array $entry): void
    {
        set_transient(self::PREFIX . self::version() . '_' . $key, $entry, self::TTL);
    }

    private static function version(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }

        $version = get_transient(self::VERSION_KEY);
        if (!is_string($version) || $version === '') {
            $version = (string) microtime(true);
            set_transient(self::VERSION_KEY, $version, 0);
        }

        return self::$version = $version;
    }
}
