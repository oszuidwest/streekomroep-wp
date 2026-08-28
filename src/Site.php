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

    public function register_post_types()
    {
        include(get_template_directory() . '/lib/post_type_fragment.php');
        include(get_template_directory() . '/lib/post_type_tvshow.php');
        include(get_template_directory() . '/lib/post_type_fmshow.php');
    }

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
        $context['breadcrumb_separator'] = class_exists('WPSEO_Options') ? \WPSEO_Options::get('breadcrumbs-sep', '/') : '/';
        $context['layout'] = [
            'content_width' => Layout::CONTENT_WIDTH,
            'content_sizes' => Layout::CONTENT_SIZES,
        ];
        return $context;
    }

    public function theme_supports()
    {
        add_theme_support('automatic-feed-links');

        add_theme_support('title-tag');

        add_theme_support('post-thumbnails');

        add_theme_support('responsive-embeds');

        add_theme_support('editor-styles');
        add_editor_style('dist/editor.css');

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
        $short = fn ($day) => substr($day, 0, 2);
        $label = match (true) {
            count($days) === 7 => 'elke dag',
            $days === array_slice($names, 0, 5) => 'elke werkdag',
            count($days) === 2 => implode(' en ', array_map($short, $days)),
            count($days) >= 3 && $days === array_slice($names, $positions[$days[0]], count($days))
            => $short($days[0]) . ' t/m ' . $short(end($days)),
            default => implode(', ', array_map($short, $days)),
        };

        if (empty($entry['fm_show_starttijd']) || empty($entry['fm_show_eindtijd'])) {
            return $label;
        }

        return trim($label . ' van ' . substr($entry['fm_show_starttijd'], 0, 5)
            . ' tot ' . substr($entry['fm_show_eindtijd'], 0, 5) . ' uur');
    }

    /** Formats a video duration. */
    public function format_duration($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds >= 3600) {
            return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
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
        $twig->addFilter(new \Twig\TwigFilter('format_duration', [$this, 'format_duration']));
        $twig->addFunction(new \Twig\TwigFunction('icon', [$this, 'get_icon']));
        $twig->addFunction(new \Twig\TwigFunction('responsive_image_srcset', [ResponsiveImage::class, 'srcset']));
        $twig->addFunction(new \Twig\TwigFunction('responsive_image_srcset_widths', [ResponsiveImage::class, 'srcsetForWidths']));
        $twig->addFilter(new \Twig\TwigFilter('imgproxy', [$this, 'imgproxy']));
        $twig->addFilter(new \Twig\TwigFilter('rows', 'zw_acf_rows'));
        $twig->addFilter(new \Twig\TwigFilter('plain', 'zw_plain_text'));
        return $twig;
    }
}
