<?php

// After save
add_action('acf/save_post', function (int $post_ID) {
    if (get_post_type($post_ID) !== 'fragment') return;

    $url = get_field('fragment_url', $post_ID, false);
    $url = trim($url);
    if (!preg_match('|^https://vimeo.com/(\d+)$|', $url, $m)) {
        return;
    }

    $id = $m[1];

    $args = [
        'headers' => [
            'Authorization' => 'bearer ' . get_field('vimeo_access_token', 'option')
        ]
    ];
    $response = wp_remote_get('https://api.vimeo.com/videos/' . $id, $args);
    $vimeo = json_decode($response['body']);

    $duration = $vimeo->duration;
    update_field('fragment_duur', $duration, $post_ID);
});
