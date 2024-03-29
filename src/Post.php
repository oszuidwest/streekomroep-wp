<?php

namespace Streekomroep;

use Timber\Timber;

class Post extends \Timber\Post
{
    public $_region;
    public $_topic;

    public function region()
    {
        if ($this->_region === null) {
            $id = yoast_get_primary_term_id('regio', $this->ID);
            if ($id) {
                $this->_region = Timber::get_term($id, 'regio');
            } else {
                $this->_region = false;
            }
        }
        return $this->_region;
    }

    public function topic()
    {
        if (!$this->_topic) {
            $topics = $this->terms(['query' => ['taxonomy' => 'dossier']]);
            if (is_array($topics) && count($topics)) {
                $this->_topic = $topics[0];
            }
        }
        return $this->_topic;
    }
}
