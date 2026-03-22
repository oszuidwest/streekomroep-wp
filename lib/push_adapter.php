<?php

add_filter('zw_webapp_send_notification', 'zw_webapp_send_notification', 10, 2);
add_filter('zw_webapp_title', 'zw_webapp_push_title', 10, 2);

function zw_webapp_send_notification($do_send, $post_id)
{
    return get_field('push_post', $post_id);
}

function zw_webapp_push_title($title, $post_id)
{
    if (has_term('breaking', 'ranking', $post_id)) {
        return 'Breaking';
    }
    if (has_term('leestip', 'ranking', $post_id)) {
        return 'Leestip';
    }

    $yoast_primary_term = get_post_meta($post_id, '_yoast_wpseo_primary_regio', true) ?: '';
    if ($yoast_primary_term) {
        $term = get_term($yoast_primary_term, 'regio');
        $yoast_primary_term = $term ? $term->name : '';
    } else {
        $terms = get_the_terms($post_id, 'regio');
        $yoast_primary_term = $terms && !is_wp_error($terms) ? $terms[0]->name : '';
    }

    if (!empty($yoast_primary_term)) {
        return $yoast_primary_term;
    }

    return $title;
}
