<?php

namespace Streekomroep;

use Carbon\Carbon;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

/** Serves the current broadcast state to public theme clients. */
final class BroadcastDataController extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'zw/v1';
        $this->rest_base = 'broadcast_data';
    }

    /** Registers the public read-only broadcast route. */
    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_item'],
                    'permission_callback' => '__return_true',
                ],
                'schema' => [$this, 'get_public_item_schema'],
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
        $schedule = new BroadcastSchedule();
        $current = $schedule->getCurrentRadioBroadcast();
        $next = $schedule->getNextRadioBroadcast();

        $response = rest_ensure_response([
            'fm' => [
                'now' => $current ? $this->decode($current->getName()) : null,
                'next' => $next ? $this->decode($next->getName()) : null,
                'schedule' => [
                    'current' => $current ? $this->formatRadioBroadcast($current) : null,
                    'upcoming' => array_map(
                        fn (RadioBroadcast $broadcast) => $this->formatRadioBroadcast(
                            $broadcast,
                            $this->whenLabel($broadcast->start)
                        ),
                        $schedule->getUpcomingRadioBroadcasts(2)
                    ),
                    'refresh_after' => $current ? max(
                        1,
                        $current->end->timestamp - Carbon::now(wp_timezone())->timestamp
                    ) : 30,
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
        ]);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }

    /** Describes the response for REST discovery and client validation. */
    public function get_item_schema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'broadcast-data',
            'type' => 'object',
            'properties' => [
                'fm' => [
                    'type' => 'object',
                    'properties' => [
                        'now' => ['type' => ['string', 'null']],
                        'next' => ['type' => ['string', 'null']],
                        'schedule' => [
                            'type' => 'object',
                            'properties' => [
                                'current' => [
                                    'type' => ['object', 'null'],
                                    'properties' => $this->getRadioBroadcastSchema(),
                                ],
                                'upcoming' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => $this->getRadioBroadcastSchema(),
                                    ],
                                ],
                                'refresh_after' => [
                                    'type' => 'integer',
                                    'minimum' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
                'tv' => [
                    'type' => 'object',
                    'properties' => [
                        'today' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'tomorrow' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** Returns the shared schema for current and upcoming radio slots. */
    private function getRadioBroadcastSchema(): array
    {
        return [
            'name' => ['type' => 'string'],
            'start' => ['type' => 'integer'],
            'end' => ['type' => 'integer'],
            'start_time' => ['type' => 'string'],
            'end_time' => ['type' => 'string'],
            'label' => ['type' => ['string', 'null']],
            'show' => [
                'type' => ['object', 'null'],
                'properties' => [
                    'title' => ['type' => 'string'],
                    'link' => ['type' => 'string', 'format' => 'uri'],
                    'makers' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'photo' => [
                                    'type' => ['object', 'null'],
                                    'properties' => [
                                        'src' => ['type' => 'string', 'format' => 'uri'],
                                        'srcset' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** Formats one radio slot for REST consumers. */
    private function formatRadioBroadcast(RadioBroadcast $broadcast, ?string $label = null): array
    {
        return [
            'name' => $this->decode($broadcast->getName()),
            'start' => $broadcast->start->timestamp,
            'end' => $broadcast->end->timestamp,
            'start_time' => $broadcast->start->format('H:i'),
            'end_time' => $broadcast->end->format('H:i'),
            'label' => $label,
            'show' => $broadcast->show ? $this->formatShow($broadcast->show) : null,
        ];
    }

    /** Formats the public show fields used by the live page. */
    private function formatShow(\Timber\Post $show): array
    {
        return [
            'title' => $this->decode($show->title()),
            'link' => $show->link(),
            'makers' => array_map(function (array $maker) {
                $photo = $maker['fm_show_maker_foto'] ?? null;

                return [
                    'name' => $this->decode((string) ($maker['fm_show_maker_naam'] ?? '')),
                    'photo' => $photo ? [
                        'src' => zw_imgproxy($photo, 44, 44),
                        'srcset' => zw_imgproxy($photo, 88, 88) . ' 2x',
                    ] : null,
                ];
            }, zw_acf_rows($show->meta('fm_show_makers'))),
        ];
    }

    /** Labels future slots that do not occur today. */
    private function whenLabel(Carbon $start): ?string
    {
        if ($start->isToday()) {
            return null;
        }

        return $start->isTomorrow() ? 'morgen' : BroadcastDay::WEEKDAY_NAMES[$start->dayOfWeekIso];
    }

    /** Decodes stored HTML entities for JSON text values. */
    private function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
