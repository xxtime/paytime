<?php

namespace Xxtime\PayTime;


use Xxtime\PayTime\Common\Container;

class PayTime
{

    /**
     * @var Container
     */
    public $di;


    /**
     * PayTime constructor.
     * @param string $provider
     */
    public function __construct($provider = '')
    {
        $this->di = new Container();
        $this->di->provider = function () use ($provider) {
            $class = '\Xxtime\\PayTime\\Providers\\' . $provider;
            return new $class();
        };
    }


    /**
     * @param array $options
     */
    public function setOptions($options = [])
    {
        $this->di->provider->setOption($options);
    }


    /**
     * @param array $options
     */
    public function purchase($options = [])
    {
        $this->di->provider->purchase($options);
    }


    public function send()
    {
        $this->di->provider->send();
    }


    public function notify()
    {
        $this->di->provider->notify();
    }

}