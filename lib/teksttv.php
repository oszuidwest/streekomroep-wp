<?php

namespace ZW\TekstTV;

if (!defined('ABSPATH')) {
    exit;
}

use DateTime;
use DateTimeZone;
use WP_Query;
use WP_REST_Response;
use ZW\Radio\BroadcastSchedule;

class TekstTVAPI
{
    private static $instance = null;

    private $wp_timezone;

    private const SLIDE_DURATIONS = [
        'text' => 20000,    // 20 seconds for text slides
        'image' => 7000,    // 7 seconds for image slides
        'ad_transition' => 5000  // 5 seconds for ad transitions
    ];

    /**
     * Singleton pattern implementatie
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - initialiseert de API
     */
    private function __construct()
    {
        $this->wp_timezone = new DateTimeZone(wp_timezone_string());
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }

    /**
     * Registreert de API endpoints
     */
    public function register_api_routes()
    {
        register_rest_route('zw/v1', '/teksttv-slides', [
            'methods' => 'GET',
            'callback' => [$this, 'get_slides'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('zw/v1', '/teksttv-ticker', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ticker_messages'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Haalt alle slides op voor de TekstTV weergave
     */
    public function get_slides()
    {
        if (!function_exists('get_field')) {
            return new WP_REST_Response([
                'slides' => [],
                '_debug' => $this->get_debug_info(),
                'error' => 'ACF plugin is niet actief'
            ], 200);
        }

        $blocks = get_field('teksttv_blokken', 'option') ?: [];
        $slides = $this->process_blocks($blocks);

        return new WP_REST_Response([
            'slides' => $slides,
            '_debug' => $this->get_debug_info()
        ], 200);
    }

    /**
     * Verwerkt verschillende type content blokken naar slides
     */
    private function process_blocks($blocks)
    {
        $slides = [];
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
                }
            }
        }

        return array_filter($slides);
    }

    /**
     * Genereert slides van artikelen
     */
    private function get_article_slides($block)
    {
        $slides = [];
        $args = [
            'post_type' => 'post',
            'posts_per_page' => $block['aantal_artikelen'],
            'meta_query' => [
                [
                    'key' => 'post_in_kabelkrant',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ];

        // Add region filters if specified
        if (!empty($block['Regiofilter'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'regio',
                'field' => 'term_id',
                'terms' => array_map(function ($term) {
                    return $term->term_id;
                }, $block['Regiofilter'])
            ];
        }

        // Add category filters if specified
        if (!empty($block['categoriefilter'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => array_map(function ($term) {
                    return $term->term_id;
                }, $block['categoriefilter'])
            ];
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Check display days
                $current_date = new DateTime('now', $this->wp_timezone);
                $display_days = get_field('post_kabelkrant_dagen', $post_id);
                if (!empty($display_days) && !in_array($current_date->format('N'), $display_days, true)) {
                    continue;
                }

                // Check end date
                $end_date = get_field('post_kabelkrant_datum_uit', $post_id);
                if (!empty($end_date)) {
                    $end_date = trim($end_date);
                    $end_date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $end_date, $this->wp_timezone);
                    if ($end_date_obj && $current_date > $end_date_obj) {
                        continue;
                    }
                }

                // Get content and images
                $content = get_field('post_kabelkrant_content', $post_id);
                $slide_image = $this->get_primary_category_image($post_id);

                if (!empty($content)) {
                    $pages = preg_split('/\n*-{3,}\n*/', $content);
                    foreach ($pages as $page_content) {
                        $slides[] = [
                            'type' => 'text',
                            'duration' => self::SLIDE_DURATIONS['text'],
                            'title' => get_the_title(),
                            'body' => wpautop(trim($page_content)),
                            'image' => $slide_image ?: null
                        ];
                    }
                }

                // Add extra images
                $extra_images = get_field('post_kabelkrant_extra_afbeeldingen', $post_id);
                if (!empty($extra_images)) {
                    foreach ($extra_images as $image) {
                        if (!empty($image['url'])) {
                            $slides[] = [
                                'type' => 'image',
                                'duration' => self::SLIDE_DURATIONS['image'],
                                'url' => $image['url']
                            ];
                        }
                    }
                }
            }
            wp_reset_postdata();
        }

        return $slides;
    }

    /**
     * Haalt de primaire categorie afbeelding op
     */
    private function get_primary_category_image($post_id)
    {
        $primary_term_id = get_post_meta($post_id, '_yoast_wpseo_primary_category', true);

        if ($primary_term_id) {
            $term_image = get_field('teksttv_categorie_afbeelding', 'category_' . $primary_term_id);
            if (!empty($term_image['url'])) {
                return $term_image['url'];
            }
        }

        return get_the_post_thumbnail_url($post_id, 'large');
    }

    /**
     * Genereert een image slide
     */
    private function get_image_slide($block)
    {
        $current_date = new DateTime('now', $this->wp_timezone);

        // Check date range
        if (!empty($block['datum_in'])) {
            $start_date = DateTime::createFromFormat('d/m/Y', trim($block['datum_in']), $this->wp_timezone);
            if ($start_date && $current_date < $start_date) {
                return null;
            }
        }

        if (!empty($block['datum_uit'])) {
            $end_date = DateTime::createFromFormat('d/m/Y', trim($block['datum_uit']), $this->wp_timezone);
            if ($end_date && $current_date > $end_date) {
                return null;
            }
        }

        // Check display days
        if (!empty($block['dagen'])) {
            $today = $current_date->format('N');
            if (!in_array($today, $block['dagen'], true)) {
                return null;
            }
        }

        if (!empty($block['afbeelding']) && !empty($block['afbeelding']['url'])) {
            return [
                'type' => 'image',
                'duration' => intval($block['seconden']) * 1000,
                'url' => $block['afbeelding']['url']
            ];
        }

        return null;
    }

    /**
     * Haalt actieve advertentie campagnes op
     */
    private function get_ad_campaigns()
    {
        $campaigns = [];
        if (function_exists('get_field')) {
            $all_campaigns = get_field('teksttv_reclame', 'option');
            if ($all_campaigns) {
                $current_date = new DateTime('now', $this->wp_timezone);

                foreach ($all_campaigns as $campaign) {
                    if ($this->is_campaign_active($campaign, $current_date)) {
                        $campaigns[] = $campaign;
                    }
                }
            }
        }
        return $campaigns;
    }

    /**
     * Controleert of een campagne actief is
     */
    private function is_campaign_active($campaign, $current_date)
    {
        if (!empty($campaign['campagne_datum_in'])) {
            $start_date = DateTime::createFromFormat('d/m/Y', trim($campaign['campagne_datum_in']), $this->wp_timezone);
            if ($start_date && $current_date < $start_date) {
                return false;
            }
        }

        if (!empty($campaign['campagne_datum_uit'])) {
            $end_date = DateTime::createFromFormat('d/m/Y', trim($campaign['campagne_datum_uit']), $this->wp_timezone);
            if ($end_date && $current_date > $end_date) {
                return false;
            }
        }

        return true;
    }

    /**
     * Genereert advertentie slides
     */
    private function get_ad_slides($block, $campaigns)
    {
        $slides = [];
        $group = $block['groep'];

        foreach ($campaigns as $campaign) {
            if (in_array($group, $campaign['campagne_groep'], true)) {
                foreach ($campaign['campagne_slides'] as $slide) {
                    if (!empty($slide['url'])) {
                        $slides[] = [
                            'type' => 'image',
                            'duration' => intval($campaign['campagne_seconden']) * 1000,
                            'url' => $slide['url']
                        ];
                    }
                }
            }
        }

        // Add intro and outro images
        if (!empty($slides)) {
            if (!empty($block['afbeelding_in']) && !empty($block['afbeelding_in']['url'])) {
                array_unshift($slides, [
                    'type' => 'image',
                    'duration' => self::SLIDE_DURATIONS['ad_transition'],
                    'url' => $block['afbeelding_in']['url']
                ]);
            }

            if (!empty($block['afbeelding_uit']) && !empty($block['afbeelding_uit']['url'])) {
                $slides[] = [
                    'type' => 'image',
                    'duration' => self::SLIDE_DURATIONS['ad_transition'],
                    'url' => $block['afbeelding_uit']['url']
                ];
            }
        }

        return $slides;
    }

    /**
     * Haalt ticker berichten op
     */
    public function get_ticker_messages()
    {
        $messages = [];
        $debug_info = $this->get_debug_info();

        if (function_exists('get_field')) {
            $ticker_content = get_field('teksttv_ticker', 'option');

            if ($ticker_content) {
                foreach ($ticker_content as $item) {
                    switch ($item['acf_fc_layout']) {
                        case 'ticker_nufm':
                            $message = $this->get_current_fm_program();
                            if ($message) {
                                $messages[] = ['message' => $message];
                            }
                            break;

                        case 'ticker_straksfm':
                            $message = $this->get_next_fm_program();
                            if ($message) {
                                $messages[] = ['message' => $message];
                            }
                            break;

                        case 'ticker_tekst':
                            if (!empty($item['ticker_tekst_tekst'])) {
                                $messages[] = ['message' => $item['ticker_tekst_tekst']];
                            }
                            break;
                    }
                }
            }
        }

        return new WP_REST_Response([
            'messages' => $messages,
            '_debug' => $debug_info
        ], 200);
    }

    /**
     * Haalt het huidige FM programma op
     */
    private function get_current_fm_program()
    {
        $schedule = new BroadcastSchedule();
        return 'Nu op Radio Rucphen: ' . $schedule->getCurrentRadioBroadcast()->getName();
    }

    /**
     * Haalt het volgende FM programma op
     */
    private function get_next_fm_program()
    {
        $schedule = new BroadcastSchedule();
        return 'Straks op Radio Rucphen: ' . $schedule->getNextRadioBroadcast()->getName();
    }

    /**
     * Verzamelt debug informatie
     */
    private function get_debug_info()
    {
        $current_date = new DateTime('now', $this->wp_timezone);
        return [
            'current_time' => $current_date->format('Y-m-d H:i:s'),
            'timezone' => [
                'wp_timezone_string' => wp_timezone_string(),
                'wp_timezone_offset' => get_option('gmt_offset'),
                'php_timezone' => date_default_timezone_get(),
                'server_time' => date('Y-m-d H:i:s')
            ]
        ];
    }
}

// Initialiseer de plugin
add_action('init', function () {
    TekstTVAPI::get_instance();
});
