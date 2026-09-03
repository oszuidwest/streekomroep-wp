<?php

/**
 * Loads the WordPress HTML API outside WordPress for the standalone tests.
 *
 * The core files come from the roots/wordpress-no-content dev dependency, pinned
 * to the version the theme requires; its classes are in the dev classmap. Core
 * notices become exceptions so misuse fails loudly.
 */

$core = __DIR__ . '/../vendor/roots/wordpress-no-content/wp-includes';

if (!function_exists('__')) {
    function __(string $text): string // phpcs:ignore WordPress.WP.I18n -- core test double.
    {
        return $text;
    }
}

if (!function_exists('_doing_it_wrong')) {
    function _doing_it_wrong(string $function, string $message, string $version): void
    {
        throw new RuntimeException($function . ': ' . $message . ' (' . $version . ')');
    }
}

require $core . '/compat-utf8.php';
require $core . '/utf8.php';
require $core . '/html-api/html5-named-character-references.php';
