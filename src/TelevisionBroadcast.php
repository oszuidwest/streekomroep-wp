<?php

namespace Streekomroep;

class TelevisionBroadcast
{
    public $name;

    public function __construct(public $show, $name, public $times)
    {
        $this->name = trim($name) ?: $show->post_title;
    }
}
