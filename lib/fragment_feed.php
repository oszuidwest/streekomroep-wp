<?php

/**
 * Stores the media file of fragments as core `enclosure` meta, which WordPress
 * prints in RSS and Atom feeds. Resolving costs a Bunny API call and a HEAD
 * request, so it runs off the feed request. Unavailable media clears it.
 */

use Streekomroep\Fragment;
use Timber\Timber;

function zw_fragment_update_enclosure(int $post_id): void
{
    $fragment = Timber::get_post($post_id);
    if (!$fragment instanceof Fragment) {
        return;
    }

    $source_url = $fragment->meta('fragment_url', ['format_value' => false]);
    try {
        $source = $fragment->getSources()[0] ?? null;
    } catch (Throwable $error) {
        error_log('Failed to resolve enclosure for fragment ' . $fragment->ID . ': ' . $error->getMessage());
        $source = null;
    }

    $enclosure = null;
    if ($source) {
        $response = wp_safe_remote_head($source['src'], ['timeout' => 10, 'redirection' => 5]);
        $length = (int) wp_remote_retrieve_header($response, 'content-length');
        if (wp_remote_retrieve_response_code($response) === 200 && $length > 0) {
            $enclosure = implode("\n", [$source['src'], $length, $source['type']]);
        }
    }

    // Never write a result for a URL an editor replaced while we were resolving.
    wp_cache_delete($fragment->ID, 'post_meta');
    if ($source_url !== get_post_meta($fragment->ID, 'fragment_url', true)) {
        return;
    }

    $enclosure ? update_post_meta($fragment->ID, 'enclosure', $enclosure) : delete_post_meta($fragment->ID, 'enclosure');
}

add_action('zw_fragment_update_enclosure', 'zw_fragment_update_enclosure');

add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) === 'fragment') {
        delete_post_meta($post_id, 'enclosure');
        wp_schedule_single_event(time(), 'zw_fragment_update_enclosure', [(int) $post_id]);
    }
});

// Retry feed items without an enclosure, e.g. videos that finished encoding.
add_action('zw_10mins', function () {
    $batch_size = (int) get_option('posts_per_rss');
    $page = (int) get_option('zw_fragment_enclosure_page', 1);
    $fragments = Timber::get_posts([
        'post_type' => 'fragment',
        'posts_per_page' => $batch_size,
        'paged' => $page,
        'no_found_rows' => true,
        'meta_key' => 'enclosure',
        'meta_compare' => 'NOT EXISTS',
    ]);

    update_option('zw_fragment_enclosure_page', count($fragments) < $batch_size ? 1 : $page + 1, false);
    foreach ($fragments as $fragment) {
        zw_fragment_update_enclosure($fragment->ID);
    }
});
