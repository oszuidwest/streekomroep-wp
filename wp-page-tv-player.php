<?php
/**
 * Template Name: TV Player
 */

use Streekomroep\BroadcastSchedule;

$context = Timber::context();
$context['post'] = Timber::get_post();

$context['tv_channels'] = zw_get_pages_by_template('wp-page-tv-player.php');

$schedule = new BroadcastSchedule();
$today = $schedule->getToday();
$context['tv_today'] = $today->television;

zw_require_videojs();
Timber::render(['page-tv-player.twig', 'page.twig'], $context);
