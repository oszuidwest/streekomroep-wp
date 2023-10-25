<?php

$context = Timber::context();
$region = Timber::get_term(get_queried_object());
$context['region'] = $region;

global $paged;
if (!isset($paged) || !$paged) {
    // @phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    $paged = 1;
}

// TODO: Check if we need to have a custom query here
$context['news'] = Timber::get_posts([
    'post_type' => 'post',
    'ignore_sticky_posts' => true,
    'paged' => $paged,
    'tax_query' => [
        [
            'taxonomy' => 'regio',
            'include_children' => true,
            'terms' => $region->term_id,
        ]
    ]
]);

wp_enqueue_script('jquery');
Timber::render(['regio.twig'], $context);
