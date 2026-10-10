<?php

namespace Streekomroep;

class TelevisionBroadcast
{
    public $name;

    public function __construct(public $show, $name, public $times)
    {
        // getTvWeeks() already trims the override and skips rows with neither name nor show.
        $this->name = $name ?: $show->post_title;
    }
}
