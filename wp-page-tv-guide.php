<?php
/**
 * Template Name: TV Guide
 */

$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;
$context['schedule'] = new \Streekomroep\BroadcastSchedule();
$context['fm'] = zw_get_page_by_template('wp-page-fm-guide.php');

Timber::render(array('page-tv-guide.twig', 'page.twig'), $context);
