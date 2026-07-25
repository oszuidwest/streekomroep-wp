<?php

namespace Streekomroep;

use Carbon\Carbon;
use WP_Error;

/**
 * Read-only client for the Aeron Toolbox API.
 *
 * Feeds the live radio page: the recently played tracks come from the playlist endpoint and album
 * art is fetched here rather than in the browser, so the API key never leaves the server.
 */
class AeronToolbox
{
    /** Rows shown under "Net gedraaid". */
    public const RECENT_LIMIT = 10;

    /** A track lasts minutes, so this window keeps Aeron out of the request path without lagging. */
    private const RECENT_TTL = 30;

    /** Remember an empty or failed answer longer, so an unreachable Toolbox cannot slow every view. */
    private const RECENT_EMPTY_TTL = 120;
    private const RECENT_TRANSIENT = 'zw_fm_recent_tracks';

    /** Most tracks have no art at all; remember that instead of asking again on every listener. */
    private const IMAGE_MISS_TTL = 900;
    private const IMAGE_MISS_TRANSIENT = 'zw_fm_track_image_miss_';

    /** Front-end requests wait on this, so it has to stay well under a page-load budget. */
    private const TIMEOUT = 3;

    /** Aeron identifies tracks with a UUID v4, the same one zwfm-metadata reports as songID. */
    private const UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/';

    public static function isConfigured(): bool
    {
        return self::baseUrl() !== '' && self::apiKey() !== '';
    }

    /**
     * Normalises a track identifier to the form Aeron accepts.
     *
     * zwfm-metadata reports the songID as a braced, upper-case GUID, while Toolbox validates a
     * bare lower-case UUID v4. Anything else is rejected instead of forwarded.
     */
    public static function normalizeId($id): ?string
    {
        if (!is_string($id)) {
            return null;
        }

        $id = strtolower(trim(trim($id), '{}'));

        return preg_match(self::UUID_PATTERN, $id) ? $id : null;
    }

    /**
     * Returns the most recently started tracks, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recentTracks(): array
    {
        if (!self::isConfigured()) {
            return [];
        }

        $cached = get_transient(self::RECENT_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $tracks = self::collectRecentTracks();
        set_transient(
            self::RECENT_TRANSIENT,
            $tracks,
            $tracks === [] ? self::RECENT_EMPTY_TTL : self::RECENT_TTL
        );

        return $tracks;
    }

    /**
     * Returns the album art for a track as ['body' => binary, 'type' => mime], or null.
     */
    public static function trackImage(string $id): ?array
    {
        $id = self::normalizeId($id);
        if ($id === null || !self::isConfigured()) {
            return null;
        }

        $miss = self::IMAGE_MISS_TRANSIENT . $id;
        if (get_transient($miss)) {
            return null;
        }

        $response = self::request('/tracks/' . $id . '/image', [], 'image/*');
        $body = $response === null ? '' : wp_remote_retrieve_body($response);
        $type = $response === null ? '' : strtolower(wp_remote_retrieve_header($response, 'content-type'));

        if ($body === '' || !str_starts_with($type, 'image/')) {
            set_transient($miss, 1, self::IMAGE_MISS_TTL);
            return null;
        }

        // Strip any charset parameter so the byte stream is served under a bare image type.
        return ['body' => $body, 'type' => trim(explode(';', $type)[0])];
    }

    private static function baseUrl(): string
    {
        return untrailingslashit(trim((string)get_field('radio_live_toolbox_url', 'option')));
    }

    private static function apiKey(): string
    {
        return trim((string)get_field('radio_live_toolbox_key', 'option'));
    }

    /** Accepts the base URL with or without the /api suffix an operator may have pasted in. */
    private static function endpoint(string $path): string
    {
        $base = self::baseUrl();
        if (str_ends_with($base, '/api')) {
            $base = substr($base, 0, -4);
        }

        return $base . '/api' . $path;
    }

