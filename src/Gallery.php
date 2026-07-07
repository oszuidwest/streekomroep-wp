<?php

namespace Streekomroep;

use Timber\Image;
use Timber\Timber;
use WP_Post;

class Gallery
{
    private const DEFAULT_TYPE = 'rectangular';
    private const DEFAULT_COLUMNS = 3;
    private const DEFAULT_IMAGE_SIZE = 'large';
    private const MAX_COLUMNS = 6;

    // Galleries render inside the max-w-3xl content column, so image widths cap at 768px.
    private const CONTENT_WIDTH = 768;

    // Shared classes for every gallery tile wrapper.
    private const ITEM_BASE = 'group relative m-0 overflow-hidden bg-gray-100';

    // The distinct rectangular slots. Each geometry (classes/sizes/width/height) lives in exactly one place.
    private const LAYOUT_HERO = [
        'classes' => self::ITEM_BASE . ' col-span-2 aspect-[16/9] md:col-span-6',
        'sizes' => '(min-width: 768px) 768px, 100vw',
        'width' => 768,
        'height' => 432,
    ];
    private const LAYOUT_HALF = [
        'classes' => self::ITEM_BASE . ' aspect-[4/3] md:col-span-3',
        'sizes' => '(min-width: 768px) 384px, 50vw',
        'width' => 384,
        'height' => 288,
    ];
    private const LAYOUT_HERO_HALF = [
        'classes' => self::ITEM_BASE . ' col-span-2 aspect-[16/9] md:col-span-3 md:aspect-[4/3]',
        'sizes' => '(min-width: 768px) 384px, 100vw',
        'width' => 768,
        'height' => 432,
    ];
    private const LAYOUT_THIRD = [
        'classes' => self::ITEM_BASE . ' aspect-[4/3] md:col-span-2',
        'sizes' => '(min-width: 768px) 256px, 50vw',
        'width' => 256,
        'height' => 192,
    ];

    public static function register(): void
    {
        add_filter('post_gallery', [self::class, 'renderShortcode'], 10, 2);
    }

    /**
     * Replaces the default WordPress gallery shortcode with a theme-native tiled gallery.
     */
    public static function renderShortcode($output, array $attributes): string
    {
        if (!empty($output) || is_feed()) {
            return (string) $output;
        }

        $attributes = self::normalizeAttributes($attributes);
        $attachments = self::getAttachments($attributes);
        $items = self::buildItems($attachments, $attributes);

        if ($items === []) {
            return (string) $output;
        }

        $isRectangular = $attributes['type'] === 'rectangular';
        $groups = $isRectangular ? self::buildRectangularGroups($items, $attributes) : [];
        $renderedItems = $isRectangular ? [] : self::buildUniformItems($items, $attributes);

        if ($groups === [] && $renderedItems === []) {
            return (string) $output;
        }

        return trim((string) Timber::compile(
            'partial/gallery.twig',
            [
                'gallery' => [
                    'type' => $attributes['type'],
                    'classes' => self::getGalleryClasses($attributes),
                    'groups' => $groups,
                    'items' => $renderedItems,
                ],
            ]
        ));
    }

    private static function normalizeAttributes(array $attributes): array
    {
        global $post;

        if (!empty($attributes['ids'])) {
            $attributes['include'] = $attributes['ids'];
            $attributes['orderby'] = 'post__in';
        }

        $attributes = shortcode_atts(
            [
                'order' => 'ASC',
                'orderby' => 'menu_order ID',
                'id' => $post instanceof WP_Post ? $post->ID : 0,
                'include' => '',
                'exclude' => '',
                'link' => '',
                'columns' => self::DEFAULT_COLUMNS,
                'size' => self::DEFAULT_IMAGE_SIZE,
                'type' => self::DEFAULT_TYPE,
            ],
            $attributes,
            'gallery'
        );

        $order = strtoupper((string) $attributes['order']);

        if ($order === 'RAND') {
            $attributes['orderby'] = 'rand';
        }

        $attributes['id'] = absint($attributes['id']);
        $attributes['order'] = $order === 'DESC' ? 'DESC' : 'ASC';
        $attributes['orderby'] = self::normalizeOrderby((string) $attributes['orderby']);
        $attributes['link'] = self::normalizeLink((string) $attributes['link']);
        $attributes['columns'] = self::normalizeColumns($attributes['columns']);
        $attributes['size'] = sanitize_key((string) $attributes['size']) ?: self::DEFAULT_IMAGE_SIZE;
        $attributes['type'] = self::normalizeType((string) $attributes['type']);

        return $attributes;
    }

