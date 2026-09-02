<?php
/** Streekomroep theme bootstrap */

use Timber\Timber;

const ZW_TV_META_VIDEOS = 'bunny_data';
const ZW_BUNNY_LIBRARY_TV = -1;

require __DIR__ . '/vendor/autoload.php';

Timber::init();

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


add_filter('pre_oembed_result', function ($default, $url, $args) {
    return \Streekomroep\VideoRenderer::renderFromUrl($url) ?: $default;
}, 10, 3);
add_filter('acf/update_value/name=fragment_url', 'zw_normalize_bunny_url');
add_filter('content_save_pre', 'zw_normalize_bunny_url');

function zw_normalize_bunny_url($value)
{
    if (is_string($value)) {
        $value = str_replace('://iframe.mediadelivery.net/', '://player.mediadelivery.net/', $value);
    }

    return $value;
}

/**
 * Normalizes an ACF repeater value to rows.
 *
 * ACF returns a row count when the field-key reference is missing.
 */
function zw_acf_rows($value): array
{
    return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
}

/**
 * Decodes HTML entities before context-specific escaping.
 */
function zw_plain_text(string $text): string
{
    return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Returns valid FM schedule rows.
 *
 * @return array<int, array<string, mixed>>
 */
function zw_fm_schedule_rows($value): array
{
    $weekdays = array_values(\Streekomroep\BroadcastDay::WEEKDAY_NAMES);
    $time_pattern = '/\A(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d\z/';

    return array_values(array_filter(zw_acf_rows($value), function ($row) use ($weekdays, $time_pattern) {
        $days = $row['fm_show_dagen'] ?? null;
        $start = $row['fm_show_starttijd'] ?? null;
        $end = $row['fm_show_eindtijd'] ?? null;

        return is_array($days)
            && $days !== []
            && count($days) === count(array_filter(
                $days,
                fn ($day) => is_string($day) && in_array($day, $weekdays, true)
            ))
            && is_string($start)
            && preg_match($time_pattern, $start)
            && is_string($end)
            && preg_match($time_pattern, $end);
    }));
}

/**
 * Sorts FM shows by their earliest broadcast slot in the configured week order;
 * unscheduled shows follow in natural title order.
 *
 * @param \Streekomroep\Post[] $shows FM shows to sort.
 *
 * @return \Streekomroep\Post[] Sorted FM shows.
 */
function zw_fm_shows_in_broadcast_order(array $shows): array
{
    $weekdays = array_values(\Streekomroep\BroadcastDay::WEEKDAY_NAMES);
    if (get_field('radio_week_start', 'option') === 'zondag') {
        array_unshift($weekdays, array_pop($weekdays));
    }
    $positions = array_flip($weekdays);

    $keyed = [];
    foreach ($shows as $show) {
        $slots = [];
        foreach ($show->schedule() as $entry) {
            foreach ($entry['fm_show_dagen'] as $day) {
                $slots[] = [$positions[$day], $entry['fm_show_starttijd']];
            }
        }

        $keyed[] = [$slots ? min($slots) : [PHP_INT_MAX, ''], zw_plain_text($show->title()), $show];
    }

    usort($keyed, function ($lhs, $rhs) {
        return $lhs[0] <=> $rhs[0] ?: strnatcasecmp($lhs[1], $rhs[1]);
    });

    return array_column($keyed, 2);
}

require 'fragment-thumbnail.php';

Timber::$dirname = ['templates'];

require_once 'lib/content_images.php';
require_once 'lib/input_sanitizer.php';
require_once 'lib/push_adapter.php';
require_once 'lib/search.php';
require_once 'lib/collapsible.php';
require_once 'lib/tinymce.php';

// TODO: Remove this loader and migration_fm_makers.php after the FM-maker migration.
if (is_admin()) {
    require_once 'lib/migration_fm_makers.php';
}

add_filter('timber/post/classmap', function ($base) {
    $custom = [
        'post' => \Streekomroep\Post::class,
        'fragment' => \Streekomroep\Fragment::class,
        'fm' => \Streekomroep\Post::class,
        'tv' => \Streekomroep\Post::class,
    ];

    return array_merge($base, $custom);
});

new \Streekomroep\Site();

add_filter('acf/settings/save_json', 'streekomroep_acf_json_save_point');

function streekomroep_acf_json_save_point($path)
{
    return get_stylesheet_directory() . '/streekomroep-acf-json';
}

add_filter('acf/settings/load_json', 'streekomroep_acf_json_load_point');

function streekomroep_acf_json_load_point($paths)
{
    unset($paths[0]);
    $paths[] = get_stylesheet_directory() . '/streekomroep-acf-json';
    return $paths;
}

add_action('acf/init', function () {
    if (!function_exists('acf_add_options_page') || !function_exists('acf_add_options_sub_page')) {
        add_action(
            'admin_notices',
            function () {
                echo '<div class="error"><p>' . esc_html__(
                    'ACF Options Pages are not available. Use Secure Custom Fields or Advanced Custom Fields Pro.',
                    'streekomroep'
                ) . '</p></div>';
            }
        );

        return;
    }

    acf_add_options_page([
        'page_title' => 'Radio instellingen',
        'menu_title' => 'Radio instellingen',
        'menu_slug' => 'radio-instellingen',
        'capability' => 'manage_options',
        'icon_url' => 'dashicons-playlist-audio',
        'redirect' => false
    ]);

    acf_add_options_page([
        'page_title' => 'TV instellingen',
        'menu_title' => 'TV instellingen',
        'menu_slug' => 'tv-instellingen',
        'capability' => 'manage_options',
        'icon_url' => 'dashicons-format-video',
        'redirect' => false
    ]);

    acf_add_options_page([
        'page_title' => 'Desking',
        'menu_title' => 'Desking',
        'menu_slug' => 'desking',
        'capability' => 'manage_options',
        'icon_url' => 'dashicons-layout',
        'redirect' => false
    ]);
});

function zw_parse_query(WP_Query $query)
{
    if (!$query->is_main_query() || $query->is_admin) {
        return;
    }

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
    (new \Streekomroep\BroadcastDataController())->register_routes();

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
            ]
        );
    }

    register_rest_field(
        'fragment',
        'posts',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                $posts = fragment_get_posts($post_arr['id']);
                return array_map(function ($post) {
                    return $post->id;
                }, $posts->to_array());
            },
        ]
    );

    register_rest_field(
        'fragment',
        'fragment_type',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                return strtolower(get_field('fragment_type', $post_arr['id']));
            },
        ]
    );

    register_rest_field(
        'fragment',
        'sources',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                $type = get_field('fragment_type', $post_arr['id']);
                if ($type === \Streekomroep\Fragment::TYPE_VIDEO) {
                    $url = get_field('fragment_url', $post_arr['id'], false);
                    $video = \Streekomroep\VideoRenderer::resolveVideo($url);
                    if ($video && $video->isAvailable()) {
                        return $video->getSources();
                    }
                } elseif ($type === \Streekomroep\Fragment::TYPE_AUDIO) {
                    return [
                        ['type' => 'audio/mpeg', 'src' => get_field('fragment_url', $post_arr['id'], false)]
                    ];
                }

                return [];
            },
        ]
    );

    register_rest_field(
        'tv',
        'episodes',
        [
            'get_callback' => function ($post_arr, $attr, $request, $object_type) {
                $data = [];
                $videos = \Streekomroep\VideoCollection::forTvShow($post_arr['id']);

                foreach ($videos as $video) {
                    $data[] = [
                        'sources' => $video->getSources(),
                        'title' => $video->getName(),
                        'description' => $video->getDescription(),
                        'date' => $video->getBroadcastDate()?->format('c'),
                        'thumbnail' => $video->getThumbnail(),
                    ];
                }

                return $data;
            },
        ]
    );

    register_rest_field(
        'tv',
        'active',
        [
            'get_callback' => function ($post_arr) {
                return get_field('tv_show_actief', $post_arr['id']);
            }
        ]
    );

    // TV presenters remain WordPress user IDs for API compatibility.
    register_rest_field(
        'tv',
        'presenters',
        [
            'get_callback' => function ($post_arr) {
                return get_field('tv_show_presentator', $post_arr['id']) ?: [];
            }
        ]
    );

    // FM presenters expose embedded profiles instead of user IDs.
    register_rest_field(
        'fm',
        'presenters',
        [
            'get_callback' => function ($post_arr) {
                return array_values(array_map(function ($maker) {
                    return [
                        'naam' => (string) ($maker['fm_show_maker_naam'] ?? ''),
                        'bio' => (string) ($maker['fm_show_maker_bio'] ?? ''),
                        'foto' => empty($maker['fm_show_maker_foto']) ? null : (string) $maker['fm_show_maker_foto'],
                    ];
                }, zw_acf_rows(get_field('fm_show_makers', $post_arr['id']))));
            }
        ]
    );
}

