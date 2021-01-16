<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();

Timber::render(['page-fm-live.twig', 'page.twig'], $context);
