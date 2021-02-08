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
    $project_id = $timber_post->meta('tv_show_gemist_locatie');

    $vimeo = get_transient('vimeo/projects/' . $project_id . '/videos');
    if ($vimeo === false) {
        $vimeo = [];
        try {
            $vimeo = vimeo_get_project_videos($project_id);
            set_transient('vimeo/projects/' . $project_id . '/videos', $vimeo, 1 * HOUR_IN_SECONDS);
        } catch (Throwable$t) {
            ob_start();
            var_dump($t);
            $obj = ob_get_clean();
            trigger_error('Error fetching vimeo project: ' . $obj, E_USER_NOTICE);
        }
    }

    if (isset($_GET['v'])) {
        $videoId = '/videos/' . $_GET['v'];
        $video = null;
        foreach ($vimeo as $item) {
            if ($item->uri == $videoId) {
                $video = $item;
                break;
            }
        }

        if ($video) {
            $context['video'] = $video;
            $context['embed'] = $wp_embed->shortcode([], $video->link);
            Timber::render('single-tv-video.twig', $context);
            return;
        }
    }

    $context['videos'] = $vimeo;
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