/**
 * Replaces a Gravatar URL with the user's ACF profile image.
 *
 * @param string $url         Default avatar URL.
 * @param mixed  $id_or_email Avatar identifier.
 * @param array  $args        Avatar arguments.
 * @return string Avatar URL.
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

    $imageId = get_field('gebruiker_profielfoto', 'user_' . $id);
    if (!$imageId) {
        return $url;
    }

    $src = wp_get_attachment_image_src($imageId, [$args['size'], $args['size']]);
    return $src ? $src[0] : $url;
}

/**
 * Routes guest authors to GuestAuthor and keeps regular accounts on Timber\User
 * so zw_get_avatar_url() remains effective.
 *
 * CoAuthorsPlusUser ships inside vendor/timber, so referencing it is safe even
 * when the Co-Authors Plus plugin is inactive (this branch then never matches).
 *
 * @param string   $class Timber user class.
 * @param \WP_User $user  User being built; wraps the Co-Authors Plus record for guest authors.
 * @return string User class.
 */
function zw_timber_user_class($class, $user)
{
    if ($class !== \Timber\Integration\CoAuthorsPlus\CoAuthorsPlusUser::class) {
        return $class;
    }

    // Read data directly: `$user->type` triggers WP_User::__isset() and a user-meta lookup.
    if (($user->data->type ?? null) === 'guest-author') {
        return \Streekomroep\GuestAuthor::class;
    }

    return \Timber\User::class;
}

