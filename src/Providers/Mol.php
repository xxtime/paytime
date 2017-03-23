<?php


/**
 * 获得Mol开发信息后(applicationCode)
 * 需要向Mol提供以下信息:
 * 回调地址 Callback URL
 * IP白名单 IP Address
 */
namespace Xxtime\PayTime\Providers;


class Mol
{

    private $app_id;

    private $app_key;

    private $return_url;

    private $channel;   // 网关渠道

    private $redirect;  // 跳转URL

    private $endpoint_sandbox = "https://sandbox-api.mol.com/payout/payments";

    private $endpoint = "https://api.mol.com/payout/payments";


    public function __construct($sub_gateway = '')
    {
        $this->channel = $sub_gateway;
    }


    public function setOption($option = [])
    {
        $this->app_id = $option['app_id'];
        $this->app_key = $option['app_key'];
        $this->return_url = $option['return_url'];
        if (isset($option['sandbox']) && $option['sandbox'] == 1) {
            $this->endpoint = $this->endpoint_sandbox;
        }
    }


    /**
     * 推荐测试渠道 MOLPoints Wallet 和 MOLPoints Direct Top Up
     * Please avoid testing of other sandbox channels such as MOLPay Credit Card, Mobile Direct Top Up (easy2pay)
     * or Maybank2u channel using actual Credit Card or actual Phone number, as actual cost will incur and not refundable.
     * @param array $option
     * @throws \Exception
     */
    public function purchase($option = [])
    {
        $data['applicationCode'] = $this->app_id;
        $data['referenceId'] = $option['transactionId'];    // 订单编号
        $data['version'] = 'v1';
        $data['channelId'] = $this->channel;                // 支付渠道: 留空或者忽略则使用 MOL Payment Wall (MOL支付墙)

        $data['returnUrl'] = $this->return_url;             // 必须
        $data['description'] = $option['productDesc'];      // 产品描述,可选
        $data['customerId'] = $option['custom'];            // 自定义
        // 不指定则使用预付费卡或者运营商计费
        if (!empty($option['productId'])) {
            $data['amount'] = intval($option['amount'] * 100);  // 单位分
            $data['currencyCode'] = $option['currency'];        // 货币类型与amount对应 http://www.xe.com/iso4217.php
        }
        $data['signature'] = $this->createSign($data, $this->app_key); // 签名


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $output = curl_exec($ch);
        if ($output === false) {
            throw new \Exception("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);
        $result = json_decode($output, true);


        // 异常错误
        if (!isset($result['paymentUrl'])) {
            throw new \Exception($result['message']);
        }

        /*
         * 返回结构
         * {
            "applicationCode" : "3f2504e04f8911d39a0c0305e82c3301",
            "referenceId" : "TRX1708901",
            "version" : "v1",
            "amount" : 1000 ,
            "currencyCode" : "MYR",
            "paymentId" : "MPO000000000001",
            "paymentUrl" :
            "https://payout.mol.com/index.aspx?token=F786525494694176A7D1308B479010C3",
            "signature" : "1c01d4d676d4e5445ab064edb2efa7f8"
        }*/

        // 跳转支付
        $this->redirect = $result['paymentUrl'];
    }


    public function send()
    {
        header("Location:" . $this->redirect);
        exit();
    }


    /**
     * 回调
     * 成功则返回 HTTP Status Code 200
     * MOL Global API v1.20 4.1.4
     * 如通知失败MOL会在24小时重新通知3次
     * applicationCode=3f2504e04f8911d39a0c0305e82c3301&referenceId=TRX1708901&paymentId=MPO000000000001&version=v1&amount=1000&currencyCode=MYR&paymentStatusCode=00&paymentStatusDate=2012-12-31T14%3A59%3A59Z&customerId=12321144221&signature=67626c0bde4e0cf66658fa403b91bf57
     */
    public function notify()
    {
        $req = $_REQUEST;
        $sign = $req['signature'];
        unset($req['_url']);
        unset($req['signature']);
        $verify_sign = $this->createSign($req, $this->app_key);


        // 验签不通过
        if ($sign != $verify_sign) {
            return [
                'transactionId'        => $req['referenceId'],
                'transactionReference' => $req['paymentId'],
                'isSuccessful'         => false,
                'message'              => 'sign failed',
            ];
        }


        // 订单不成功
        if ($req['paymentStatusCode'] != '00') {
            return [
                'transactionId'        => $req['referenceId'],
                'transactionReference' => $req['paymentId'],
                'isSuccessful'         => false,
                'message'              => 'not complete',
            ];
        }


        // 成功返回
        $result = [
            'isSuccessful'         => true,
            'message'              => 'success',
            'transactionId'        => $req['referenceId'],
            'transactionReference' => $req['paymentId'],
            'amount'               => intval($req['amount'] / 100),
            'currency'             => $req['currencyCode'],
            'raw'                  => $req,
        ];

        // TODO :: 沙箱状态
        // $result['sandbox'] = true;

        return $result;
    }


    // TODO :: 成功相应
    public function success()
    {
        exit('success');
    }


    private function createSign($data = [], $signKey = '')
    {
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= "$value";
        }
        return md5($string . $signKey);
    }
}