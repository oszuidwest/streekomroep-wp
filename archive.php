<?php
/**
 * Archive template.
 *
 * @package Streekomroep
 */

$templates = [ 'archive.twig', 'index.twig' ];

$context = Timber::context();

$context['title'] = 'Archief';
if (is_day()) {
    $context['title'] = 'Archief: ' . get_the_date('D M Y');
} elseif (is_month()) {
    $context['title'] = 'Archief: ' . get_the_date('M Y');
} elseif (is_year()) {
    $context['title'] = 'Archief: ' . get_the_date('Y');
} elseif (is_tag()) {
    $context['title'] = single_tag_title('', false);
} elseif (is_category()) {
    $context['title'] = single_cat_title('', false);
    array_unshift($templates, 'archive-' . get_query_var('cat') . '.twig');
} elseif (is_post_type_archive()) {
    $context['title'] = post_type_archive_title('', false);
    array_unshift($templates, 'archive-' . get_post_type() . '.twig');
} elseif (is_tax('dossier')) {
    $context['term'] = Timber::get_term(get_queried_object());
    array_unshift($templates, 'dossier.twig');
}

$context['posts'] = Timber::get_posts();

if (is_post_type_archive() && get_post_type() === 'tv') {
    $context['posts'] = $context['posts']->to_array();
    foreach ($context['posts'] as $show) {
        $videos = \Streekomroep\VideoCollection::forTvShow($show->id);
        $show->lastBroadcast = isset($videos[0]) ? $videos[0]->getBroadcastDate() : null;
    }

    usort($context['posts'], function ($lhs, $rhs) {
        return $rhs->lastBroadcast <=> $lhs->lastBroadcast;
    });
}

if (is_post_type_archive('fm')) {
    $context['posts'] = zw_fm_shows_in_broadcast_order($context['posts']->to_array());
}

Timber::render($templates, $context);