add_filter('timber/user/class', 'zw_timber_user_class', 11, 2);

add_filter('oembed_fetch_url', 'zw_oembed_fetch_url', 10, 3);

function zw_oembed_fetch_url($provider, $url, $args)
{
    if (str_starts_with($provider, 'https://publish.twitter.com/oembed')) {
        $provider = add_query_arg('align', 'center', $provider);
    }
    return $provider;
}

add_filter('embed_oembed_html', 'zw_embed_oembed_html', 99, 4);

function zw_embed_oembed_html_iframe($cache, $url, $attr, $post_ID)
{

    $doc = new DOMDocument();
    // Provider fragments may trigger libxml warnings for malformed markup.
    @$doc->loadHTML('<div id="oembed">' . $cache . '</div>');

    /** @var DOMElement|null $iframe */
    $iframe = $doc->getElementsByTagName('iframe')->item(0);
    if (!$iframe) {
        return $cache;
    }

    $width = intval($iframe->getAttribute('width'));
    $height = intval($iframe->getAttribute('height'));

    if ($width <= 0 || $height <= 0) {
        return $cache;
    }

    $iframe->removeAttribute('width');
    $iframe->removeAttribute('height');
    $iframe->setAttribute('class', 'absolute inset-0 w-full h-full');
    $padding = $height / $width * 100;

    return sprintf('<div class="relative" style="height: 0; padding-bottom: %f%%;">', $padding)
    . $doc->saveHTML($iframe)
    . '</div>';
}

