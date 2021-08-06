<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$schedule = new \Streekomroep\BroadcastSchedule();
$context['current'] = $schedule->getCurrentRadioBroadcast();
$context['next'] = $schedule->getNextRadioBroadcast();

wp_enqueue_style('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.14.3/video-js.min.css');
wp_enqueue_script('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.14.3/video.min.js');

Timber::render(['page-fm-player.twig', 'page.twig'], $context);
