<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = Timber::get_post();
$schedule = new \Streekomroep\BroadcastSchedule();
$context['current'] = $schedule->getCurrentRadioBroadcast();
$context['next'] = $schedule->getNextRadioBroadcast();

zw_require_videojs();
Timber::render(['page-fm-player.twig', 'page.twig'], $context);
