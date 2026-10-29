<?php

use Streekomroep\CollapsibleNormalizer;

// Blocks that save their text as raw HTML.
const ZW_RAW_HTML_BLOCKS = ['core/html', 'core/shortcode'];

/**
 * Sanitizes classic post content and raw HTML blocks against the theme's HTML allowlist.
 *
 * @param array $data An array of slashed, sanitized, and processed post data.
 * @param array $postarr Sanitized, slashed, otherwise unmodified post data.
 *
 * @return array Sanitized post data.
 */
function zw_sanitize_post_content(array $data, array $postarr): array
{
    $allowed_elements = [
        'a'          => [
            'href'   => true,
            'target' => true,
            'rel'    => true,
            'name'   => true,
            'title'  => true,
        ],
        'blockquote' => ['cite' => true],
        'details'    => ['class' => ['values' => ['collapsible-item']], 'open' => true],
        'div'        => ['class' => ['values' => ['collapsible']]],
        'em'         => [],
        'h2'         => ['id' => true],
        'h3'         => ['id' => true, 'class' => ['values' => ['collapsible-title']]],
        'iframe'     => [
            'src'             => true,
            'width'           => true,
            'height'          => true,
            'style'           => true,
            'scrolling'       => true,
            'loading'         => true,
            'frameborder'     => true,
            'allow'           => true,
            'allowfullscreen' => true,
        ],
        'img'        => [
            'alt'    => true,
            'class'  => true,
            'height' => true,
            'src'    => true,
            'width'  => true,
        ],
        'li'         => [],
        'ol'         => ['start' => true],
        'strong'     => [],
        'summary'    => [],
        'table'      => [],
        'tbody'      => [],
        'td'         => [],
        'tfoot'      => [],
        'th'         => [],
        'thead'      => [],
        'tr'         => [],
        'ul'         => [],
    ];

    if (!isset($data['post_content'])) {
        return $data;
    }

    $content = wp_unslash($data['post_content']);

    // The allowlist would strip the markup the block editor generates (<p>, <figure>,
    // classes) and invalidate every block, so only raw HTML blocks go through it.
    // Unchanged posts are not re-serialized, which could rewrite their markup.
    if (has_blocks($content)) {
        $blocks = parse_blocks($content);
        $filtered = zw_filter_raw_html_blocks($blocks, $allowed_elements);
        if ($filtered !== $blocks) {
            $data['post_content'] = wp_slash(serialize_blocks($filtered));
        }

        return $data;
    }

    // Normalize first so the allowlist receives canonical markup.
    $data['post_content'] = wp_slash(wp_kses(CollapsibleNormalizer::normalize($content), $allowed_elements));

    return $data;
}

add_filter('wp_insert_post_data', 'zw_sanitize_post_content', 10, 2);

/**
 * Runs the content of raw HTML blocks through the allowlist, at any depth.
 *
 * @param array $blocks Parsed blocks.
 * @param array $allowed_elements Allowed elements and attributes for wp_kses().
 *
 * @return array Parsed blocks.
 */
function zw_filter_raw_html_blocks(array $blocks, array $allowed_elements): array
{
    return array_map(function (array $block) use ($allowed_elements): array {
        if (in_array($block['blockName'], ZW_RAW_HTML_BLOCKS, true)) {
            // Serialization reads innerContent; inner block placeholders are null.
            $block['innerContent'] = array_map(fn ($chunk) => is_string($chunk) ? wp_kses($chunk, $allowed_elements) : $chunk, $block['innerContent']);
        }
        $block['innerBlocks'] = zw_filter_raw_html_blocks($block['innerBlocks'], $allowed_elements);

        return $block;
    }, $blocks);
}

/**
 * Hides the Custom HTML and Shortcode blocks from the block editor's inserter. Both
 * save their text as raw HTML; shortcodes still work inside paragraphs.
 */
function zw_hide_raw_html_blocks(): void
{
    // The editor's own block.json settings override server-side supports, so this
    // has to run in the client, before the core blocks are registered.
    wp_add_inline_script('wp-blocks', sprintf(
        "wp.hooks.addFilter('blocks.registerBlockType', 'zw/hide-raw-html-blocks', (settings, name) => %s.includes(name) ? { ...settings, supports: { ...settings.supports, inserter: false } } : settings);",
        wp_json_encode(ZW_RAW_HTML_BLOCKS)
    ));
}

add_action('enqueue_block_editor_assets', 'zw_hide_raw_html_blocks');

/**
 * Turns off the block editor's code editor and "Edit as HTML" for every block.
 *
 * @param array $settings Block editor settings.
 *
 * @return array Block editor settings.
 */
function zw_disable_block_code_editing(array $settings): array
{
    $settings['codeEditingEnabled'] = false;

    return $settings;
}

add_filter('block_editor_settings_all', 'zw_disable_block_code_editing');
