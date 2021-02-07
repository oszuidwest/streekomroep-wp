<?php

namespace Streekomroep;

class BroadcastDay
{
    public $name;

    /** @var Broadcast[] */
    public $broadcasts = [];

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function add(Broadcast $param)
    {
        $this->broadcasts[] = $param;
        usort($this->broadcasts, [Broadcast::class, 'sort']);
    }
}
