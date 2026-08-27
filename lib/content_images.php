<?php

/**
 * WordPress core generates sizes="100vw" for content images, while the content
 * column (Layout::CONTENT_WIDTH) is far narrower on most screens. Core also
 * serves content images straight from wp-content as JPEG, bypassing the CDN's
 * WebP content negotiation. Rewriting the tags to imgproxy URLs fixes both.
 */

use Streekomroep\Layout;
use Streekomroep\ResponsiveImage;

// Priority 5 runs before core's wp_img_tag_add_auto_sizes() (priority 10), so
// lazy-loaded images still get "auto" prepended to the rewritten sizes value.
add_filter('wp_content_img_tag', 'zw_content_image_cdn', 5, 3);

function zw_content_image_cdn($image, $context, $attachment_id)
{
    if ($context !== 'the_content' || !$attachment_id || !zw_imgproxy_is_configured()) {
        return $image;
    }

    // Only photos; PNG transparency would be lost in imgproxy's JPEG output.
    if (get_post_mime_type($attachment_id) !== 'image/jpeg') {
        return $image;
    }

    $src = wp_get_attachment_image_src($attachment_id, 'full');
    if (!$src || $src[1] < 1 || $src[2] < 1) {
        return $image;
    }
    [$full_url, $full_width, $full_height] = $src;

    $processor = new WP_HTML_Tag_Processor($image);
    if (!$processor->next_tag('img')) {
        return $image;
    }

    // Editor-inserted thumbnails narrower than the column keep core's defaults,
    // including wp-content delivery; they are knowingly left off the CDN.
    $width_attr = (int) $processor->get_attribute('width');
    if ($width_attr > 0 && $width_attr < Layout::CONTENT_WIDTH) {
        return $image;
    }

    // Twice the column width covers 2x displays; never upscale beyond the source.
    $max_width = min($full_width, Layout::CONTENT_WIDTH * 2);
    $widths = array_filter([480, Layout::CONTENT_WIDTH, 960, $max_width], fn ($w) => $w <= $max_width);

    $src_width = min(Layout::CONTENT_WIDTH, $max_width);
    [, $src_height] = wp_constrain_dimensions($full_width, $full_height, $src_width);

    $processor->set_attribute('src', zw_imgproxy($full_url, $src_width, $src_height));
    $processor->set_attribute(
        'srcset',
        ResponsiveImage::srcsetForWidths($full_url, $widths, $full_width, $full_height)
    );
    $processor->set_attribute('sizes', Layout::CONTENT_SIZES);

    return $processor->get_updated_html();
}
