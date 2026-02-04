<?php

namespace Streekomroep;

use Carbon\Carbon;

class RadioBroadcast
{
    public $title;

    /** @var \Timber\Post */
    public $show;

    public Carbon $start;
    public Carbon $end;

    public function __construct($show, Carbon $start, Carbon $end)
    {
        if (is_string($show)) {
            $this->title = $show;
        } else {
            $this->show = $show;
        }

        $this->start = $start;
        $this->end = $end;
    }

    public static function sort(RadioBroadcast $lhs, RadioBroadcast $rhs)
    {
        return $lhs->start <=> $rhs->start;
    }

    public function getName()
    {
        if ($this->title) {
            return $this->title;
        }

        if (!$this->show) {
            throw new \RuntimeException('RadioBroadcast has no title or show set');
        }

        return $this->show->post_title;
    }
}
