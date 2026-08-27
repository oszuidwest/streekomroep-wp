<?php

/**
 * Prevents an article and its attached fragment from appearing as separate search results.
 *
 * A fragment remains searchable when no article containing it matches the search term.
 */
function zw_exclude_linked_fragments_from_search(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    $search = trim((string) $query->get('s'));
    if ($search === '') {
        return;
    }

    $articles = new WP_Query([
        's' => $search,
        'post_type' => 'post',
        'post_status' => 'publish',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'orderby' => 'none',
        'meta_query' => [
            [
                'key' => 'post_gekoppeld_fragment',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    if (!$articles->posts) {
        return;
    }

    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($articles->posts), '%d'));
    // Fetch only this key; priming the meta cache would load every meta row of every matched article.
    // phpcs:disable WordPress.DB.PreparedSQL -- table name comes from $wpdb, values go through placeholders.
    $meta_values = $wpdb->get_col($wpdb->prepare(
        'SELECT meta_value FROM ' . $wpdb->postmeta
            . " WHERE meta_key = 'post_gekoppeld_fragment' AND post_id IN (" . $placeholders . ')',
        $articles->posts
    ));
    // phpcs:enable WordPress.DB.PreparedSQL

    $fragment_ids = [];
    foreach ($meta_values as $meta_value) {
        array_push($fragment_ids, ...(array) maybe_unserialize($meta_value));
    }

    $fragment_ids = array_filter(wp_parse_id_list($fragment_ids));
    if (!$fragment_ids) {
        return;
    }

    $query->set('post__not_in', array_merge((array) $query->get('post__not_in'), $fragment_ids));
}

add_action('pre_get_posts', 'zw_exclude_linked_fragments_from_search');
