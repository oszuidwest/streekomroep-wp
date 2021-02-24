<?php

namespace Streekomroep;

use InvalidArgumentException;

class BroadcastDay
{
    public $weekday;
    public $name;

    /** @var RadioBroadcast[] */
    public $radio = [];

    /** @var TelevisionBroadcast[] */
    public $television = [];

    public function __construct($number, $name)
    {
        $this->weekday = $number;
        $this->name = $name;
    }

    public function add($param)
    {
        if ($param instanceof RadioBroadcast) {
            $this->radio[] = $param;
            usort($this->radio, [RadioBroadcast::class, 'sort']);
        } else if ($param instanceof TelevisionBroadcast) {
            $this->television[] = $param;
        } else {
            throw new InvalidArgumentException('Broadcast was of  unexpected type ' . get_class($param));
        }
    }
}
