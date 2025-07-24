<?php

namespace App\Support;

class SafeObject extends \stdClass
{
    public function __get($name)
    {
        return property_exists($this, $name) ? $this->$name : null;
    }

    public function __isset($name)
    {
        return true;
    }
}