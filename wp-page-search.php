<?php
/**
 * Template Name: Search
 */

$context = Timber::context();
$context['post'] = new Timber\Post();

Timber::render(['search-page.twig'], $context);
