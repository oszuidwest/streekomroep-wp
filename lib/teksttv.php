<?php

namespace Streekomroep;

use WP_Query;
use WP_REST_Response;

class TekstTVAPI
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }

    public function register_api_routes()
    {
        register_rest_route('zw/v1', '/teksttv-slides', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_slides'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('zw/v1', '/teksttv-ticker', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_ticker_messages'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_slides()
    {
        $slides = [];

        if (function_exists('get_field')) {
            $blocks = get_field('teksttv_blokken', 'option');
            $ad_campaigns = $this->get_ad_campaigns();

            if ($blocks) {
                foreach ($blocks as $block) {
                    switch ($block['acf_fc_layout']) {
                        case 'blok_artikelen':
                            $slides = array_merge($slides, $this->get_article_slides($block));
                            break;
                        case 'blok_afbeelding':
                            $image_slide = $this->get_image_slide($block);
                            if ($image_slide) {
                                $slides[] = $image_slide;
                            }
                            break;
                        case 'blok_reclame':
                            $slides = array_merge($slides, $this->get_ad_slides($block, $ad_campaigns));
                            break;
                        case 'blok_fm_programmering':
                            // Implement FM programming slide if needed
                            break;
                    }
                }
            }
        }

        return new WP_REST_Response($slides, 200);
    }

    private function get_article_slides($block)
    {
        $slides = [];
        $args = [
            'post_type'      => 'post',
            'posts_per_page' => $block['aantal_artikelen'],
            'meta_query'     => [
                [
                    'key'     => 'post_in_kabelkrant',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
        ];

        if (!empty($block['Regiofilter'])) {
            $region_ids = array_map(function ($term) {
                return $term->term_id;
            }, $block['Regiofilter']);

            $args['tax_query'][] = [
                'taxonomy' => 'regio',
                'field'    => 'term_id',
                'terms'    => $region_ids,
            ];
        }

        if (!empty($block['categoriefilter'])) {
            $category_ids = array_map(function ($term) {
                return $term->term_id;
            }, $block['categoriefilter']);

            $args['tax_query'][] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $category_ids,
            ];
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $display_days = get_field('post_kabelkrant_dagen', get_the_ID());
                $today = date('N');

                if (!empty($display_days) && !in_array($today, $display_days, true)) {
                    continue;
                }

                $end_date = get_field('post_kabelkrant_datum_uit', get_the_ID());
                if (!empty($end_date) && strtotime($end_date) < current_time('timestamp')) {
                    continue;
                }

                $kabelkrant_content = get_field('post_kabelkrant_content', get_the_ID());
                if (empty($kabelkrant_content)) {
                    continue;
                }

                $slide_image = $this->get_primary_category_image(get_the_ID());

                $pages = preg_split('/\n*-{3,}\n*/', $kabelkrant_content);

                foreach ($pages as $index => $page_content) {
                    $slides[] = [
                        'type'     => 'text',
                        'duration' => 15000, // 15 seconds, adjust as needed
                        'title'    => get_the_title(),
                        'body'     => wpautop(trim($page_content)),
                        'image'    => !empty($slide_image) ? $slide_image : null,
                    ];
                }

                $extra_images = get_field('post_kabelkrant_extra_afbeeldingen', get_the_ID());
                if (!empty($extra_images)) {
                    foreach ($extra_images as $image) {
                        if (!empty($image['url'])) {
                            $slides[] = [
                                'type'     => 'image',
                                'duration' => 7000, // 7 seconds, adjust as needed
                                'url'      => $image['url'],
                            ];
                        }
                    }
                }
            }
            wp_reset_postdata();
        }

        return $slides;
    }

    private function get_primary_category_image($post_id)
    {
        $primary_term_id = get_post_meta($post_id, '_yoast_wpseo_primary_category', true);

        if ($primary_term_id) {
            $term_image = get_field('teksttv_categorie_afbeelding', 'category_' . $primary_term_id);
            if ($term_image) {
                return $term_image['url'];
            }
        }

        // Fallback to post thumbnail if no primary category image
        return get_the_post_thumbnail_url($post_id, 'large');
    }

    private function get_image_slide($block)
    {
        if (!empty($block['afbeelding']) && !empty($block['afbeelding']['url'])) {
            return [
                'type'     => 'image',
                'duration' => intval($block['seconden']) * 1000,
                'url'      => $block['afbeelding']['url'],
            ];
        }

        return null;
    }

    private function get_ad_campaigns()
    {
        $campaigns = [];
        if (function_exists('get_field')) {
            $all_campaigns = get_field('teksttv_reclame', 'option');
            if ($all_campaigns) {
                $current_timestamp = current_time('timestamp');
                foreach ($all_campaigns as $campaign) {
                    $start_timestamp = !empty($campaign['campagne_datum_in']) ? strtotime($campaign['campagne_datum_in'] . ' 00:00:00') : 0;
                    $end_timestamp = !empty($campaign['campagne_datum_uit']) ? strtotime($campaign['campagne_datum_uit'] . ' 23:59:59') : PHP_INT_MAX;

                    if ($current_timestamp >= $start_timestamp && $current_timestamp <= $end_timestamp) {
                        $campaigns[] = $campaign;
                    }
                }
            }
        }
        return $campaigns;
    }

    private function get_ad_slides($block, $campaigns)
    {
        $slides = [];
        $group  = $block['groep'];

        foreach ($campaigns as $campaign) {
            if (in_array($group, $campaign['campagne_groep'], true)) {
                foreach ($campaign['campagne_slides'] as $slide) {
                    if (!empty($slide['url'])) {
                        $slides[] = [
                            'type'     => 'image',
                            'duration' => intval($campaign['campagne_seconden']) * 1000,
                            'url'      => $slide['url'],
                        ];
                    }
                }
            }
        }

        if (!empty($slides)) {
            if (!empty($block['afbeelding_in']) && !empty($block['afbeelding_in']['url'])) {
                array_unshift($slides, [
                    'type'     => 'image',
                    'duration' => 5000, // 5 seconds, adjust as needed
                    'url'      => $block['afbeelding_in']['url'],
                ]);
            }

            if (!empty($block['afbeelding_uit']) && !empty($block['afbeelding_uit']['url'])) {
                $slides[] = [
                    'type'     => 'image',
                    'duration' => 5000, // 5 seconds, adjust as needed
                    'url'      => $block['afbeelding_uit']['url'],
                ];
            }
        }

        return $slides;
    }

    public function get_ticker_messages()
    {
        $ticker_messages = [];

        if (function_exists('get_field')) {
            $ticker_content = get_field('teksttv_ticker', 'option');

            if ($ticker_content) {
                foreach ($ticker_content as $item) {
                    switch ($item['acf_fc_layout']) {
                        case 'ticker_nufm':
                            $message = $this->get_current_fm_program();
                            if ($message) {
                                $ticker_messages[] = [
                                    'message' => $message,
                                ];
                            }
                            break;

                        case 'ticker_straksfm':
                            $message = $this->get_next_fm_program();
                            if ($message) {
                                $ticker_messages[] = [
                                    'message' => $message,
                                ];
                            }
                            break;

                        case 'ticker_tekst':
                            if (!empty($item['ticker_tekst_tekst'])) {
                                $ticker_messages[] = [
                                    'message' => $item['ticker_tekst_tekst'],
                                ];
                            }
                            break;
                    }
                }
            }
        }

        return new WP_REST_Response($ticker_messages, 200);
    }

    private function get_current_fm_program()
    {
        $schedule = new BroadcastSchedule();
        return 'Nu op Radio Rucphen: ' . $schedule->getCurrentRadioBroadcast()->getName();
    }

    private function get_next_fm_program()
    {
        $schedule = new BroadcastSchedule();
        return 'Straks op Radio Rucphen: ' . $schedule->getNextRadioBroadcast()->getName();
    }
}

$teksttv_api = new TekstTVAPI();
