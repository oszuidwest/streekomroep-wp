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

    update_meta_cache('post', $articles->posts);

    $fragment_ids = [];
    foreach ($articles->posts as $article_id) {
        $fragment_ids = array_merge($fragment_ids, (array) get_post_meta($article_id, 'post_gekoppeld_fragment', true));
    }

    $fragment_ids = array_filter(wp_parse_id_list($fragment_ids));
    if (!$fragment_ids) {
        return;
    }

    $query->set('post__not_in', wp_parse_id_list(array_merge((array) $query->get('post__not_in'), $fragment_ids)));
}

add_action('pre_get_posts', 'zw_exclude_linked_fragments_from_search');