    /**
     * @return array|null The raw wp_remote_get response, or null when the call failed outright.
     */
    private static function request(string $path, array $query = [], string $accept = 'application/json')
    {
        $url = self::endpoint($path);
        if ($query) {
            $url = add_query_arg($query, $url);
        }

        $response = wp_remote_get($url, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'X-API-Key' => self::apiKey(),
                'Accept' => $accept,
            ],
        ]);

        if ($response instanceof WP_Error) {
            self::log($path, $response->get_error_message());
            return null;
        }

        $code = (int)wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            // A missing image is normal, anything else points at a configuration or service problem.
            if ($code !== 404) {
                self::log($path, 'HTTP ' . $code);
            }

            return null;
        }

        return $response;
    }

    /**
     * Returns the `data` payload of a JSON endpoint.
     */
    private static function getJson(string $path, array $query = []): ?array
    {
        $response = self::request($path, $query);
        if ($response === null) {
            return null;
        }

        try {
            $body = json_decode(wp_remote_retrieve_body($response), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            self::log($path, 'invalid JSON: ' . $exception->getMessage());
            return null;
        }

        if (!is_array($body)) {
            return null;
        }

        // Toolbox wraps every JSON answer in {success, data}.
        $data = array_key_exists('data', $body) ? $body['data'] : $body;

        return is_array($data) ? $data : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function collectRecentTracks(): array
    {
        $now = Carbon::now(wp_timezone());
        $tracks = self::tracksForDate($now->toDateString(), $now);

        // Early in the morning today's playlist holds too little history to fill the list.
        if (count($tracks) < self::RECENT_LIMIT) {
            $tracks = array_merge($tracks, self::tracksForDate($now->copy()->subDay()->toDateString(), $now));
        }

        usort($tracks, fn($lhs, $rhs) => $rhs['timestamp'] <=> $lhs['timestamp']);
        $tracks = array_slice($tracks, 0, self::RECENT_LIMIT);

        // Only the newest entry can still be on air.
        foreach ($tracks as $index => $track) {
            $tracks[$index]['current'] = $index === 0 && $track['current'];
        }

        return $tracks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function tracksForDate(string $date, Carbon $now): array
    {
        $blocks = self::getJson('/playlist', ['date' => $date]);
        if ($blocks === null) {
            return [];
        }

        $tracks = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !is_array($block['tracks'] ?? null)) {
                continue;
            }

            $blockDate = is_string($block['date'] ?? null) ? $block['date'] : $date;
            $blockStart = is_string($block['start_time'] ?? null) ? $block['start_time'] : '00:00:00';

            foreach ($block['tracks'] as $item) {
                $track = is_array($item) ? self::playlistItem($item, $blockDate, $blockStart, $now) : null;
                if ($track) {
                    $tracks[] = $track;
                }
            }
        }

        return $tracks;
    }

    private static function playlistItem(array $item, string $date, string $blockStart, Carbon $now): ?array
    {
        // Jingles, commercials and spoken links are not records and have no artist to show.
        if (!empty($item['is_voicetrack']) || !empty($item['is_commblock'])) {
            return null;
        }

        $title = trim((string)($item['tracktitle'] ?? ''));
        $artist = trim((string)($item['artistname'] ?? ''));
        if ($title === '' || $artist === '') {
            return null;
        }

        $start = self::itemStart($date, $blockStart, (string)($item['start_time'] ?? ''));
        if ($start === null || $start->isAfter($now)) {
            return null;
        }

        $duration = max(0, (int)($item['duration'] ?? 0));

        return [
            'id' => self::normalizeId($item['trackid'] ?? null),
            'title' => $title,
            'artist' => $artist,
            'time' => $start->format('H:i'),
            'timestamp' => $start->getTimestamp(),
            'has_image' => !empty($item['has_track_image']),
            'current' => $duration > 0 && $start->copy()->addMilliseconds($duration)->isAfter($now),
        ];
    }

    /**
     * Rebuilds the moment an item started.
     *
     * Toolbox reports item times as a time of day against the block's date, so an item that plays
     * after midnight reads as earlier than the block it belongs to and belongs to the next day.
     */
    private static function itemStart(string $date, string $blockStart, string $time): ?Carbon
    {
        if ($time === '') {
            return null;
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time, wp_timezone());
        } catch (\Exception $exception) {
            return null;
        }

        if (!$start instanceof Carbon) {
            return null;
        }

        return $time < $blockStart ? $start->addDay() : $start;
    }

    private static function log(string $path, string $reason): void
    {
        static $logged = [];

        // One line per problem per request; a broken Toolbox should not flood the log.
        if (isset($logged[$path])) {
            return;
        }

        $logged[$path] = true;
        error_log(sprintf('AeronToolbox: request to %s failed (%s).', $path, $reason));
    }
}
