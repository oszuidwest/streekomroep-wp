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
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
	$timber = new Timber\Timber();
}

/**
 * This ensures that Timber is loaded and available as a PHP class.
 * If not, it gives an error message to help direct developers on where to activate
 */
if ( ! class_exists( 'Timber' ) ) {

	add_action(
		'admin_notices',
		function() {
			echo '<div class="error"><p>Timber not activated. Make sure you activate the plugin in <a href="' . esc_url( admin_url( 'plugins.php#timber' ) ) . '">' . esc_url( admin_url( 'plugins.php' ) ) . '</a></p></div>';
		}
	);

	add_filter(
		'template_include',
		function( $template ) {
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

/**
 * Sets the directories (inside your theme) to find .twig files
 */
Timber::$dirname = array( 'templates', 'views' );

/**
 * By default, Timber does NOT autoescape values. Want to enable Twig's autoescape?
 * No prob! Just set this value to true
 */
Timber::$autoescape = false;

require_once 'src/Post.php';
require_once 'src/Site.php';

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

  function streekomroep_acf_json_save_point( $path ) {

      // update path
      $path = get_stylesheet_directory() . '/streekomroep-acf-json';

      // return
      return $path;

  }

  add_filter('acf/settings/load_json', 'streekomroep_acf_json_load_point');

  function streekomroep_acf_json_load_point( $paths ) {

      // remove original path (optional)
      unset($paths[0]);

      // append path
      $paths[] = get_stylesheet_directory() . '/streekomroep-acf-json';


      // return
      return $paths;

  }
}

if( function_exists('acf_add_options_page') ) {
	
	acf_add_options_page(array(
		'page_title' 	=> 'Radio Instellingen',
		'menu_title'	=> 'Radio Instellingen',
		'menu_slug' 	=> 'radio-instellingen',
		'capability'	=> 'edit_posts',
		'icon_url' => 'dashicons-playlist-audio',
		'redirect'	=> false
	));	
}

if( function_exists('acf_add_options_page') ) {
	
	acf_add_options_page(array(
		'page_title' 	=> 'TV Instellingen',
		'menu_title'	=> 'TV Instellingen',
		'menu_slug' 	=> 'tv-instellingen',
		'capability'	=> 'edit_posts',
		'icon_url' => 'dashicons-format-video',
		'redirect'	=> false
	));	
}

if( function_exists('acf_add_options_page') ) {
	
	acf_add_options_page(array(
		'page_title' 	=> 'Desking',
		'menu_title'	=> 'Desking',
		'menu_slug' 	=> 'desking',
		'capability'	=> 'edit_posts',
		'icon_url' => 'dashicons-layout',
		'redirect'	=> false
	));	
}

function zw_parse_query(WP_Query $query)
{
    if (is_post_type_archive('fm') || is_post_type_archive('tv')) {
        $query->set('nopaging', 1);
    }
}

add_action('parse_query', 'zw_parse_query');

add_filter('get_avatar_url', 'zw_get_avatar_url', 10, 3);

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
    if ($field === null) {
        return $url;
    }

    $src = wp_get_attachment_image_src($field['id'], [$args['size'], $args['size']]);
    return $src[0];
}

class Jetpack_Options{
    public static function get_option_and_ensure_autoload() {
        return 'rectangular';
    }

    public static function get_option($option) {
        return get_option($option);
    }
}

class Jetpack{
    public static function get_content_width() {
        return 672;
    }

    public static function get_active_modules() {
        return ['carousel'];
    }
}

function jetpack_photon_url ($image_url, $args = array(), $scheme = null ) {
//    var_dump(__FUNCTION__);
    return $image_url;
}

include 'modules/assets.php';
include 'modules/tiled-gallery/tiled-gallery.php';
