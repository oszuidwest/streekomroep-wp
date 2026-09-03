<?php

use Streekomroep\CollapsibleNormalizer;

/**
 * Sanitizes post content against the theme's HTML allowlist.
 *
 * @param array $data An array of slashed, sanitized, and processed post data.
 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
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

    // Normalize first so the allowlist receives canonical markup.
    if (isset($data['post_content'])) {
        $content = CollapsibleNormalizer::normalize(wp_unslash($data['post_content']));
        $data['post_content'] = wp_slash(wp_kses($content, $allowed_elements));
    }

    return $data;
}

add_filter('wp_insert_post_data', 'zw_sanitize_post_content', 10, 2);