/**
 * Makes YouTube and Vimeo embeds responsive.
 *
 * @param string $cache   Cached embed markup.
 * @param string $url     Embed URL.
 * @param array  $attr    Shortcode attributes.
 * @param int    $post_ID Post ID.
 * @return string Embed markup.
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

    $out = [];

    if (!empty($seo_data['facebook_site'])) {
        $out[] = [
            'name' => 'Facebook',
            'link' => $seo_data['facebook_site']
        ];
    }

    if (!empty($seo_data['twitter_site'])) {
        $out[] = [
            'name' => 'X',
            'link' => 'https://x.com/' . $seo_data['twitter_site']
        ];
    }

    $social_patterns = [
        'instagram.com' => ['name' => 'Instagram'],
        'linkedin.com' => ['name' => 'LinkedIn'],
        'youtube.com' => ['name' => 'YouTube'],
        'youtu.be' => ['name' => 'YouTube'],
        'pinterest.com' => ['name' => 'Pinterest'],
        'tiktok.com' => ['name' => 'TikTok'],
        'mastodon' => ['name' => 'Mastodon'],
        'bsky.app' => ['name' => 'Bluesky'],
    ];

    foreach ((array) ($seo_data['other_social_urls'] ?? []) as $url) {
        $url = trim($url);
        if (empty($url)) {
            continue;
        }

        foreach ($social_patterns as $pattern => $meta) {
            if (str_contains($url, $pattern)) {
                $out[] = ['name' => $meta['name'], 'link' => $url];
                break;
            }
        }
    }

    return $out;
}

wp_embed_register_handler('zw-bunny', '#^https://(?:iframe|player)\.mediadelivery\.net/play/[^\s<>"]+$#i', function ($matches, $attr, $url, $rawattr) {
    return \Streekomroep\VideoRenderer::renderFromUrl($url) ?: '';
});
wp_embed_register_handler('zw-readmore', '#^(.*)$#', 'zw_embed_handler');

function zw_embed_handler($matches, $attr, $url, $rawattr)
{
    $self = parse_url(get_site_url(), PHP_URL_HOST);
    $host = parse_url($url, PHP_URL_HOST);
    if (!in_array($host, [$self, 'www.zuidwestfm.nl'])) {
        return false;
    }

    $postId = url_to_postid($url);
    if ($postId === 0) {
        return false;
    }

    if (get_post_type($postId) !== 'post') {
        return false;
    }

    return '[zw_embed]' . $url . '[/zw_embed]';
}

add_shortcode('zw_embed', 'zw_embed');

function zw_embed($atts, $content, $shortcode_tag)
{
    $url = $content;

    $postId = url_to_postid($url);
    if ($postId === 0) {
        return false;
    }

    $post = Timber::get_post($postId);
    $html = Timber::compile('embed.twig', ['post' => $post]);

    return $html;
}


function zw_get_pages_by_template($template)
{
    return Timber::get_posts([
        'post_type' => 'page',
        'meta_key' => '_wp_page_template',
        'meta_value' => $template,
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);
}

function zw_get_page_by_template($template)
{
    foreach (zw_get_pages_by_template($template) as $page) {
        return $page;
    }

    return null;
}

// Show the highest fragment IDs first in the ACF selector.
add_filter('acf/fields/relationship/query/name=post_gekoppeld_fragment', 'zw_sort_fragments_selector', 10, 3);
function zw_sort_fragments_selector($args, $field, $post_id)
{
    $args['orderby'] = 'ID';
    $args['order'] = 'DESC';

    return $args;
}

// Refresh Bunny collections every ten minutes.
add_filter('cron_schedules', function ($schedules) {
    $schedules['10mins'] = [
        'interval' => 10 * 60,
        'display' => __('Every 10 minutes', 'streekomroep'),
    ];
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
    wp_clear_scheduled_hook('zw_hourly');
    wp_clear_scheduled_hook('zw_10mins');
}

// Invalidate catch-up data after an ACF options save.
add_action('acf/save_post', function ($post_id) {
    if ($post_id === 'options') {
        \Streekomroep\TvGemistCache::invalidate();
    }
});

// Invalidate catch-up data when a TV show changes.
add_action('save_post_tv', function () {
    \Streekomroep\TvGemistCache::invalidate();
});

// ACF stores tv_week subfields as separate options.
foreach (['added_option', 'updated_option', 'deleted_option'] as $zw_option_hook) {
    add_action($zw_option_hook, function ($option) {
        if (is_string($option) && str_starts_with($option, \Streekomroep\BroadcastSchedule::OPTION_PREFIX)) {
            // ACF writes hundreds of repeater options in one request; invalidate once after the save.
            add_action('shutdown', [\Streekomroep\BroadcastSchedule::class, 'invalidateCache']);
        }
    });
}

add_action('zw_10mins', 'zw_project_cron');
function zw_project_cron()
{
    $shows = Timber::get_posts([
        'post_type' => 'tv',
        'ignore_sticky_posts' => true,
        'nopaging' => true,
    ]);

    $credentials = \Streekomroep\BunnyClient::getCredentials(ZW_BUNNY_LIBRARY_TV);
    if (!$credentials) {
        return;
    }

    $client = new \Streekomroep\BunnyClient($credentials);

    $changed = false;
    foreach ($shows as $show) {
        $collectionId = $show->meta('tv_show_gemist_locatie');
        if (empty($collectionId)) {
            $changed = update_post_meta($show->ID, ZW_TV_META_VIDEOS, []) !== false || $changed;
            continue;
        }

        try {
            $videos = $client->fetchCollection($collectionId);
            \Streekomroep\VideoCollection::preprocess($videos);
            $changed = update_post_meta($show->ID, ZW_TV_META_VIDEOS, $videos) !== false || $changed;
        } catch (Exception $e) {
            error_log($e);
        }
    }

    // Invalidate only when the sync changes stored video data.
    if ($changed) {
        \Streekomroep\TvGemistCache::invalidate();
    }
}


if (defined('WP_DEBUG') && WP_DEBUG) {
    add_filter('yoast_seo_development_mode', '__return_true');
}
\Streekomroep\VideoSeo::register();
add_filter('wpseo_schema_article', 'zw_seo_article_add_region', 10, 2);

function fragment_get_posts($fragmentID)
{
    return Timber::get_posts([
        'post_type' => 'post',
        'ignore_sticky_posts' => true,
        'meta_query' => [
            [
                'key' => 'post_gekoppeld_fragment',
                'value' => '"' . $fragmentID . '"', // Match a complete ID in the serialized ACF value.
                'compare' => 'LIKE'
            ]
        ]
    ]);
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

// Preserve originals; Timber and imgproxy generate renditions on demand.
add_filter('big_image_size_threshold', '__return_false');
// Let CSS control caption width.
add_filter('img_caption_shortcode_width', '__return_false');

add_action('admin_init', 'zw_register_imgproxy_media_settings');

function zw_register_imgproxy_media_settings()
{
    $fields = [
        'zw_imgproxy_key' => [
            'sanitize_callback' => 'sanitize_text_field',
            'description' => __('Hex-encoded key voor ondertekende imgproxy-URL\'s.', 'streekomroep'),
            'type' => 'password',
            'autocomplete' => 'off',
        ],
        'zw_imgproxy_salt' => [
            'sanitize_callback' => 'sanitize_text_field',
            'description' => __('Hex-encoded salt voor ondertekende imgproxy-URL\'s.', 'streekomroep'),
            'type' => 'password',
            'autocomplete' => 'off',
        ],
        'zw_imgproxy_url' => [
            'sanitize_callback' => 'zw_sanitize_imgproxy_url',
            'description' => __('Basis-URL van imgproxy, inclusief https:// en trailing slash.', 'streekomroep'),
            'type' => 'url',
            'placeholder' => 'https://imgproxy.example.com/',
        ],
    ];

    add_settings_section(
        'zw_imgproxy_settings',
        __('Imgproxy', 'streekomroep'),
        'zw_render_imgproxy_settings_section',
        'media'
    );

    foreach ($fields as $option => $args) {
        register_setting(
            'media',
            $option,
            [
                'type' => 'string',
                'sanitize_callback' => $args['sanitize_callback'],
                'default' => '',
            ]
        );

        add_settings_field(
            $option,
            // Keep imgproxy's canonical option names as field labels.
            str_replace('zw_', '', $option),
            'zw_render_imgproxy_settings_field',
            'media',
            'zw_imgproxy_settings',
            ['label_for' => $option, 'option' => $option] + $args
        );
    }

    zw_backfill_imgproxy_media_settings();
}

function zw_render_imgproxy_settings_section()
{
    echo '<p>' . esc_html__('Laat deze velden leeg om Timber image resizing te gebruiken.', 'streekomroep') . '</p>';
}

function zw_render_imgproxy_settings_field($args)
{
    $option = $args['option'];
    $type = $args['type'] ?? 'text';
    $value = get_option($option, '');
    $placeholder = $args['placeholder'] ?? '';
    $autocomplete = $args['autocomplete'] ?? '';

    printf(
        '<input name="%1$s" id="%1$s" type="%2$s" value="%3$s" class="regular-text code" placeholder="%4$s" autocomplete="%5$s">',
        esc_attr($option),
        esc_attr($type),
        esc_attr($value),
        esc_attr($placeholder),
        esc_attr($autocomplete)
    );

    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function zw_normalize_imgproxy_url($url)
{
    $url = trim((string) $url);

    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }

    $sanitized = esc_url_raw($url);

    if ($sanitized === '') {
        return '';
    }

    $parsed = wp_parse_url($sanitized);

    if (!is_array($parsed) || empty($parsed['host'])) {
        return '';
    }

    $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }

    return trailingslashit($sanitized);
}

function zw_sanitize_imgproxy_url($url)
{
    $normalized = zw_normalize_imgproxy_url($url);

    if ($normalized === '' && trim((string) $url) !== '') {
        add_settings_error(
            'zw_imgproxy_url',
            'invalid_url',
            __('De opgegeven imgproxy URL is ongeldig en is niet opgeslagen.', 'streekomroep')
        );
        return (string) get_option('zw_imgproxy_url', '');
    }

    return $normalized;
}

function zw_get_imgproxy_constant($constant)
{
    if (!defined($constant)) {
        return '';
    }

    $value = constant($constant);

    if (!is_scalar($value)) {
        return '';
    }

    return trim((string) $value);
}

function zw_backfill_imgproxy_setting($option, $constant, $sanitize_callback)
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (trim((string) get_option($option, '')) !== '') {
        return;
    }

    $value = zw_get_imgproxy_constant($constant);

    if ($value === '') {
        return;
    }

    $value = call_user_func($sanitize_callback, $value);

    if ($value === '') {
        return;
    }

    update_option($option, $value);
}

function zw_backfill_imgproxy_media_settings()
{
    zw_backfill_imgproxy_setting('zw_imgproxy_key', 'IMGPROXY_KEY', 'sanitize_text_field');
    zw_backfill_imgproxy_setting('zw_imgproxy_salt', 'IMGPROXY_SALT', 'sanitize_text_field');
    zw_backfill_imgproxy_setting('zw_imgproxy_url', 'IMGPROXY_URL', 'zw_normalize_imgproxy_url');
}

function zw_get_imgproxy_setting($option, $constant)
{
    $value = trim((string) get_option($option, ''));

    if ($value !== '') {
        return $value;
    }

    return zw_get_imgproxy_constant($constant);
}

/**
 * @return array{key: string, salt: string, host: string} Empty strings represent unset values.
 */
