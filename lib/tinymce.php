<?php

function zw_tinymce_paste_as_text(array $init): array
{
    $init['paste_as_text'] = true;

    return $init;
}

add_filter('tiny_mce_before_init', 'zw_tinymce_paste_as_text');

/** The [uitklap] button belongs to the main content editor of articles only. */
function zw_tinymce_is_post_content_editor(string $editor_id): bool
{
    if ($editor_id !== 'content' || !function_exists('get_current_screen')) {
        return false;
    }

    $screen = get_current_screen();

    return $screen !== null && $screen->base === 'post' && $screen->post_type === 'post';
}

function zw_tinymce_uitklap_plugin(array $plugins, string $editor_id = ''): array
{
    if (zw_tinymce_is_post_content_editor($editor_id)) {
        $plugins['zw_uitklap'] = add_query_arg('ver', wp_get_theme()->get('Version'), get_theme_file_uri('static/tinymce-uitklap.js'));
    }

    return $plugins;
}

add_filter('mce_external_plugins', 'zw_tinymce_uitklap_plugin', 10, 2);

function zw_tinymce_uitklap_button(array $buttons, string $editor_id = ''): array
{
    if (!zw_tinymce_is_post_content_editor($editor_id)) {
        return $buttons;
    }

    // Sit next to the "Read more" tag, which is the other article-structure button.
    $position = array_search('wp_more', $buttons, true);
    array_splice($buttons, $position === false ? count($buttons) : $position + 1, 0, 'zw_uitklap');

    return $buttons;
}

add_filter('mce_buttons', 'zw_tinymce_uitklap_button', 10, 2);
