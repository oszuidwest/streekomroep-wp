<?php
$context = Timber::context();

$timber_post = new Timber\Post();
$context['post'] = $timber_post;
$context['options'] = get_fields('options');
Timber::render(array('page-' . $timber_post->post_name . '.twig', 'page.twig'), $context);
