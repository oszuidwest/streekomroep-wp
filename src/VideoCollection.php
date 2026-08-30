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

    /** @var array<int, array> */
    private static array $rawVideos = [];

    /**
     * Per-request memo of the raw bunny_data meta; get_metadata() unserializes
     * the stored blob on every call, which adds up when several cached refs
     * point at the same show.
     */
    private static function rawForTvShow(int $postId): array
    {
        if (!array_key_exists($postId, self::$rawVideos)) {
            $videos = get_post_meta($postId, ZW_TV_META_VIDEOS, true);
            self::$rawVideos[$postId] = is_array($videos) ? $videos : [];
        }

        return self::$rawVideos[$postId];
    }

    /**
     * Load a single episode by guid without materializing the whole collection.
     */
    public static function findVideo(int $postId, string $guid): ?Video
    {
        $credentials = BunnyClient::getCredentials(ZW_BUNNY_LIBRARY_TV);
        if (!$credentials) {
            return null;
        }

        foreach (self::rawForTvShow($postId) as $raw) {
            if (!is_object($raw) || ($raw->guid ?? null) !== $guid) {
                continue;
            }

            // Keep parity with sortAndFilter(): a ref that no longer passes
            // the availability filter must not render via the warm path.
            if ($raw->status !== Video::STATUS_FINISHED) {
                return null;
            }
            if (($raw->_broadcastTimestamp ?? null) === null || $raw->_broadcastTimestamp > time()) {
                return null;
            }

            return new Video($credentials, $raw);
        }

        return null;
    }

    /**
     * Load and sort episodes for a TV show from post meta.
     *
     * @return Video[]
     */
    public static function forTvShow(int $postId): array
    {
        $credentials = BunnyClient::getCredentials(ZW_BUNNY_LIBRARY_TV);
        if (!$credentials) {
            return [];
        }

        return self::sortAndFilter($credentials, self::rawForTvShow($postId));
    }
}
