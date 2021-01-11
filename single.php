<?php
/**
 * The Template for displaying all single posts
 *
 * Methods for TimberHelper can be found in the /lib sub-directory
 *
 * @package  WordPress
 * @subpackage  Timber
 * @since    Timber 0.1
 */

$context         = Timber::context();
$timber_post     = Timber::get_post();
$context['post'] = $timber_post;

if ($timber_post->post_type == 'fragment') {
    $context['embed'] = wp_oembed_get($timber_post->fragment_url, ['width' => 960]);
    $context['posts'] = Timber::get_posts(array(
        'post_type' => 'post',
        'ignore_sticky_posts' => true,
        'meta_query' => array(
            array(
                'key' => 'post_gekoppeld_fragment', // name of custom field
                'value' => '"' . $timber_post->id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                'compare' => 'LIKE'
            )
        )
    ));
}

$topic = $timber_post->topic();
$region = $timber_post->region();
if ($topic) {
    $related = [];
    $related['topic'] = $topic;
    $related['posts'] = Timber::get_posts(
        [
            'posts_per_page' => 4,
            'post_type' => 'post',
            'ignore_sticky_posts' => true,
            'tax_query' => [
                [
                    'taxonomy' => 'dossier',
                    'terms' => $topic->term_id,
                ]
            ]
        ]
    );
    $context['topical'] = $related;
} else if ($region) {
    $related = [];
    $related['region'] = $region;
    $related['posts'] = Timber::get_posts(
        [
            'posts_per_page' => 4,
            'post_type' => 'post',
            'ignore_sticky_posts' => true,
            'tax_query' => [
                [
                    'taxonomy' => 'regio',
                    'include_children' => false,
                    'terms' => $region->term_id,
                ]
            ]
        ]
    );
    $context['local'] = $related;
}

if ($timber_post->post_gekoppeld_fragment) {
    $fragment = Timber::get_post($timber_post->post_gekoppeld_fragment);
    $context['embed'] = wp_oembed_get($fragment->fragment_url, ['width' => 960]);
}

if ( post_password_required( $timber_post->ID ) ) {
	Timber::render( 'single-password.twig', $context );
} else {
	Timber::render( array( 'single-' . $timber_post->ID . '.twig', 'single-' . $timber_post->post_type . '.twig', 'single-' . $timber_post->slug . '.twig', 'single.twig' ), $context );
}
