<?php

// After save
function zw_vimeo_save_thumbnail($post_ID)
{
    if (!is_int($post_ID)) return;
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

    $thumbnail_id = get_post_thumbnail_id($post_ID);
    $key = $vimeo->pictures->resource_key;
    if ($thumbnail_id != 0) {
        $meta = get_post_meta($thumbnail_id, 'vimeo_resource_key', true);
        if ($meta == $key) {
            // Current thumbnail is already the selected one
            return;
        }
    }

    $bestPic = null;
    $bestWidth = -1;
    foreach ($vimeo->pictures->sizes as $size) {
        if ($size->width > $bestWidth) {
            $bestPic = $size;
        }
    }

    if ($bestPic === null)
        return;

    $tempPath = download_url($bestPic->link);
    if ($tempPath instanceof WP_Error) {
        dd($tempPath);
        return;
    }

    $path = parse_url($bestPic->link, PHP_URL_PATH);

    $file = [
        'name' => basename($path),
        'tmp_name' => $tempPath,
    ];
    $thumbnail_id = media_handle_sideload($file, $post_ID);
    if ($thumbnail_id instanceof WP_Error) {
        var_dump($path);
        var_dump($thumbnail_id);
        return;
    }

    set_post_thumbnail($post_ID, $thumbnail_id);
    update_post_meta($thumbnail_id, 'vimeo_resource_key', $key);
}

add_action('acf/save_post', 'zw_vimeo_save_thumbnail');
