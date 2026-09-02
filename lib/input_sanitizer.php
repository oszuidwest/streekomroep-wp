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
        'blockquote' => ['cite' => true],
        // Collapsible sections inserted by the editor plugin (lib/collapsible.php).
        'details'    => ['class' => true, 'open' => true],
        'div'        => ['class' => true],
        'em'         => [],
        'h2'         => ['id' => true],
        'h3'         => ['id' => true, 'class' => true],
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

    // Validate and sanitize post content.
    if (isset($data['post_content'])) {
        $data['post_content'] = zw_restrict_classes(wp_kses($data['post_content'], $allowed_elements), [
            'DIV'     => 'collapsible',
            'H3'      => 'collapsible-title',
            'DETAILS' => 'collapsible-item',
        ]);
    }

    return $data;
}

/**
 * Keeps the class attribute on the given tags only for the one value each needs.
 *
 * kses allows an attribute wholesale or not at all, which would let any theme
 * utility class reach the front end on these elements.
 */
function zw_restrict_classes(string $content, array $classes): string
{
    $html = new WP_HTML_Tag_Processor($content);
    while ($html->next_tag()) {
        $tag = $html->get_tag();
        if (isset($classes[$tag]) && $html->get_attribute('class') !== $classes[$tag]) {
            $html->remove_attribute('class');
        }
    }

    return $html->get_updated_html();
}

add_filter('wp_insert_post_data', 'zw_sanitize_post_content', 10, 2);
