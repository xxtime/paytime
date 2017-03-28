<?php

/**
 * @link https://github.com/thephpleague/omnipay-paypal
 * @see https://github.com/paypal/
 *
 * 三种方式区别
 * 一、Website Payment Standard WPS（IPN）网站标准版，客户需要在网站注册才可以完成购买流程。不会在购物车显示paypal按钮。
 * 二、Express Checkout快速结账专业版的与WPS主要区别是：check out为快速支付，在购物车页面直接显示paypal支付的按钮，可直接进入paypal页面付款，不注册成网店会员即可完成购买，但是也可以走正常的注册会员流程。
 * 三、Website Payments Pro需要是美国的账号才可以用。且收取月服务费用。
 */
namespace Xxtime\PayTime\Providers\Paypal;


class Adapter
{

    private $provider;

    private $channel;


    public function __construct($sub = '')
    {
        if (!$sub) {
            $sub = 'wps'; // 默认网站标准版WPS
        }
        $this->channel = $sub;
        $class = '\\Xxtime\\PayTime\\Providers\\Paypal\\' . ucfirst($sub);
        $this->provider = new $class;
    }

    /**
     * 设置选项
     * @param array $option
     */
    public function setOption($option = [])
    {
        if (!isset($option['sandbox'])) {
            $option['sandbox'] = false;
        }
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