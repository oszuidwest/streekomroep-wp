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
use Yoast\WP\SEO\Config\Schema_IDs;

const ZW_TV_META_VIDEOS = 'bunny_data';
const ZW_BUNNY_LIBRARY_TV = -1;
const ZW_BUNNY_LIBRARY_FRAGMENTEN = -2;

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
            echo '<div class="error"><p>ACF not activated. Make sure you activate the plugin in <a href="' . esc_url(admin_url('plugins.php#timber')) . '">' . esc_url(
                    admin_url('plugins.php')
                ) . '</a></p></div>';
        }
    );
    return;
}

if (!class_exists('Yoast\WP\SEO\Main')) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="error"><p>Yoast not activated. Make sure you activate the plugin in <a href="' . esc_url(admin_url('plugins.php')) . '">' . esc_url(
                    admin_url('plugins.php')
                ) . '</a></p></div>';
        }
    );
    return;
}

function zw_bunny_get(string $accessKey, string $url)
{
    $args = [
        'timeout' => 30,
        'headers' => [
            'AccessKey' => $accessKey,
            'accept' => 'application/json',
        ]
    ];

    return wp_remote_get($url, $args);
}


add_filter('pre_oembed_result', 'zw_filter_pre_oembed_result', 10, 3);

function zw_bunny_get_video(\Streekomroep\BunnyCredentials $credentials, \Streekomroep\BunnyVideoId $id)
{
    $response = zw_bunny_get($credentials->apiKey, 'https://video.bunnycdn.com/library/' . $id->libraryId . '/videos/' . $id->videoId);
    if ($response instanceof WP_Error) {
        return null;
    }

    if ($response['response']['code'] !== 200) {
        return null;
    }

    /** @var \Streekomroep\BunnyVideo $video */
    $video = json_decode($response['body']);

    return $video;
}

function zw_filter_pre_oembed_result($default, $url, $args)
{
    $id = zw_bunny_parse_url($url);
    if (!$id) {
        return $default;
    }

    $credentials = zw_bunny_credentials_get($id->libraryId);
    if (!$credentials) {
        return $default;
    }

    $video = zw_bunny_get_video($credentials, $id);
    if (!$video) {
        return $default;
    }

    $video = new Video($credentials, $video);

    if (!$video->isAvailable()) {
        return false;
    }

    $out = '';

    $out .= '<video class="video-js vjs-fluid vjs-big-play-centered playsinline" data-setup="{}" controls';
    $out .= ' poster="' . htmlspecialchars($video->getThumbnail()) . '"';
    $out .= '>';
    $out .= '<source src="' . htmlspecialchars($video->getPlaylistUrl()) . '" type="application/x-mpegURL">';
    $out .= '<source src="' . htmlspecialchars($video->getMP4Url()) . '" type="video/mp4">';
    $out .= '</video>';

    return $out;
}

require 'fragment-thumbnail.php';

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
require_once 'src/BunnyCredentials.php';
require_once 'src/BunnyMeta.php';
require_once 'src/BunnyVideo.php';
require_once 'src/BunnyVideoId.php';
require_once 'src/Post.php';
require_once 'src/Fragment.php';
require_once 'src/RadioBroadcast.php';
require_once 'src/SafeObject.php';
require_once 'src/Site.php';
require_once 'src/TelevisionBroadcast.php';
require_once 'src/Video.php';

// Use default class for all post types, except for pages.
add_filter('Timber\PostClassMap', function () {
    return [
        'post' => \Streekomroep\Post::class,
        'fragment' => \Streekomroep\Fragment::class,
        'fm' => \Streekomroep\Post::class,
        'tv' => \Streekomroep\Post::class,
    ];
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
        'page_title' => 'Radio instellingen',
        'menu_title' => 'Radio instellingen',
        'menu_slug' => 'radio-instellingen',
        'capability' => 'manage_options',
        'icon_url' => 'dashicons-playlist-audio',
        'redirect' => false
    ));
}

if (function_exists('acf_add_options_page')) {

    acf_add_options_page(array(
        'page_title' => 'TV instellingen',
        'menu_title' => 'TV instellingen',
        'menu_slug' => 'tv-instellingen',
        'capability' => 'manage_options',
        'icon_url' => 'dashicons-format-video',
        'redirect' => false
    ));
}

if (function_exists('acf_add_options_page')) {

    acf_add_options_page(array(
        'page_title' => 'Desking',
        'menu_title' => 'Desking',
        'menu_slug' => 'desking',
        'capability' => 'manage_options',
        'icon_url' => 'dashicons-layout',
        'redirect' => false
    ));
}

