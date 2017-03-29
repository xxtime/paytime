<?php

/**
 * @link https://github.com/lokielse/omnipay-alipay/wiki/Aop-APP-Gateway
 */
namespace Xxtime\PayTime\Providers\Alipay;


use Omnipay\Omnipay;
use Exception;

class App
{

    private $provider;

    private $request;

    private $response;

    private $parameters;


    public function __construct()
    {
        $this->provider = Omnipay::create('Alipay_AopApp');
    }


    /**
     * @param array $option
     */
    public function setOption($option = [])
    {
        $this->provider->setAppId($option['app_id']);
        $this->provider->setPrivateKey($option['private_key']);
        $this->provider->setAlipayPublicKey($option['public_key']);
        $this->provider->setNotifyUrl($option['notify_url']);
    }


    /**
     * @param array $option
     */
    public function purchase($option = [])
    {
        $this->parameters = $option;
        $this->request = $this->provider->purchase();
        $this->request->setBizContent([
            'out_trade_no' => $option['transactionId'],
            'total_amount' => $option['amount'],
            'subject'      => $option['productDesc'],
            'product_code' => 'QUICK_MSECURITY_PAY',
        ]);

    }


    public function send()
    {
        $response = $this->request->send();
        $string = $response->getOrderString();
        $result = [
            'message'              => 'success',
            'transactionId'        => $this->parameters['transactionId'],
            'transactionReference' => '',
            'productId'            => $this->parameters['productId'],
            'amount'               => $this->parameters['amount'],
            'currency'             => $this->parameters['currency'],
            'raw'                  => $string,
        ];
        return $result;
    }


    public function notify()
    {
    }

}