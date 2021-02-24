<?php

namespace Streekomroep;

class RadioBroadcast
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

    public static function sort(RadioBroadcast $lhs, RadioBroadcast $rhs)
    {
        return strcmp($lhs->startTime, $rhs->startTime);
    }
}
