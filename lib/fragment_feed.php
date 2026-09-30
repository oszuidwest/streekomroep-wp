<?php

/**
 * Stores the media file of fragments as core `enclosure` meta, which
 * WordPress prints in RSS and Atom feeds.
 */

use Streekomroep\Fragment;
use Timber\Timber;

/**
 * Resolves the enclosure off the feed request: it costs a Bunny API call and a
 * HEAD request. Unavailable media (e.g. a video still encoding) clears it.
 */
function zw_fragment_update_enclosure(Fragment $fragment): void
{
    $source = $fragment->getSources()[0] ?? null;
    if ($source) {
        $response = wp_safe_remote_head($source['src'], ['timeout' => 10, 'redirection' => 5]);
        $length = (int) wp_remote_retrieve_header($response, 'content-length');
        if (wp_remote_retrieve_response_code($response) === 200 && $length > 0) {
            update_post_meta($fragment->ID, 'enclosure', implode("\n", [$source['src'], $length, $source['type']]));
            return;
        }
    }

    delete_post_meta($fragment->ID, 'enclosure');
}

add_action('acf/save_post', function ($post_id) {
    if (is_int($post_id) && get_post_type($post_id) === 'fragment') {
        zw_fragment_update_enclosure(Timber::get_post($post_id));
    }
});

// Retry feed items without an enclosure, e.g. videos that finished encoding.
add_action('zw_10mins', function () {
    $fragments = Timber::get_posts([
        'post_type' => 'fragment',
        'posts_per_page' => get_option('posts_per_rss'),
        'ignore_sticky_posts' => true,
    ]);

    foreach ($fragments as $fragment) {
        if (!get_post_meta($fragment->ID, 'enclosure', true)) {
            zw_fragment_update_enclosure($fragment);
        }
    }
});
