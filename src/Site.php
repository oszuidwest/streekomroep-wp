<?php

namespace Streekomroep;

/**
 * We're going to configure our theme inside of a subclass of Timber\Site
 * You can move this to its own file and include here via php's include("MySite.php")
 */
class Site extends \Timber\Site
{
    /** Add timber support. */
    public function __construct()
    {
        add_action('after_setup_theme', array($this, 'theme_supports'));
        add_filter('timber/context', array($this, 'add_to_context'));
        add_filter('timber/twig', array($this, 'add_to_twig'));
        add_action('init', array($this, 'register_menus'));
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        parent::__construct();
    }

    public function register_menus()
    {
        register_nav_menu('main', 'Main menu');
        register_nav_menu('top', 'Top menu');
        register_nav_menu('footer', 'Footer Menu');
    }

    /** This is where you can register custom post types. */
    public function register_post_types()
    {
        include(get_template_directory() . '/lib/post_type_fragment.php');
        include(get_template_directory() . '/lib/post_type_tvshow.php');
        include(get_template_directory() . '/lib/post_type_fmshow.php');
        include(get_template_directory() . '/lib/post_type_agenda.php');
    }

    /** This is where you can register custom taxonomies. */
    public function register_taxonomies()
    {
        include(get_template_directory() . '/lib/taxonomy_dossier.php');
        include(get_template_directory() . '/lib/taxonomy_regio.php');

    }

    /** This is where you add some context
     *
     * @param string $context context['this'] Being the Twig's {{ this }}.
     */
    public function add_to_context($context)
    {
        $context['foo'] = 'bar';
        $context['stuff'] = 'I am a value set in your functions.php file';
        $context['notes'] = 'These values are available everytime you call Timber::context();';
        $context['mainmenu'] = new \Timber\Menu('main');
        $context['topmenu'] = new \Timber\Menu('top');
        $context['footer'] = new \Timber\Menu('footer');
        $context['socials'] = zw_get_socials();
        $context['site'] = $this;
        $context['options'] = get_fields('option');
        return $context;
    }

    public function theme_supports()
    {
        // Add default posts and comments RSS feed links to head.
        add_theme_support('automatic-feed-links');

        /*
         * Let WordPress manage the document title.
         * By adding theme support, we declare that this theme does not use a
         * hard-coded <title> tag in the document head, and expect WordPress to
         * provide it for us.
         */
        add_theme_support('title-tag');

        /*
         * Enable support for Post Thumbnails on posts and pages.
         *
         * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
         */
        add_theme_support('post-thumbnails');

        /*
         * Enable support for Responsive embeds.
         */
        add_theme_support('responsive-embeds');

        /*
         * Switch default core markup for search form, comment form, and comments
         * to output valid HTML5.
         */
        add_theme_support(
            'html5',
            array(
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            )
        );

        /*
         * Enable support for Post Formats.
         *
         * See: https://codex.wordpress.org/Post_Formats
         */
        add_theme_support(
            'post-formats',
            array(
                'video',
                'audio',
            )
        );

        add_theme_support('menus');

        add_theme_support('custom-logo');
    }

    public function format_schedule($entry)
    {
        $dayString = '';
        $days = $entry['fm_show_dagen'];
        if (count($days) == 1) {
            $dayString = $days[0];
        } else {
            for ($i = 0; $i < count($days); $i++) {
                if ($i == count($days) - 1) {
                    $dayString .= ' en ';
                } else if ($i != 0) {
                    $dayString .= ', ';
                }

                $dayString .= $days[$i];
            }
        }

        $out = sprintf('Elke %s van %d tot %d uur', $dayString, $entry['fm_show_starttijd'], $entry['fm_show_eindtijd']);
        return $out;
    }

    public function get_icon($name)
    {
        if (!preg_match('/^icon-(.*)$/', $name, $m)) {
            return null;
        }

        $path = get_theme_file_path('icons/' . $m[1] . '/baseline.svg');
        $svg = file_get_contents($path);

        $svg = str_replace('<svg ', '<svg class="fill-current" ', $svg);
        return $svg;
    }

	public function thumbor($a) {
		var_dump($a);
	}
	
    /** This is where you can add your own functions to twig.
     *
     * @param string $twig get extension.
     */
    public function add_to_twig($twig)
    {
        $twig->addExtension(new \Twig\Extension\StringLoaderExtension());
        $twig->addFilter(new \Twig\TwigFilter('format_schedule', [$this, 'format_schedule']));
        $twig->addFunction(new \Twig\TwigFunction('icon', [$this, 'get_icon']));
        $twig->addFilter(new \Twig\TwigFilter('thumbor', [$this, 'thumbor']));
        return $twig;
    }

}
