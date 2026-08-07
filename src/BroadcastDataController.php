<?php

namespace Streekomroep;

use WP_REST_Response;
use WP_REST_Server;

final class BroadcastDataController
{
    private const REST_NAMESPACE = 'zw/v1';
    private const REST_BASE = 'broadcast_data';

    private const TRANSIENT = 'zw_broadcast_data';

    /** Maximum time schedule edits can remain hidden by this cache. */
    private const CACHE_TTL_MAX = 60;

    public static function url(): string
    {
        return rest_url(self::REST_NAMESPACE . '/' . self::REST_BASE);
    }

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

    public function get_item($request): WP_REST_Response
    {
        // A short transient reduces duplicate rebuilds when clients poll at slot boundaries.
        $payload = get_transient(self::TRANSIENT);
        if (!is_array($payload)) {
            $payload = $this->build();
            set_transient(
                self::TRANSIENT,
                $payload,
                min($payload['fm']['schedule']['refresh_after'], self::CACHE_TTL_MAX)
            );
        }

        // Recompute the relative countdown from the cached absolute end time.
        $payload['fm']['schedule']['refresh_after'] = BroadcastSchedule::refreshAfter(
            $payload['fm']['schedule']['current']['end'] ?? null
        );

        $response = rest_ensure_response($payload);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    /** Preserves the legacy `fm.now`, `fm.next` and `tv` fields alongside the live-page schedule. */
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

    private function decode(string $text): string
    {
        return zw_plain_text($text);
    }
}
