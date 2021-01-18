<?php
/**
 * Template Name: TV Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$context['options'] = get_fields('option');

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
