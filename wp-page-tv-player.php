<?php
/**
 * Template Name: TV Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
