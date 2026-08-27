<?php

/** Routes eligible JPEG content images through imgproxy with article-column sizing. */

use Streekomroep\Layout;
use Streekomroep\ResponsiveImage;

add_filter('wp_content_img_tag', 'zw_content_image_cdn', 10, 3);

function zw_content_image_cdn($image, $context, $attachment_id)
{
    if ($context !== 'the_content' || !$attachment_id || !zw_imgproxy_is_configured()) {
        return $image;
    }

    // Re-encoding other formats could discard transparency or animation.
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

    // Preserve renditions narrower than the content column.
    $width_attr = (int) $processor->get_attribute('width');
    if ($width_attr > 0 && $width_attr < Layout::CONTENT_WIDTH) {
        return $image;
    }

    // Cap candidates at 2x the desktop content width without upscaling.
    $max_width = min($full_width, Layout::CONTENT_WIDTH * 2);
    $widths = array_filter([480, Layout::CONTENT_WIDTH, 960, $max_width], fn ($w) => $w <= $max_width);

    $src_width = min(Layout::CONTENT_WIDTH, $full_width);
    $src_height = ResponsiveImage::scaleHeight($src_width, $full_width, $full_height);

    $processor->set_attribute('src', zw_imgproxy($full_url, $src_width, $src_height));
    $processor->set_attribute(
        'srcset',
        ResponsiveImage::srcsetForWidths($full_url, $widths, $full_width, $full_height)
    );
    $processor->set_attribute('sizes', Layout::CONTENT_SIZES);

    // Reapply Core's conditional "auto" prefix after replacing sizes.
    return wp_img_tag_add_auto_sizes($processor->get_updated_html());
}
