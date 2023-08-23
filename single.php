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

use Carbon\Carbon;
use Streekomroep\Fragment;
use Streekomroep\Video;

$context = Timber::context();

/** @var \Timber\Post $timber_post */
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

if ($timber_post->post_type == 'fragment') {
    /** @var Fragment $timber_post */
    $context['posts'] = fragment_get_posts($timber_post->id);
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

    // Only show block if there's other posts to show
    if (count($related['posts']) >= 1) {
        $context['topical'] = $related;
    }
}

if ($region && !isset($context['topical'])) {
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

if ($timber_post->post_type == 'tv') {
    $videos = get_post_meta($timber_post->ID, ZW_TV_META_VIDEOS, true);
    if (!is_array($videos)) {
        $videos = [];
    }

    $credentials = zw_bunny_credentials_get(ZW_BUNNY_LIBRARY_TV);
    $videos = zw_sort_videos($credentials, $videos);

    $seasons = [];
    foreach ($videos as $video) {
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
        foreach ($videos as $item) {
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
            $videoData->thumbnailUrl = $video->getThumbnail();
            $videoData->contentUrl = $video->getFile();
            add_filter('wpseo_schema_graph_pieces', function ($pieces, $context) use ($videoData) {
                $pieces[] = new VideoObject($videoData);
                return $pieces;
            }, 11, 2);
            add_filter('wpseo_schema_imageobject', function ($data, $context) use ($video, $videoData) {
                $thumb = $video->getThumbnail();
                $data['url'] = $thumb;
                $data['contentUrl'] = $thumb;
                return $data;
            }, 10, 2);
            add_filter('wpseo_schema_webpage', function ($data, $context) use ($video, $videoData) {
                $data['description'] = $video->getDescription();
                $data['name'] = $videoData->name;
                $data['datePublished'] = $videoData->uploadDate;
                $data['dateModified'] = $videoData->uploadDate;
                $data['url'] .= '?v=' . $_GET['v'];
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

$weekdayNames = [
    1 => 'maandag',
    2 => 'dinsdag',
    3 => 'woensdag',
    4 => 'donderdag',
    5 => 'vrijdag',
    6 => 'zaterdag',
    7 => 'zondag'
];

if ($timber_post->post_type == 'fm') {
    $show = $timber_post;
    $gemist = (bool)$show->meta('fm_show_actief');

    $recordings = [];
    if ($gemist) {
        $items = [];
        $rules = $show->meta('fm_show_programmatie');
        if (!$rules) {
            $rules = [];
        }

        $hour = Carbon::now('Europe/Amsterdam')->startOfHour()->subHour();
        $end = $hour->copy()->subDays(get_field('radio_gemist_retentie', 'option'));

        while ($hour->isAfter($end)) {
            $dayname = $weekdayNames[$hour->dayOfWeekIso];

            foreach ($rules as $rule) {
                if (!in_array($dayname, $rule['fm_show_dagen'])) {
                    continue;
                }

                $ruleStart = $hour->copy()->setTimeFromTimeString($rule['fm_show_starttijd']);
                $ruleEnd = $hour->copy()->setTimeFromTimeString($rule['fm_show_eindtijd']);


                if (($hour->isAfter($ruleStart) || $hour->is($ruleStart)) && $hour->isBefore($ruleEnd)) {
                    $recordings[] = $hour;
                }
            }

            // Work around DST causing an endless loop
            $hour = Carbon::createFromTimestamp($hour->timestamp - (60 * 60), 'Europe/Amsterdam');
        }
    }

    $context['recordings'] = $recordings;
}

if ($timber_post->post_gekoppeld_fragment) {
    /** @var Fragment $fragment */
    $fragment = Timber::get_post($timber_post->post_gekoppeld_fragment);
    if ($fragment) {
        $context['embed'] = $fragment->getEmbed();
    }
}

if (post_password_required($timber_post->ID)) {
    Timber::render('single-password.twig', $context);
} else {
    Timber::render(array('single-' . $timber_post->ID . '.twig', 'single-' . $timber_post->post_type . '.twig', 'single-' . $timber_post->slug . '.twig', 'single.twig'), $context);
}
