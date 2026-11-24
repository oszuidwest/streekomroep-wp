<?php
/**
 * Single post template
 *
 * Builds the context for single posts and renders the matching Timber template.
 *
 * @package Streekomroep
 * @since 1.10.0
 */

use Streekomroep\BroadcastDay;
use Streekomroep\Fragment;
use Streekomroep\RadioGemist;

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

$relatedPosts = fn (string $taxonomy, int $termId, string $postType, bool $includeChildren = true) => Timber::get_posts(
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

$topic = $timber_post->topic();
$region = $timber_post->region();
if ($topic) {
    $topicPosts = $relatedPosts('dossier', $topic->term_id, 'post');

    // Do not render an empty related-posts block.
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
        $videoId = is_string($_GET['v']) ? sanitize_text_field(wp_unslash($_GET['v'])) : '';
        /** @var \Streekomroep\Video $video */
        $video = null;
        $newerVideo = null;
        $olderVideo = null;
        foreach ($videos as $i => $item) {
            if ($item->getId() === $videoId) {
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
    $published = $show->post_status === 'publish';
    $rules = $show->schedule();
    $recordingDays = $published ? iterator_to_array(RadioGemist::days($rules)) : [];
    $retention = (int)get_field('radio_gemist_retentie', 'option');

    // Prefer whole weeks in the listener-facing retention copy.
    $retentionLabel = null;
    if ($retention > 0 && $recordingDays) {
        $weeks = intdiv($retention, 7);
        $retentionLabel = $retention % 7 === 0 ? ($weeks === 1 ? '1 week' : $weeks . ' weken') : ($retention === 1 ? '1 dag' : $retention . ' dagen');
    }

    $context['gemist'] = ['days' => $recordingDays, 'retention_label' => $retentionLabel];

    // Only build the full schedule when this published show can have a
    // successor.
    $context['following_show'] = $published && $rules ? new \Streekomroep\BroadcastSchedule()->getFollowingRadioBroadcast($show->ID) : null;

    $context['programmatie'] = $rules;
    $context['schedule_days'] = $rules ? array_merge(...array_column($rules, 'fm_show_dagen')) : [];
    $context['weekday_names'] = array_values(BroadcastDay::WEEKDAY_NAMES);

    $context['fm_post_type'] = get_post_type_object('fm');
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
