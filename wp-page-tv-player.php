<?php
/**
 * Template Name: TV Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();

wp_enqueue_style('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.14.3/video-js.min.css');
wp_enqueue_script('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.14.3/video.min.js');

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
