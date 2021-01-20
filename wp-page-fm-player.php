<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$context['current'] = new Timber\Post();
$context['next'] = new Timber\Post();
$context['options'] = get_fields('option');

wp_enqueue_style('video.js', 'https://vjs.zencdn.net/7.11.2/video-js.css');
wp_enqueue_script('video.js', 'https://vjs.zencdn.net/7.11.2/video.min.js');

Timber::render(['page-fm-player.twig', 'page.twig'], $context);
