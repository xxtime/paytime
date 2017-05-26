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
            $sub = substr($provider, $offset + 1);
        }
        else {
            $gateway = $provider;
            $sub = '';
        }
        $this->di = new Container();
        $this->di->provider = function () use ($gateway, $sub) {
            if (file_exists(__DIR__ . '/Providers/' . $gateway . '.php')) {
                $class = '\Xxtime\\PayTime\\Providers\\' . $gateway;
                return new $class($sub);
            }
            if (file_exists(__DIR__ . '/Providers/' . $gateway . '/Adapter.php')) {
                $class = '\Xxtime\\PayTime\\Providers\\' . $gateway . '\\Adapter';
                return new $class($sub);
            }
            throw new \Exception('no providers found');
        };
    }


    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->di->provider, $name)) {
            $this->di->provider->$name($arguments);
        }
    }


    /**
     * @param array $option
     */
    public function setOption($option = [])
    {
        $this->di->provider->setOption($option);
    }


    /**
     * @param array $option
     */
    public function purchase($option = [])
    {
        $this->di->provider->purchase($option);
    }


    /**
     * 发送请求
     * @return mixed
     */
    public function send()
    {
        return $this->di->provider->send();
    }


    /**
     * 跳转
     */
    public function redirect()
    {
        if (method_exists($this->di->provider, 'redirect')) {
            $this->di->provider->redirect();
        }
    }


    /**
     * @return array(transactionId, transactionReference, isSuccessful, message);
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