function zw_parse_query(WP_Query $query)
{
    if (!$query->is_main_query() || $query->is_admin) return;

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
    $fields = [
        'image_wide' => 'dossier_afbeelding_breed',
        'image_tall' => 'dossier_afbeelding_hoog'
    ];
    foreach ($fields as $apiField => $acfField) {
        register_rest_field(
            'dossier',
            $apiField,
            [
                'get_callback' => function ($term_arr, $attr, $request, $object_type) use ($acfField) {
                    $term = get_term($term_arr['id'], 'dossier');
                    $field = get_field($acfField, $term);
                    if ($field !== null) {
                        return $field['url'];
                    }

                    return null;
                }
            ]);
    }

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

    register_rest_field(
        'post',
        'ranking',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                $selected = get_field('post_ranking', $post_arr['id']);
                return $selected;
            },
        ]
    );

    register_rest_field(
        'fragment',
        'posts',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                $posts = fragment_get_posts($post_arr['id']);
                return array_map(function ($post) {
                    return $post->id;
                }, $posts);
            },
        ]
    );

    register_rest_field(
        'fragment',
        'source',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                if (get_field('fragment_type', $post_arr['id']) === 'Video') {
                    return 'bunny';
                }

                return null;
            },
        ]
    );

    register_rest_field(
        'fragment',
        'vimeo_id',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                // TODO: add support for Bunny to API
                return null;
            },
        ]
    );

    register_rest_field(
        'tv',
        'episodes',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                $data = [];
                $videos = get_post_meta($post_arr['id'], ZW_TV_META_VIDEOS, true);
                if (!is_array($videos)) {
                    $videos = [];
                }
                $credentials = zw_bunny_credentials_get(ZW_BUNNY_LIBRARY_TV);
                $videos = zw_sort_videos($credentials, $videos);

                foreach ($videos as $video) {
                    $d = [];
                    $d['sources'] = [];
                    $d['sources'][] = [
                        'src' => $video->getMP4Url(),
                        'type' => 'video/mp4'
                    ];

                    $d['sources'][] = [
                        'src' => $video->getPlaylistUrl(),
                        'type' => 'application/x-mpegURL'
                    ];

                    $d['title'] = $video->getName();
                    $d['description'] = $video->getDescription();
                    $d['date'] = $video->getBroadcastDate()->format('c');
                    $d['thumbnail'] = $video->getThumbnail();
                    $data[] = $d;
                }

                return $data;
            },
        ]
    );

    $types = ['tv', 'fm'];
    foreach ($types as $type) {
        register_rest_field(
            $type,
            'active',
            [
                'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                    return get_field($object_type . '_show_actief', $post_arr['id']);
                }
            ]);

        register_rest_field(
            $type,
            'presenters',
            [
                'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                    $data = get_field($object_type . '_show_presentator', $post_arr['id']);
                    if ($data === false) {
                        $data = [];
                    }
                    return $data;
                }
            ]);
    }

    register_rest_route('zw/v1', '/broadcast_data', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
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

            $now = new DateTime('now', wp_timezone());
            foreach ($options['tv_reclame_slides'] as $slide) {
                // Ignore slides with no image
                if ($slide['tv_reclame_afbeelding'] === false) continue;

                $start = DateTime::createFromFormat('d/m/Y', $slide['tv_reclame_start'], wp_timezone());
                $start->setTime(0, 0);
                // TODO: is end date inclusive?
                $end = DateTime::createFromFormat('d/m/Y', $slide['tv_reclame_eind'], wp_timezone());
                $end->setTime(24, 0);

                if ($now >= $start && $now < $end) {
                    $commercials[] = $slide['tv_reclame_afbeelding']['url'];
                }
            }

            $response['commercials'] = $commercials;

            return $response;
        }
    ]);

    register_rest_route('zw/v1', '/desking', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => function (WP_REST_Request $request) {
            $output = [];
            $blocks = get_field('desking_blokken_voorpagina', 'option');

            foreach ($blocks as $block) {
                switch ($block['acf_fc_layout']) {
                    case 'blok_top_stories':
                        $output[] = [
                            'type' => 'topstories',
                        ];
                        break;

                    case 'blok_tv_gemist':
                        $output[] = [
                            'type' => 'recent_tv',
                            'title' => trim($block['tekst_boven_videos']),
                            'deduplicate' => $block['ontdubbel'],
                            'count' => $block['aantal_videos'],
                        ];
                        break;

                    case 'blok_fm_gemist':
                        $output[] = [
                            'type' => 'recent_fm',
                            'title' => trim($block['tekst_boven_audio']),
                        ];
                        break;

                    case 'blok_fragmenten_carrousel':
                        $title = $block['tekst_boven_fragmenten'];
                        if (empty($title)) {
                            $title = null;
                        }
                        $output[] = [
                            'type' => 'fragments',
                            'title' => $title,
                        ];
                        break;

                    case 'blok_artikel_lijst':
                        $title = $block['tekst_boven_artikelen'];
                        if (empty($title)) {
                            $title = null;
                        }
                        $output[] = [
                            'type' => 'posts',
                            'title' => $title,
                            'count' => $block['aantal_artikelen'],
                            'offset' => $block['offset'],
                        ];
                        break;

                    case 'blok_dossier':
                        $term = get_term($block['selecteer_dossier'], 'dossier');
                        $title = $block['tekst_boven_dossier'];
                        if (empty($title)) {
                            $title = $term->name;
                        }

                        $output[] = [
                            'type' => 'dossier',
                            'title' => $title,
                            'count' => $block['aantal_artikelen'],
                            'offset' => $block['offset'],
                            'term_id' => $term->term_id,
                        ];
                        break;

                    case 'blok_dossiers_carrousel':
                        $title = $block['tekst_boven_dossiers'];
                        if (empty($title)) {
                            $title = 'Dossiers';
                        }

                        $output[] = [
                            'type' => 'dossier_carousel',
                            'title' => $title,
                        ];
                        break;

                    case 'blok_nu_op_fmtv':
                        $output[] = [
                            'type' => 'now_onair'
                        ];
                        break;

                    case 'blok_meest_gelezen':
                        $title = $block['tekst_boven_artikelen'];
                        if (empty($title)) {
                            $title = null;
                        }

                        $output[] = [
                            'type' => 'popular',
                            'title' => $title,
                        ];
                        break;
                }
            }

            return $output;
        }
    ]);
}

