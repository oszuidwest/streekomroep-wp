<?php
/**
 * Template Name: FM Guide
 */

$context = Timber::context();

$timber_post = Timber::get_post();
$context['post'] = $timber_post;
$context['schedule'] = new \Streekomroep\BroadcastSchedule();
$context['tv'] = zw_get_page_by_template('wp-page-tv-guide.php');

Timber::render(['page-fm-guide.twig', 'page.twig'], $context);
