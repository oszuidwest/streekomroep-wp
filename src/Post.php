<?php

namespace Streekomroep;

class Post extends \Timber\Post
{

    var $_region;
    var $_topic;

    public function region()
    {
        if (!$this->_region) {
            $regions = $this->get_terms(['query' => ['taxonomy' => 'regio']]);
            if (is_array($regions) && count($regions)) {
                $this->_region = $regions[0];
            }
        }
        return $this->_region;
    }

    public function topic()
    {
        if (!$this->_topic) {
            $topics = $this->get_terms(['query' => ['taxonomy' => 'dossier']]);
            if (is_array($topics) && count($topics)) {
                $this->_topic = $topics[0];
            }
        }
        return $this->_topic;
    }
}
