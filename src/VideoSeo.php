<?php

namespace Streekomroep;

/**
 * Yoast SEO integration for video content (fragments and TV episodes).
 */
class VideoSeo
{
    public const OG_IMAGE_WIDTH = 1920;
    public const OG_IMAGE_HEIGHT = 1080;
    public static function register(): void
    {
        add_filter('wpseo_schema_graph_pieces', [self::class, 'addFragmentSchema'], 11, 2);

        add_filter('wpseo_opengraph_type', function ($type) {
            if (is_singular('fragment')) {
                return 'video.episode';
            }
            return $type;
        });

        add_action('template_redirect', [self::class, 'overrideTvEpisodeSeo']);
    }

    public static function addFragmentSchema(array $pieces, $context): array
    {
        if (is_singular('fragment')) {
            $type = get_field('fragment_type', false, false);
            if ($type === Fragment::TYPE_VIDEO) {
                $video = self::getFragmentVideoData($context->post->ID);
                $pieces[] = new VideoObject($video);
            }
        }

        return $pieces;
    }

    public static function getFragmentVideoData(int $id): VideoData
    {
        $fragment = get_post($id);
        $video = new VideoData();
        $video->duration = (int)get_field('fragment_duur', $id, false);
        $video->name = get_the_title($fragment);
        $video->description = get_the_content(null, false, $fragment);
        $video->uploadDate = get_the_date('c', $fragment);
        $video->thumbnailUrl = get_the_post_thumbnail_url($fragment);

        $url = get_field('fragment_url', $id, false);
        if (is_string($url) && $url !== '') {
            $bunnyVideo = VideoRenderer::resolveVideo($url);
            if ($bunnyVideo) {
                $video->contentUrl = $bunnyVideo->getMP4Url();
            }
        }

        return $video;
    }

    public static function overrideTvEpisodeSeo(): void
    {
        if (is_admin() || !is_singular('tv') || !isset($_GET['v'])) {
            return;
        }

        $videos = VideoCollection::forTvShow(get_the_ID());

        // @phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $videoId = wp_unslash($_GET['v']);
        $video = null;
        foreach ($videos as $item) {
            if ($item->getId() == $videoId) {
                $video = $item;
                break;
            }
        }

        if (!$video) {
            return;
        }

        $canonical = function ($url) use ($video) {
            return $url . '?v=' . $video->getId();
        };

        $title = function ($a) use ($video) {
            return $video->getName();
        };

        $description = function () use ($video) {
            return $video->getDescription();
        };

        $thumbnail = function () use ($video) {
            return $video->getThumbnail();
        };

        add_filter('wpseo_title', $title);
        add_filter('wpseo_metadesc', $description);
        add_filter('wpseo_canonical', $canonical);

        add_filter('wpseo_opengraph_title', $title);
        add_filter('wpseo_opengraph_desc', $description);
        add_filter('wpseo_opengraph_type', function () {
            return 'video.episode';
        });
        add_action('wpseo_add_opengraph_images', function ($images) use ($video) {
            $images->add_image([
                'url' => zw_imgproxy($video->getThumbnail(), self::OG_IMAGE_WIDTH, self::OG_IMAGE_HEIGHT),
                'width' => self::OG_IMAGE_WIDTH,
                'height' => self::OG_IMAGE_HEIGHT,
            ]);
        });
        add_filter('wpseo_opengraph_url', $canonical);

        add_filter('wpseo_twitter_title', $title);
        add_filter('wpseo_twitter_description', $description);
        add_filter('wpseo_twitter_image', $thumbnail);

        $broadcastDateIso = $video->getBroadcastDate()?->format('c');

        add_filter('wpseo_frontend_presenters', function ($presenters) use ($broadcastDateIso) {
            foreach ($presenters as $i => $presenter) {
                if (
                    $broadcastDateIso !== null
                    && $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Article_Modified_Time_Presenter
                ) {
                    $presenters[$i] = new VideoModifiedTimePresenter($broadcastDateIso);
                } elseif (
                    $broadcastDateIso !== null
                    && $presenter instanceof \Yoast\WP\SEO\Presenters\Open_Graph\Article_Published_Time_Presenter
                ) {
                    unset($presenters[$i]);
                }
            }

            return $presenters;
        });

        add_filter('wpseo_frontend_presentation', function ($presentation, $context) {
            $presentation->model->open_graph_image_id = null;
            $presentation->model->open_graph_image_meta = null;
            $presentation->model->open_graph_image = null;
            return $presentation;
        }, 10, 2);
    }
}
