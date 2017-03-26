<?php


namespace Xxtime\PayTime\Providers\Mycard;


use Xxtime\PayTime\Providers\Mycard\Card;
use Xxtime\PayTime\Providers\Mycard\Wallet;
use Xxtime\PayTime\Providers\Mycard\Telecom;

class Adapter
{

    private $provider;

    private $channel;

    public function __construct($sub = '')
    {
        if (!$sub) {
            $sub = $this->checkChannel();
        }
        $this->channel = $sub;
        $class = '\\Xxtime\\PayTime\\Providers\\Mycard\\' . ucfirst($sub);
        $this->provider = new $class;
    }

    /**
     * 特殊: MyCard传入所有配置
     * @param array $option
     */
    public function setOption($option = [])
    {
        $op = $option[$this->channel];
        $op['sandbox'] = isset($option['sandbox']) ? $option['sandbox'] : 0;
        $this->provider->setOption($op);
    }


    public function purchase($option = [])
    {
        $this->provider->purchase($option);
    }


    public function send()
    {
        return $this->provider->send();
    }


    public function redirect()
    {
        $this->provider->redirect();
    }


    public function notify()
    {
        return $this->provider->notify();
    }


    /**
     * 检测通知类型
     * 通知回调时使用
     * @return string
     */
    private function checkChannel()
    {
        if (isset($_REQUEST['DATA'])) {
            // SDK
            $provider = 'sdk';
        } elseif (isset($_REQUEST['OTP'])) {
            // Wallet
            $provider = 'wallet';
        } elseif (isset($_REQUEST['CardPoint'])) {
            // Card
            $provider = 'card';
        } else {
            // Billing储值
            $provider = 'telecom';
        }
        return $provider;
    }


    /**
     * 响应
     */
    public function success()
    {
    }


    /**
     * 仅MyCard卡支付时调用
     * @param array $avg
     */
    public function card($avg = [])
    {
        if ($this->channel == 'card') {
            $this->provider->card($avg);
        }
    }

}