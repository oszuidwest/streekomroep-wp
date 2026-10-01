<?php

namespace Streekomroep;

class TelevisionBroadcast
{
    public $name = null;

    public function __construct(public $show, $name, public $times)
    {
        $name = trim($name);
        if (!empty($name)) {
            $this->name = $name;
        } else {
            $this->name = $this->show->post_title;
        }
    }
}
