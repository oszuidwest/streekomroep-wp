<?php

namespace Streekomroep;

class TelevisionBroadcast
{
    public $show;
    public $name = null;
    public $times;

    public function __construct($programme, $name, $times)
    {
        $this->show = $programme;

        $name = trim($name);
        if (!empty($name)) {
            $this->name = $name;
        } else {
            $this->name = $this->show->post_title;
        }

        $this->times = $times;
    }
}
