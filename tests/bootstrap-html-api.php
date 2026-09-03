<?php

/**
 * Loads the WordPress HTML API outside WordPress for the standalone tests.
 *
 * The core files come from the roots/wordpress-no-content dev dependency, pinned
 * to the version the theme requires. Core notices become exceptions so misuse fails loudly.
 */

$core = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';

if (!function_exists('__')) {
    function __(string $text): string // phpcs:ignore WordPress.WP.I18n -- core test double.
    {
        return $text;
    }
}

if (!function_exists('_wp_can_use_pcre_u')) {
    function _wp_can_use_pcre_u(): bool
    {
        return true;
    }
}

if (!function_exists('_doing_it_wrong')) {
    function _doing_it_wrong(string $function, string $message, string $version): void
    {
        throw new RuntimeException($function . ': ' . $message . ' (' . $version . ')');
    }
}

if (!function_exists('wp_trigger_error')) {
    function wp_trigger_error(string $function, string $message): void
    {
        throw new RuntimeException($function . ': ' . $message);
    }
}

require $core . '/utf8.php';
require $core . '/class-wp-token-map.php';

foreach (
    [
        'html5-named-character-references',
        'class-wp-html-attribute-token',
        'class-wp-html-span',
        'class-wp-html-text-replacement',
        'class-wp-html-decoder',
        'class-wp-html-tag-processor',
        'class-wp-html-unsupported-exception',
        'class-wp-html-token',
        'class-wp-html-stack-event',
        'class-wp-html-doctype-info',
        'class-wp-html-active-formatting-elements',
        'class-wp-html-open-elements',
        'class-wp-html-processor-state',
        'class-wp-html-processor',
    ] as $file
) {
    require $core . '/html-api/' . $file . '.php';
}
