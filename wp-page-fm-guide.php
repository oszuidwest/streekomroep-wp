<?php
/**
 * Template Name: FM Guide
 */

$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;

$shows = Timber::get_posts([
    'post_type' => 'fm',
    'posts_per_page' => -1,
    'ignore_sticky_posts' => true,
]);
$context['shows'] = $shows;

Timber::render(array('page-fm-guide.twig', 'page.twig'), $context);
