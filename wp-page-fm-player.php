<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$context['current'] = new Timber\Post();
$context['next'] = new Timber\Post();
$context['options'] = get_fields('option');

wp_enqueue_style('wp-mediaelement');
wp_enqueue_script('wp-mediaelement');

Timber::render(['page-fm-player.twig', 'page.twig'], $context);
