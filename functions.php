<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since   Timber 0.1
 */

/**
 * If you are installing Timber as a Composer dependency in your theme, you'll need this block
 * to load your dependencies and initialize Timber. If you are using Timber via the WordPress.org
 * plug-in, you can safely delete this block.
 */

use Streekomroep\Video;

$composer_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
    $timber = new Timber\Timber();
}

/**
 * This ensures that Timber is loaded and available as a PHP class.
 * If not, it gives an error message to help direct developers on where to activate
 */
if (!class_exists('Timber')) {

    add_action(
        'admin_notices',
        function () {
            echo '<div class="error"><p>Timber not activated. Make sure you activate the plugin in <a href="' . esc_url(admin_url('plugins.php#timber')) . '">' . esc_url(admin_url('plugins.php')) . '</a></p></div>';
        }
    );

    add_filter(
        'template_include',
        function ($template) {
            return get_stylesheet_directory() . '/static/no-timber.html';
        }
    );
    return;
}

if (!class_exists('ACF')) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="error"><p>ACF not activated. Make sure you activate the plugin in <a href="' . esc_url(admin_url('plugins.php#timber')) . '">' . esc_url(admin_url('plugins.php')) . '</a></p></div>';
        }
    );
    return;
}

require 'vimeo-thumbnail.php';

/**
 * Sets the directories (inside your theme) to find .twig files
 */
Timber::$dirname = array('templates', 'views');

/**
 * By default, Timber does NOT autoescape values. Want to enable Twig's autoescape?
 * No prob! Just set this value to true
 */
Timber::$autoescape = false;

require_once 'src/BroadcastDay.php';
require_once 'src/BroadcastSchedule.php';
require_once 'src/Post.php';
require_once 'src/RadioBroadcast.php';
require_once 'src/SafeObject.php';
require_once 'src/Site.php';
require_once 'src/TelevisionBroadcast.php';
require_once 'src/Video.php';

// Use default class for all post types, except for pages.
add_filter('Timber\PostClassMap', function () {
    return \Streekomroep\Post::class;
});

new \Streekomroep\Site();

/**
 * Include ACF Fields. These are saved as local JSON
 * This is not a function of Timber so we declare them afer the Timber specific functions
 */
if (function_exists('get_field')) {

    add_filter('acf/settings/save_json', 'streekomroep_acf_json_save_point');

    function streekomroep_acf_json_save_point($path)
    {

        // update path
        $path = get_stylesheet_directory() . '/streekomroep-acf-json';

        // return
        return $path;

    }

    add_filter('acf/settings/load_json', 'streekomroep_acf_json_load_point');

    function streekomroep_acf_json_load_point($paths)
    {

        // remove original path (optional)
        unset($paths[0]);

        // append path
        $paths[] = get_stylesheet_directory() . '/streekomroep-acf-json';


        // return
        return $paths;

    }
}

if (function_exists('acf_add_options_page')) {

    acf_add_options_page(array(
        'page_title' => 'Radio Instellingen',
        'menu_title' => 'Radio Instellingen',
        'menu_slug' => 'radio-instellingen',
        'capability' => 'edit_posts',
        'icon_url' => 'dashicons-playlist-audio',
        'redirect' => false
    ));
}

if (function_exists('acf_add_options_page')) {

    acf_add_options_page(array(
        'page_title' => 'TV Instellingen',
        'menu_title' => 'TV Instellingen',
        'menu_slug' => 'tv-instellingen',
        'capability' => 'edit_posts',
        'icon_url' => 'dashicons-format-video',
        'redirect' => false
    ));
}

if (function_exists('acf_add_options_page')) {

    acf_add_options_page(array(
        'page_title' => 'Desking',
        'menu_title' => 'Desking',
        'menu_slug' => 'desking',
        'capability' => 'edit_posts',
        'icon_url' => 'dashicons-layout',
        'redirect' => false
    ));
}

function zw_parse_query(WP_Query $query)
{
    if (is_admin()) return;

    if ($query->is_post_type_archive(['fm', 'tv'])) {
        $query->set('nopaging', 1);
    }

    if ($query->is_post_type_archive('tv')) {
        $query->set('meta_query', [
            [
                'key' => 'tv_show_actief',
                'value' => 1
            ]
        ]);
    }
}

