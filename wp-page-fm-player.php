<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$schedule = new \Streekomroep\BroadcastSchedule();
$context['current'] = $schedule->getCurrentRadioBroadcast();
$context['next'] = $schedule->getNextRadioBroadcast();

wp_enqueue_style('video.js', 'https://vjs.zencdn.net/7.11.8/video-js.css');
wp_enqueue_script('video.js', 'https://vjs.zencdn.net/7.11.8/video.min.js');

Timber::render(['page-fm-player.twig', 'page.twig'], $context);
