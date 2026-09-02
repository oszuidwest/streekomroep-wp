<?php

/**
 * [uitklap] shortcode: a collapsible background section inside an article.
 *
 * Usage in the editor:
 *
 *     [uitklap kop="Hoe zat het ook alweer?"]
 *     Paragraphs, links, images and embeds.
 *     [/uitklap]
 *
 * Add `open` to the opening tag to expand the section by default. Only regular
 * articles collapse; other post types and feeds render a plain heading and text.
 */

add_shortcode('uitklap', 'zw_uitklap_shortcode');

function zw_uitklap_shortcode($atts, ?string $content = null): string
{
    $atts = is_array($atts) ? $atts : [];
    // The visual editor stores an ampersand in the heading as an entity; decode so the template escapes it once.
    $kop = trim(html_entity_decode((string) ($atts['kop'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return Timber::compile('partial/uitklap.twig', [
        'kop' => $kop !== '' ? $kop : 'Meer informatie',
        'open' => zw_uitklap_is_open($atts),
        'content' => zw_uitklap_body($content),
        'collapsible' => get_post_type() === 'post' && !is_feed(),
    ]);
}

/** Accepts both the bare `open` flag and `open="ja"` style values. */
function zw_uitklap_is_open(array $atts): bool
{
    $flags = array_filter($atts, 'is_int', ARRAY_FILTER_USE_KEY);
    if (in_array('open', $flags, true)) {
        return true;
    }

    $value = strtolower(trim((string) ($atts['open'] ?? '')));

    return in_array($value, ['1', 'true', 'ja', 'yes', 'open'], true);
}

/**
 * wpautop runs before shortcodes, so an enclosed section arrives with a
 * dangling closing paragraph tag in front and an opening one behind it (or with
 * line breaks when the tags share a paragraph). Strip those and rebuild the
 * paragraphs from what remains.
 */
function zw_uitklap_body(?string $content): string
{
    $content = preg_replace('#^\s*(?:</p>|<br\s*/?>)+#i', '', (string) $content);
    $content = preg_replace('#(?:<p>|<br\s*/?>)+\s*$#i', '', $content);

    return do_shortcode(shortcode_unautop(wpautop(trim($content))));
}
