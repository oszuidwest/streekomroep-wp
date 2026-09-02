<?php

/**
 * Collapsible sections in articles.
 *
 * The Classic Editor plugin in static/tinymce-uitklap.js inserts an editable
 * <div class="uitklap-groep"> holding a title and <details class="uitklap">
 * items with their headings in <summary>. The block is stored as plain HTML and
 * styled by assets/style.css; nothing here runs on regular page views.
 */

/** The button belongs to the main content editor of articles only. */
function zw_uitklap_is_post_content_editor(string $editor_id): bool
{
    if ($editor_id !== 'content' || !function_exists('get_current_screen')) {
        return false;
    }

    $screen = get_current_screen();

    return $screen !== null && $screen->base === 'post' && $screen->post_type === 'post';
}

function zw_uitklap_editor_plugin(array $plugins, string $editor_id = ''): array
{
    if (zw_uitklap_is_post_content_editor($editor_id)) {
        $plugins['zw_uitklap'] = add_query_arg('ver', wp_get_theme()->get('Version'), get_theme_file_uri('static/tinymce-uitklap.js'));
    }

    return $plugins;
}

add_filter('mce_external_plugins', 'zw_uitklap_editor_plugin', 10, 2);

function zw_uitklap_editor_button(array $buttons, string $editor_id = ''): array
{
    if (!zw_uitklap_is_post_content_editor($editor_id)) {
        return $buttons;
    }

    // Sit next to the "Read more" tag, the other article-structure button.
    $position = array_search('wp_more', $buttons, true);
    array_splice($buttons, $position === false ? count($buttons) : $position + 1, 0, 'zw_uitklap');

    return $buttons;
}

add_filter('mce_buttons', 'zw_uitklap_editor_button', 10, 2);

/** Feed readers rarely render disclosure widgets, so an item becomes a heading with its text. */
function zw_uitklap_flatten(string $content): string
{
    if (!str_contains($content, 'uitklap')) {
        return $content;
    }

    $content = preg_replace(
        '#<details\b[^>]*\bclass="[^"]*\buitklap\b[^"]*"[^>]*>\s*<summary>(.*?)</summary>#is',
        '<h4>$1</h4>',
        $content,
        -1,
        $count
    );

    return $count > 0 ? preg_replace('#</details>#i', '', $content) : $content;
}

add_filter('the_content_feed', 'zw_uitklap_flatten');