add_action('parse_query', 'zw_parse_query');

add_filter('get_avatar_url', 'zw_get_avatar_url', 10, 3);

add_action('rest_api_init', 'zw_rest_api_init');

function zw_rest_api_init()
{
    register_rest_field(
        'post',
        'kabelkrant_text',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                $data = null;
                $show = (bool)get_field('post_in_kabelkrant', $post_arr['id']);
                if ($show) {
                    $data = get_field('post_kabelkrant_content', $post_arr['id']);
                }

                return $data;
            },
        ]
    );

    register_rest_route('zw/v1', '/broadcast_data', array(
        'methods' => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $schedule = new \Streekomroep\BroadcastSchedule();
            $response = [];

            $options = get_fields('option');

            $radiotext = '';
            $currentRadioBroadcast = $schedule->getCurrentRadioBroadcast();

            switch ($options['radio_rds_rt_optie']) {
                case 1: // Statische tekst
                    $radiotext = $options['radio_rds_rt_statische_tekst'];
                    break;
                case 2: // Programmanaam
                case 3: // Programmanaam en presentator(en)
                    $title = $currentRadioBroadcast->getName();
                    $hosts = [];
                    if ($currentRadioBroadcast->show) {
                        $overrule = get_field('fm_show_overschrijf_programmanaam', $currentRadioBroadcast->show->ID);
                        if (!empty($overrule)) {
                            $title = $overrule;
                        }

                        foreach (get_field('fm_show_presentator', $currentRadioBroadcast->show->ID) as $user) {
                            $user = get_user_by('id', $user);
                            $hosts[] = $user->display_name;
                        }
                    }

                    if ($options['radio_rds_rt_optie'] == 3 && count($hosts) > 0) {
                        $radiotext = sprintf('%s met %s', $title, implode(' ', $hosts));
                    } else {
                        $radiotext = $title;
                    }

                    break;
            };

            $response['fm'] = [
                'now' => $currentRadioBroadcast->getName(),
                'next' => $schedule->getNextRadioBroadcast()->getName(),
                'rds' => [
                    'program' => $options['radio_rds_zendernaam'],
                    'radiotext' => $radiotext,
                ]
            ];

            $response['tv'] = [
                'today' => array_map(function ($item) {
                    return $item->name;
                }, $schedule->getToday()->television),
                'tomorrow' => array_map(function ($item) {
                    return $item->name;
                }, $schedule->getTomorrow()->television),
            ];

            $commercials = [];

            if (!is_array($options['tv_reclame_slides']))
                $options['tv_reclame_slides'] = [];

            $now = new DateTime();
            foreach ($options['tv_reclame_slides'] as $slide) {
                // Ignore slides with no image
                if ($slide['tv_reclame_afbeelding'] === false) continue;

                $start = DateTime::createFromFormat('d/m/Y', $slide['tv_reclame_start']);
                $start->setTime(0, 0);
                // TODO: is end date inclusive?
                $end = DateTime::createFromFormat('d/m/Y', $slide['tv_reclame_eind']);
                $end->setTime(24, 0);

                if ($now >= $start && $now < $end) {
                    $commercials[] = $slide['tv_reclame_afbeelding']['url'];
                }
            }

            $response['commercials'] = $commercials;

            return $response;
        }
    ));
}

/**
 * @param $url
 * @param $id_or_email The Gravatar to retrieve. Accepts a user ID, Gravatar MD5 hash,
 * user email, WP_User object, WP_Post object, or WP_Comment object.
 * @param $args
 * @return string
 */
function zw_get_avatar_url($url, $id_or_email, $args)
{
    $id = null;
    if (is_numeric($id_or_email)) {
        $id = absint($id_or_email);
    }
    if ($id === null) {
        return $url;
    }
    $field = get_field('gebruiker_profielfoto', 'user_' . $id);
    if ($field === null || $field === false) {
        return $url;
    }

    $src = wp_get_attachment_image_src($field['id'], [$args['size'], $args['size']]);
    return $src[0];
}

add_filter('oembed_fetch_url', 'zw_oembed_fetch_url', 10, 3);

function zw_oembed_fetch_url($provider, $url, $args)
{
    if (strpos($provider, 'https://publish.twitter.com/oembed') === 0) {
        $provider = add_query_arg('align', 'center', $provider);
    }
    return $provider;
}

