<?php

namespace Xxtime\PayTime\Common;


use Exception;

class Container
{
    private $s = array();

    function __set($k, $c)
    {
        if ($c instanceof \Closure) {
            $this->s[$k] = $c($this);
        } else {
            $this->s[$k] = $c;
        }
    }

    function __get($k)
    {
        if (isset($this->s[$k])) {
            return $this->s[$k];
        }
        throw new Exception("DI Error, undefine [{$k}]");
    }
}