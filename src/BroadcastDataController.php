<?php

namespace Streekomroep;

use WP_REST_Response;
use WP_REST_Server;

/** Serves the current broadcast state to public theme clients. */
final class BroadcastDataController
{
    private const REST_NAMESPACE = 'zw/v1';
    private const REST_BASE = 'broadcast_data';

    private const TRANSIENT = 'zw_broadcast_data';

    /** Upper bound on the cache lifetime, and thus on how long schedule edits stay invisible. */
    private const CACHE_TTL_MAX = 60;

    /** The URL clients poll for broadcast data. */
    public static function url(): string
    {
        return rest_url(self::REST_NAMESPACE . '/' . self::REST_BASE);
    }

    /** Registers the public read-only broadcast route. */
    public function register_routes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            '/' . self::REST_BASE,
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Returns current radio and television schedule data.
     *
     * @param \WP_REST_Request $request Full details about the request.
     */
    public function get_item($request): WP_REST_Response
    {
        // Clients poll in sync around programme boundaries; the transient absorbs that
        // stampede so only the first request rebuilds the schedule. It expires at the
        // boundary itself, so a new slot is never served from the old cache.
        $payload = get_transient(self::TRANSIENT);
        if (!is_array($payload)) {
            $payload = $this->build();
            set_transient(
                self::TRANSIENT,
                $payload,
                min($payload['fm']['schedule']['refresh_after'], self::CACHE_TTL_MAX)
            );
        }

        // The countdown keeps moving while the payload sits in cache.
        $payload['fm']['schedule']['refresh_after'] = BroadcastSchedule::refreshAfter(
            $payload['fm']['schedule']['current']['end'] ?? null
        );

        $response = rest_ensure_response($payload);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    /** Builds the full response payload; `fm.now`/`next` and `tv` are the legacy contract. */
    private function build(): array
    {
        $schedule = new BroadcastSchedule();
        $current = $schedule->getCurrentRadioBroadcast();
        $next = $schedule->getNextRadioBroadcast($current);

        return [
            'fm' => [
                'now' => $current ? $this->decode($current->getName()) : null,
                'next' => $next ? $this->decode($next->getName()) : null,
                'schedule' => [
                    'current' => $current?->toArray(),
                    'upcoming' => array_map(
                        fn (RadioBroadcast $broadcast) => $broadcast->toArray(),
                        $schedule->getUpcomingRadioBroadcasts(2)
                    ),
                    // Derived from the same broadcast that is being sent, so the two cannot disagree.
                    'refresh_after' => $schedule->getRefreshAfter($current),
                ],
            ],
            'tv' => [
                'today' => array_map(
                    fn ($item) => $this->decode($item->name),
                    $schedule->getToday()->television
                ),
                'tomorrow' => array_map(
                    fn ($item) => $this->decode($item->name),
                    $schedule->getTomorrow()->television
                ),
            ],
        ];
    }

    /** Decodes stored HTML entities for JSON text values. */
    private function decode(string $text): string
    {
        return zw_plain_text($text);
    }
}
