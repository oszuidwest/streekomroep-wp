<?php

namespace Streekomroep;

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

        return trim((string) Timber::compile(
            'partial/gallery.twig',
            [
                'gallery' => [
                    'type' => $attributes['type'],
                    'classes' => self::getGalleryClasses($attributes),
                    'items' => $items,
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

        foreach ($attachments as $index => $attachment) {
            if (!$attachment instanceof WP_Post) {
                continue;
            }

            $caption = trim(wp_strip_all_tags((string) $attachment->post_excerpt));
            $label = $caption !== '' ? $caption : trim(wp_strip_all_tags((string) $attachment->post_title));
            $layout = self::getItemLayout(self::getRatio($attachment), $attributes, (int) $index);
            $image = self::renderImage($attachment, $attributes, $label, $layout['sizes']);

            if ($image === '') {
                continue;
            }

            $items[] = [
                'caption' => $caption,
                'classes' => $layout['classes'],
                'image' => $image,
                'label' => $label,
                'link' => self::getLinkUrl($attachment, $attributes['link']),
            ];
        }

        return $items;
    }

    private static function getRatio(WP_Post $attachment): float
    {
        $meta = wp_get_attachment_metadata($attachment->ID);
        $width = absint($meta['width'] ?? 0);
        $height = absint($meta['height'] ?? 0);

        if ($width === 0 || $height === 0) {
            $image = wp_get_attachment_image_src($attachment->ID, 'full');
            $width = absint($image[1] ?? 1);
            $height = absint($image[2] ?? 1);
        }

        return max(0.5, min(3.5, $width / max(1, $height)));
    }

    private static function renderImage(WP_Post $attachment, array $attributes, string $label, string $sizes): string
    {
        $imageAttributes = [
            'class' => self::getImageClasses($attributes['type']),
            'sizes' => $sizes,
        ];

        // Core only falls back to _wp_attachment_image_alt; supply caption/title when that is empty.
        if (trim((string) get_post_meta($attachment->ID, '_wp_attachment_image_alt', true)) === '') {
            $imageAttributes['alt'] = $label;
        }

        return wp_get_attachment_image($attachment->ID, $attributes['size'], false, $imageAttributes);
    }

    private static function getGalleryClasses(array $attributes): string
    {
        $base = 'not-prose clear-both my-6 grid';
        $type = $attributes['type'];

        if ($type === 'rectangular') {
            return $base . ' grid-flow-dense grid-cols-2 gap-1 md:grid-cols-6 md:auto-rows-[8.5rem] lg:auto-rows-[9.5rem]';
        }

        $gap = $type === 'circle' ? ' gap-3 md:gap-4' : ' gap-1';

        return $base . $gap . ' ' . self::getColumnClasses((int) $attributes['columns']);
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
     * Tiles use only even column and row spans on the 6-column grid, so every
     * row sums to 6 and grid-flow-dense can backfill without leaving holes.
     *
     * @return array{classes: string, sizes: string}
     */
    private static function getItemLayout(float $ratio, array $attributes, int $index): array
    {
        $base = 'group relative m-0 overflow-hidden bg-gray-100';
        $type = $attributes['type'];

        if ($type === 'circle' || $type === 'square') {
            $columns = (int) $attributes['columns'];
            $width = (int) round(self::CONTENT_WIDTH / $columns);

            return [
                'classes' => $base . ' aspect-square' . ($type === 'circle' ? ' rounded-full' : ''),
                'sizes' => $columns === 1 ? '100vw' : sprintf('(min-width: 768px) %dpx, 50vw', $width),
            ];
        }

        if ($ratio >= 2.2) {
            return [
                'classes' => $base . ' col-span-2 aspect-square md:aspect-auto md:col-span-4 md:row-span-2',
                'sizes' => '(min-width: 768px) 510px, 100vw',
            ];
        }

        if ($ratio <= 0.85) {
            return [
                'classes' => $base . ' col-span-1 aspect-square md:aspect-auto md:col-span-2 md:row-span-4',
                'sizes' => '(min-width: 768px) 250px, 50vw',
            ];
        }

        if ($index % 7 === 0) {
            return [
                'classes' => $base . ' col-span-1 aspect-square md:aspect-auto md:col-span-4 md:row-span-2',
                'sizes' => '(min-width: 768px) 510px, 50vw',
            ];
        }

        return [
            'classes' => $base . ' col-span-1 aspect-square md:aspect-auto md:col-span-2 md:row-span-2',
            'sizes' => '(min-width: 768px) 250px, 50vw',
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
