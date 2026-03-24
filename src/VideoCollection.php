<?php

namespace Streekomroep;

use DateTime;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;

class VideoCollection
{
    private static ?FrontMatterParser $parser = null;

    private static function getParser(): FrontMatterParser
    {
        if (!self::$parser) {
            self::$parser = new FrontMatterParser(new SymfonyYamlFrontMatterParser());
        }
        return self::$parser;
    }

    /**
     * Pre-extract broadcast date and description from a single video's YAML front-matter.
     * Sets _broadcastDate (ISO 8601 string or null), _broadcastTimestamp (int or null),
     * and _description on the object.
     */
    public static function preprocessOne(object $rawVideo): void
    {
        if (property_exists($rawVideo, '_broadcastTimestamp')) {
            return;
        }

        $rawVideo->_broadcastDate = null;
        $rawVideo->_broadcastTimestamp = null;
        $rawVideo->_description = '';

        $description = null;
        foreach ($rawVideo->metaTags as $meta) {
            if ($meta->property === 'description') {
                $description = $meta->value;
                break;
            }
        }

        if (!$description) {
            return;
        }

        try {
            $result = self::getParser()->parse($description);
            $yaml = $result->getFrontMatter();
            $rawVideo->_description = $result->getContent();
        } catch (InvalidFrontMatterException $e) {
            $rawVideo->_description = $description;
            return;
        }

        if (!isset($yaml['broadcast_date'])) {
            return;
        }

        $broadcastDate = $yaml['broadcast_date'];
        if (is_int($broadcastDate)) {
            $broadcastDate = date('Y-m-d\TH:i:s', $broadcastDate);
        }

        try {
            $date = new DateTime($broadcastDate, wp_timezone());
            $rawVideo->_broadcastDate = $date->format('c');
            $rawVideo->_broadcastTimestamp = $date->getTimestamp();
        } catch (\Exception $e) {
            // Ignore unparseable dates
        }
    }

    /**
     * Pre-extract broadcast dates for an array of raw video objects.
     * Called during cron before storing in post meta.
     */
    public static function preprocess(array $rawVideos): void
    {
        foreach ($rawVideos as $rawVideo) {
            self::preprocessOne($rawVideo);
        }
    }

    /**
     * Sort and filter raw video data into Video objects.
     * Filters to available videos with a broadcast date in the past.
     * Returns newest first.
     */
    public static function sortAndFilter(BunnyCredentials $credentials, array $rawVideos): array
    {
        $nowTimestamp = time();

        $filtered = array_filter($rawVideos, function ($video) use ($nowTimestamp) {
            if ($video->status !== Video::STATUS_FINISHED) {
                return false;
            }
            if (!property_exists($video, '_broadcastTimestamp') || $video->_broadcastTimestamp === null) {
                return false;
            }
            return $video->_broadcastTimestamp <= $nowTimestamp;
        });

        usort($filtered, function ($left, $right) {
            return $right->_broadcastTimestamp <=> $left->_broadcastTimestamp;
        });

        return array_map(function ($raw) use ($credentials) {
            return new Video($credentials, $raw);
        }, $filtered);
    }

    /**
     * Load and sort episodes for a TV show from post meta.
     *
     * @return Video[]
     */
    public static function forTvShow(int $postId): array
    {
        $videos = get_post_meta($postId, ZW_TV_META_VIDEOS, true);
        if (!is_array($videos)) {
            return [];
        }

        $credentials = BunnyClient::getCredentials(ZW_BUNNY_LIBRARY_TV);
        if (!$credentials) {
            return [];
        }

        return self::sortAndFilter($credentials, $videos);
    }
}
