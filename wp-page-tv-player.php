<?php
/**
 * Template Name: TV Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$context['options'] = get_fields('option');

wp_enqueue_style('video.js', 'https://vjs.zencdn.net/7.9.7/video-js.css'');
wp_enqueue_script('video.js', 'https://vjs.zencdn.net/7.9.7/video.min.js');


wp_enqueue_style('wp-mediaelement');
wp_enqueue_script('wp-mediaelement');

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
