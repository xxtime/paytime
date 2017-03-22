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
        $offset = strpos($provider, '_');
        if ($offset) {
            $gateway = substr($provider, 0, $offset);
            $sub = substr($provider, -$offset);
        } else {
            $gateway = $provider;
            $sub = '';
        }
        $this->di = new Container();
        $this->di->provider = function () use ($gateway, $sub) {
            if (!file_exists(__DIR__ . '/Providers/' . $gateway . '.php')) {
                throw new \Exception('no providers found');
            }
            $class = '\Xxtime\\PayTime\\Providers\\' . $gateway;
            return new $class($sub);
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


    /**
     * @return array(transactionId, transactionReference, isSuccessful,'message);
     */
    public function notify()
    {
        return $this->di->provider->notify();
    }


    /**
     * 输出
     * @return bool
     */
    public function success()
    {
        if (method_exists($this->di->provider, 'success')) {
            $this->di->provider->success();
        }
    }

}