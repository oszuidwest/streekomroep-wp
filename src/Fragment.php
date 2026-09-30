<?php

namespace Streekomroep;

use Timber\Timber;

class Fragment extends Post
{
    public const string TYPE_VIDEO = 'Video';
    public const string TYPE_AUDIO = 'Audio';

    public function getEmbed(?string $posterUrl = null)
    {
        if ($this->meta('fragment_type') === self::TYPE_VIDEO) {
            $url = $this->meta('fragment_url', ['format_value' => false]);
            if (!$url) {
                return null;
            }
            return VideoRenderer::renderFromUrl($url, $posterUrl) ?: null;
        } elseif ($this->meta('fragment_type') === self::TYPE_AUDIO) {
            return Timber::compile('partial/player-audio-fragment.twig', [
                'fragment' => $this,
                'poster_url' => $posterUrl,
            ]);
        }

        return null;
    }

    /**
     * Returns the playable media files, MP4 first for video.
     *
     * @return list<array{src: string, type: string}>
     */
    public function getSources(): array
    {
        $url = $this->meta('fragment_url', ['format_value' => false]);
        if (!$url) {
            return [];
        }

        if ($this->meta('fragment_type') === self::TYPE_VIDEO) {
            $video = VideoRenderer::resolveVideo($url);
            return $video && $video->isAvailable() ? $video->getSources() : [];
        } elseif ($this->meta('fragment_type') === self::TYPE_AUDIO) {
            return [['type' => 'audio/mpeg', 'src' => $url]];
        }

        return [];
    }
}
