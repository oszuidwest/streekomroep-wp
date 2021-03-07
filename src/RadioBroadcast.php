<?php

namespace Streekomroep;

use Timber\Timber;

class RadioBroadcast
{
    public $title;

    /** @var \Timber\Post */
    public $show;

    public $startTime;
    public $endTime;

    public function __construct($show, $startTime, $endTime)
    {
        if (is_string($show)) {
            $this->title = $show;
        } else {
            $this->show = $show;
        }
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }

    public static function sort(RadioBroadcast $lhs, RadioBroadcast $rhs)
    {
        return strcmp($lhs->startTime, $rhs->startTime);
    }

    public function getName()
    {
        if ($this->title) {
            return $this->title;
        } else {
            return $this->show->post_title;
        }
    }
}
