<?php

/**
 * Routes JPEG images in post content through imgproxy, with srcset candidates
 * and a sizes attribute matched to the article column.
 *
 * WordPress core generates sizes="100vw" for content images, while the content
 * column (max-w-3xl minus md:px-12) is at most 672 CSS pixels wide. Core also
 * serves content images straight from wp-content as JPEG, bypassing the CDN's
 * WebP content negotiation.
 */

function zw_imgproxy_is_configured(): bool
{
    $key = zw_get_imgproxy_setting('zw_imgproxy_key', 'IMGPROXY_KEY');
    $salt = zw_get_imgproxy_setting('zw_imgproxy_salt', 'IMGPROXY_SALT');
    $host = zw_normalize_imgproxy_url(zw_get_imgproxy_setting('zw_imgproxy_url', 'IMGPROXY_URL'));

    return $key !== '' && $salt !== '' && $host !== '';
}

// Priority 5 runs before core's wp_img_tag_add_auto_sizes() (priority 10), so
// lazy-loaded images still get "auto" prepended to the rewritten sizes value.
add_filter('wp_content_img_tag', 'zw_content_image_cdn', 5, 3);

function zw_content_image_cdn($image, $context, $attachment_id)
{
    if ($context !== 'the_content' || !$attachment_id || !zw_imgproxy_is_configured()) {
        return $image;
    }

    $meta = wp_get_attachment_metadata($attachment_id);
    $full_width = isset($meta['width']) ? (int) $meta['width'] : 0;
    $full_height = isset($meta['height']) ? (int) $meta['height'] : 0;
    if ($full_width < 1 || $full_height < 1) {
        return $image;
    }

    // Only photos; PNG transparency would be lost in imgproxy's JPEG output.
    $full_url = wp_get_attachment_url($attachment_id);
    if (!$full_url || !preg_match('/\.jpe?g$/i', $full_url)) {
        return $image;
    }

    $processor = new WP_HTML_Tag_Processor($image);
    if (!$processor->next_tag('img')) {
        return $image;
    }

    // Editor-inserted thumbnails narrower than the column keep core's defaults.
    $width_attr = (int) $processor->get_attribute('width');
    if ($width_attr > 0 && $width_attr < 672) {
        return $image;
    }

    // 1344 covers the 672px column on 2x displays; never upscale beyond the source.
    $max_width = min($full_width, 1344);
    $widths = array_filter([480, 672, 960], fn ($width) => $width < $max_width);
    $widths[] = $max_width;

    $src_width = min(672, $max_width);
    $src_height = (int) round($src_width / $full_width * $full_height);

    $processor->set_attribute('src', zw_imgproxy($full_url, $src_width, $src_height));
    $processor->set_attribute(
        'srcset',
        \Streekomroep\ResponsiveImage::srcsetForWidths($full_url, $widths, $full_width, $full_height)
    );
    $processor->set_attribute('sizes', '(min-width: 768px) 672px, calc(100vw - 3rem)');

    return $processor->get_updated_html();
}
