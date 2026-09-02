<?php

/**
 * Classic Editor and feed support for collapsible article sections.
 */

use Streekomroep\CollapsibleNormalizer;

function zw_collapsible_is_post_content_editor(string $editor_id): bool
{
    if ($editor_id !== 'content' || !is_admin()) {
        return false;
    }

    $screen = get_current_screen();

    return $screen !== null && $screen->base === 'post' && $screen->post_type === 'post';
}

function zw_collapsible_editor_plugin(array $plugins, string $editor_id = ''): array
{
    if (zw_collapsible_is_post_content_editor($editor_id)) {
        $plugins['zw_collapsible'] = add_query_arg('ver', wp_get_theme()->get('Version'), get_theme_file_uri('static/tinymce-collapsible.js'));
    }

    return $plugins;
}

add_filter('mce_external_plugins', 'zw_collapsible_editor_plugin', 10, 2);

function zw_collapsible_editor_button(array $buttons, string $editor_id = ''): array
{
    if (!zw_collapsible_is_post_content_editor($editor_id)) {
        return $buttons;
    }

    $position = array_search('wp_more', $buttons, true);
    array_splice($buttons, is_int($position) ? $position + 1 : count($buttons), 0, 'zw_collapsible');

    return $buttons;
}

add_filter('mce_buttons', 'zw_collapsible_editor_button', 10, 2);

add_filter('the_content_feed', [CollapsibleNormalizer::class, 'flatten']);
