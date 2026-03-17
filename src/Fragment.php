<?php

namespace Streekomroep;

use Timber\Timber;

class Fragment extends Post
{
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

    public function getEmbed()
    {
        if ($this->meta('fragment_type') === 'Video') {
            $url = $this->meta('fragment_url', ['format_value' => false]);
            if (!$url) {
                return null;
            }
            return zw_render_bunny_embed_from_url($url) ?: null;
        } elseif ($this->meta('fragment_type') === 'Audio') {
            return Timber::compile('partial/player-audio-fragment.twig', [
                'fragment' => $this
            ]);
        }

        return null;
    }
}
