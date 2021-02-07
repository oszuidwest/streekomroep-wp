<?php
/**
 * Template Name: FM Guide
 */

$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;
$context['schedule'] = new \Streekomroep\BroadcastSchedule();

Timber::render(array('page-fm-guide.twig', 'page.twig'), $context);
