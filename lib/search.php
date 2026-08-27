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
        $linked_fragments = get_post_meta($article_id, 'post_gekoppeld_fragment', true);
        foreach ((array) $linked_fragments as $fragment_id) {
            $fragment_id = absint($fragment_id);
            if ($fragment_id) {
                $fragment_ids[] = $fragment_id;
            }
        }
    }

    if (!$fragment_ids) {
        return;
    }

    $excluded_ids = array_map('absint', (array) $query->get('post__not_in'));
    $query->set('post__not_in', array_values(array_unique(array_merge($excluded_ids, $fragment_ids))));
}

add_action('pre_get_posts', 'zw_exclude_linked_fragments_from_search');
