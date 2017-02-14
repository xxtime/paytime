<?php

namespace Xxtime\PayTime\Common;


use Exception;

class Container
{
    private $s = array();

    function __set($k, $c)
    {
        $this->s[$k] = $c($this);
    }

    function __get($k)
    {
        if (isset($this->s[$k])) {
            return $this->s[$k];
        }
        throw new Exception("DI Error, no argv [{$k}]");
    }
}