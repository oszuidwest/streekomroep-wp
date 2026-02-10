<?php
/**
 * Template Name: TV Player
 */

use Streekomroep\BroadcastSchedule;

$context = Timber::context();
$context['post'] = Timber::get_post();

$context['tv_channels'] = Timber::get_posts([
    'post_type'      => 'page',
    'meta_key'       => '_wp_page_template',
    'meta_value'     => 'wp-page-tv-player.php',
    'posts_per_page' => -1,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
]);

$schedule = new BroadcastSchedule();
$today = $schedule->getToday();
$context['tv_today'] = $today->television;

Timber::render(['page-tv-player.twig', 'page.twig'], $context);