add_filter('embed_oembed_html', 'zw_embed_oembed_html', 99, 4);

function zw_embed_oembed_html_iframe($cache, $url, $attr, $post_ID)
{

    $doc = new DOMDocument();
    $doc->loadHTML('<div id="oembed">' . $cache . '</div>');

    $iframes = $doc->getElementsByTagName('iframe');
    /** @var DOMElement $iframe */
    foreach ($iframes as $iframe) {
        $width = intval($iframe->getAttribute('width'));
        $iframe->removeAttribute('width');
        $height = intval($iframe->getAttribute('height'));
        $iframe->removeAttribute('height');
        $iframe->setAttribute('class', 'absolute inset-0 w-full h-full');
        $padding = $height / $width * 100;
        $out = '';
        $out .= sprintf('<div class="relative" style="height: 0; padding-bottom: %f%%;">', $padding);
        $out .= $doc->saveHTML($iframe);
        $out .= '</div>';

        return $out;

    }

    return $cache;
}

/**
 * @param $cache (string|false) The cached HTML result, stored in post meta.
 * @param $url (string) The attempted embed URL.
 * @param $attr (array) An array of shortcode attributes.
 * @param $post_ID (int) Post ID.
 * @return string
 */
function zw_embed_oembed_html($cache, $url, $attr, $post_ID)
{
    if (preg_match('#https?://youtu\.be/.*#i', $url) || preg_match('#https?://((m|www)\.)?youtube\.com/watch.*#i', $url)) {
        return zw_embed_oembed_html_iframe($cache, $url, $attr, $post_ID);
    }

    if (preg_match('#https?://(.+\.)?vimeo\.com/.*#i', $url)) {
        return zw_embed_oembed_html_iframe($cache, $url, $attr, $post_ID);
    }

    return $cache;
}

function zw_get_socials()
{
    $seo_data = get_option('wpseo_social');
    if ($seo_data === false) {
        return [];
    }

    $socials = [
        ['name' => 'Facebook', 'class' => 'facebook', 'field' => 'facebook_site'],
        ['name' => 'Instagram', 'class' => 'instagram', 'field' => 'instagram_url'],
        ['name' => 'LinkedIN', 'class' => 'linkedin', 'field' => 'linkedin_url'],
        ['name' => 'Pinterest', 'class' => 'pinterest', 'field' => 'pinterest_url'],
        ['name' => 'Twitter', 'class' => 'twitter', 'field' => 'twitter_site'],
        ['name' => 'YouTube', 'class' => 'youtube', 'field' => 'youtube_url']
    ];

    $out = [];
    foreach ($socials as $item) {
        if (!isset($seo_data[$item['field']]))
            continue;

        $value = trim($seo_data[$item['field']]);
        if (empty($value))
            continue;

        if ($item['field'] == 'twitter_site') {
            $value = 'https://twitter.com/' . $value;
        }

        $item['link'] = $value;
        $out[] = $item;
    }

    return $out;
}

wp_embed_register_handler('zw-readmore', '#^(.*)$#', 'zw_embed_handler');

function zw_embed_handler($matches, $attr, $url, $rawattr)
{
    $self = parse_url(get_site_url(), PHP_URL_HOST);
    $host = parse_url($url, PHP_URL_HOST);
    if (!in_array($host, [$self, 'www.zuidwestfm.nl']))
        return false;

    $postId = url_to_postid($url);
    if ($postId === 0)
        return false;

    if (get_post_type($postId) !== 'post')
        return false;

    return '[zw_embed]' . $url . '[/zw_embed]';
}

add_shortcode('zw_embed', 'zw_embed');

function zw_embed($atts, $content, $shortcode_tag)
{
    $url = $content;

    $postId = url_to_postid($url);
    if ($postId === 0)
        return false;

    $post = Timber::get_post($postId);
    $html = Timber::compile('embed.twig', ['post' => $post]);

    return $html;
}

function vimeo_get($url)
{
    $args = [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'bearer ' . get_field('vimeo_access_token', 'option')
        ]
    ];

    $url = 'https://api.vimeo.com' . $url;
    $parsed = parse_url($url);
    if (isset($parsed['query'])) {
        $url .= '&';
    } else {
        $url .= '?';
    }

    $url .= 'fields=name,description,uri,link,pictures,parent_folder.uri&sizes=295x166';

    return wp_remote_get($url, $args);
}