function zw_imgproxy_settings(): array
{
    // Cache lookups across src and srcset generation.
    static $settings = null;

    return $settings ??= [
        'key' => zw_get_imgproxy_setting('zw_imgproxy_key', 'IMGPROXY_KEY'),
        'salt' => zw_get_imgproxy_setting('zw_imgproxy_salt', 'IMGPROXY_SALT'),
        'host' => zw_normalize_imgproxy_url(zw_get_imgproxy_setting('zw_imgproxy_url', 'IMGPROXY_URL')),
    ];
}

function zw_imgproxy_is_configured(): bool
{
    return !in_array('', zw_imgproxy_settings(), true);
}

add_action('admin_notices', 'zw_imgproxy_admin_notice');

function zw_imgproxy_admin_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = zw_imgproxy_settings();
    $configured = count(array_filter($settings, fn ($value) => $value !== ''));

    if ($configured === 0 || $configured === count($settings)) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html__('Imgproxy is gedeeltelijk geconfigureerd (key, salt en URL zijn niet alle drie ingevuld). De theme valt terug op Timber image resizing tot alle drie zijn ingesteld.', 'streekomroep')
    );
}

function zw_enqueue_theme_assets()
{
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('streekomroep-style', get_theme_file_uri('dist/style.css'), [], $version);
    wp_enqueue_script('streekomroep-site', get_theme_file_uri('static/site.js'), [], $version, true);
}

