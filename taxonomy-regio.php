<?php

$context = Timber::context();
$region = new \Timber\Term(get_queried_object());
$context['term'] = $region;

$context['calendar'] = Timber::get_posts([
    'post_type' => 'agenda',
]);

$context['fragment'] = Timber::get_posts([
    'post_type' => 'fragment',
]);

$context['news'] = Timber::get_posts([
    'post_type' => 'post',
    'ignore_sticky_posts' => true,
    'tax_query' => [
        [
            'taxonomy' => 'regio',
            'include_children' => false,
            'terms' => $region->term_id,
        ]
    ]
]);

$context['regions'] = Timber::get_terms([
    'taxonomy' => 'regio'
]);

Timber::render(['regio.twig'], $context);