    private static function normalizeOrderby(string $orderby): string
    {
        if ($orderby === 'post__in' || strtolower($orderby) === 'rand') {
            return $orderby;
        }

        $sanitized = sanitize_sql_orderby($orderby);

        return $sanitized ?: 'menu_order ID';
    }

    private static function normalizeLink(string $link): string
    {
        $link = sanitize_key($link);

        if (in_array($link, ['file', 'none'], true)) {
            return $link;
        }

        return 'attachment';
    }

    private static function normalizeColumns($columns): int
    {
        $columns = absint($columns);

        return $columns === 0 ? self::DEFAULT_COLUMNS : min($columns, self::MAX_COLUMNS);
    }

    private static function normalizeType(string $type): string
    {
        $type = sanitize_key($type);

        if (in_array($type, ['rectangular', 'square', 'circle'], true)) {
            return $type;
        }

        return self::DEFAULT_TYPE;
    }

    /**
     * @return WP_Post[]
     */
    private static function getAttachments(array $attributes): array
    {
        $query = [
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'order' => $attributes['order'],
            'orderby' => $attributes['orderby'],
            'posts_per_page' => -1,
            'suppress_filters' => false,
        ];

        $include = array_filter(wp_parse_id_list((string) $attributes['include']));

        if ($include !== []) {
            $query['post__in'] = $include;
        } elseif ($attributes['id'] !== 0) {
            $query['post_parent'] = $attributes['id'];
            $exclude = array_filter(wp_parse_id_list((string) $attributes['exclude']));

            if ($exclude !== []) {
                $query['post__not_in'] = $exclude;
            }
        } else {
            return [];
        }

        return get_posts($query);
    }

    /**
     * @param WP_Post[] $attachments
     */
    private static function buildItems(array $attachments, array $attributes): array
    {
        $items = [];

        foreach ($attachments as $attachment) {
            if (!$attachment instanceof WP_Post) {
                continue;
            }

            $caption = trim(wp_strip_all_tags((string) $attachment->post_excerpt));
            $label = $caption !== '' ? $caption : trim(wp_strip_all_tags((string) $attachment->post_title));
            $items[] = [
                'attachment' => $attachment,
                'caption' => $caption,
                'label' => $label,
                'link' => self::getLinkUrl($attachment, $attributes['link']),
            ];
        }

        return $items;
    }

    private static function buildImage(WP_Post $attachment, array $attributes, string $label, array $layout): ?array
    {
        $image = Timber::get_image($attachment);

        if (!$image instanceof Image) {
            return null;
        }

        $src = $image->src();

        if ($src === '') {
            return null;
        }

        $width = (int) $layout['width'];
        $height = (int) $layout['height'];

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $alt = $image->alt() ?: $label;

        return [
            'class' => self::getImageClasses($attributes['type']),
            'src' => $src,
            'srcset' => self::buildImageSrcset($width, $height),
            'sizes' => $layout['sizes'],
            'alt' => $alt,
            'width' => $width,
            'height' => $height,
            'loading' => 'lazy',
            'decoding' => 'async',
        ];
    }

    private static function buildImageSrcset(int $width, int $height): array
    {
        $srcset = [];

        foreach (self::getSrcsetWidths($width) as $srcsetWidth) {
            $srcsetHeight = (int) round($srcsetWidth / $width * $height);
            $srcset[] = [
                'width' => $srcsetWidth,
                'height' => $srcsetHeight,
                'descriptor' => $srcsetWidth . 'w',
            ];
        }

        return $srcset;
    }

    /**
     * @return int[]
     */
    private static function getSrcsetWidths(int $width): array
    {
        $widths = [
            max(192, (int) round($width / 2)),
            $width,
            $width * 2,
        ];

        $widths = array_values(array_unique(array_filter($widths)));
        sort($widths);

        return $widths;
    }

    private static function getGalleryClasses(array $attributes): string
    {
        $base = 'not-prose clear-both my-6';
        $type = $attributes['type'];

        if ($type === 'rectangular') {
            return $base . ' space-y-1';
        }

        $gap = $type === 'circle' ? ' gap-3 md:gap-4' : ' gap-1';

        return $base . ' grid' . $gap . ' ' . self::getColumnClasses((int) $attributes['columns']);
    }

    private static function getColumnClasses(int $columns): string
    {
        return match ($columns) {
            1 => 'grid-cols-1',
            2 => 'grid-cols-2',
            4 => 'grid-cols-2 sm:grid-cols-4',
            5 => 'grid-cols-2 sm:grid-cols-3 md:grid-cols-5',
            6 => 'grid-cols-2 sm:grid-cols-3 md:grid-cols-6',
            default => 'grid-cols-2 sm:grid-cols-3',
        };
    }

