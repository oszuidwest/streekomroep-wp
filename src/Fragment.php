<?php

namespace Streekomroep;

use Timber\Timber;

class Fragment extends Post
{
    public const TYPE_VIDEO = 'Video';
    public const TYPE_AUDIO = 'Audio';
    public function region()
    {
        if (!$this->_region) {
            $regions = $this->terms(['query' => ['taxonomy' => 'regio']]);
            if (is_array($regions) && count($regions)) {
                $this->_region = $regions[0];
            }
        }
        return $this->_region;
    }

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
}
