<?php
/**
 * Template Name: TV Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();

wp_enqueue_style('video.js', 'https://vjs.zencdn.net/7.11.5/video-js.css');
wp_enqueue_script('video.js', 'https://vjs.zencdn.net/7.11.5/video.min.js');

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
