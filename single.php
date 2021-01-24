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

/** @var \Timber\Post $timber_post */
$timber_post     = Timber::get_post();
$context['post'] = $timber_post;

if ($timber_post->post_type == 'fragment') {
    global $wp_embed;
    $context['embed'] = $wp_embed->shortcode([], $timber_post->fragment_url);

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
            'post__not_in' => [$timber_post->id],
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
            'post__not_in' => [$timber_post->id],
            'posts_per_page' => 4,
            'post_type' => $timber_post->post_type,
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

if ($timber_post->post_type == 'agenda') {

    $related = [];
    $related['posts'] = Timber::get_posts(
        [
            'post__not_in' => [$timber_post->id],
            'posts_per_page' => 4,
            'post_type' => $timber_post->post_type,
            'ignore_sticky_posts' => true,
            'meta_query' => [
                [
                    'key' => 'agenda_item_soort',
                    'value' => $timber_post->agenda_item_soort,
                    'compare' => '=',
                ]
            ]
        ]
    );
    $context['topical'] = $related;
}

if ($timber_post->post_type == 'tv') {
    $args = [
        'headers' => [
            'Authorization' => 'bearer ' . get_field('vimeo_access_token', 'option')
        ]
    ];
    $response = wp_remote_get('https://api.vimeo.com/me/projects/' . $timber_post->meta('tv_show_gemist_locatie') . '/videos', $args);
    $context['vimeo'] = json_decode($response['body']);
}

if ($timber_post->post_gekoppeld_fragment) {
    $fragment = Timber::get_post($timber_post->post_gekoppeld_fragment);

    global $wp_embed;
    $context['embed'] = $wp_embed->shortcode([], $fragment->fragment_url);
}

if ( post_password_required( $timber_post->ID ) ) {
	Timber::render( 'single-password.twig', $context );
} else {
	Timber::render( array( 'single-' . $timber_post->ID . '.twig', 'single-' . $timber_post->post_type . '.twig', 'single-' . $timber_post->slug . '.twig', 'single.twig' ), $context );
}
