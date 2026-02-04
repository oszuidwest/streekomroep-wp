<?php

namespace Streekomroep;

use DateTime;
use WP_Query;
use WP_REST_Response;

/**
 * Text TV API handler - manages content for Tekst TV system at https://github.com/oszuidwest/teksttv
 */
class TekstTVAPI
{
    private static $instance = null;

    // Slide durations in milliseconds
    private const SLIDE_DURATIONS = [
        'text' => 20000,
        'image' => 7000,
        'ad_transition' => 5000,
        'weather' => 15000
    ];

    // Cache duration for weather data (1 hour)
    private const WEATHER_CACHE_DURATION = 3600;

    // Singleton pattern implementation
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Initialize the API and register hooks
    private function __construct()
    {
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }

    // Register REST API endpoints
    public function register_api_routes()
    {
        register_rest_route('zw/v1', '/teksttv-slides', [
            'methods' => 'GET',
            'callback' => [$this, 'get_slides'],
            'permission_callback' => '__return_true',
            'args' => [
                'kanaal' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Kanaal slug (bijv. breda, roosendaal)',
                    'validate_callback' => [$this, 'validate_channel']
                ]
            ]
        ]);

        register_rest_route('zw/v1', '/teksttv-ticker', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ticker_messages'],
            'permission_callback' => '__return_true',
            'args' => [
                'kanaal' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Kanaal slug (bijv. breda, roosendaal)',
                    'validate_callback' => [$this, 'validate_channel']
                ]
            ]
        ]);
    }

    // Validate channel parameter against defined channels
    public function validate_channel(string $param, \WP_REST_Request $request, string $key): bool
    {
        if (!defined('ZW_TEKSTTV_CHANNELS')) {
            return false;
        }
        return array_key_exists($param, ZW_TEKSTTV_CHANNELS);
    }

    // Get ACF options page ID for a channel
    private function get_channel_options_id($channel)
    {
        return 'teksttv_' . $channel;
    }

    // Retrieve all slides for text TV display
    public function get_slides(\WP_REST_Request $request)
    {
        if (!function_exists('get_field')) {
            return new WP_REST_Response([], 200);
        }

        $channel = $request->get_param('kanaal');
        $options_id = $this->get_channel_options_id($channel);

        $blocks = get_field('teksttv_blokken', $options_id) ?: [];
        $slides = $this->process_blocks($blocks, $options_id);

        return new WP_REST_Response($slides, 200);
    }

    // Process different types of content blocks into slides
    private function process_blocks($blocks, $options_id)
    {
        $slides = [];
        $ad_campaigns = $this->get_ad_campaigns($options_id);

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
                    case 'blok_weer':
                        $weather_slides = $this->get_weather_slides($block);
                        if ($weather_slides) {
                            $slides = array_merge($slides, $weather_slides);
                        }
                        break;
                }
            }
        }

        return array_filter($slides);
    }

    // Generate slides from articles with filter support
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

        // Add region filter if specified
        if (!empty($block['Regiofilter'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'regio',
                'field' => 'term_id',
                'terms' => array_map(function ($term) {
                    return $term->term_id;
                }, $block['Regiofilter'])
            ];
        }

        // Add category filter if specified
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
                $current_date = current_datetime();
                $display_days = get_field('post_kabelkrant_dagen', $post_id);
                if (!empty($display_days) && !in_array($current_date->format('N'), $display_days, true)) {
                    continue;
                }

                // Check end date
                $end_date = get_field('post_kabelkrant_datum_uit', $post_id);
                if (!empty($end_date)) {
                    $end_date = trim($end_date);
                    $end_date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $end_date, wp_timezone());
                    if ($end_date_obj && $current_date > $end_date_obj) {
                        continue;
                    }
                }

                // Create slides from content and images
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

                // Add extra images as separate slides
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

    // Get primary category image or fallback to post thumbnail
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

    // Generate image slide with date range and day restrictions
    private function get_image_slide($block)
    {
        $current_date = current_datetime();

        // Check start date if set
        if (!empty($block['datum_in'])) {
            $start_date = DateTime::createFromFormat('d/m/Y', trim($block['datum_in']), wp_timezone());
            if ($start_date && $current_date < $start_date) {
                return null;
            }
        }

        // Check end date if set
        if (!empty($block['datum_uit'])) {
            $end_date = DateTime::createFromFormat('d/m/Y', trim($block['datum_uit']), wp_timezone());
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

    // Retrieve active advertising campaigns
    private function get_ad_campaigns($options_id)
    {
        $campaigns = [];
        if (function_exists('get_field')) {
            $all_campaigns = get_field('teksttv_reclame', $options_id);
            if ($all_campaigns) {
                $current_date = current_datetime();

                foreach ($all_campaigns as $campaign) {
                    if ($this->is_campaign_active($campaign, $current_date)) {
                        $campaigns[] = $campaign;
                    }
                }
            }
        }
        return $campaigns;
    }

    // Check if campaign is currently active based on date range
    private function is_campaign_active($campaign, $current_date)
    {
        if (!empty($campaign['campagne_datum_in'])) {
            $start_date = DateTime::createFromFormat('Y-m-d', trim($campaign['campagne_datum_in']), wp_timezone());
            if ($start_date && $current_date < $start_date->setTime(0, 0)) {
                return false;
            }
        }

        if (!empty($campaign['campagne_datum_uit'])) {
            $end_date = DateTime::createFromFormat('Y-m-d', trim($campaign['campagne_datum_uit']), wp_timezone());
            if ($end_date && $current_date > $end_date->setTime(23, 59, 59)) {
                return false;
            }
        }

        return true;
    }

    // Generate advertisement slides with intro and outro transitions
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

        // Add transition slides if main slides exist
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

    // Retrieve ticker messages including radio program info
    public function get_ticker_messages(\WP_REST_Request $request)
    {
        $messages = [];

        if (function_exists('get_field')) {
            $channel = $request->get_param('kanaal');
            $options_id = $this->get_channel_options_id($channel);

            $ticker_content = get_field('teksttv_ticker', $options_id);

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

        return new WP_REST_Response($messages, 200);
    }

    // Get current radio program name
    private function get_current_fm_program()
    {
        try {
            $schedule = new BroadcastSchedule();
            return 'Nu op Radio Rucphen: ' . $schedule->getCurrentRadioBroadcast()->getName();
        } catch (\Exception $e) {
            return 'Radio Rucphen - Altijd nieuws, altijd muziek';
        }
    }

    // Get next radio program name
    private function get_next_fm_program()
    {
        try {
            $schedule = new BroadcastSchedule();
            return 'Straks op Radio Rucphen: ' . $schedule->getNextRadioBroadcast()->getName();
        } catch (\Exception $e) {
            return '';
        }
    }

    // Generate weather slide from block configuration
    private function get_weather_slides($block)
    {
        $location = $block['plaats'];
        $title = $block['titel'];

        try {
            $weather_data = $this->fetch_weather_data($location);
            if (!$weather_data) {
                return null;
            }

            return [$this->format_weather_slide($weather_data, $title)];
        } catch (\Exception $e) {
            error_log('Weather API error: ' . $e->getMessage());
            return null;
        }
    }

    // Fetch weather data from OpenWeather OneCall API 3.0 with caching
    private function fetch_weather_data($location)
    {
        $api_key = get_field('openweather_api_key', 'teksttv_instellingen');
        if (empty($api_key)) {
            return null;
        }

        // Create cache key from location
        $cache_key = 'weather_' . sanitize_title($location);
        $cached_data = get_transient($cache_key);

        if ($cached_data !== false) {
            return $cached_data;
        }

        // First, geocode the location to get coordinates
        $coords = $this->geocode_location($location, $api_key);
        if (!$coords) {
            throw new \Exception('Could not geocode location: ' . $location);
        }

        // Fetch from OpenWeather OneCall API 3.0
        $url = add_query_arg([
            'lat' => $coords['lat'],
            'lon' => $coords['lon'],
            'appid' => $api_key,
            'units' => 'metric',
            'lang' => 'nl',
            'exclude' => 'minutely,hourly,alerts'
        ], 'https://api.openweathermap.org/data/3.0/onecall');

        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception('API returned status ' . $status_code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || !isset($data['daily'])) {
            throw new \Exception('Invalid API response');
        }

        // Process daily forecasts (API returns up to 8 days)
        $processed = $this->process_weather_data($data, $coords['name']);

        // Cache for 1 hour
        set_transient($cache_key, $processed, self::WEATHER_CACHE_DURATION);

        return $processed;
    }

    // Geocode location name to coordinates using OpenWeather Geocoding API
    private function geocode_location($location, $api_key)
    {
        $geo_cache_key = 'weather_geo_' . sanitize_title($location);
        $cached_coords = get_transient($geo_cache_key);

        if ($cached_coords !== false) {
            return $cached_coords;
        }

        $url = add_query_arg([
            'q' => $location,
            'limit' => 1,
            'appid' => $api_key
        ], 'https://api.openweathermap.org/geo/1.0/direct');

        $response = wp_remote_get($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data[0]['lat'])) {
            return null;
        }

        $coords = [
            'lat' => $data[0]['lat'],
            'lon' => $data[0]['lon'],
            'name' => $data[0]['local_names']['nl'] ?? $data[0]['name'] ?? $location
        ];

        // Cache geocoding for 1 week (locations don't move)
        set_transient($geo_cache_key, $coords, WEEK_IN_SECONDS);

        return $coords;
    }

    // Process OneCall API 3.0 daily forecasts
    private function process_weather_data($data, $city_name)
    {
        $daily = [];

        // OneCall API provides daily forecasts directly (up to 8 days)
        foreach ($data['daily'] as $day_data) {
            $date = new DateTime('@' . $day_data['dt']);
            $date->setTimezone(wp_timezone());

            $daily[] = [
                'date' => $date,
                'temp_min' => $day_data['temp']['min'],
                'temp_max' => $day_data['temp']['max'],
                'icon' => $day_data['weather'][0]['icon'] ?? '01d',
                'description' => ucfirst($day_data['weather'][0]['description'] ?? ''),
                'wind_speed' => $day_data['wind_speed'] ?? 0,
                'wind_deg' => $day_data['wind_deg'] ?? 0,
                'wind_gust' => $day_data['wind_gust'] ?? null
            ];
        }

        return [
            'city' => $city_name,
            'days' => $daily
        ];
    }

    // Format weather data into slide structure
    private function format_weather_slide($weather_data, $title)
    {
        $days_output = [];

        foreach ($weather_data['days'] as $index => $day) {
            $wind_speed = $day['wind_speed'] ?? 0;
            $wind_deg = $day['wind_deg'] ?? 0;

            $days_output[] = [
                'date' => $this->format_dutch_date($day['date']),
                'day_short' => $index === 0 ? 'vandaag' : date_i18n('D', $day['date']->getTimestamp()),
                'temp_min' => round($day['temp_min']),
                'temp_max' => round($day['temp_max']),
                'description' => $day['description'],
                'icon' => $day['icon'],
                'wind_direction' => $this->wind_deg_to_direction($wind_deg),
                'wind_beaufort' => $this->wind_speed_to_beaufort($wind_speed)
            ];
        }

        return [
            'type' => 'weather',
            'duration' => self::SLIDE_DURATIONS['weather'],
            'title' => $title,
            'location' => $weather_data['city'],
            'days' => $days_output
        ];
    }

    // Convert wind degrees to compass direction
    private function wind_deg_to_direction($deg)
    {
        $directions = ['N', 'NNO', 'NO', 'ONO', 'O', 'OZO', 'ZO', 'ZZO', 'Z', 'ZZW', 'ZW', 'WZW', 'W', 'WNW', 'NW', 'NNW'];
        $index = round($deg / 22.5) % 16;
        return $directions[$index];
    }

    // Convert wind speed (m/s) to Beaufort scale
    private function wind_speed_to_beaufort($speed)
    {
        $beaufort_scale = [0.3, 1.6, 3.4, 5.5, 8.0, 10.8, 13.9, 17.2, 20.8, 24.5, 28.5, 32.7];
        foreach ($beaufort_scale as $bft => $threshold) {
            if ($speed < $threshold) {
                return $bft;
            }
        }
        return 12;
    }

    // Format date in Dutch (e.g., "woensdag 5 feb")
    private function format_dutch_date($date)
    {
        return date_i18n('l j M', $date->getTimestamp());
    }
}

// Initialize the TekstTV API
add_action('init', function () {
    TekstTVAPI::get_instance();
});
