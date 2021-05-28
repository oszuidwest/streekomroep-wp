<?php

namespace Streekomroep;

use Timber\Term;
use Timber\Timber;
use Timber\Twig;

class Fragment extends Post
{

    public function getEmbed()
    {
        if ($this->meta('fragment_type') === 'Video') {
            global $wp_embed;
            return $wp_embed->shortcode([], $this->fragment_url);
        } else if ($this->meta('fragment_type') === 'Audio') {
            return Timber::compile('partial/player-audio-fragment.twig', [
                    'fragment' => $this]
            );
        }

        return null;
    }

    public function enqueueScriptsAndStyles()
    {
        if ($this->meta('fragment_type') === 'Audio') {
            wp_enqueue_style('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.12.1/video-js.min.css');
            wp_enqueue_script('video.js', 'https://cdnjs.cloudflare.com/ajax/libs/video.js/7.12.1/video.min.js');
        }
    }

}
