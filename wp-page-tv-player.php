<?php
/**
 * Template Name: TV Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$context['options'] = get_fields('option');

wp_enqueue_style('video.js', 'https://unpkg.com/video.js/dist/video-js.min.css');
wp_enqueue_script('video.js', 'https://unpkg.com/video.js/dist/video.min.js');

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
