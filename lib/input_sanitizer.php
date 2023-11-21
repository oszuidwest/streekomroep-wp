<?php

/**
 * Sanitizes post content to remove unwanted HTML elements.
 *
 * This function is designed to ensure that only allowed HTML elements and
 * attributes are included in post content, removing any arbitrary HTML added
 * by editors.
 *
 * @param array $data An array of slashed, sanitized, and processed post data.
 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
 *
 * @return array Sanitized data with only allowed HTML elements.
 */
function zw_sanitize_post_content(array $data, array $postarr): array
{
    // Define allowed HTML elements and attributes.
    $allowed_elements = [
        'a'          => [
            'href'   => true,
            'target' => true,
            'rel'    => true,
            'name'   => true,
            'title'  => true,
        ],
        'abbr'       => ['title' => true],
        'acronym'    => ['title' => true],
        'blockquote' => ['cite' => true],
        'cite'       => [],
        'code'       => [],
        'del'        => ['datetime' => true],
        'em'         => [],
        'h2'         => ['id' => true],
        'h3'         => ['id' => true],
        'h4'         => ['id' => true],
        'iframe'     => [
            'src'             => true,
            'width'           => true,
            'height'          => true,
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
        'q'          => ['cite' => true],
        'strike'     => [],
        'strong'     => [],
        'table'      => [],
        'tbody'      => [],
        'td'         => [],
        'tfoot'      => [],
        'th'         => [],
        'thead'      => [],
        'tr'         => [],
        'ul'         => [],
    ];

    // Validate and sanitize post content.
    if (isset($data['post_content'])) {
        $data['post_content'] = wp_kses($data['post_content'], $allowed_elements);
    }

    return $data;
}

add_filter('wp_insert_post_data', 'zw_sanitize_post_content', 10, 2);
