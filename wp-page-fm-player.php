<?php
/**
 * Template Name: FM Player
 */

$context = Timber::context();
$context['post'] = new Timber\Post();
$schedule = new \Streekomroep\BroadcastSchedule();
$context['current'] = $schedule->getCurrentRadioBroadcast();
$context['next'] = $schedule->getNextRadioBroadcast();

Timber::render(['page-fm-player.twig', 'page.twig'], $context);