function zw_bunny_parse_url($url)
{
    if (preg_match('|^https://iframe.mediadelivery.net/play/(\d+)/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})|', $url, $m)) {
        return new \Streekomroep\BunnyVideoId((int)$m[1], $m[2]);
    }

    return null;
}

function zw_bunny_credentials_get(int $libraryId)
{
    if ($libraryId === ZW_BUNNY_LIBRARY_TV || $libraryId == get_field('bunny_cdn_library_id', 'option')) {
        $libraryId = get_field('bunny_cdn_library_id', 'option');
        $hostname = get_field('bunny_cdn_hostname', 'option');
        $apiKey = get_field('bunny_cdn_api_key', 'option');
        return new \Streekomroep\BunnyCredentials($libraryId, $hostname, $apiKey);
    } elseif ($libraryId === ZW_BUNNY_LIBRARY_FRAGMENTEN || $libraryId == get_field('bunny_cdn_library_id_fragmenten', 'option')) {
        $libraryId == get_field('bunny_cdn_library_id_fragmenten', 'option');
        $hostname = get_field('bunny_cdn_hostname_fragmenten', 'option');
        $apiKey = get_field('bunny_cdn_api_key_fragmenten', 'option');
        return new \Streekomroep\BunnyCredentials($libraryId, $hostname, $apiKey);
    }

    return null;
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
    if ($id_or_email instanceof WP_User) {
        $id = $id_or_email->ID;
    } else if (is_numeric($id_or_email)) {
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
    // Ignore warnings (invalid entities, unknown tags)
    @$doc->loadHTML('<div id="oembed">' . $cache . '</div>');

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

function zw_bunny_get_collection(\Streekomroep\BunnyCredentials $credentials, $collectionId)
{
    $url = 'https://video.bunnycdn.com/library/' . $credentials->libraryId . '/videos';
    $query = [
        'itemsPerPage' => 100,
        'collection' => $collectionId,
    ];

    $page = 1;
    $data = [];
    while (true) {
        $query['page'] = $page;
        $response = zw_bunny_get($credentials->apiKey, $url . '?' . build_query($query));
        if ($response instanceof WP_Error) {
            throw new Exception($response->get_error_message());
        }

        if ($response['response']['code'] !== 200) {
            throw new Exception('Error while fetching bunny data: ' . $response['body']);
        }

        $body = json_decode($response['body']);;

        $data = array_merge($data, $body->items);
        if (count($data) >= $body->totalItems) {
            break;
        }

        $page++;
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

/*
 * Create custom cron to refresh Bunny content every 10min
 */
add_filter('cron_schedules', function ($schedules) {
    // add a '10mins' schedule to the existing set
    $schedules['10mins'] = array(
        'interval' => 10 * 60,
        'display' => __('Every 10 minutes')
    );
    return $schedules;
});

add_action('init', function () {
    if (!wp_next_scheduled('zw_10mins')) {
        wp_schedule_event(time(), '10mins', 'zw_10mins');
    }
});

add_action('switch_theme', 'zw_deactivate');

function zw_deactivate()
{
    // Legacy hook
    wp_clear_scheduled_hook('zw_hourly');
    wp_clear_scheduled_hook('zw_10mins');
}

add_action('zw_10mins', 'zw_project_cron');
function zw_project_cron()
{
    $shows = Timber::get_posts([
        'post_type' => 'tv',
        'ignore_sticky_posts' => true,
        'nopaging' => true,
    ]);

    $credentials = zw_bunny_credentials_get(ZW_BUNNY_LIBRARY_TV);

    foreach ($shows as $show) {
        $collectionId = $show->meta('tv_show_gemist_locatie');
        if (empty($collectionId)) {
            update_post_meta($show->ID, ZW_TV_META_VIDEOS, []);
            continue;
        }

        try {
            $videos = zw_bunny_get_collection($credentials, $collectionId);
            update_post_meta($show->ID, ZW_TV_META_VIDEOS, $videos);
        } catch (Exception $e) {
            error_log($e);
        }
    }
}

function zw_sort_videos(\Streekomroep\BunnyCredentials $credentials, array $videos)
{
    /** @var Video[] $videos */
    $videos = array_map(function ($a) use ($credentials) {
        return new Video($credentials, $a);
    }, $videos);

    // Filter videos that are still being uploaded or transcoded
    $videos = array_filter($videos, function ($video) {
        return $video->isAvailable();
    });

    $now = new DateTime('now', wp_timezone());
    $videos = array_filter($videos, function ($video) use ($now) {
        $date = $video->getBroadcastDate();

        // Ignore videos with no valid date
        if (!$date) return false;

        // Ignore videos with a date in the future
        if ($date > $now) return false;

        return true;
    });

    usort($videos, function (Video $left, Video $right) {
        return $right->getBroadcastDate() <=> $left->getBroadcastDate();
    });

    return $videos;
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    add_filter('yoast_seo_development_mode', '__return_true');
}
add_filter('wpseo_schema_graph_pieces', 'add_custom_schema_piece', 11, 2);
add_filter('wpseo_schema_webpage', 'zw_seo_add_fragment_video', 10, 2);
add_filter('wpseo_schema_article', 'zw_seo_article_add_region', 10, 2);

class VideoData
{
    public $duration;
    public $description;
    public $name;
    public $uploadDate;
    public $thumbnailUrl;
    public $contentUrl;
}

function fragment_get_video($id)
{
    $fragment = get_post($id);
    $video = new VideoData();
    $video->duration = (int)get_field('fragment_duur', $id, false);
    $video->name = get_the_title($fragment);
    $video->description = get_the_content(null, false, $fragment);
    $video->uploadDate = get_the_date('c', $fragment);
    $video->thumbnailUrl = get_the_post_thumbnail_url($fragment);
    $video->contentUrl = ''; // TODO: get video url

    return $video;
}

class VideoObject extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece
{
    public $video;

    public function __construct(VideoData $video)
    {
        $this->video = $video;
    }

    public function generate()
    {
        $timespan = $this->video->duration;
        $hour = floor($timespan / (60 * 60));
        $min = floor($timespan / 60) % 60;
        $sec = $timespan % 60;

        return [
            '@type' => 'VideoObject',
            '@id' => $this->context->canonical . '#video',
            "name" => $this->video->name,
            "description" => $this->video->description,
            "thumbnailUrl" => [
                $this->video->thumbnailUrl
            ],
            "uploadDate" => $this->video->uploadDate,
            "duration" => sprintf('PT%dH%dM%dS', $hour, $min, $sec),
            "isFamilyFriendly" => true,
            "inLanguage" => 'nl',
            "contentUrl" => $this->video->contentUrl,
        ];
    }

    public function is_needed()
    {
        return true;
    }
}

/**
 * @param $timber_post
 * @return mixed
 */
function fragment_get_posts($fragmentID)
{
    return Timber::get_posts(array(
        'post_type' => 'post',
        'ignore_sticky_posts' => true,
        'meta_query' => array(
            array(
                'key' => 'post_gekoppeld_fragment', // name of custom field
                'value' => '"' . $fragmentID . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                'compare' => 'LIKE'
            )
        )
    ));
}

function add_custom_schema_piece($pieces, $context)
{
    if (is_singular('fragment')) {
        $type = get_field('fragment_type', false, false);
        if ($type === 'Video') {
            $video = fragment_get_video($context->post->ID);
            $pieces[] = new VideoObject($video);
        }
    }

    return $pieces;
}

function zw_seo_add_fragment_video($data, $context)
{
    if (!is_singular('fragment'))
        return $data;

    $type = get_field('fragment_type', false, false);
    if ($type !== 'Video')
        return $data;

    $data['video'] = [
        ['@id' => $context->canonical . '#video']
    ];
    return $data;
}

function zw_seo_article_add_region($data, $context)
{
    /** @var WP_Term[] $terms */
    $terms = wp_get_post_terms($context->post->ID, 'regio');
    if (is_string($data['articleSection'])) {
        $data['articleSection'] = [$data['articleSection']];
    }
    foreach ($terms as $term) {
        $data['articleSection'][] = $term->name;
    }

    return $data;
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

function zw_add_videojs()
{
    wp_enqueue_style('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/8.3.0/video-js.min.css');
    wp_enqueue_script('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/8.3.0/video.min.js');
    wp_enqueue_script('video.js.nl', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/8.3.0/lang/nl.min.js');
}

add_action('wp_enqueue_scripts', 'zw_remove_wp_block_library_css', 100);
add_action('wp_enqueue_scripts', 'zw_add_videojs');



class ZW_Video_Modified_Time_Presenter extends \Yoast\WP\SEO\Presenters\Abstract_Indexable_Tag_Presenter
{
    public function __construct($date)
    {
        $this->date = $date;
    }

    protected $tag_format = '<meta property="article:modified_time" content="%s" />';

    public function get()
    {
        return $this->helpers->date->format($this->date);
    }
}

add_action('template_redirect', function () {
    if (!is_admin() && is_singular('tv') && isset($_GET['v'])) {
        $videos = get_post_meta(get_the_ID(), ZW_TV_META_VIDEOS, true);
        if (!is_array($videos)) {
            $videos = [];
        }

        $credentials = zw_bunny_credentials_get(ZW_BUNNY_LIBRARY_TV);
        $videos = zw_sort_videos($credentials, $videos);

        $videoId = $_GET['v'];
        $video = null;
        foreach ($videos as $item) {
            if ($item->getId() == $videoId) {
                $video = $item;
                break;
            }
        }

        if ($video) {
            $canonical = function ($url) use ($video) {
                return $url . '?v=' . $video->getId();
            };

            $title = function ($a) use ($video) {
                return $video->getName();
            };

            $description = function () use ($video) {
                return $video->getDescription();
            };

            $thumbnail = function () use ($video) {
                return $video->getThumbnail();
            };

            add_filter('wpseo_title', $title);
            add_filter('wpseo_metadesc', $description);
            add_filter('wpseo_canonical', $canonical);

            add_filter('wpseo_opengraph_title', $title);
            add_filter('wpseo_opengraph_desc', $description);
            add_filter('wpseo_opengraph_type', function () {
                return 'video.episode';
            });
            add_action('wpseo_add_opengraph_images', function ($images) use ($video) {
                $images->add_image(['url' => $video->getThumbnail()]);
            });
            add_filter('wpseo_opengraph_url', $canonical);

            add_filter('wpseo_twitter_title', $title);
            add_filter('wpseo_twitter_description', $description);
            add_filter('wpseo_twitter_image', $thumbnail);

            add_filter('wpseo_frontend_presenters', function ($presenters) use ($video) {
                foreach ($presenters as $i => $presenter) {
                    if ($presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Article_Modified_Time_Presenter) {
                        $presenters[$i] = new ZW_Video_Modified_Time_Presenter($video->getBroadcastDate()->format('c'));
                    } else if ($presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Article_Published_Time_Presenter) {
                        unset($presenters[$i]);
                    }
                }

                return $presenters;
            });

            add_filter('wpseo_frontend_presentation', function ($presentation, $context) {
                $presentation->model->open_graph_image_id = null;
                $presentation->model->open_graph_image_meta = null;
                $presentation->model->open_graph_image = null;
                return $presentation;
            }, 10, 2);
        }

    }
});

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
