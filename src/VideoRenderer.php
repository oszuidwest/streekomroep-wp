<?php

namespace Streekomroep;

class VideoRenderer
{
    /**
     * Renders a Video.js player for the video's HLS playlist.
     *
     * @param Video       $video
     * @param string|null $posterUrl Optional poster override; falls back to the Video's thumbnail when empty.
     * @return string
     */
    public static function renderPlayer(Video $video, ?string $posterUrl = null): string
    {
        $posterUrl = $posterUrl ?: (string) $video->getThumbnail();

        return sprintf(
            '<div class="not-prose" style="aspect-ratio: %f;"><media-i18n><video-player poster="%s">'
            . '<video-skin class="block size-full"><hlsjs-video src="%s" playsinline></hlsjs-video></video-skin>'
            . '</video-player></media-i18n></div>',
            $video->getAspectRatio(),
            esc_url($posterUrl),
            esc_url($video->getPlaylistUrl())
        );
    }

    /**
     * Resolves a Bunny player URL to a Video object.
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
     * Fetches a Bunny video and renders its player.
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
