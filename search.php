<?php
/**
 * Search results template.
 *
 * @package Streekomroep
 */

$templates = [ 'search.twig', 'archive.twig', 'index.twig' ];

$context          = Timber::context();
$context['posts'] = Timber::get_posts();

Timber::render($templates, $context);
