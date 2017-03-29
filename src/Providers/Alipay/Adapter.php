<?php


namespace Xxtime\PayTime\Providers\Alipay;


class Adapter
{

    private $provider;

    private $channel;


    public function __construct($sub = '')
    {
        if (!$sub) {
            $sub = 'wap';
        }
        $this->channel = $sub;
        $class = '\\Xxtime\\PayTime\\Providers\\Alipay\\' . ucfirst($sub);
        $this->provider = new $class;
    }


    /**
     * 设置选项
     * @param array $option
     */
    public function setOption($option = [])
    {
        $this->provider->setOption($option);
    }


    /**
     * 设置产品信息
     * @param array $parameters
     */
    public function purchase($parameters = [])
    {
        $this->provider->purchase($parameters);
    }


    /**
     * 发送请求
     * @return mixed
     */
    public function send()
    {
        return $this->provider->send();
    }


    /**
     * 跳转
     */
    public function redirect()
    {
        $this->provider->redirect();
    }


    /**
     * 通知回调
     * @return mixed
     */
    public function notify()
    {
        return $this->provider->notify();
    }


    /**
     * 响应
     */
    public function success()
    {
    }

}