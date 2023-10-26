<?php
/**
 * Template Name: Search
 */

$context = Timber::context();
$context['post'] = Timber::get_post();

Timber::render(['search-page.twig'], $context);
