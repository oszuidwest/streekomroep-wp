<?php

$context = Timber::context();
$region = Timber::get_term(get_queried_object());
$context['region'] = $region;

// TODO: Check if we need to have a custom query here
$context['news'] = Timber::get_posts([
    'post_type' => 'post',
    'ignore_sticky_posts' => true,
    'paged' => max(1, (int) get_query_var('paged')),
    'tax_query' => [
        [
            'taxonomy' => 'regio',
            'include_children' => true,
            'terms' => $region->term_id,
        ]
    ]
]);

Timber::render(['regio.twig'], $context);