function vimeo_get_project_videos($project_id)
{
    $next = '/me/projects/' . $project_id . '/videos?per_page=100';
    $data = [];
    while ($next !== null) {
        $response = vimeo_get($next);
        if ($response instanceof WP_Error) {
            throw new Exception($response->get_error_message());
        }

        if ($response['response']['code'] !== 200) {
            throw new Exception('Error while fetching vimeo data: ' . $response['body']);
        }

        $body = json_decode($response['body']);;
        $next = $body->paging->next;
        $data = array_merge($data, $body->data);
    }

    return $data;
}

function zw_get_page_by_template($template)
{
    $pages = get_pages([
        'meta_key' => '_wp_page_template',
        'meta_value' => $template
    ]);

    if (count($pages) == 0)
        return null;

    return Timber::get_post($pages[0]->ID);
}

/*
 * Sort fragments by newness when assigning a fragment to an article
 */
add_filter('acf/fields/relationship/query/name=post_gekoppeld_fragment', 'zw_sort_fragments_selector', 10, 3);
function zw_sort_fragments_selector($args, $field, $post_id)
{
    // Sort by ID
    $args['orderby'] = 'ID';

    // Newest (Highest) ID first
    $args['order'] = 'DESC';

    return $args;
}

add_action('init', function () {
    register_deactivation_hook(__FILE__, 'zw_deactivate');

    if (!wp_next_scheduled('zw_hourly')) {
        wp_schedule_event(time(), 'hourly', 'zw_hourly');
    }
});

function zw_deactivate()
{
    wp_clear_scheduled_hook('zw_hourly');
}

add_action('zw_hourly', 'zw_project_cron');
function zw_project_cron()
{
    $shows = Timber::get_posts([
        'post_type' => 'tv',
        'ignore_sticky_posts' => true,
        'nopaging' => true,
    ]);

    foreach ($shows as $show) {

        $project_id = $show->meta('tv_show_gemist_locatie');
        if (empty($project_id)) {
            update_post_meta($show->ID, 'vimeo_data', []);
            continue;
        }

        $videos = [];
        try {
            $videos = vimeo_get_project_videos($project_id);
        } catch (Exception $e) {
            error_log($e);
        }
        update_post_meta($show->ID, 'vimeo_data', $videos);
    }
}

function zw_sort_videos(array $videos)
{
    /** @var Video[] $vimeo */
    $vimeo = array_map(function ($a) {
        return new Video($a);
    }, $videos);

    $now = new DateTime();
    $vimeo = array_filter($vimeo, function ($video) use ($now) {
        $date = $video->getBroadcastDate();

        // Ignore videos with no valid date
        if (!$date) return false;

        // Ignore videos with a date in the future
        if ($date > $now) return false;

        return true;
    });

    usort($vimeo, function (Video $left, Video $right) {
        return $right->getBroadcastDate() <=> $left->getBroadcastDate();
    });

    return $vimeo;
}

class Jetpack_Options
{
    public static function get_option_and_ensure_autoload()
    {
        return 'rectangular';
    }

    public static function get_option($option)
    {
        return get_option($option);
    }
}

/**
 * Image handling filters
 * The first filter disables resizing of 'big' images by WordPress, since we do this in timber
 * The second filter removes the width-element from the shortcode element to prevent images from showing up too big
 */
add_filter('big_image_size_threshold', '__return_false');
add_filter('img_caption_shortcode_width', '__return_false');

/**
 * Remove block editor (Gutenberg) CSS
 * This function dequeues the CSS for the block editor which this theme does not use
 */
function zw_remove_wp_block_library_css()
{
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-block-style');
}

add_action('wp_enqueue_scripts', 'zw_remove_wp_block_library_css', 100);

class Jetpack
{
    public static function get_content_width()
    {
        return 672;
    }

    public static function get_active_modules()
    {
        return ['carousel'];
    }
}

function jetpack_photon_url($image_url, $args = array(), $scheme = null)
{
//    var_dump(__FUNCTION__);
    return $image_url;
}

include 'modules/assets.php';
include 'modules/tiled-gallery/tiled-gallery.php';
