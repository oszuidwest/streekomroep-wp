<?php

namespace Streekomroep;

use Timber\Timber;

class Post extends \Timber\Post
{
    public $_region;
    public $_topic;
    public $_schedule;

    public function region()
    {
        return $this->resolveRegion();
    }

    protected function resolveRegion()
    {
        if ($this->_region === null) {
            $id = yoast_get_primary_term_id('regio', $this->ID);
            if ($id) {
                $this->_region = Timber::get_term($id, 'regio');
                return $this->_region;
            }

            $regions = $this->terms(['query' => ['taxonomy' => 'regio']]);
            if (is_array($regions) && count($regions)) {
                $this->_region = $regions[0];
                return $this->_region;
            }

            $this->_region = false;
        }
        return $this->_region;
    }

    /**
     * Retrieves complete FM schedule rows for this show.
     *
     * @return array Complete rows, or an empty array for other post types.
     */
    public function schedule(): array
    {
        if ($this->_schedule === null) {
            $this->_schedule = \zw_fm_schedule_rows($this->meta('fm_show_programmatie'));
        }
        return $this->_schedule;
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
