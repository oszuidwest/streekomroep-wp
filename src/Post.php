<?php

namespace Streekomroep;

use Timber\Term;
use Timber\Timber;

class Post extends \Timber\Post
{

    var $_region;
    var $_topic;

    public function region()
    {
        if (!$this->_region) {
            $this->_region = Timber::get_term(yoast_get_primary_term_id('regio', $this->ID), 'regio');
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
