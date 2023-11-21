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
            return $this->meta('fragment_url');
        } else if ($this->meta('fragment_type') === 'Audio') {
            return Timber::compile('partial/player-audio-fragment.twig', [
                'fragment' => $this
            ]);
        }

        return null;
    }
}
