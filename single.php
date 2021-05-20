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

use Streekomroep\Video;

$context = Timber::context();

/** @var \Timber\Post $timber_post */
$timber_post = Timber::get_post();
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
    $vimeo = get_post_meta($timber_post->ID, 'vimeo_data', true);
    if (!is_array($vimeo)) {
        $vimeo = [];
    }
    $vimeo = zw_sort_videos($vimeo);

    $seasons = [];
    foreach ($vimeo as $video) {
        $date = $video->getBroadcastDate();

        $year = $date->format('Y');
        if (!isset($seasons[$year])) {
            $seasons[$year] = [];
        }

        $seasons[$year][] = $video;
    }


    if (isset($_GET['v'])) {
        $videoId = $_GET['v'];
        $video = null;
        foreach ($vimeo as $item) {
            if ($item->getId() == $videoId) {
                $video = $item;
                break;
            }
        }

        if ($video) {
            $videoData = new VideoData();
            $videoData->description = $video->getDescription();
            $videoData->name = $video->getName() . ' - ZuidWest TV';
            $videoData->duration = $video->getDuration();
            $videoData->uploadDate = $video->getBroadcastDate()->format('c');
            $videoData->thumbnailUrl = $video->getLargestThumbnail()->link;
            $videoData->contentUrl = $video->getFile();
            add_filter('wpseo_schema_graph_pieces', function ($pieces, $context) use ($videoData) {
                $pieces[] = new VideoObject($videoData);
                return $pieces;
            }, 11, 2);
            add_filter('wpseo_schema_webpage', function ($data, $context) {
                $data['video'] = [
                    ['@id' => $context->canonical . '#video']
                ];
                return $data;
            }, 10, 2);

            $context['video'] = $video;
            $context['embed'] = $wp_embed->shortcode([], $video->getLink());
            Timber::render('single-tv-video.twig', $context);
            return;
        }
    }

    $context['seasons'] = $seasons;
}

if ($timber_post->post_gekoppeld_fragment) {
    $fragment = Timber::get_post($timber_post->post_gekoppeld_fragment);

    global $wp_embed;
    $context['embed'] = $wp_embed->shortcode([], $fragment->fragment_url);
}

if (post_password_required($timber_post->ID)) {
    Timber::render('single-password.twig', $context);
} else {
    Timber::render(array('single-' . $timber_post->ID . '.twig', 'single-' . $timber_post->post_type . '.twig', 'single-' . $timber_post->slug . '.twig', 'single.twig'), $context);
}