add_action('wp_enqueue_scripts', 'zw_enqueue_theme_assets');

/** Enqueues the dependency-free FM live player. */
function zw_enqueue_fm_live_assets()
{
    if (!is_page_template('wp-page-fm-player.php')) {
        return;
    }

    wp_enqueue_script(
        'zw-fm-live',
        get_theme_file_uri('static/fm-live.js'),
        [],
        wp_get_theme()->get('Version'),
        ['strategy' => 'defer', 'in_footer' => true]
    );
}

add_action('wp_enqueue_scripts', 'zw_enqueue_fm_live_assets', 21);

/**
 * Marks the request for VideoJS.
 *
 * Late calls enqueue immediately because wp_enqueue_scripts already ran.
 */
function zw_require_videojs()
{
    $GLOBALS['zw_requires_videojs'] = true;

    if (did_action('wp_enqueue_scripts')) {
        zw_enqueue_videojs_assets();
    }
}

function zw_enqueue_videojs_assets()
{
    static $videojs_enqueued = false;

    if ($videojs_enqueued) {
        return;
    }

    $videojs_enqueued = true;
    $videojs_version = '8.23.4';
    $videojs_base_url = 'https://cdnjs.cloudflare.com/ajax/libs/video.js/' . $videojs_version;

    // TODO: Defer the render-blocking Video.js stylesheet.
    wp_enqueue_style('video.js', $videojs_base_url . '/video-js.min.css', [], $videojs_version);
    wp_enqueue_script('video.js', $videojs_base_url . '/video.min.js', [], $videojs_version, ['strategy' => 'defer']);
    wp_enqueue_script('video.js.nl', $videojs_base_url . '/lang/nl.min.js', ['video.js'], $videojs_version, ['strategy' => 'defer']);
    wp_enqueue_script(
        'zw-videojs-init',
        get_theme_file_uri('static/videojs-init.js'),
        ['video.js', 'video.js.nl'],
        wp_get_theme()->get('Version'),
        true
    );
}

