<?php
/**
 * Template Name: TV Player
 */

$context = Timber::context();
$context['post'] = Timber::get_post();

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
