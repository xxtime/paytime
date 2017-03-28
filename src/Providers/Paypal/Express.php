<?php

/**
 * Express Checkout
 * @link https://github.com/thephpleague/omnipay-paypal
 * @link http://stackoverflow.com/questions/16293744/paypal-express-checkout-error-order-total-is-missing-error-10400?answertab=active#tab-top
 * @link http://stackoverflow.com/questions/18427110/how-do-i-use-omnipay-to-send-a-payment-without-credit-card-details-using-paypal
 */
namespace Xxtime\PayTime\Providers\Paypal;


use Omnipay\Omnipay;

class Express
{

    private $options;       // 配置选项

    private $parameters;    // 产品参数

    private $provider;


    /**
     * 设置选项
     * @param array $options
     */
    public function setOption($options = [])
    {
        $this->options = $options;
        $this->provider = Omnipay::create('PayPal_Express');
        $this->provider->setUsername($this->options['username']);
        $this->provider->setPassword($this->options['password']);
        $this->provider->setSignature($this->options['sign']);

        // 测试配置必须开启测试模式
        if ($this->options['sandbox']) {
            $this->provider->setTestMode(true);
        }

        // 查看配置选项 @see http://omnipay.thephpleague.com/gateways/configuring/
        // $this->provider->getDefaultParameters();
    }


    public function purchase($parameters = [])
    {
        $this->parameters = [
            'amount'               => $parameters['amount'],
            'currency'             => $parameters['currency'],
            'description'          => $parameters['productDesc'],
            'transactionId'        => $parameters['transactionId'],
            'transactionReference' => $parameters['transactionId'],
            'returnUrl'            => $this->options['return_url'],
            'cancelUrl'            => $this->options['cancel_url'],
        ];
    }


    /**
     * 发送请求
     * @return mixed
     */
    public function send()
    {
        // $this->provider->getParameters();
        $response = $this->provider->purchase($this->parameters)->send();
        $response->redirect();
        // $response->getRedirectUrl()
    }

}