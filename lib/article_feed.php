<?php

/**
 * Adds an article's featured fragment to its RSS item, using the media file the
 * fragment feed cached for it. Feed requests never call Bunny.
 */

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