    /**
     * @param array<int, array{attachment: WP_Post, caption: string, label: string, link: string}> $items
     *
     * @return array<int, array{classes: string, items: array<int, array{caption: string, classes: string, image: array, label: string, link: string}>}>
     */
    private static function buildRectangularGroups(array $items, array $attributes): array
    {
        $groups = [];
        $offset = 0;
        $total = count($items);

        while ($offset < $total) {
            $groupSize = self::getNextRectangularGroupSize($total - $offset);
            $chunk = array_slice($items, $offset, $groupSize);
            $groupItems = [];

            foreach ($chunk as $position => $item) {
                $layout = self::getRectangularItemLayout(count($chunk), (int) $position);
                $rendered = self::renderItem($item, $attributes, $layout);

                if ($rendered !== null) {
                    $groupItems[] = $rendered;
                }
            }

            if ($groupItems !== []) {
                $groups[] = [
                    'classes' => 'grid grid-cols-2 gap-1 md:grid-cols-6',
                    'items' => $groupItems,
                ];
            }

            $offset += $groupSize;
        }

        return $groups;
    }

    private static function getNextRectangularGroupSize(int $remaining): int
    {
        if ($remaining <= 5) {
            return $remaining;
        }

        return $remaining === 6 ? 3 : 5;
    }

    /**
     * @return array{classes: string, sizes: string, width: int, height: int}
     */
    private static function getRectangularItemLayout(int $groupSize, int $position): array
    {
        return match ($groupSize) {
            1 => self::LAYOUT_HERO,
            2 => self::LAYOUT_HALF,
            3 => $position === 0 ? self::LAYOUT_HERO : self::LAYOUT_HALF,
            4 => ($position === 0 || $position === 3) ? self::LAYOUT_HERO_HALF : self::LAYOUT_HALF,
            default => match ($position) {
                0 => self::LAYOUT_HERO_HALF,
                1 => self::LAYOUT_HALF,
                default => self::LAYOUT_THIRD,
            },
        };
    }

    /**
     * @param array<int, array{attachment: WP_Post, caption: string, label: string, link: string}> $items
     *
     * @return array<int, array{caption: string, classes: string, image: array, label: string, link: string}>
     */
    private static function buildUniformItems(array $items, array $attributes): array
    {
        $renderedItems = [];
        $layout = self::getUniformItemLayout($attributes);

        foreach ($items as $item) {
            $rendered = self::renderItem($item, $attributes, $layout);

            if ($rendered !== null) {
                $renderedItems[] = $rendered;
            }
        }

        return $renderedItems;
    }

    /**
     * @param array{attachment: WP_Post, caption: string, label: string, link: string} $item
     * @param array{classes: string, sizes: string, width: int, height: int} $layout
     *
     * @return array{caption: string, classes: string, image: array, label: string, link: string}|null
     */
    private static function renderItem(array $item, array $attributes, array $layout): ?array
    {
        $image = self::buildImage($item['attachment'], $attributes, $item['label'], $layout);

        if ($image === null) {
            return null;
        }

        return [
            'caption' => $item['caption'],
            'classes' => $layout['classes'],
            'image' => $image,
            'label' => $item['label'],
            'link' => $item['link'],
        ];
    }

    /**
     * @return array{classes: string, sizes: string, width: int, height: int}
     */
    private static function getUniformItemLayout(array $attributes): array
    {
        // Only the square and circle types reach this path; both render on a square grid.
        $columns = (int) $attributes['columns'];
        $width = (int) round(self::CONTENT_WIDTH / $columns);
        $rounded = $attributes['type'] === 'circle' ? ' rounded-full' : '';

        return [
            'classes' => self::ITEM_BASE . ' aspect-square' . $rounded,
            'sizes' => $columns === 1 ? '100vw' : sprintf('(min-width: 768px) %dpx, 50vw', $width),
            'width' => $width,
            'height' => $width,
        ];
    }

    private static function getImageClasses(string $type): string
    {
        $classes = 'block h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.025] group-focus-within:scale-[1.025]';

        if ($type === 'circle') {
            return $classes . ' rounded-full';
        }

        return $classes;
    }

    private static function getLinkUrl(WP_Post $attachment, string $link): string
    {
        if ($link === 'none') {
            return '';
        }

        if ($link === 'file') {
            return (string) wp_get_attachment_url($attachment->ID);
        }

        return get_attachment_link($attachment->ID);
    }
}
