<?php

/**
 * Loads the bundled WordPress HTML API for standalone tests.
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
