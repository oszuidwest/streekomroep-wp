<?php

/**
 * Contact Form 7 integration: input purpose for personal data fields.
 *
 * Form definitions live in the database, so the theme adds the autocomplete
 * attribute (WCAG 1.3.5) to the fields that ask for the sender's own contact
 * details. A form that sets its own autocomplete option, for example
 * [text naam autocomplete:off], is left alone.
 */

add_filter('wpcf7_form_tag', 'zw_cf7_autocomplete_tag');

/**
 * Adds an autocomplete option to name, e-mail and telephone fields.
 *
 * @param array $tag Scanned form tag.
 * @return array
 */
function zw_cf7_autocomplete_tag($tag)
{
    if (!is_array($tag)) {
        return $tag;
    }

    $options = (array) ($tag['options'] ?? []);
    foreach ($options as $option) {
        if (str_starts_with((string) $option, 'autocomplete:')) {
            return $tag;
        }
    }

    $token = match ($tag['basetype'] ?? '') {
        'email' => 'email',
        'tel' => 'tel',
        'text' => in_array($tag['name'] ?? '', ['naam', 'name', 'your-name', 'uw-naam'], true) ? 'name' : null,
        default => null,
    };

    if ($token !== null) {
        $tag['options'] = [...$options, 'autocomplete:' . $token];
    }

    return $tag;
}
