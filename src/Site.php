<?php

namespace Streekomroep;

use Timber\Timber;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/** Configures the theme through Timber. */
class Site extends \Timber\Site
{
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'theme_supports']);
        add_filter('timber/context', [$this, 'add_to_context']);
        add_filter('timber/twig', [$this, 'add_to_twig']);
        add_action('init', [$this, 'register_menus']);
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_taxonomies']);
        parent::__construct();
    }

    public function register_menus()
    {
        register_nav_menu('main', 'Main menu');
        register_nav_menu('top', 'Top menu');
        register_nav_menu('footer', 'Footer Menu');
    }

    /** Registers custom post types. */
    public function register_post_types()
    {
        include(get_template_directory() . '/lib/post_type_fragment.php');
        include(get_template_directory() . '/lib/post_type_tvshow.php');
        include(get_template_directory() . '/lib/post_type_fmshow.php');
    }

    /** Registers custom taxonomies. */
    public function register_taxonomies()
    {
        include(get_template_directory() . '/lib/taxonomy_dossier.php');
        include(get_template_directory() . '/lib/taxonomy_regio.php');
        include(get_template_directory() . '/lib/taxonomy_ranking.php');
    }

    public function add_to_context($context)
    {
        $context['mainmenu'] = Timber::get_menu('main');
        $context['topmenu'] = Timber::get_menu('top');
        $context['footer'] = Timber::get_menu('footer');
        $context['socials'] = zw_get_socials();
        $context['site'] = $this;
        $context['options'] = get_fields('option') ?: [];
        return $context;
    }

    public function theme_supports()
    {
        // Let WordPress expose the site's RSS feeds.
        add_theme_support('automatic-feed-links');

        // Let WordPress manage the document title.
        add_theme_support('title-tag');

        // Enable featured images on supported post types.
        add_theme_support('post-thumbnails');

        // Make embeds adapt to the available content width.
        add_theme_support('responsive-embeds');

        // Use semantic HTML5 markup for WordPress-generated components.
        add_theme_support(
            'html5',
            [
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            ]
        );

        add_theme_support(
            'post-formats',
            [
                'video',
                'audio',
            ]
        );

        add_theme_support('custom-logo');
    }

    /** Formats a schedule rule for compact labels in the FM show UI. */
    public function format_schedule_compact($entry)
    {
        $names = array_values(BroadcastDay::WEEKDAY_NAMES);
        $days = array_values(array_intersect($names, $entry['fm_show_dagen'] ?: []));
        $positions = array_flip($names);
        $short = fn ($day) => strtoupper(substr($day, 0, 2));
        $label = match (true) {
            count($days) === 7 => 'ELKE DAG',
            $days === array_slice($names, 0, 5) => 'ELKE WERKDAG',
            count($days) >= 3 && $days === array_slice($names, $positions[$days[0]], count($days))
            => $short($days[0]) . ' T/M ' . $short(end($days)),
            default => implode(', ', array_map($short, $days)),
        };

        if (empty($entry['fm_show_starttijd']) || empty($entry['fm_show_eindtijd'])) {
            return $label;
        }

        return trim($label . ' van ' . substr($entry['fm_show_starttijd'], 0, 5)
            . ' tot ' . substr($entry['fm_show_eindtijd'], 0, 5) . ' uur');
    }

    public function get_icon($name)
    {
        static $cache = [];

        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }

        if (!preg_match('/^icon-(.*)$/', $name, $m)) {
            return $cache[$name] = null;
        }

        $path = get_theme_file_path('icons/' . $m[1] . '/baseline.svg');
        $svg = file_get_contents($path);

        return $cache[$name] = str_replace('<svg ', '<svg class="fill-current" ', $svg);
    }

    public function imgproxy($src, $width, $height)
    {
        return zw_imgproxy($src, $width, $height);
    }

    /** Registers the theme's Twig extensions. */
    public function add_to_twig($twig)
    {
        $twig->addExtension(new MarkdownExtension());
        $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
            public function load($class)
            {
                if (MarkdownRuntime::class === $class) {
                    return new MarkdownRuntime(new DefaultMarkdown());
                }
            }
        });

        $twig->addFilter(new \Twig\TwigFilter('format_schedule_compact', [$this, 'format_schedule_compact']));
        $twig->addFunction(new \Twig\TwigFunction('icon', [$this, 'get_icon']));
        $twig->addFunction(new \Twig\TwigFunction('responsive_image_srcset', [ResponsiveImage::class, 'srcset']));
        $twig->addFilter(new \Twig\TwigFilter('imgproxy', [$this, 'imgproxy']));
        return $twig;
    }
}
