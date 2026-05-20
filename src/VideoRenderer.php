<?php

namespace Streekomroep;

class VideoRenderer
{
    /**
     * Render a VideoJS player for a Video object.
     *
     * @param Video       $video
     * @param string|null $posterUrl Optional poster override; falls back to the Video's thumbnail when empty.
     * @return string
     */
    public static function renderPlayer(Video $video, ?string $posterUrl = null): string
    {
        $posterUrl = $posterUrl ?: (string) $video->getThumbnail();

        $out = sprintf('<div class="not-prose" style="aspect-ratio: %f;">', $video->getAspectRatio());
        $out .= '<video class="video-js vjs-fill vjs-big-play-centered playsinline" controls';
        if ($posterUrl !== '') {
            $out .= ' poster="' . esc_url($posterUrl) . '"';
        }
        $out .= ' data-vjs-src="' . esc_url($video->getPlaylistUrl()) . '"';
        $out .= ' data-vjs-type="application/x-mpegURL"';
        $out .= '>';
        $out .= '<source src="' . esc_url($video->getMP4Url()) . '" type="video/mp4">';
        $out .= '</video>';
        $out .= '</div>';

        return $out;
    }

    /**
     * Resolve a Bunny player URL to a Video object.
     */
    public static function resolveVideo(string $url): ?Video
    {
        $id = BunnyClient::parseUrl(trim($url));
        if (!$id) {
            return null;
        }

        $credentials = BunnyClient::getCredentials($id->libraryId);
        if (!$credentials) {
            return null;
        }

        $client = new BunnyClient($credentials);
        $rawVideo = $client->fetchVideo($id);
        if (!$rawVideo) {
            return null;
        }

        VideoCollection::preprocessOne($rawVideo);

        return new Video($credentials, $rawVideo);
    }

    /**
     * Fetch a video from a Bunny URL and render its player.
     *
     * @param string      $url
     * @param string|null $posterUrl Optional poster override forwarded to renderPlayer().
     * @return string|false Player HTML or false if unavailable
     */
    public static function renderFromUrl(string $url, ?string $posterUrl = null): string|false
    {
        $video = self::resolveVideo($url);
        if (!$video || !$video->isAvailable()) {
            return false;
        }

        return self::renderPlayer($video, $posterUrl);
    }
}
