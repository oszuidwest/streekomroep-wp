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
    private const MIN_COLUMNS = 1;

    public static function register(): void
    {
        add_filter('post_gallery', [self::class, 'renderShortcode'], 1001, 3);
    }

    /**
     * Replaces the default WordPress gallery shortcode with a theme-native tiled gallery.
     */
    public static function renderShortcode($output, array $attributes, int $instance = 0): string
    {
        if (!empty($output) || is_feed() || defined('IS_HTML_EMAIL')) {
            return (string) $output;
        }

        $attributes = self::normalizeAttributes($attributes);
        $attachments = self::getAttachments($attributes);

        if ($attachments === []) {
            return (string) $output;
        }

        $html = self::render($attachments, $attributes, $instance);

        return trim((string) preg_replace('/\s+/', ' ', $html));
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

        $attributes['id'] = absint($attributes['id']);
        $attributes['order'] = self::normalizeOrder((string) $attributes['order']);
        $attributes['orderby'] = self::normalizeOrderby((string) $attributes['orderby']);
        $attributes['link'] = self::normalizeLink((string) $attributes['link']);
        $attributes['columns'] = self::normalizeColumns($attributes['columns']);
        $attributes['size'] = sanitize_key((string) $attributes['size']) ?: self::DEFAULT_IMAGE_SIZE;
        $attributes['type'] = self::normalizeType((string) $attributes['type']);

        if ($attributes['order'] === 'RAND') {
            $attributes['orderby'] = 'rand';
            $attributes['order'] = 'ASC';
        }

        return $attributes;
    }

    private static function normalizeOrder(string $order): string
    {
        $order = strtoupper($order);

        if (in_array($order, ['ASC', 'DESC', 'RAND'], true)) {
            return $order;
        }

        return 'ASC';
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

        if ($columns < self::MIN_COLUMNS) {
            return self::DEFAULT_COLUMNS;
        }

        return min($columns, self::MAX_COLUMNS);
    }

    private static function normalizeType(string $type): string
    {
        $type = sanitize_key($type);

        if ($type === 'rectangle' || $type === 'default' || $type === 'thumbnails') {
            return self::DEFAULT_TYPE;
        }

        if (in_array($type, ['rectangular', 'square', 'circle', 'columns'], true)) {
            return $type;
        }

        return self::DEFAULT_TYPE;
    }

    /**
     * @return WP_Post[]
     */
    private static function getAttachments(array $attributes): array
    {
        $include = self::sanitizeIds((string) $attributes['include']);

        if ($include !== []) {
            $orderby = $attributes['orderby'] === 'post__in' ? 'post__in' : $attributes['orderby'];

            return get_posts(
                [
                    'post__in' => $include,
                    'post_status' => 'inherit',
                    'post_type' => 'attachment',
                    'post_mime_type' => 'image',
                    'order' => $attributes['order'],
                    'orderby' => $orderby,
                    'posts_per_page' => -1,
                    'suppress_filters' => false,
                ]
            );
        }

        if ((int) $attributes['id'] === 0) {
            return [];
        }

        $query = [
            'post_parent' => (int) $attributes['id'],
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'order' => $attributes['order'],
            'orderby' => $attributes['orderby'],
            'posts_per_page' => -1,
            'suppress_filters' => false,
        ];

        $exclude = self::sanitizeIds((string) $attributes['exclude']);

        if ($exclude !== []) {
            $query['post__not_in'] = $exclude;
        }

        return get_posts($query);
    }

    /**
     * @return int[]
     */
    private static function sanitizeIds(string $ids): array
    {
        if ($ids === '') {
            return [];
        }

        $ids = array_map('absint', explode(',', preg_replace('/[^0-9,]+/', '', $ids)));

        return array_values(array_filter($ids));
    }

    /**
     * @param WP_Post[] $attachments
     */
    private static function render(array $attachments, array $attributes, int $instance): string
    {
        $items = self::buildItems($attachments, $attributes);

        if ($items === []) {
            return '';
        }

        return Timber::compile(
            'partial/gallery.twig',
            [
                'gallery' => [
                    'id' => 'zw-gallery-' . $instance,
                    'type' => $attributes['type'],
                    'classes' => self::getGalleryClasses($attributes),
                    'items' => $items,
                ],
            ]
        );
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

            $meta = wp_get_attachment_metadata($attachment->ID);
            $width = isset($meta['width']) ? absint($meta['width']) : 0;
            $height = isset($meta['height']) ? absint($meta['height']) : 0;

            if ($width === 0 || $height === 0) {
                $image = wp_get_attachment_image_src($attachment->ID, 'full');
                $width = isset($image[1]) ? absint($image[1]) : 1;
                $height = isset($image[2]) ? absint($image[2]) : 1;
            }

            $ratio = max(0.5, min(3.5, $width / max(1, $height)));

            $image = self::renderImage($attachment, $attributes);

            if ($image === '') {
                continue;
            }

            $items[] = [
                'caption' => trim(wp_strip_all_tags($attachment->post_excerpt)),
                'classes' => self::getItemClasses($ratio, $attributes['type'], (int) $index),
                'image' => $image,
                'label' => self::getItemLabel($attachment),
                'link' => self::getLinkUrl($attachment, $attributes['link']),
            ];
        }

        return $items;
    }

    private static function renderImage(WP_Post $attachment, array $attributes): string
    {
        $alt = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true);

        if (!is_string($alt) || $alt === '') {
            $alt = $attachment->post_excerpt ?: $attachment->post_title;
        }

        $image = wp_get_attachment_image(
            $attachment->ID,
            $attributes['size'],
            false,
            [
                'alt' => trim(wp_strip_all_tags((string) $alt)),
                'class' => self::getImageClasses($attributes['type']),
                'decoding' => 'async',
                'loading' => 'lazy',
                'sizes' => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
            ]
        );

        return is_string($image) ? $image : '';
    }

    private static function getGalleryClasses(array $attributes): string
    {
        $base = 'not-prose clear-both my-6 grid';
        $type = $attributes['type'];

        if ($type === 'rectangular') {
            return $base . ' grid-flow-dense grid-cols-2 gap-1 md:grid-cols-6 md:auto-rows-[8.5rem] lg:auto-rows-[9.5rem]';
        }

        if ($type === 'columns') {
            return $base . ' grid-flow-dense grid-cols-2 gap-1 md:grid-cols-6 md:auto-rows-[10rem]';
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

    private static function getItemClasses(float $ratio, string $type, int $index): string
    {
        $base = 'group relative m-0 overflow-hidden bg-gray-100';

        if ($type === 'circle') {
            return $base . ' aspect-square rounded-full';
        }

        if ($type === 'square') {
            return $base . ' aspect-square';
        }

        if ($ratio >= 2.2) {
            return $base . ' col-span-2 aspect-square md:aspect-auto md:col-span-4 md:row-span-2';
        }

        if ($ratio <= 0.85) {
            return $base . ' col-span-1 aspect-square md:aspect-auto md:col-span-2 md:row-span-3';
        }

        if ($index % 7 === 0 || $ratio >= 1.25) {
            return $base . ' col-span-1 aspect-square md:aspect-auto md:col-span-3 md:row-span-2';
        }

        return $base . ' col-span-1 aspect-square md:aspect-auto md:col-span-2 md:row-span-2';
    }

    private static function getImageClasses(string $type): string
    {
        $classes = 'block h-full w-full object-cover transition-transform duration-200 group-hover:scale-[1.025] group-focus-within:scale-[1.025]';

        if ($type === 'circle') {
            return $classes . ' rounded-full';
        }

        return $classes;
    }

    private static function getItemLabel(WP_Post $attachment): string
    {
        $caption = trim(wp_strip_all_tags($attachment->post_excerpt));

        if ($caption !== '') {
            return $caption;
        }

        return trim(wp_strip_all_tags($attachment->post_title));
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
