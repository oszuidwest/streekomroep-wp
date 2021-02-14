<?php

namespace Streekomroep;

class SafeObject
{
    public function __construct($data, $node = 'video')
    {
        $this->data = $data;
        $this->node = $node;
    }

    public function __isset($name)
    {
        if (isset($this->data->$name)) {
            return true;
        }

        user_error('Property ' . $name . ' not found in ' . $this->node, E_USER_ERROR);
    }


    public function __get($name)
    {
        if (isset($this->$name)) {
            if (is_object($this->data->$name)) {
                return new SafeObject($this->data->$name, $this->node . '.' . $name);
            }

            return $this->data->$name;
        }

        return null;
    }
}
