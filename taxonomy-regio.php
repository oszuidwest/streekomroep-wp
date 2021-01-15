<?php

$context = Timber::context();
$context['term'] = new \Timber\Term(get_queried_object());

$context['calendar'] = Timber::get_posts([
    'post_type' => 'agenda',
]);

$context['fragment'] = Timber::get_posts([
    'post_type' => 'fragment',
]);

$context['news'] = Timber::get_posts([
    'post_type' => 'post',
]);

Timber::render(['regio.twig'], $context);
