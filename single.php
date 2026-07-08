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
use Streekomroep\BroadcastDay;
use Streekomroep\Fragment;

$context = Timber::context();

/** @var \Timber\Post $timber_post */
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

if (post_password_required($timber_post->ID)) {
    Timber::render('single-password.twig', $context);
    return;
}

if ($timber_post->post_type == 'fragment') {
    /** @var Fragment $timber_post */
    $context['posts'] = fragment_get_posts($timber_post->id);
    $context['embed'] = $timber_post->getEmbed();
    if ($context['embed']) {
        zw_require_videojs();
    }
}

$relatedPosts = function (string $taxonomy, int $termId, string $postType, bool $includeChildren = true) use ($timber_post) {
    return Timber::get_posts(
        [
            'post__not_in' => [$timber_post->id],
            'posts_per_page' => 4,
            'post_type' => $postType,
            'ignore_sticky_posts' => true,
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'include_children' => $includeChildren,
                    'terms' => $termId,
                ]
            ]
        ]
    );
};

$topic = $timber_post->topic();
$region = $timber_post->region();
if ($topic) {
    $topicPosts = $relatedPosts('dossier', $topic->term_id, 'post');

    // Only show block if there's other posts to show
    if (count($topicPosts) >= 1) {
        $context['topical'] = ['topic' => $topic, 'posts' => $topicPosts];
    }
}

if ($region && !isset($context['topical'])) {
    $context['local'] = [
        'region' => $region,
        'posts' => $relatedPosts('regio', $region->term_id, $timber_post->post_type, false),
    ];
}

if ($timber_post->post_type == 'tv') {
    $videos = \Streekomroep\VideoCollection::forTvShow($timber_post->ID);

    if (isset($_GET['v'])) {
        // @phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $videoId = wp_unslash($_GET['v']);
        /** @var \Streekomroep\Video $video */
        $video = null;
        $newerVideo = null;
        $olderVideo = null;
        foreach ($videos as $i => $item) {
            if ($item->getId() == $videoId) {
                $video = $item;
                $newerVideo = $videos[$i - 1] ?? null;
                $olderVideo = $videos[$i + 1] ?? null;
                break;
            }
        }

        if ($video) {
            $videoData = new \Streekomroep\VideoData();
            $videoData->description = $video->getDescription();
            $videoData->name = $video->getName() . ' - ZuidWest TV';
            $videoData->duration = $video->getDuration();
            $videoData->uploadDate = $video->getBroadcastDate()?->format('c');
            $videoData->thumbnailUrl = $video->getThumbnail();
            $videoData->contentUrl = $video->getMP4Url();
            add_filter('wpseo_schema_graph_pieces', function ($pieces, $context) use ($videoData) {
                $pieces[] = new \Streekomroep\VideoObject($videoData);
                return $pieces;
            }, 11, 2);
            add_filter('wpseo_schema_imageobject', function ($data, $context) use ($video) {
                $thumb = zw_imgproxy(
                    $video->getThumbnail(),
                    \Streekomroep\VideoSeo::OG_IMAGE_WIDTH,
                    \Streekomroep\VideoSeo::OG_IMAGE_HEIGHT
                );
                $data['url'] = $thumb;
                $data['contentUrl'] = $thumb;
                $data['width'] = \Streekomroep\VideoSeo::OG_IMAGE_WIDTH;
                $data['height'] = \Streekomroep\VideoSeo::OG_IMAGE_HEIGHT;
                return $data;
            }, 10, 2);
            add_filter('wpseo_schema_webpage', function ($data, $context) use ($video, $videoData, $videoId) {
                $data['description'] = $video->getDescription();
                $data['name'] = $videoData->name;
                $data['datePublished'] = $videoData->uploadDate;
                $data['dateModified'] = $videoData->uploadDate;
                $data['url'] .= '?v=' . $videoId;
                $data['video'] = [
                    ['@id' => $context->canonical . '#video']
                ];
                return $data;
            }, 10, 2);

            $context['video'] = $video;
            $context['older'] = $olderVideo;
            $context['newer'] = $newerVideo;
            zw_require_videojs();
            $context['embed'] = \Streekomroep\VideoRenderer::renderPlayer($video);
            Timber::render('single-tv-video.twig', $context);
            return;
        }
    }

    $seasons = [];
    foreach ($videos as $video) {
        $date = $video->getBroadcastDate();
        if (!$date) {
            continue;
        }

        $seasons[$date->format('Y')][] = $video;
    }

    $context['seasons'] = $seasons;
}

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
            $dayname = BroadcastDay::WEEKDAY_NAMES[$hour->dayOfWeekIso];

            foreach ($rules as $rule) {
                if (!in_array($dayname, $rule['fm_show_dagen'])) {
                    continue;
                }

                $ruleStart = $hour->copy()->setTimeFromTimeString($rule['fm_show_starttijd']);
                $ruleEnd = $hour->copy()->setTimeFromTimeString($rule['fm_show_eindtijd']);


                if (($hour->isAfter($ruleStart) || $hour->is($ruleStart)) && $hour->isBefore($ruleEnd)) {
                    // Store a copy because the loop cursor is a mutable Carbon instance.
                    $recordings[] = $hour->copy();
                }
            }

            // Subtract a real hour (via timestamp) so DST transitions can't cause an endless loop
            $hour = $hour->subUTCHour();
        }
    }

    usort($recordings, function (Carbon $lhs, Carbon $rhs) {
        if ($lhs->isSameDay($rhs)) {
            return $lhs <=> $rhs;
        }

        return $rhs <=> $lhs;
    });

    $context['recordings'] = $recordings;
}

if ($timber_post->post_gekoppeld_fragment) {
    /** @var Fragment $fragment */
    $fragment = Timber::get_post($timber_post->post_gekoppeld_fragment);
    if ($fragment) {
        $posterUrl = null;
        if ($timber_post->meta('post_fragment_is_featured')) {
            $thumbnailUrl = get_the_post_thumbnail_url($timber_post->ID, 'full');
            if ($thumbnailUrl) {
                $posterUrl = zw_imgproxy($thumbnailUrl, 1280, 720);
            }
        }

        $context['embed'] = $fragment->getEmbed($posterUrl);
        if ($context['embed']) {
            zw_require_videojs();
        }
    }
}

Timber::render(['single-' . $timber_post->ID . '.twig', 'single-' . $timber_post->post_type . '.twig', 'single-' . $timber_post->slug . '.twig', 'single.twig'], $context);
