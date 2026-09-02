<?php

/**
 * Collapsible sections in articles.
 *
 * The Classic Editor plugin in static/tinymce-collapsible.js inserts an editable
 * <div class="collapsible"> holding a title and <details class="collapsible-item">
 * items with their headings in <summary>. The block is stored as plain HTML and
 * styled by assets/style.css; nothing here runs on regular page views.
 */

use Streekomroep\CollapsibleNormalizer;

/** The button belongs to the main content editor of articles only. */
function zw_collapsible_is_post_content_editor(string $editor_id): bool
{
    if ($editor_id !== 'content' || !function_exists('get_current_screen')) {
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

    // Sit next to the "Read more" tag, the other article-structure button.
    $position = array_search('wp_more', $buttons, true);
    array_splice($buttons, $position === false ? count($buttons) : $position + 1, 0, 'zw_collapsible');

    return $buttons;
}

add_filter('mce_buttons', 'zw_collapsible_editor_button', 10, 2);

/**
 * Rewrites sections into their canonical form before they are stored. This runs
 * before the theme's kses allowlist, which would otherwise drop a stray h1
 * together with the title class.
 */
function zw_collapsible_sanitize_post_data(array $data): array
{
    if (isset($data['post_content']) && str_contains($data['post_content'], 'collapsible')) {
        $data['post_content'] = wp_slash(CollapsibleNormalizer::normalize(wp_unslash($data['post_content'])));
    }

    return $data;
}

add_filter('wp_insert_post_data', 'zw_collapsible_sanitize_post_data', 9);

add_filter('the_content_feed', [CollapsibleNormalizer::class, 'flatten']);
