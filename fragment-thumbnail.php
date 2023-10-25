<?php

// After save
function zw_bunny_save_thumbnail($post_ID)
{
    if (!is_int($post_ID)) {
        return;
    }
    if (get_post_type($post_ID) !== 'fragment') {
        return;
    }

    $url = get_field('fragment_url', $post_ID, false);
    $video = zw_bunny_get_video_from_url($url);

    if (!$video) {
        return;
    }

    if (!$video->isAvailable()) {
        return;
    }

    update_field('fragment_duur', $video->getDuration(), $post_ID);

    $poster = $video->getThumbnail();

    $thumbnail_id = get_post_thumbnail_id($post_ID);
    if ($thumbnail_id != 0) {
        // This fragment already has a thumbnail
        return;
    }

    $tempPath = download_url($poster);
    if ($tempPath instanceof WP_Error) {
        error_log('Error downloading file: ' . $tempPath->get_error_message());
        return;
    }

    $file = [
        'name' => basename($poster),
        'tmp_name' => $tempPath,
    ];

    $post_array = [];
    $parent = get_post($post_ID);
    $post_array['post_date'] = $parent->post_date;
    $post_array['post_date_gmt'] = $parent->post_date_gmt;
    $post_array['meta_input'] = [];
    $post_array['meta_input']['bunny_poster_url'] = $poster;
    $thumbnail_id = media_handle_sideload($file, $post_ID, null, $post_array);
    if ($thumbnail_id instanceof WP_Error) {
        error_log('Error uploading file: ' . $thumbnail_id->get_error_message());
        return;
    }

    set_post_thumbnail($post_ID, $thumbnail_id);
}

add_action('acf/save_post', 'zw_bunny_save_thumbnail');
