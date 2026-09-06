<?php
/**
 * Fallback template.
 *
 * @package Streekomroep
 */

$context          = Timber::context();
$context['posts'] = Timber::get_posts();
Timber::render(['index.twig'], $context);
