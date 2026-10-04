<?php

/**
 * Exposes an article's featured video in RSS using the fragment's cached file.
 * Feed requests never resolve Bunny videos or download media metadata.
 */

use Streekomroep\Fragment;

function zw_article_video_enclosure(): void
{
    $post_id = get_the_ID();
    if (get_post_type($post_id) !== 'post' || get_post_field('post_password', $post_id) !== '') {
        return;
    }
    if (!get_post_meta($post_id, 'post_fragment_is_featured', true)) {
        return;
    }

    $linked = (array) get_post_meta($post_id, 'post_gekoppeld_fragment', true);
    $fragment_id = (int) ($linked[0] ?? 0);
    if (
        get_post_type($fragment_id) !== 'fragment'
        || get_post_status($fragment_id) !== 'publish'
        || get_post_field('post_password', $fragment_id) !== ''
        || get_post_meta($fragment_id, 'fragment_type', true) !== Fragment::TYPE_VIDEO
    ) {
        return;
    }

    $enclosure = explode("\n", (string) get_post_meta($fragment_id, 'enclosure', true));
    if (count($enclosure) < 3 || trim($enclosure[2]) !== 'video/mp4' || (int) $enclosure[1] <= 0) {
        return;
    }
    $url = esc_url(trim($enclosure[0]));
    if ($url === '') {
        return;
    }

    printf('<enclosure url="%s" length="%d" type="video/mp4" />' . "\n", $url, $enclosure[1]);
}

add_action('rss2_item', 'zw_article_video_enclosure');

/**
 * Hides the enclosures core discovers from media linked in an article's body,
 * so an article only ever exposes its featured video.
 */
function zw_hide_article_enclosure(string $enclosure): string
{
    return get_post_type() === 'post' ? '' : $enclosure;
}

add_filter('rss_enclosure', 'zw_hide_article_enclosure');
add_filter('atom_enclosure', 'zw_hide_article_enclosure');
