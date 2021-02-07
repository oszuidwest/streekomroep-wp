<?php

namespace Streekomroep;

class Broadcast
{
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

    public static function sort(Broadcast $lhs, Broadcast $rhs)
    {
        return strcmp($lhs->startTime, $rhs->startTime);
    }
}
