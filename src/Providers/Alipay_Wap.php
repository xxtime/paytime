<?php

/**
 * @link https://github.com/lokielse/omnipay-alipay/wiki/Aop-WAP-Gateway
 */
namespace Xxtime\PayTime\Providers;


use Omnipay\Omnipay;
use Exception;

class Alipay_Wap
{

    private $provider;


    private $response;


    public function __construct()
    {
        $this->provider = Omnipay::create('Alipay_AopWap');
    }


    /**
     * @param array $option
     */
    public function setOption($option = [])
    {
        $this->provider->setAppId($option['appId']);
        $this->provider->setPrivateKey($option['privateKey']);
        $this->provider->setAlipayPublicKey($option['publicKey']);
        $this->provider->setNotifyUrl($option['notifyUrl']);
        $this->provider->setReturnUrl($option['returnUrl']);
    }


    /**
     * @link https://doc.open.alipay.com/doc2/detail.htm?treeId=203&articleId=105463&docType=1
     * @param array $option
     */
    public function purchase($option = [])
    {
        $request = $this->provider->purchase();
        $request->setBizContent([
            'out_trade_no' => $option['transactionId'],
            'total_amount' => $option['amount'],
            'subject'      => $option['productDesc'],
            'product_code' => 'QUICK_WAP_PAY',
        ]);
        $this->response = $request->send();
    }


    public function send()
    {
        //$redirectUrl = $this->response->getRedirectUrl();
        $this->response->redirect();
    }


    public function notify()
    {
        $request = $this->provider->completePurchase();
        $argv = array_merge($_POST, $_GET);
        $argv['sign'] = str_replace(' ', '+', $argv['sign']);
        $request->setParams($argv);

        try {
            $response = $request->send();
            if (!$response->isPaid()) {
                throw new Exception('sorry failed');
            }

            $result = [
                'transactionId'        => $response->getData()['out_trade_no'],
                'transactionReference' => $response->getData()['trade_no'],
                'isSuccessful'         => true,
                'message'              => 'success',
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'transactionId'        => $response->getData()['out_trade_no'],
                'transactionReference' => $response->getData()['trade_no'],
                'isSuccessful'         => false,
                'message'              => 'failed',
            ];
            return $result;
        }
    }

}