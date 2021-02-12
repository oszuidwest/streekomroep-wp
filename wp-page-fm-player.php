<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$schedule = new \Streekomroep\BroadcastSchedule();
$context['current'] = $schedule->getCurrentBroadcast();
$context['next'] = $schedule->getNextBroadcast();

wp_enqueue_style('video.js', 'https://vjs.zencdn.net/7.11.5/video-js.css');
wp_enqueue_script('video.js', 'https://vjs.zencdn.net/7.11.5/video.min.js');

Timber::render(['page-fm-player.twig', 'page.twig'], $context);
