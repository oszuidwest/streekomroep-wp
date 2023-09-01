<?php

$context = Timber::context();
$region = new \Timber\Term(get_queried_object());
$context['region'] = $region;

global $paged;
if (!isset($paged) || !$paged) {
    $paged = 1;
}

$context['news'] = new Timber\PostQuery([
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
