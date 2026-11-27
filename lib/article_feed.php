<?php

/**
 * Adds an RSS item's featured image and, for articles, its featured fragment. The fragment
 * uses the media file the fragment feed cached for it, so feed requests never call Bunny.
 */

function zw_article_featured_image_media(): void
{
    $post_id = get_the_ID();
    // The password form on the site doesn't show the featured image either.
    if (get_post_field('post_password', $post_id) !== '') {
        return;
    }

    $image = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'large');
    if (!$image) {
        return;
    }

    // Take the type from the file the tag points to: a sub-size can have another format than the upload.
    $type = wp_check_filetype($image[0], wp_get_mime_types())['type'];
    $url = esc_url($image[0]);
    if (!$type || $url === '') {
        return;
    }

    printf(
        '<media:content xmlns:media="http://search.yahoo.com/mrss/" medium="image" type="%s" url="%s" width="%d" height="%d" />' . "\n",
        esc_attr($type),
        $url,
        $image[1],
        $image[2]
    );
}

add_action('rss2_item', 'zw_article_featured_image_media');

function zw_article_fragment_enclosure(): void
{
    $post_id = get_the_ID();
    if (!get_post_meta($post_id, 'post_fragment_is_featured', true) || get_post_field('post_password', $post_id) !== '') {
        return;
    }

    $linked = (array) get_post_meta($post_id, 'post_gekoppeld_fragment', true);
    $fragment_id = (int) ($linked[0] ?? 0);
    // Without an ID, get_post_status() and get_post_field() read the article itself.
    if (!$fragment_id || get_post_status($fragment_id) !== 'publish' || get_post_field('post_password', $fragment_id) !== '') {
        return;
    }

    $enclosure = explode("\n", get_post_meta($fragment_id, 'enclosure', true));
    $url = esc_url(trim($enclosure[0]));
    if (isset($enclosure[2]) && (int) $enclosure[1] > 0 && $url !== '') {
        printf('<enclosure url="%s" length="%d" type="%s" />' . "\n", $url, $enclosure[1], esc_attr($enclosure[2]));
    }
}

add_action('rss2_item', 'zw_article_fragment_enclosure');
