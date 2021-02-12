<?php

namespace Streekomroep;

class BroadcastDay
{
    public $number;
    public $name;

    /** @var Broadcast[] */
    public $broadcasts = [];

    public function __construct($number, $name)
    {
        $this->number = $number;
        $this->name = $name;
    }

    public function add(Broadcast $param)
    {
        $this->broadcasts[] = $param;
        usort($this->broadcasts, [Broadcast::class, 'sort']);
    }
}