function zw_maybe_enqueue_videojs()
{
    $post = get_post();

    if (!empty($GLOBALS['zw_requires_videojs'])) {
        zw_enqueue_videojs_assets();
        return;
    }

    if (is_singular() && $post instanceof WP_Post && zw_post_content_contains_videojs_embed($post)) {
        zw_enqueue_videojs_assets();
    }
}

add_action('wp_enqueue_scripts', 'zw_maybe_enqueue_videojs', 20);

/**
 * Detects Bunny embeds in raw post content.
 *
 * ACF renderers must call zw_require_videojs() directly.
 */
function zw_post_content_contains_videojs_embed(WP_Post $post): bool
{
    $result = preg_match('#https?://(?:iframe|player)\.mediadelivery\.net/play/[^\s<>"\']+#i', $post->post_content);

    if ($result === false) {
        error_log('zw_post_content_contains_videojs_embed: preg_match failed: ' . preg_last_error_msg());
        return true;
    }

    return $result === 1;
}

function zw_normalize_imgproxy_src($src): ?string
{
    if ($src instanceof \Timber\ImageInterface) {
        $src = $src->src();
    }

    if (!is_string($src)) {
        return null;
    }

    $src = trim($src);
    if ($src === '') {
        return null;
    }

    return $src;
}

function zw_log_invalid_imgproxy_src($src, string $action): void
{
    static $warned = [];

    $type = get_debug_type($src);
    $key = $action . ':' . $type;
    if (isset($warned[$key])) {
        return;
    }

    $warned[$key] = true;
    $message = sprintf('zw_imgproxy: invalid image source (%s) - %s.', $type, $action);
    error_log($message);
}

function zw_base64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function zw_imgproxy($src, $width, $height)
{
    $originalSrc = $src;
    $src = zw_normalize_imgproxy_src($src);
    if ($src === null) {
        zw_log_invalid_imgproxy_src($originalSrc, 'using blank placeholder');
        return 'data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20viewBox=%220%200%201%201%22%3E%3Crect%20width=%221%22%20height=%221%22%20fill=%22%23e5e7eb%22/%3E%3C/svg%3E';
    }

    $settings = zw_imgproxy_settings();
    if (in_array('', $settings, true)) {
        return \Timber\ImageHelper::resize($src, $width, $height);
    }

    ['key' => $key, 'salt' => $salt, 'host' => $host] = $settings;

    $resize = 'fill';
    $gravity = 'ce'; // Imgproxy center gravity.
    $enlarge = 1;
    $extension = 'jpeg';

    $width = (int)round($width);
    $height = (int)round($height);

    $encodedUrl = zw_base64url($src);

    // @phpcs:ignore Squiz.Strings.DoubleQuoteUsage.ContainsVar
    $path = "/rs:{$resize}:{$width}:{$height}:{$enlarge}/g:{$gravity}/{$encodedUrl}.{$extension}";

    $keyBin = pack('H*', $key);
    $saltBin = pack('H*', $salt);
    $signature = zw_base64url(hash_hmac('sha256', $saltBin . $path, $keyBin, true));

    return $host . $signature . $path;
}


\Streekomroep\Gallery::register();
